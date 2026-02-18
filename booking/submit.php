<?php
session_start();
include(__DIR__ . '/../inc/include.php');
require_once __DIR__ . '/../admin/include/roles.php';
require_once __DIR__ . '/../admin/include/password_utils.php';
require_once __DIR__ . '/../admin/include/email_config.php';
require_once __DIR__ . '/../inc/email_template.php';

function table_exists_local($conexion, $table)
{
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    return ($res && mysqli_num_rows($res) > 0);
}

function table_has_column_local($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function value_type_local($value)
{
    if (is_int($value)) {
        return 'i';
    }
    if (is_float($value)) {
        return 'd';
    }
    return 's';
}

function bind_stmt_params_local($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [];
    $bind[] = &$types;
    foreach ($params as $k => $val) {
        $bind[] = &$params[$k];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function random_hex_local($bytes = 16)
{
    $bytes = max(8, (int)$bytes);
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Exception $e) {
            // fallback below
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $raw = openssl_random_pseudo_bytes($bytes);
        if ($raw !== false && $raw !== null) {
            return bin2hex($raw);
        }
    }
    return md5(uniqid((string)mt_rand(), true)) . md5((string)microtime(true));
}

function insert_booking_items_mvp($conexion, $booking_request_id, $selected_offers, $medtravel_services)
{
    if (!table_exists_local($conexion, 'booking_request_items')) {
        error_log('booking_submit: booking_request_items table does not exist; skipping item insert for booking_request_id=' . intval($booking_request_id));
        return;
    }

    $hasOfferActive = table_has_column_local($conexion, 'provider_service_offers', 'is_active');
    $hasOfferDeleted = table_has_column_local($conexion, 'provider_service_offers', 'is_deleted');
    $hasProviderActive = table_has_column_local($conexion, 'providers', 'is_active');
    $hasProviderDeleted = table_has_column_local($conexion, 'providers', 'is_deleted');

    $offerSql = "SELECT o.provider_id
                 FROM provider_service_offers o
                 INNER JOIN providers p ON p.id = o.provider_id
                 WHERE o.id = ?";
    if ($hasOfferActive) {
        $offerSql .= " AND o.is_active = 1";
    }
    if ($hasOfferDeleted) {
        $offerSql .= " AND o.is_deleted = 0";
    }
    if ($hasProviderActive) {
        $offerSql .= " AND p.is_active = 1";
    }
    if ($hasProviderDeleted) {
        $offerSql .= " AND p.is_deleted = 0";
    }
    $offerSql .= " LIMIT 1";

    $offerStmt = mysqli_prepare($conexion, $offerSql);
    $insertMedicalStmt = mysqli_prepare(
        $conexion,
        "INSERT INTO booking_request_items (booking_request_id, item_type, offer_id, provider_id, item_status, created_at)
         VALUES (?, 'medical_offer', ?, ?, 'pending_provider', NOW())"
    );

    if ($offerStmt && $insertMedicalStmt) {
        foreach ($selected_offers as $offerId) {
            $offerId = intval($offerId);
            if ($offerId <= 0) {
                continue;
            }

            mysqli_stmt_bind_param($offerStmt, 'i', $offerId);
            if (!mysqli_stmt_execute($offerStmt)) {
                error_log('booking_submit: offer lookup failed for offer_id=' . $offerId . ' error=' . mysqli_stmt_error($offerStmt));
                continue;
            }
            $offerRes = mysqli_stmt_get_result($offerStmt);
            $offerRow = $offerRes ? mysqli_fetch_assoc($offerRes) : null;
            if (!$offerRow || empty($offerRow['provider_id'])) {
                error_log('booking_submit: offer skipped (not found/invalid/inactive) offer_id=' . $offerId);
                continue;
            }

            $providerId = intval($offerRow['provider_id']);
            mysqli_stmt_bind_param($insertMedicalStmt, 'iii', $booking_request_id, $offerId, $providerId);
            if (!mysqli_stmt_execute($insertMedicalStmt)) {
                error_log('booking_submit: medical item insert failed booking_request_id=' . intval($booking_request_id) . ' offer_id=' . $offerId . ' error=' . mysqli_stmt_error($insertMedicalStmt));
            }
        }
    } else {
        error_log('booking_submit: prepare failed for medical items. offerStmt=' . ($offerStmt ? 'ok' : 'null') . ', insertMedicalStmt=' . ($insertMedicalStmt ? 'ok' : 'null'));
    }
    if ($offerStmt) {
        mysqli_stmt_close($offerStmt);
    }
    if ($insertMedicalStmt) {
        mysqli_stmt_close($insertMedicalStmt);
    }

    $serviceProviderColumn = null;
    if (table_has_column_local($conexion, 'medtravel_services_catalog', 'provider_id')) {
        $serviceProviderColumn = 'provider_id';
    } elseif (table_has_column_local($conexion, 'medtravel_services_catalog', 'service_provider_id')) {
        $serviceProviderColumn = 'service_provider_id';
    }

    if ($serviceProviderColumn === null) {
        if (!empty($medtravel_services)) {
            error_log('booking_submit: medtravel_services_catalog has no provider_id/service_provider_id; skipping complementary item insert for booking_request_id=' . intval($booking_request_id));
        }
        return;
    }

    $hasSvcActive = table_has_column_local($conexion, 'medtravel_services_catalog', 'is_active');
    $hasSvcDeleted = table_has_column_local($conexion, 'medtravel_services_catalog', 'is_deleted');
    $hasSvcProviderTable = table_exists_local($conexion, 'service_providers');
    $hasSvcProviderActive = $hasSvcProviderTable && table_has_column_local($conexion, 'service_providers', 'is_active');
    $hasSvcProviderDeleted = $hasSvcProviderTable && table_has_column_local($conexion, 'service_providers', 'is_deleted');

    $serviceSql = "SELECT m.`{$serviceProviderColumn}` AS service_provider_id
                   FROM medtravel_services_catalog m";
    if ($hasSvcProviderTable) {
        $serviceSql .= " LEFT JOIN service_providers sp ON sp.id = m.`{$serviceProviderColumn}`";
    }
    $serviceSql .= " WHERE m.id = ?";
    if ($hasSvcActive) {
        $serviceSql .= " AND m.is_active = 1";
    }
    if ($hasSvcDeleted) {
        $serviceSql .= " AND m.is_deleted = 0";
    }
    if ($hasSvcProviderActive) {
        $serviceSql .= " AND sp.is_active = 1";
    }
    if ($hasSvcProviderDeleted) {
        $serviceSql .= " AND sp.is_deleted = 0";
    }
    $serviceSql .= " LIMIT 1";

    $serviceStmt = mysqli_prepare($conexion, $serviceSql);
    $insertComplementaryStmt = mysqli_prepare(
        $conexion,
        "INSERT INTO booking_request_items (booking_request_id, item_type, medtravel_service_id, service_provider_id, item_status, created_at)
         VALUES (?, 'complementary_service', ?, ?, 'pending_provider', NOW())"
    );

    if ($serviceStmt && $insertComplementaryStmt) {
        foreach ($medtravel_services as $serviceId) {
            $serviceId = intval($serviceId);
            if ($serviceId <= 0) {
                continue;
            }

            mysqli_stmt_bind_param($serviceStmt, 'i', $serviceId);
            if (!mysqli_stmt_execute($serviceStmt)) {
                error_log('booking_submit: service lookup failed for medtravel_service_id=' . $serviceId . ' error=' . mysqli_stmt_error($serviceStmt));
                continue;
            }
            $serviceRes = mysqli_stmt_get_result($serviceStmt);
            $serviceRow = $serviceRes ? mysqli_fetch_assoc($serviceRes) : null;
            if (!$serviceRow || empty($serviceRow['service_provider_id'])) {
                error_log('booking_submit: complementary service skipped (not found/invalid/inactive) medtravel_service_id=' . $serviceId);
                continue;
            }

            $serviceProviderId = intval($serviceRow['service_provider_id']);
            mysqli_stmt_bind_param($insertComplementaryStmt, 'iii', $booking_request_id, $serviceId, $serviceProviderId);
            if (!mysqli_stmt_execute($insertComplementaryStmt)) {
                error_log('booking_submit: complementary item insert failed booking_request_id=' . intval($booking_request_id) . ' medtravel_service_id=' . $serviceId . ' error=' . mysqli_stmt_error($insertComplementaryStmt));
            }
        }
    } else {
        error_log('booking_submit: prepare failed for complementary items. serviceStmt=' . ($serviceStmt ? 'ok' : 'null') . ', insertComplementaryStmt=' . ($insertComplementaryStmt ? 'ok' : 'null'));
    }

    if ($serviceStmt) {
        mysqli_stmt_close($serviceStmt);
    }
    if ($insertComplementaryStmt) {
        mysqli_stmt_close($insertComplementaryStmt);
    }
}

function find_or_create_client_user_for_booking($conexion, $booking)
{
    $email = trim((string)($booking['email'] ?? ''));
    if ($email === '') {
        return 0;
    }

    $hasUsersSoftDelete = table_has_column_local($conexion, 'usuarios', 'is_deleted');
    $findSql = "SELECT * FROM usuarios WHERE (email = ? OR usuario = ?)";
    if ($hasUsersSoftDelete) {
        $findSql .= " AND is_deleted = 0";
    }
    $findSql .= " ORDER BY id ASC LIMIT 1";

    $stmtFind = mysqli_prepare($conexion, $findSql);
    if (!$stmtFind) {
        error_log('booking_submit: find_or_create_client_user prepare failed: ' . mysqli_error($conexion));
        return 0;
    }
    mysqli_stmt_bind_param($stmtFind, 'ss', $email, $email);
    if (!mysqli_stmt_execute($stmtFind)) {
        error_log('booking_submit: find_or_create_client_user execute failed: ' . mysqli_stmt_error($stmtFind));
        mysqli_stmt_close($stmtFind);
        return 0;
    }
    $resFind = mysqli_stmt_get_result($stmtFind);
    $userRow = $resFind ? mysqli_fetch_assoc($resFind) : null;
    mysqli_stmt_close($stmtFind);

    if ($userRow && !empty($userRow['id'])) {
        return intval($userRow['id']);
    }

    $userName = trim((string)($booking['name'] ?? 'Client'));
    if ($userName === '') {
        $userName = 'Client';
    }

    $baseToken = function_exists('generate_user_token') ? generate_user_token() : md5(uniqid(rand(), true));
    $randomPassword = random_hex_local(16);
    $passwordHash = function_exists('hash_password')
        ? hash_password($randomPassword, $baseToken)
        : password_hash($randomPassword, PASSWORD_DEFAULT);

    $data = [];
    if (table_has_column_local($conexion, 'usuarios', 'usuario')) {
        $data['usuario'] = $email;
    }
    if (table_has_column_local($conexion, 'usuarios', 'password')) {
        $data['password'] = $passwordHash;
    }
    if (table_has_column_local($conexion, 'usuarios', 'avatar')) {
        $data['avatar'] = 'img/perfil/default.png';
    }
    if (table_has_column_local($conexion, 'usuarios', 'nombre')) {
        $data['nombre'] = $userName;
    }
    if (table_has_column_local($conexion, 'usuarios', 'activo')) {
        $data['activo'] = 1;
    }
    if (table_has_column_local($conexion, 'usuarios', 'token')) {
        $data['token'] = $baseToken;
    }
    if (table_has_column_local($conexion, 'usuarios', 'empresa')) {
        $data['empresa'] = 'MedTravel Clients';
    }
    if (table_has_column_local($conexion, 'usuarios', 'ppal')) {
        $data['ppal'] = 0;
    }
    if (table_has_column_local($conexion, 'usuarios', 'usrlogin')) {
        $data['usrlogin'] = $email;
    }
    if (table_has_column_local($conexion, 'usuarios', 'rol')) {
        $data['rol'] = (string)(defined('ROLE_CLIENT') ? ROLE_CLIENT : 3);
    }
    if (table_has_column_local($conexion, 'usuarios', 'role_id')) {
        $data['role_id'] = (int)(defined('ROLE_CLIENT') ? ROLE_CLIENT : 3);
    }
    if (table_has_column_local($conexion, 'usuarios', 'cargo')) {
        $data['cargo'] = 'Cliente';
    }
    if (table_has_column_local($conexion, 'usuarios', 'email')) {
        $data['email'] = $email;
    }
    if (table_has_column_local($conexion, 'usuarios', 'telefono')) {
        $data['telefono'] = trim((string)($booking['phone'] ?? ''));
    }
    if (table_has_column_local($conexion, 'usuarios', 'cambio_password')) {
        $data['cambio_password'] = 0;
    }

    if (empty($data['usuario']) || empty($data['password'])) {
        error_log('booking_submit: cannot create client user due missing required columns (usuario/password)');
        return 0;
    }

    $columns = array_keys($data);
    $values = array_values($data);
    $types = '';
    foreach ($values as $value) {
        $types .= value_type_local($value);
    }
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $insertSql = "INSERT INTO usuarios (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})";

    $stmtInsert = mysqli_prepare($conexion, $insertSql);
    if (!$stmtInsert) {
        error_log('booking_submit: create client user prepare failed: ' . mysqli_error($conexion));
        return 0;
    }

    if (!bind_stmt_params_local($stmtInsert, $types, $values)) {
        error_log('booking_submit: create client user bind failed');
        mysqli_stmt_close($stmtInsert);
        return 0;
    }

    if (!mysqli_stmt_execute($stmtInsert)) {
        error_log('booking_submit: create client user execute failed: ' . mysqli_stmt_error($stmtInsert));
        mysqli_stmt_close($stmtInsert);
        return 0;
    }

    $newId = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtInsert);
    return intval($newId);
}

function update_booking_client_user($conexion, $bookingRequestId, $clientUserId)
{
    if ($bookingRequestId <= 0 || $clientUserId <= 0) {
        return;
    }
    if (!table_has_column_local($conexion, 'booking_requests', 'client_user_id')) {
        return;
    }

    $stmt = mysqli_prepare($conexion, "UPDATE booking_requests SET client_user_id = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        error_log('booking_submit: update booking client_user_id prepare failed: ' . mysqli_error($conexion));
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $clientUserId, $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('booking_submit: update booking client_user_id execute failed: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
}

function set_password_reset_token_for_user($conexion, $userId)
{
    $result = [
        'enabled' => false,
        'saved' => false,
        'token' => '',
        'expires_at' => '',
    ];

    if ($userId <= 0) {
        return $result;
    }

    $hasToken = table_has_column_local($conexion, 'usuarios', 'password_reset_token');
    $hasExpiry = table_has_column_local($conexion, 'usuarios', 'password_reset_expires_at');
    if (!$hasToken || !$hasExpiry) {
        return $result;
    }

    $result['enabled'] = true;
    $token = random_hex_local(32);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ? LIMIT 1"
    );
    if (!$stmt) {
        error_log('booking_submit: set reset token prepare failed: ' . mysqli_error($conexion));
        return $result;
    }

    mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiresAt, $userId);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('booking_submit: set reset token execute failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return $result;
    }
    mysqli_stmt_close($stmt);

    $result['saved'] = true;
    $result['token'] = $token;
    $result['expires_at'] = $expiresAt;
    return $result;
}

function fetch_booking_summary_items($conexion, $selected_offers, $medtravel_services)
{
    $items = [];

    if (!empty($selected_offers)) {
        $offerIds = array_values(array_filter(array_map('intval', $selected_offers)));
        if (!empty($offerIds)) {
            $offerCsv = implode(',', $offerIds);
            $hasOfferDeleted = table_has_column_local($conexion, 'provider_service_offers', 'is_deleted');
            $hasProviderDeleted = table_has_column_local($conexion, 'providers', 'is_deleted');
            $offerSql = "SELECT o.id,
                                COALESCE(NULLIF(o.title, ''), sc.name, CONCAT('Offer #', o.id)) AS item_name,
                                COALESCE(p.name, 'MedTravel') AS provider_name,
                                COALESCE(cat.name, 'General Medical') AS category_name,
                                o.price_from AS price,
                                COALESCE(NULLIF(o.currency, ''), 'USD') AS currency
                         FROM provider_service_offers o
                         LEFT JOIN providers p ON p.id = o.provider_id
                         LEFT JOIN service_catalog sc ON sc.id = o.service_id
                         LEFT JOIN service_categories cat ON cat.id = sc.category_id
                         WHERE o.id IN ({$offerCsv})";
            if ($hasOfferDeleted) {
                $offerSql .= " AND o.is_deleted = 0";
            }
            if ($hasProviderDeleted) {
                $offerSql .= " AND (p.id IS NULL OR p.is_deleted = 0)";
            }
            $offerSql .= " ORDER BY o.id ASC";

            $offerRes = mysqli_query($conexion, $offerSql);
            if ($offerRes) {
                while ($row = mysqli_fetch_assoc($offerRes)) {
                    $currency = strtoupper(trim((string)($row['currency'] ?? 'USD')));
                    if ($currency === '') {
                        $currency = 'USD';
                    }
                    $price = is_numeric($row['price']) ? (float)$row['price'] : 0.0;
                    $items[] = [
                        'item_type' => 'medical_offer',
                        'item_type_label' => 'Medical',
                        'name' => (string)$row['item_name'],
                        'provider' => (string)$row['provider_name'],
                        'category' => (string)$row['category_name'],
                        'price' => $price,
                        'currency' => $currency,
                    ];
                }
            }
        }
    }

    if (!empty($medtravel_services)) {
        $serviceIds = array_values(array_filter(array_map('intval', $medtravel_services)));
        if (!empty($serviceIds)) {
            $serviceCsv = implode(',', $serviceIds);
            $providerCol = null;
            if (table_has_column_local($conexion, 'medtravel_services_catalog', 'provider_id')) {
                $providerCol = 'provider_id';
            } elseif (table_has_column_local($conexion, 'medtravel_services_catalog', 'service_provider_id')) {
                $providerCol = 'service_provider_id';
            }

            $hasSvcDeleted = table_has_column_local($conexion, 'medtravel_services_catalog', 'is_deleted');
            $hasSvcProviderDeleted = table_has_column_local($conexion, 'service_providers', 'is_deleted');

            $serviceSql = "SELECT m.id,
                                  COALESCE(NULLIF(m.service_name, ''), CONCAT('Service #', m.id)) AS item_name,
                                  COALESCE(sp.provider_name, 'MedTravel') AS provider_name,
                                  COALESCE(NULLIF(m.service_type, ''), 'other') AS category_name,
                                  m.sale_price AS price,
                                  COALESCE(NULLIF(m.currency, ''), 'USD') AS currency
                           FROM medtravel_services_catalog m";
            if ($providerCol !== null && table_exists_local($conexion, 'service_providers')) {
                $serviceSql .= " LEFT JOIN service_providers sp ON sp.id = m.`{$providerCol}`";
            } else {
                $serviceSql .= " LEFT JOIN service_providers sp ON 1=0";
            }
            $serviceSql .= " WHERE m.id IN ({$serviceCsv})";
            if ($hasSvcDeleted) {
                $serviceSql .= " AND m.is_deleted = 0";
            }
            if ($hasSvcProviderDeleted) {
                $serviceSql .= " AND (sp.id IS NULL OR sp.is_deleted = 0)";
            }
            $serviceSql .= " ORDER BY m.id ASC";

            $serviceRes = mysqli_query($conexion, $serviceSql);
            if ($serviceRes) {
                while ($row = mysqli_fetch_assoc($serviceRes)) {
                    $currency = strtoupper(trim((string)($row['currency'] ?? 'USD')));
                    if ($currency === '') {
                        $currency = 'USD';
                    }
                    $price = is_numeric($row['price']) ? (float)$row['price'] : 0.0;
                    $items[] = [
                        'item_type' => 'complementary_service',
                        'item_type_label' => 'Complementary',
                        'name' => (string)$row['item_name'],
                        'provider' => (string)$row['provider_name'],
                        'category' => ucfirst((string)$row['category_name']),
                        'price' => $price,
                        'currency' => $currency,
                    ];
                }
            }
        }
    }

    return $items;
}

function build_totals_by_currency($items)
{
    $totals = [];
    foreach ($items as $item) {
        $currency = strtoupper(trim((string)($item['currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        $price = isset($item['price']) ? (float)$item['price'] : 0.0;
        if ($price <= 0) {
            continue;
        }
        if (!isset($totals[$currency])) {
            $totals[$currency] = 0.0;
        }
        $totals[$currency] += $price;
    }
    return $totals;
}

function format_totals_by_currency($totals)
{
    if (empty($totals)) {
        return 'Price on request';
    }
    $parts = [];
    foreach ($totals as $currency => $amount) {
        $parts[] = $currency . ' $' . number_format((float)$amount, 2);
    }
    return implode(' / ', $parts);
}

function format_item_price_display($item)
{
    $price = isset($item['price']) ? (float)$item['price'] : 0.0;
    $currency = strtoupper(trim((string)($item['currency'] ?? 'USD')));
    if ($currency === '') {
        $currency = 'USD';
    }
    if ($price <= 0) {
        return 'On request';
    }
    return $currency . ' $' . number_format($price, 2);
}

function escape_html_local($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function build_submission_summary_payload($bookingRequestId, $booking, $timeline, $items)
{
    $totals = build_totals_by_currency($items);
    $normalizedItems = [];
    foreach ($items as $item) {
        $item['price_display'] = format_item_price_display($item);
        $normalizedItems[] = $item;
    }

    return [
        'booking_id' => (int)$bookingRequestId,
        'patient_name' => (string)($booking['name'] ?? ''),
        'patient_email' => (string)($booking['email'] ?? ''),
        'patient_phone' => (string)($booking['phone'] ?? ''),
        'destination' => (string)($booking['destination'] ?? ''),
        'timeline' => (string)$timeline,
        'items' => $normalizedItems,
        'totals_by_currency' => $totals,
        'total_display' => format_totals_by_currency($totals),
    ];
}

function build_booking_confirmation_content_html($summaryPayload, $passwordResetUrl, $loginLink)
{
    $bookingId = (int)($summaryPayload['booking_id'] ?? 0);
    $patientName = trim((string)($summaryPayload['patient_name'] ?? ''));
    $patientEmail = trim((string)($summaryPayload['patient_email'] ?? ''));
    if ($patientName === '') {
        $patientName = 'Patient';
    }

    $timeline = trim((string)($summaryPayload['timeline'] ?? ''));
    $destination = trim((string)($summaryPayload['destination'] ?? ''));
    $totalDisplay = trim((string)($summaryPayload['total_display'] ?? 'Price on request'));
    $items = (isset($summaryPayload['items']) && is_array($summaryPayload['items'])) ? $summaryPayload['items'] : [];

    $itemRowsHtml = '';
    foreach ($items as $item) {
        $typeLabel = trim((string)($item['item_type_label'] ?? ''));
        if ($typeLabel === '') {
            $typeLabel = ((string)($item['item_type'] ?? '') === 'complementary_service') ? 'Complementary' : 'Medical';
        }
        $itemRowsHtml .= '<tr>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#0f172a;">' . escape_html_local($item['name'] ?? '-') . '</td>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . escape_html_local($typeLabel) . '</td>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . escape_html_local($item['provider'] ?? 'MedTravel') . '</td>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . escape_html_local($item['price_display'] ?? format_item_price_display($item)) . '</td>'
            . '</tr>';
    }
    if ($itemRowsHtml === '') {
        $itemRowsHtml = '<tr><td colspan="4" style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#64748b;">No services were itemized yet. A coordinator will complete details with you.</td></tr>';
    }

    $portalAccessBlock = ''
        . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Access your MedTravel Patient Portal</h3>'
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">We have created a secure patient account for you.</p>'
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Username:</strong> ' . escape_html_local($patientEmail !== '' ? $patientEmail : 'Not available') . '</p>'
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">To activate your access, please create your password using the secure link below.</p>';

    if ($passwordResetUrl !== '') {
        $portalAccessBlock .= '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Create your password:</strong> <a href="' . escape_html_local($passwordResetUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . escape_html_local($passwordResetUrl) . '</a></p>'
            . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">If the button does not work, copy and paste this link:<br><a href="' . escape_html_local($passwordResetUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . escape_html_local($passwordResetUrl) . '</a></p>';
    }

    $portalAccessBlock .= ''
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">For security reasons, this link expires in 24 hours. If it expires, you can request a new one on the same page.</p>'
        . '<p style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">In your portal you can:</p>'
        . '<ul style="margin:0 0 16px 18px; padding:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">'
        . '<li style="margin:0 0 6px 0;">Track the status of each requested service</li>'
        . '<li style="margin:0 0 6px 0;">See provider confirmations or proposed changes</li>'
        . '<li style="margin:0 0 6px 0;">Review updates from MedTravel</li>'
        . '<li style="margin:0;">Follow your case progress in real time</li>'
        . '</ul>';

    $content = ''
        . '<p style="margin:0 0 14px 0;">Hello ' . escape_html_local($patientName) . ',</p>'
        . '<p style="margin:0 0 14px 0;">We received your request and opened your case with MedTravel.</p>'
        . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 16px 0;">'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Booking ID:</strong> #' . $bookingId . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Patient:</strong> ' . escape_html_local($patientName) . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Email:</strong> ' . escape_html_local($patientEmail) . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Destination:</strong> ' . escape_html_local($destination !== '' ? $destination : 'Not specified') . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Timeline:</strong> ' . escape_html_local($timeline !== '' ? $timeline : 'To be defined') . '</td></tr>'
        . '</table>'
        . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Selected services</h3>'
        . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 12px 0;">'
        . '<tr>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Service</th>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Type</th>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Provider</th>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Price</th>'
        . '</tr>'
        . $itemRowsHtml
        . '</table>'
        . '<p style="margin:0 0 16px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#0f172a;"><strong>Total estimated:</strong> ' . escape_html_local($totalDisplay) . '</p>'
        . $portalAccessBlock
        . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Next steps</h3>'
        . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 16px 0;">'
        . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">1. MedTravel reviews your request.</td></tr>'
        . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">2. We coordinate providers.</td></tr>'
        . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">3. You will receive confirmation updates.</td></tr>'
        . '</table>'
        . '<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">After creating your password, you can sign in here: <a href="' . escape_html_local($loginLink) . '" style="color:#0b4ea2; text-decoration:none;">' . escape_html_local($loginLink) . '</a></p>';

    return $content;
}

function send_booking_confirmation_email($conexion, $email, $summaryPayload, $resetToken)
{
    $email = trim((string)$email);
    if ($email === '') {
        return;
    }

    $bookingId = (int)($summaryPayload['booking_id'] ?? 0);
    $subject = "MedTravel – Request received (ID #{$bookingId})";

    $passwordResetUrl = $resetToken !== ''
        ? 'https://medtravel.com.co/set_password.php?token=' . urlencode($resetToken)
        : '';
    $loginLink = 'https://medtravel.com.co/login.php';

    $body = '';
    if (function_exists('renderMedTravelEmail')) {
        $contentHtml = build_booking_confirmation_content_html($summaryPayload, $passwordResetUrl, $loginLink);
        $body = renderMedTravelEmail(
            'Request received',
            'We received your request and opened your MedTravel case.',
            $contentHtml,
            'This is an automated message.',
            ($passwordResetUrl !== '' ? [
                'text' => 'Create your password',
                'url' => $passwordResetUrl,
            ] : null)
        );
    }

    if ($body === '') {
        // Fallback legacy body when template renderer/template file is not deployed yet.
        $body = '<h2>MedTravel request confirmation</h2>'
            . '<p>Thank you. We have received your request and created your case.</p>'
            . '<p><strong>Booking ID:</strong> #' . $bookingId . '</p>'
            . '<p><strong>Patient:</strong> ' . htmlspecialchars((string)($summaryPayload['patient_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>'
            . '<strong>Email:</strong> ' . htmlspecialchars((string)($summaryPayload['patient_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Total estimated:</strong> ' . htmlspecialchars((string)($summaryPayload['total_display'] ?? 'Price on request'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Login:</strong> <a href="' . htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($loginLink, ENT_QUOTES, 'UTF-8') . '</a></p>';
        if ($passwordResetUrl !== '') {
            $body .= '<p><strong>Create your password:</strong> <a href="' . htmlspecialchars($passwordResetUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($passwordResetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
                . '<p>If the button does not work, copy and paste this link:<br><a href="' . htmlspecialchars($passwordResetUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($passwordResetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }
    }

    try {
        $emailResult = sendEmail($email, $subject, $body, 'patientcare', [], $conexion);
        if ($emailResult !== true) {
            error_log('booking_submit: confirmation email returned non-true result for ' . $email . ' payload=' . json_encode($emailResult));
        }
    } catch (Exception $e) {
        error_log('booking_submit: confirmation email exception for ' . $email . ': ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: wizard.php');
    exit;
}

$booking = isset($_SESSION['booking_request']) ? $_SESSION['booking_request'] : [];
if (empty($booking['name']) || empty($booking['email'])) {
    $_SESSION['booking_request_status'] = 'error';
    $_SESSION['booking_request_message'] = 'Please complete the contact data before selecting services.';
    header('Location: wizard.php');
    exit;
}

$selected_offers = isset($_POST['selected_offers']) ? array_values(array_filter(array_map('intval', $_POST['selected_offers']))) : [];
$medtravel_services = isset($_POST['medtravel_services']) ? array_values(array_filter(array_map('intval', $_POST['medtravel_services']))) : [];

$budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? number_format((float) $_POST['budget'], 2, '.', '') : null;

$timeline_from = isset($_POST['timeline_from']) ? trim($_POST['timeline_from']) : '';
$timeline_to = isset($_POST['timeline_to']) ? trim($_POST['timeline_to']) : '';
$timeline = '';
if ($timeline_from && $timeline_to) {
    $timeline = $timeline_from . ' to ' . $timeline_to;
} elseif ($timeline_from) {
    $timeline = 'From ' . $timeline_from;
} elseif ($timeline_to) {
    $timeline = 'Until ' . $timeline_to;
}

$additional_notes = isset($_POST['additional_notes']) ? trim($_POST['additional_notes']) : '';

if (!empty($medtravel_services)) {
    $ids = implode(',', array_map('intval', $medtravel_services));
    $med_query = mysqli_query($conexion, "SELECT service_name FROM medtravel_services_catalog WHERE id IN ($ids)");
    $med_names = [];
    if ($med_query) {
        while ($row = mysqli_fetch_assoc($med_query)) {
            $med_names[] = $row['service_name'];
        }
    }
    if (!empty($med_names)) {
        $additional_notes .= "\n\nMedTravel Services Requested:\n- " . implode("\n- ", $med_names);
    }
}

$origin = isset($booking['origin']) ? $booking['origin'] : 'wizard';
$phone = isset($booking['phone']) ? $booking['phone'] : '';
$selected_offers_json = json_encode($selected_offers);
$booking_datetime = $timeline_from ?: (isset($booking['datetime']) ? $booking['datetime'] : '');

$booking['datetime'] = $booking_datetime;
$booking['timeline_from'] = $timeline_from;
$booking['timeline_to'] = $timeline_to;
$_SESSION['booking_request'] = $booking;

$destination = isset($booking['destination']) ? $booking['destination'] : '';
$persons = isset($booking['persons']) ? $booking['persons'] : '';
$category = isset($booking['category']) ? $booking['category'] : '';
$special_request = isset($booking['special_request']) ? $booking['special_request'] : '';

$saved = false;
$booking_request_id = 0;
$client_user_id = 0;
$summaryPayload = [];

$insert_sql = "INSERT INTO booking_requests
               (name, email, phone, origin, booking_datetime, destination, persons, category,
                special_request, selected_offers, budget, timeline, additional_notes)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
$stmt = mysqli_prepare($conexion, $insert_sql);

if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        'sssssssssssss',
        $booking['name'],
        $booking['email'],
        $phone,
        $origin,
        $booking_datetime,
        $destination,
        $persons,
        $category,
        $special_request,
        $selected_offers_json,
        $budget,
        $timeline,
        $additional_notes
    );

    $saved = mysqli_stmt_execute($stmt);
    if ($saved) {
        $booking_request_id = mysqli_insert_id($conexion);
        if ($booking_request_id > 0) {
            insert_booking_items_mvp($conexion, $booking_request_id, $selected_offers, $medtravel_services);

            $client_user_id = find_or_create_client_user_for_booking($conexion, $booking);
            if ($client_user_id > 0) {
                update_booking_client_user($conexion, $booking_request_id, $client_user_id);
            }

            $resetInfo = ['enabled' => false, 'saved' => false, 'token' => ''];
            if ($client_user_id > 0) {
                $resetInfo = set_password_reset_token_for_user($conexion, $client_user_id);
            }

            $items = fetch_booking_summary_items($conexion, $selected_offers, $medtravel_services);
            $summaryPayload = build_submission_summary_payload($booking_request_id, $booking, $timeline, $items);
            $_SESSION['booking_submission_summary'] = $summaryPayload;

            send_booking_confirmation_email(
                $conexion,
                (string)$booking['email'],
                $summaryPayload,
                ($resetInfo['saved'] ? (string)$resetInfo['token'] : '')
            );
        }
    } else {
        error_log('booking_submit: booking_requests insert failed: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
} else {
    error_log('booking_submit: booking_requests prepare failed: ' . mysqli_error($conexion));
}

if ($saved && $booking_request_id > 0) {
    $_SESSION['booking_request_status'] = 'submitted';
    $offers_count = count($selected_offers) + count($medtravel_services);
    $_SESSION['booking_request_message'] = "Your request (ID #{$booking_request_id}) with {$offers_count} selected item(s) was saved. One of our coordinators will reach back soon.";
} else {
    $_SESSION['booking_request_status'] = 'error';
    $_SESSION['booking_request_message'] = 'We could not save your request. Please try again.';
}

header('Location: wizard.php');
exit;
