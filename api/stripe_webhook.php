<?php
/**
 * Stripe Webhook – MedTravel
 *
 * Receives POST from Stripe, verifies the signature (if STRIPE_WEBHOOK_SECRET
 * is configured), and dispatches to event handlers.
 *
 * Configuration (config/stripe.php or admin/include/email_credentials.php):
 *   define('STRIPE_SECRET_KEY',      'sk_live_...');   // or sk_test_
 *   define('STRIPE_WEBHOOK_SECRET',  'whsec_...');     // signing secret
 *
 * If STRIPE_WEBHOOK_SECRET is not defined this endpoint returns 503 so Stripe
 * retries later and we never silently accept unverified payloads.
 *
 * To enable: add this URL in your Stripe Dashboard → Webhooks:
 *   https://medtravel.com.co/api/stripe_webhook.php
 *
 * Events handled (stub — add business logic per event):
 *   checkout.session.completed
 *   checkout.session.expired
 *   payment_intent.payment_failed
 *   charge.refunded
 */

// Webhook must read raw body BEFORE any output or session_start
$rawBody = file_get_contents('php://input');

// No session, no cookie – pure HTTP endpoint
header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────
function sw_log($line)
{
    $dir  = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/stripe_webhook.log';
    $entry = date('c') . ' ' . trim((string)$line) . PHP_EOL;
    @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

function sw_respond($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => $code === 200, 'message' => $message]);
    exit;
}

// ── Load Stripe config ────────────────────────────────────────────────────────
$configPath = __DIR__ . '/../config/stripe.php';
if (is_file($configPath)) {
    require_once $configPath;
}
// Fallback from email_credentials
$credPath = __DIR__ . '/../admin/include/email_credentials.php';
if (is_file($credPath) && (!defined('STRIPE_SECRET_KEY') || !defined('STRIPE_WEBHOOK_SECRET'))) {
    // Use output buffer to safely include files that might echo
    ob_start();
    include $credPath;
    ob_end_clean();
}

// ── Signature validation gate ─────────────────────────────────────────────────
$webhookSecret = defined('STRIPE_WEBHOOK_SECRET') ? trim(STRIPE_WEBHOOK_SECRET) : '';
if ($webhookSecret === '') {
    sw_log('SKIP: STRIPE_WEBHOOK_SECRET not configured — returning 503');
    sw_respond(503, 'webhook_not_configured');
}

// Load Stripe SDK
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
$manualStripe     = __DIR__ . '/../lib/stripe/init.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} elseif (is_file($manualStripe)) {
    require_once $manualStripe;
} else {
    sw_log('ERROR: Stripe SDK not found');
    sw_respond(503, 'stripe_sdk_not_found');
}

if (!class_exists('\\Stripe\\Stripe')) {
    sw_log('ERROR: Stripe class not found after include');
    sw_respond(503, 'stripe_class_not_found');
}

// Verify signature
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if ($sigHeader === '') {
    sw_log('REJECT: missing Stripe-Signature header');
    sw_respond(400, 'missing_signature');
}

try {
    $event = \Stripe\Webhook::constructEvent($rawBody, $sigHeader, $webhookSecret);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    sw_log('REJECT: signature mismatch — ' . $e->getMessage());
    sw_respond(400, 'invalid_signature');
} catch (\Throwable $e) {
    sw_log('REJECT: constructEvent exception — ' . $e->getMessage());
    sw_respond(400, 'malformed_payload');
}

sw_log('EVENT: ' . $event->type . ' id=' . $event->id);

// ── DB connection (needed for state updates) ──────────────────────────────────
require_once __DIR__ . '/../admin/include/conexion.php';

// ── Event dispatch ────────────────────────────────────────────────────────────
switch ($event->type) {

    // ── Payment completed ─────────────────────────────────────────────────
    case 'checkout.session.completed': {
        $session   = $event->data->object;
        $sessionId = (string)$session->id;
        $piId      = (string)($session->payment_intent ?? '');

        // Mark corresponding commission_payments row as paid
        $q = mysqli_prepare($conexion,
            'UPDATE commission_payments
             SET status                 = "paid",
                 stripe_payment_intent = ?,
                 paid_at               = NOW()
             WHERE stripe_session_id = ? AND status IN ("draft","pending")');
        if ($q) {
            mysqli_stmt_bind_param($q, 'ss', $piId, $sessionId);
            mysqli_stmt_execute($q);
            $affected = mysqli_stmt_affected_rows($q);
            mysqli_stmt_close($q);
            sw_log("checkout.session.completed session={$sessionId} pi={$piId} rows_updated={$affected}");
        } else {
            sw_log('ERROR: prepare failed for checkout.session.completed — ' . mysqli_error($conexion));
        }
        break;
    }

    // ── Session expired (client did not complete checkout) ────────────────
    case 'checkout.session.expired': {
        $session   = $event->data->object;
        $sessionId = (string)$session->id;

        $q = mysqli_prepare($conexion,
            'UPDATE commission_payments
             SET status = "failed"
             WHERE stripe_session_id = ? AND status = "pending"');
        if ($q) {
            mysqli_stmt_bind_param($q, 's', $sessionId);
            mysqli_stmt_execute($q);
            $affected = mysqli_stmt_affected_rows($q);
            mysqli_stmt_close($q);
            sw_log("checkout.session.expired session={$sessionId} rows_updated={$affected}");
        }
        break;
    }

    // ── Payment failed ────────────────────────────────────────────────────
    case 'payment_intent.payment_failed': {
        $pi   = $event->data->object;
        $piId = (string)$pi->id;

        $q = mysqli_prepare($conexion,
            'UPDATE commission_payments
             SET status = "failed"
             WHERE stripe_payment_intent = ? AND status IN ("draft","pending")');
        if ($q) {
            mysqli_stmt_bind_param($q, 's', $piId);
            mysqli_stmt_execute($q);
            $affected = mysqli_stmt_affected_rows($q);
            mysqli_stmt_close($q);
            sw_log("payment_intent.payment_failed pi={$piId} rows_updated={$affected}");
        }
        break;
    }

    // ── Charge refunded ───────────────────────────────────────────────────
    case 'charge.refunded': {
        $charge    = $event->data->object;
        $chargeId  = (string)$charge->id;
        $amountRef = round((float)($charge->amount_refunded ?? 0) / 100, 2);
        $currency  = strtoupper((string)($charge->currency ?? ''));

        $q = mysqli_prepare($conexion,
            'UPDATE commission_payments
             SET status         = "refunded",
                 refunded_at    = NOW(),
                 refund_amount  = ?
             WHERE stripe_charge_id = ? AND status = "paid"');
        if ($q) {
            mysqli_stmt_bind_param($q, 'ds', $amountRef, $chargeId);
            mysqli_stmt_execute($q);
            $affected = mysqli_stmt_affected_rows($q);
            mysqli_stmt_close($q);
            sw_log("charge.refunded charge={$chargeId} amount={$amountRef} {$currency} rows_updated={$affected}");
        }
        break;
    }

    // ── Unhandled events ──────────────────────────────────────────────────
    default:
        sw_log('UNHANDLED: ' . $event->type);
        break;
}

sw_respond(200, 'received');
