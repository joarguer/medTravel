<?php
/**
 * admin/ajax/commission_payments.php
 * Admin-only management for commission_payments (Phase 2).
 */

include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../../inc/commission_gate.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!is_role_admin_session()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden_admin_only']);
    exit;
}

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

function cp_table_ready($conexion, $table)
{
    return commission_gate_table_exists($conexion, $table);
}

function cp_fetch_item_row($conexion, $requestId, $itemId)
{
    $requestId = (int)$requestId;
    $itemId = (int)$itemId;
    if ($itemId <= 0 || !cp_table_ready($conexion, 'booking_request_items')) {
        return null;
    }

    $cols = [];
    foreach ([
        'id',
        'booking_request_id',
        'request_id',
        'provider_id',
        'service_provider_id',
        'provider_proposed_price',
        'proposed_price',
        'provider_proposed_currency',
        'currency',
        'item_currency',
        'item_type',
    ] as $col) {
        if (commission_gate_column_exists($conexion, 'booking_request_items', $col)) {
            $cols[] = $col;
        }
    }
    if (empty($cols)) {
        return null;
    }

    $requestCol = '';
    if (commission_gate_column_exists($conexion, 'booking_request_items', 'booking_request_id')) {
        $requestCol = 'booking_request_id';
    } elseif (commission_gate_column_exists($conexion, 'booking_request_items', 'request_id')) {
        $requestCol = 'request_id';
    }

    $sql = "SELECT " . implode(', ', $cols) . " FROM booking_request_items WHERE id = ?";
    $types = 'i';
    $params = [$itemId];
    if ($requestCol !== '' && $requestId > 0) {
        $sql .= " AND {$requestCol} = ?";
        $types .= 'i';
        $params[] = $requestId;
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    if (!commission_gate_bind_params($stmt, $types, ...$params)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function cp_fetch_settings($conexion, array $providerIds)
{
    $result = [
        'found' => false,
        'enabled' => false,
        'settings' => null,
        'provider_id' => 0,
    ];
    if (!cp_table_ready($conexion, 'provider_commission_settings')) {
        return $result;
    }
    $hasIsActive = commission_gate_column_exists($conexion, 'provider_commission_settings', 'is_active');
    $select = 'provider_id, commission_pct, fixed_fee_cop, currency';
    if ($hasIsActive) {
        $select .= ', is_active';
    }

    $providerIds = array_values(array_unique(array_filter(array_map('intval', $providerIds))));
    foreach ($providerIds as $pid) {
        if ($pid <= 0) {
            continue;
        }
        $stmt = mysqli_prepare($conexion,
            "SELECT {$select} FROM provider_commission_settings WHERE provider_id = ? LIMIT 1"
        );
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 'i', $pid);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            continue;
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            continue;
        }
        $result['found'] = true;
        $result['provider_id'] = $pid;
        $enabled = true;
        if ($hasIsActive && isset($row['is_active']) && (int)$row['is_active'] === 0) {
            $enabled = false;
        }
        $result['enabled'] = $enabled;
        $result['settings'] = $row;
        return $result;
    }
    return $result;
}

function cp_resolve_provider_id(array $ids)
{
    $candidates = [
        (int)($ids['provider_id'] ?? 0),
        (int)($ids['service_provider_id'] ?? 0),
        (int)($ids['provider_user_id'] ?? 0),
    ];
    foreach ($candidates as $id) {
        if ($id > 0) {
            return $id;
        }
    }
    return 0;
}

function cp_fetch_payment_by_status($conexion, $requestId, $itemId, $providerId, $status)
{
    $stmt = mysqli_prepare($conexion,
        "SELECT id, status, checkout_url, paid_at, created_at, amount, currency
         FROM commission_payments
         WHERE request_id = ? AND item_id = ? AND provider_id = ? AND status = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'iiis', $requestId, $itemId, $providerId, $status);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function cp_has_active_payment($conexion, $requestId, $itemId, $providerId)
{
    $stmt = mysqli_prepare($conexion,
        "SELECT COUNT(*) AS n FROM commission_payments
         WHERE request_id = ? AND item_id = ? AND provider_id = ? AND status IN ('pending','paid')"
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $requestId, $itemId, $providerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int)($row['n'] ?? 0) > 0;
}

function cp_compute_amount($basePrice, $commissionPct, $fixedFee, $currency)
{
    $basePrice = (float)$basePrice;
    $commissionPct = (float)$commissionPct;
    $fixedFee = (float)$fixedFee;
    $currency = strtoupper((string)$currency);
    if ($basePrice <= 0) {
        return [
            'ok' => false,
            'message' => 'cannot_compute_amount',
        ];
    }
    $amount = ($basePrice * $commissionPct / 100.0);
    if ($currency === 'COP') {
        $amount += $fixedFee;
    }
    return [
        'ok' => true,
        'amount' => round($amount, 2),
    ];
}

if (!cp_table_ready($conexion, 'commission_payments')) {
    cp_err('commission_payments_table_missing', 503);
}

$action = isset($_POST['action']) ? trim($_POST['action'])
        : (isset($_GET['action']) ? trim($_GET['action']) : '');
$action = strtolower($action);

if ($action === 'create_link') {
    $action = 'create_payment';
} elseif ($action === 'delete') {
    $action = 'delete_payment';
}

switch ($action) {
    case 'get_status': {
        $requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
        $itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
        if ($requestId <= 0 || $itemId <= 0) {
            cp_err('request_id and item_id required');
        }

        $itemRow = cp_fetch_item_row($conexion, $requestId, $itemId);
        if (!$itemRow) {
            cp_err('item_not_found', 404);
        }

        $providerIds = commission_gate_resolve_provider_ids($conexion, $itemId);
        $providerId = cp_resolve_provider_id($providerIds);
        if ($providerId <= 0) {
            cp_err('provider_not_found', 404);
        }

        $settings = cp_fetch_settings($conexion, [
            $providerIds['provider_user_id'],
            $providerIds['provider_id'],
            $providerIds['service_provider_id'],
        ]);

        $commissionPct = isset($settings['settings']['commission_pct']) ? (float)$settings['settings']['commission_pct'] : 0.0;
        $fixedFee = isset($settings['settings']['fixed_fee_cop']) ? (float)$settings['settings']['fixed_fee_cop'] : 0.0;
        $fallbackCurrency = (string)($itemRow['currency'] ?? '');
        if ($fallbackCurrency === '') {
            $fallbackCurrency = (string)($itemRow['provider_proposed_currency'] ?? '');
        }
        if ($fallbackCurrency === '') {
            $fallbackCurrency = (string)($itemRow['item_currency'] ?? '');
        }
        $currency = isset($settings['settings']['currency']) && $settings['settings']['currency'] !== ''
            ? $settings['settings']['currency']
            : ($fallbackCurrency !== '' ? $fallbackCurrency : 'COP');

        $basePrice = 0.0;
        if (isset($itemRow['proposed_price']) && (float)$itemRow['proposed_price'] > 0) {
            $basePrice = (float)$itemRow['proposed_price'];
        } elseif (isset($itemRow['provider_proposed_price']) && (float)$itemRow['provider_proposed_price'] > 0) {
            $basePrice = (float)$itemRow['provider_proposed_price'];
        }

        $amountPreview = cp_compute_amount($basePrice, $commissionPct, $fixedFee, $currency);

        $payment = cp_fetch_payment_by_status($conexion, $requestId, $itemId, $providerId, 'paid');
        $paymentStatus = 'PAID';
        if (!$payment) {
            $payment = cp_fetch_payment_by_status($conexion, $requestId, $itemId, $providerId, 'pending');
            $paymentStatus = $payment ? 'PENDING' : 'NONE';
        }

        cp_ok([
            'provider_id' => $providerId,
            'gate_enabled' => $settings['enabled'] ? 1 : 0,
            'settings' => $settings['settings'],
            'payment_status' => $paymentStatus,
            'payment' => $payment,
            'base_price' => $basePrice,
            'amount_preview' => $amountPreview['ok'] ? $amountPreview['amount'] : 0,
            'amount_currency' => strtoupper((string)$currency),
            'amount_error' => $amountPreview['ok'] ? '' : $amountPreview['message'],
        ]);
        break;
    }

    case 'create_payment': {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($requestId <= 0 || $itemId <= 0) {
            cp_err('request_id and item_id required');
        }

        $itemRow = cp_fetch_item_row($conexion, $requestId, $itemId);
        if (!$itemRow) {
            cp_err('item_not_found', 404);
        }

        $providerIds = commission_gate_resolve_provider_ids($conexion, $itemId);
        $providerId = cp_resolve_provider_id($providerIds);
        if ($providerId <= 0) {
            cp_err('provider_not_found', 404);
        }

        $settings = cp_fetch_settings($conexion, [
            $providerIds['provider_user_id'],
            $providerIds['provider_id'],
            $providerIds['service_provider_id'],
        ]);
        if (empty($settings['enabled'])) {
            cp_err('gate_disabled', 409);
        }

        if (cp_has_active_payment($conexion, $requestId, $itemId, $providerId)) {
            cp_err('payment_exists', 409);
        }

        $commissionPct = isset($settings['settings']['commission_pct']) ? (float)$settings['settings']['commission_pct'] : 0.0;
        $fixedFee = isset($settings['settings']['fixed_fee_cop']) ? (float)$settings['settings']['fixed_fee_cop'] : 0.0;
        $fallbackCurrency = (string)($itemRow['currency'] ?? '');
        if ($fallbackCurrency === '') {
            $fallbackCurrency = (string)($itemRow['provider_proposed_currency'] ?? '');
        }
        if ($fallbackCurrency === '') {
            $fallbackCurrency = (string)($itemRow['item_currency'] ?? '');
        }
        $currency = isset($settings['settings']['currency']) && $settings['settings']['currency'] !== ''
            ? $settings['settings']['currency']
            : ($fallbackCurrency !== '' ? $fallbackCurrency : 'COP');

        $basePrice = 0.0;
        if (isset($itemRow['proposed_price']) && (float)$itemRow['proposed_price'] > 0) {
            $basePrice = (float)$itemRow['proposed_price'];
        } elseif (isset($itemRow['provider_proposed_price']) && (float)$itemRow['provider_proposed_price'] > 0) {
            $basePrice = (float)$itemRow['provider_proposed_price'];
        }

        $amountCalc = cp_compute_amount($basePrice, $commissionPct, $fixedFee, $currency);
        if (!$amountCalc['ok']) {
            error_log('COMMISSION_PRICE_MISSING request_id=' . $requestId . ' item_id=' . $itemId);
            cp_err('cannot_compute_amount', 422);
        }

        $amount = (float)$amountCalc['amount'];
        $createdBy = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;
        $fields = ['request_id', 'item_id', 'provider_id', 'amount', 'currency', 'commission_pct_snapshot', 'fixed_fee_snapshot', 'status', 'checkout_url', 'notes'];
        $values = [$requestId, $itemId, $providerId, $amount, strtoupper((string)$currency), $commissionPct, $fixedFee, 'pending', '', 'manual_create'];
        $types = 'iiidsddsss';

        if (commission_gate_column_exists($conexion, 'commission_payments', 'created_by')) {
            $fields[] = 'created_by';
            $values[] = $createdBy;
            $types .= 'i';
        }

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO commission_payments (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            cp_err('db_prepare_failed', 500);
        }
        if (!commission_gate_bind_params($stmt, $types, ...$values)) {
            mysqli_stmt_close($stmt);
            cp_err('db_bind_failed', 500);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            cp_err('db_execute_failed: ' . $err, 500);
        }
        $paymentId = (int)mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        cp_ok(['payment_id' => $paymentId]);
        break;
    }

    case 'mark_paid': {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $requestId = (int)($_POST['request_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);

        if ($paymentId <= 0 && ($requestId <= 0 || $itemId <= 0)) {
            cp_err('payment_id or request_id/item_id required');
        }

        if ($paymentId > 0) {
            $stmt = mysqli_prepare($conexion,
                "SELECT id, request_id, item_id, provider_id FROM commission_payments WHERE id = ? LIMIT 1"
            );
            if (!$stmt) {
                cp_err('db_prepare_failed', 500);
            }
            mysqli_stmt_bind_param($stmt, 'i', $paymentId);
            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                cp_err('db_execute_failed: ' . $err, 500);
            }
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!$row) {
                cp_err('payment_not_found', 404);
            }
            $requestId = (int)($row['request_id'] ?? 0);
            $itemId = (int)($row['item_id'] ?? 0);
            $providerId = (int)($row['provider_id'] ?? 0);
            $settings = cp_fetch_settings($conexion, [$providerId]);
            if (empty($settings['enabled'])) {
                cp_err('gate_disabled', 409);
            }
        } else {
            $providerIds = commission_gate_resolve_provider_ids($conexion, $itemId);
            $providerId = cp_resolve_provider_id($providerIds);
            if ($providerId <= 0) {
                cp_err('provider_not_found', 404);
            }
            $settings = cp_fetch_settings($conexion, [
                $providerIds['provider_user_id'],
                $providerIds['provider_id'],
                $providerIds['service_provider_id'],
            ]);
            if (empty($settings['enabled'])) {
                cp_err('gate_disabled', 409);
            }
            $pending = cp_fetch_payment_by_status($conexion, $requestId, $itemId, $providerId, 'pending');
            if (!$pending) {
                cp_err('payment_not_found', 404);
            }
            $paymentId = (int)($pending['id'] ?? 0);
        }

        $stmt = mysqli_prepare($conexion,
            "UPDATE commission_payments SET status = 'paid', paid_at = NOW() WHERE id = ? LIMIT 1"
        );
        if (!$stmt) {
            cp_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $paymentId);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            cp_err('db_execute_failed: ' . $err, 500);
        }
        mysqli_stmt_close($stmt);
        cp_ok(['payment_id' => $paymentId]);
        break;
    }

    case 'delete_payment': {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            cp_err('payment_id required');
        }
        $stmt = mysqli_prepare($conexion, "DELETE FROM commission_payments WHERE id = ? LIMIT 1");
        if (!$stmt) {
            cp_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $paymentId);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            cp_err('db_execute_failed: ' . $err, 500);
        }
        mysqli_stmt_close($stmt);
        cp_ok(['payment_id' => $paymentId]);
        break;
    }

    default:
        cp_err('invalid_action', 400);
}
