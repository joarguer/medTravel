<?php
/**
 * Admin AJAX – provider_commission_settings
 *
 * Actions (POST param "action"):
 *   get    – return current settings for a provider
 *   save   – insert or update settings for a provider
 *   list   – return all active provider commission settings (paginated)
 *
 * Access: PERM_BOOKING_MANAGE (admin / admin-team roles)
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
function cs_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function cs_err($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function cs_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_commission_settings'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

function cs_sanitize_pct($v)
{
    $v = (float)$v;
    return max(0.0, min(100.0, round($v, 2)));
}

function cs_sanitize_amount($v)
{
    $v = (float)$v;
    return max(0.0, round($v, 2));
}

// ── Router ────────────────────────────────────────────────────────────────────
$action = isset($_POST['action']) ? trim($_POST['action'])
        : (isset($_GET['action']) ? trim($_GET['action']) : '');

if (!cs_table_ready($conexion)) {
    cs_err('commission_settings_table_missing — run 2026_03_03_commission_tables.sql', 503);
}

switch ($action) {

    // ── GET ───────────────────────────────────────────────────────────────
    case 'get': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        if ($providerId <= 0) {
            cs_err('provider_id required');
        }

        $stmt = mysqli_prepare($conexion,
            'SELECT id, provider_id, commission_pct, fixed_fee_cop, currency,
                    payment_terms, stripe_account_id, notes, is_active,
                    created_at, updated_at, updated_by
             FROM provider_commission_settings
             WHERE provider_id = ?
             LIMIT 1');
        if (!$stmt) {
            cs_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $providerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row    = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            // Return default config (not saved yet)
            cs_ok([
                'settings' => [
                    'provider_id'       => $providerId,
                    'commission_pct'    => 10.00,
                    'fixed_fee_cop'     => 0.00,
                    'currency'          => 'COP',
                    'payment_terms'     => '',
                    'stripe_account_id' => '',
                    'notes'             => '',
                    'is_active'         => 1,
                ],
                'exists' => false,
            ]);
        }

        cs_ok(['settings' => $row, 'exists' => true]);
        break;
    }

    // ── SAVE (INSERT or UPDATE) ───────────────────────────────────────────
    case 'save': {
        $providerId       = (int)($_POST['provider_id'] ?? 0);
        if ($providerId <= 0) {
            cs_err('provider_id required');
        }

        $commissionPct    = cs_sanitize_pct($_POST['commission_pct']    ?? 10);
        $fixedFeeCop      = cs_sanitize_amount($_POST['fixed_fee_cop']  ?? 0);
        $currency         = strtoupper(trim((string)($_POST['currency']         ?? 'COP')));
        $paymentTerms     = substr(trim((string)($_POST['payment_terms']         ?? '')), 0, 255);
        $stripeAccountId  = substr(trim((string)($_POST['stripe_account_id']     ?? '')), 0, 64);
        $notes            = trim((string)($_POST['notes'] ?? ''));
        $isActive         = isset($_POST['is_active']) ? (int)(bool)$_POST['is_active'] : 1;
        $updatedBy        = (int)($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0) ?: null;

        $allowedCurrencies = ['COP', 'USD', 'EUR'];
        if (!in_array($currency, $allowedCurrencies, true)) {
            $currency = 'COP';
        }

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
               updated_by        = VALUES(updated_by)');

        if (!$stmt) {
            cs_err('db_prepare_failed', 500);
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
            cs_err('db_error: ' . $err, 500);
        }
        $affectedId = (int)mysqli_insert_id($conexion) ?: null;
        mysqli_stmt_close($stmt);

        cs_ok(['saved' => true, 'insert_id' => $affectedId]);
        break;
    }

    // ── LIST ──────────────────────────────────────────────────────────────
    case 'list': {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        $totalRow = mysqli_fetch_assoc(mysqli_query($conexion,
            "SELECT COUNT(*) AS n FROM provider_commission_settings WHERE is_active = 1"));
        $total = $totalRow ? (int)$totalRow['n'] : 0;

        $stmt = mysqli_prepare($conexion,
            'SELECT cs.id, cs.provider_id, u.nombre, u.email,
                    cs.commission_pct, cs.fixed_fee_cop, cs.currency,
                    cs.payment_terms, cs.stripe_account_id, cs.is_active,
                    cs.updated_at
             FROM provider_commission_settings cs
             LEFT JOIN usuarios u ON u.id = cs.provider_id
             WHERE cs.is_active = 1
             ORDER BY cs.updated_at DESC
             LIMIT ? OFFSET ?');
        if (!$stmt) {
            cs_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'ii', $perPage, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows   = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        cs_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
        break;
    }

    default:
        cs_err('unknown_action');
}
