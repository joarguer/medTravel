<?php
/**
 * admin/ajax/provider_commission_settings.php
 * AJAX endpoint for provider commission settings (provider edit page).
 *
 * Actions (POST/GET param "action"):
 *   get_settings  – fetch current settings for a provider (creates defaults if missing)
 *   save_settings – insert or update settings for a provider
 *
 * Access: admin session required + PERM_BOOKING_MANAGE
 */

include '../include/conexion.php';
require_once '../include/roles.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden']);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function pcs_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function pcs_err($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function pcs_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_commission_settings'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

function pcs_sanitize_pct($v)
{
    return max(0.0, min(100.0, round((float)$v, 2)));
}

function pcs_sanitize_amount($v)
{
    return max(0.0, round((float)$v, 2));
}

// ── Table guard ───────────────────────────────────────────────────────────────
if (!pcs_table_ready($conexion)) {
    pcs_err('commission_settings_table_missing — run 2026_03_03_commission_tables.sql', 503);
}

// ── Router ────────────────────────────────────────────────────────────────────
$action = isset($_POST['action']) ? trim($_POST['action'])
        : (isset($_GET['action'])  ? trim($_GET['action'])  : '');

switch ($action) {

    // ── get_settings ──────────────────────────────────────────────────────
    case 'get_settings': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        if ($providerId <= 0) {
            pcs_err('provider_id required');
        }

        // Validate provider exists
        $chk = mysqli_prepare($conexion, 'SELECT id FROM providers WHERE id = ? LIMIT 1');
        if ($chk) {
            mysqli_stmt_bind_param($chk, 'i', $providerId);
            mysqli_stmt_execute($chk);
            $chkRes = mysqli_stmt_get_result($chk);
            $provRow = $chkRes ? mysqli_fetch_assoc($chkRes) : null;
            mysqli_stmt_close($chk);
            if (!$provRow) {
                pcs_err('provider_not_found', 404);
            }
        }

        // Try to fetch existing row
        $stmt = mysqli_prepare($conexion,
            'SELECT id, provider_id, commission_pct, fixed_fee_cop, currency,
                    payment_terms, stripe_account_id, notes, is_active,
                    created_at, updated_at, updated_by
             FROM provider_commission_settings
             WHERE provider_id = ?
             LIMIT 1');
        if (!$stmt) {
            pcs_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $providerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row    = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            // Auto-insert default row so the form always has a record to update
            $ins = mysqli_prepare($conexion,
                'INSERT IGNORE INTO provider_commission_settings (provider_id) VALUES (?)');
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'i', $providerId);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
            // Re-fetch after insert
            $stmt2 = mysqli_prepare($conexion,
                'SELECT id, provider_id, commission_pct, fixed_fee_cop, currency,
                        payment_terms, stripe_account_id, notes, is_active,
                        created_at, updated_at, updated_by
                 FROM provider_commission_settings
                 WHERE provider_id = ?
                 LIMIT 1');
            if ($stmt2) {
                mysqli_stmt_bind_param($stmt2, 'i', $providerId);
                mysqli_stmt_execute($stmt2);
                $r2 = mysqli_stmt_get_result($stmt2);
                $row = $r2 ? mysqli_fetch_assoc($r2) : null;
                mysqli_stmt_close($stmt2);
            }
            if (!$row) {
                // Fallback: return defaults without a saved row
                pcs_ok([
                    'settings' => [
                        'provider_id'       => $providerId,
                        'commission_pct'    => '10.00',
                        'fixed_fee_cop'     => '0.00',
                        'currency'          => 'COP',
                        'payment_terms'     => '',
                        'stripe_account_id' => '',
                        'notes'             => '',
                        'is_active'         => 1,
                        'updated_at'        => null,
                    ],
                    'exists' => false,
                ]);
            }
            pcs_ok(['settings' => $row, 'exists' => false]);
        }

        pcs_ok(['settings' => $row, 'exists' => true]);
        break;
    }

    // ── save_settings ─────────────────────────────────────────────────────
    case 'save_settings': {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        if ($providerId <= 0) {
            pcs_err('provider_id required');
        }

        // Validate provider exists
        $chk = mysqli_prepare($conexion, 'SELECT id FROM providers WHERE id = ? LIMIT 1');
        if ($chk) {
            mysqli_stmt_bind_param($chk, 'i', $providerId);
            mysqli_stmt_execute($chk);
            $chkRes = mysqli_stmt_get_result($chk);
            $provRow = $chkRes ? mysqli_fetch_assoc($chkRes) : null;
            mysqli_stmt_close($chk);
            if (!$provRow) {
                pcs_err('provider_not_found', 404);
            }
        }

        // Sanitize & validate inputs
        $commissionPct   = pcs_sanitize_pct($_POST['commission_pct']   ?? 10);
        $fixedFeeCop     = pcs_sanitize_amount($_POST['fixed_fee_cop'] ?? 0);

        $allowedCurrencies = ['COP', 'USD', 'EUR'];
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'COP')));
        if (!in_array($currency, $allowedCurrencies, true)) {
            $currency = 'COP';
        }

        $paymentTerms    = substr(trim((string)($_POST['payment_terms']    ?? '')), 0, 255);
        $stripeAccountId = substr(trim((string)($_POST['stripe_account_id'] ?? '')), 0, 64);
        $notes           = trim((string)($_POST['notes'] ?? ''));
        $isActive        = isset($_POST['is_active']) ? 1 : 0;
        $updatedBy       = (int)($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0) ?: null;

        $stmt = mysqli_prepare($conexion,
            'INSERT INTO provider_commission_settings
               (provider_id, commission_pct, fixed_fee_cop, currency,
                payment_terms, stripe_account_id, notes, is_active, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               commission_pct    = VALUES(commission_pct),
               fixed_fee_cop     = VALUES(fixed_fee_cop),
               currency          = VALUES(currency),
               payment_terms     = VALUES(payment_terms),
               stripe_account_id = VALUES(stripe_account_id),
               notes             = VALUES(notes),
               is_active         = VALUES(is_active),
               updated_by        = VALUES(updated_by),
               updated_at        = NOW()');

        if (!$stmt) {
            pcs_err('db_prepare_failed', 500);
        }

        mysqli_stmt_bind_param(
            $stmt,
            'iddssssii',
            $providerId,
            $commissionPct,
            $fixedFeeCop,
            $currency,
            $paymentTerms,
            $stripeAccountId,
            $notes,
            $isActive,
            $updatedBy
        );

        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            pcs_err('db_error: ' . $err, 500);
        }
        mysqli_stmt_close($stmt);

        pcs_ok(['success' => true]);
        break;
    }

    default:
        pcs_err('unknown_action — use get_settings or save_settings');
}
