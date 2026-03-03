<?php
/**
 * Client AJAX – commission_payments
 *
 * Actions (POST param "action"):
 *   create_payment  – insert a commission_payments row in status=draft/pending
 *                     and, if Stripe is configured, create a Checkout Session
 *                     returning { checkout_url, session_id }.
 *   get_payment     – return a single commission_payment row for the current client.
 *
 * Stripe integration is opt-in:
 *   - Define STRIPE_SECRET_KEY in config/stripe.php (or admin email_credentials.php)
 *   - composer require stripe/stripe-php OR place the SDK in lib/stripe/
 *   - If Stripe is not configured, payment is created as status=draft and
 *     checkout_url is null (admin can finalise manually).
 *
 * Access: authenticated client.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
function cp_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function cp_err($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function cp_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'commission_payments'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

/**
 * Load the Stripe secret key (if configured).
 * Priority:
 *   1. config/stripe.php  → define('STRIPE_SECRET_KEY', 'sk_...')
 *   2. admin/include/email_credentials.php  → $stripeSecretKey variable
 * Returns empty string if not configured.
 */
function cp_stripe_secret_key()
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    $configPath = __DIR__ . '/../../config/stripe.php';
    if (is_file($configPath)) {
        require_once $configPath;
    }
    if (defined('STRIPE_SECRET_KEY') && trim(STRIPE_SECRET_KEY) !== '') {
        $key = trim(STRIPE_SECRET_KEY);
        return $key;
    }

    // Fallback: look for $stripeSecretKey in email_credentials
    $credPath = __DIR__ . '/../../admin/include/email_credentials.php';
    if (is_file($credPath)) {
        $stripeSecretKey = '';
        include $credPath;
        if (isset($stripeSecretKey) && trim((string)$stripeSecretKey) !== '') {
            $key = trim((string)$stripeSecretKey);
            return $key;
        }
    }

    $key = '';
    return $key;
}

/**
 * Attempt to create a Stripe Checkout Session.
 * Returns ['session_id' => ..., 'checkout_url' => ...] on success.
 * Returns ['error' => ...] on failure.
 */
function cp_create_stripe_checkout($params)
{
    $secretKey = cp_stripe_secret_key();
    if ($secretKey === '') {
        return ['error' => 'stripe_not_configured'];
    }

    // Load Stripe SDK (Composer autoload or manual include)
    $composerAutoload = __DIR__ . '/../../vendor/autoload.php';
    $manualStripe     = __DIR__ . '/../../lib/stripe/init.php';
    if (is_file($composerAutoload)) {
        require_once $composerAutoload;
    } elseif (is_file($manualStripe)) {
        require_once $manualStripe;
    } else {
        return ['error' => 'stripe_sdk_not_found'];
    }

    if (!class_exists('\\Stripe\\Stripe')) {
        return ['error' => 'stripe_class_not_found'];
    }

    try {
        \Stripe\Stripe::setApiKey($secretKey);

        $cancelUrl  = $params['cancel_url']  ?? 'https://medtravel.com.co/client/app_inbox.php';
        $successUrl = $params['success_url'] ?? 'https://medtravel.com.co/client/app_inbox.php?payment=success';
        $amountCents = (int)round(((float)$params['amount']) * 100);
        $currency    = strtolower((string)($params['currency'] ?? 'cop'));

        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'price_data' => [
                    'currency'     => $currency,
                    'unit_amount'  => $amountCents,
                    'product_data' => [
                        'name'        => 'MedTravel coordination fee — case #' . $params['request_id'],
                        'description' => $params['description'] ?? '',
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => $successUrl . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $cancelUrl,
            'metadata'    => [
                'payment_id'    => (string)($params['payment_id']  ?? ''),
                'request_id'    => (string)($params['request_id']  ?? ''),
                'item_id'       => (string)($params['item_id']     ?? ''),
                'provider_id'   => (string)($params['provider_id'] ?? ''),
                'client_id'     => (string)($params['client_id']   ?? ''),
            ],
            'expires_at' => time() + 3600, // 1-hour expiry
        ];

        $session = \Stripe\Checkout\Session::create($sessionData);

        return [
            'session_id'   => $session->id,
            'checkout_url' => $session->url,
            'expires_at'   => date('Y-m-d H:i:s', $session->expires_at),
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return ['error' => 'stripe_api: ' . $e->getMessage()];
    } catch (\Throwable $e) {
        return ['error' => 'stripe_exception: ' . $e->getMessage()];
    }
}

// ── Auth ──────────────────────────────────────────────────────────────────────
$clientUserId = (int)($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0);
if ($clientUserId <= 0) {
    cp_err('unauthenticated', 401);
}

// ── Table check ───────────────────────────────────────────────────────────────
if (!cp_table_ready($conexion)) {
    cp_err('commission_payments_table_missing — run 2026_03_03_commission_tables.sql', 503);
}

// ── Router ────────────────────────────────────────────────────────────────────
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

switch ($action) {

    // ── CREATE PAYMENT ────────────────────────────────────────────────────
    case 'create_payment': {
        $requestId  = (int)($_POST['request_id'] ?? 0);
        $itemId     = (int)($_POST['item_id']    ?? 0) ?: null;
        $providerId = (int)($_POST['provider_id'] ?? 0);

        if ($requestId <= 0 || $providerId <= 0) {
            cp_err('request_id and provider_id are required');
        }

        // Verify the request belongs to this client
        $ownerCheck = mysqli_prepare($conexion,
            'SELECT id FROM booking_requests WHERE id = ? AND (user_id = ? OR client_user_id = ?) LIMIT 1');
        if (!$ownerCheck) {
            cp_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($ownerCheck, 'iii', $requestId, $clientUserId, $clientUserId);
        mysqli_stmt_execute($ownerCheck);
        $ownerRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ownerCheck));
        mysqli_stmt_close($ownerCheck);
        if (!$ownerRow) {
            cp_err('request_not_found_or_forbidden', 403);
        }

        // Load commission settings for this provider
        $csStmt = mysqli_prepare($conexion,
            'SELECT commission_pct, fixed_fee_cop, currency
             FROM provider_commission_settings
             WHERE provider_id = ? AND is_active = 1
             LIMIT 1');
        $commissionPct  = 10.00;
        $fixedFeeCop    = 0.00;
        $currency       = 'COP';
        if ($csStmt) {
            mysqli_stmt_bind_param($csStmt, 'i', $providerId);
            mysqli_stmt_execute($csStmt);
            $csRow = mysqli_fetch_assoc(mysqli_stmt_get_result($csStmt));
            mysqli_stmt_close($csStmt);
            if ($csRow) {
                $commissionPct = (float)$csRow['commission_pct'];
                $fixedFeeCop   = (float)$csRow['fixed_fee_cop'];
                $currency      = (string)$csRow['currency'];
            }
        }

        // Amount: caller may pass an explicit amount, otherwise use fixed fee
        $amount = isset($_POST['amount']) && (float)$_POST['amount'] > 0
            ? round((float)$_POST['amount'], 2)
            : round($fixedFeeCop, 2);

        if ($amount <= 0) {
            cp_err('amount must be greater than zero');
        }

        $description = substr(trim((string)($_POST['description'] ?? '')), 0, 255);
        $notes       = trim((string)($_POST['notes'] ?? ''));

        // Insert payment record as draft
        $ins = mysqli_prepare($conexion,
            'INSERT INTO commission_payments
               (request_id, item_id, provider_id, client_user_id,
                amount, currency, commission_pct_snapshot, fixed_fee_snapshot,
                status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "draft", ?, ?)');
        if (!$ins) {
            cp_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param(
            $ins,
            'iiiidsdds i',
            $requestId,
            $itemId,
            $providerId,
            $clientUserId,
            $amount,
            $currency,
            $commissionPct,
            $fixedFeeCop,
            $notes,
            $clientUserId
        );
        if (!mysqli_stmt_execute($ins)) {
            $err = mysqli_stmt_error($ins);
            mysqli_stmt_close($ins);
            cp_err('db_error: ' . $err, 500);
        }
        $paymentId = (int)mysqli_insert_id($conexion);
        mysqli_stmt_close($ins);

        // Attempt Stripe Checkout
        $stripeResult = cp_create_stripe_checkout([
            'payment_id'  => $paymentId,
            'request_id'  => $requestId,
            'item_id'     => $itemId ?? 0,
            'provider_id' => $providerId,
            'client_id'   => $clientUserId,
            'amount'      => $amount,
            'currency'    => $currency,
            'description' => $description,
        ]);

        $checkoutUrl  = null;
        $sessionId    = null;
        $newStatus    = 'draft';
        $expiresAt    = null;
        $stripeError  = null;

        if (empty($stripeResult['error'])) {
            $checkoutUrl = $stripeResult['checkout_url'];
            $sessionId   = $stripeResult['session_id'];
            $expiresAt   = $stripeResult['expires_at'] ?? null;
            $newStatus   = 'pending';

            // Update the row with Stripe data
            $upd = mysqli_prepare($conexion,
                'UPDATE commission_payments
                 SET status = "pending",
                     stripe_session_id = ?,
                     checkout_url      = ?,
                     expires_at        = ?
                 WHERE id = ?');
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'sssi', $sessionId, $checkoutUrl, $expiresAt, $paymentId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
        } else {
            $stripeError = $stripeResult['error'];
        }

        cp_ok([
            'payment_id'   => $paymentId,
            'status'       => $newStatus,
            'checkout_url' => $checkoutUrl,
            'session_id'   => $sessionId,
            'expires_at'   => $expiresAt,
            'stripe_error' => $stripeError,   // null when Stripe succeeded
            'amount'       => $amount,
            'currency'     => $currency,
        ]);
        break;
    }

    // ── GET PAYMENT ───────────────────────────────────────────────────────
    case 'get_payment': {
        $paymentId = (int)($_GET['payment_id'] ?? $_POST['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            cp_err('payment_id required');
        }

        $stmt = mysqli_prepare($conexion,
            'SELECT id, request_id, item_id, provider_id,
                    amount, currency, status,
                    stripe_session_id, stripe_payment_intent,
                    checkout_url, expires_at, paid_at, created_at
             FROM commission_payments
             WHERE id = ? AND client_user_id = ?
             LIMIT 1');
        if (!$stmt) {
            cp_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'ii', $paymentId, $clientUserId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            cp_err('payment_not_found', 404);
        }
        cp_ok(['payment' => $row]);
        break;
    }

    default:
        cp_err('unknown_action');
}
