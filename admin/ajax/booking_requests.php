<?php
include '../include/conexion.php';
require_once '../include/roles.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'forbidden']);
    exit;
}

function table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function table_exists($conexion, $table)
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$table];
}

function json_success($data = [])
{
    echo json_encode(array_merge(['ok' => true, 'success' => true], $data));
    exit;
}

function json_error($message, $httpCode = 400)
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'success' => false, 'message' => $message]);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$hasSoftDelete = table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasDeletedAt = table_has_column($conexion, 'booking_requests', 'deleted_at');
$hasDeletedBy = table_has_column($conexion, 'booking_requests', 'deleted_by');

if ($action === 'get_all') {
    $query = "SELECT id, name, email, destination, booking_datetime, persons,
                     selected_offers, status, origin, created_at
              FROM booking_requests
              WHERE 1=1";
    if ($hasSoftDelete) {
        $query .= " AND is_deleted = 0";
    }
    $query .= " ORDER BY created_at DESC";

    $result = mysqli_query($conexion, $query);
    if (!$result) {
        json_error('Error al cargar solicitudes: ' . mysqli_error($conexion), 500);
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    json_success(['data' => $data]);
}

if ($action === 'get_detail') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_error('ID inválido');
    }

    $query = "SELECT * FROM booking_requests WHERE id = ?";
    if ($hasSoftDelete) {
        $query .= " AND is_deleted = 0";
    }
    $query .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $query);
    if (!$stmt) {
        json_error('Error interno (prepare)', 500);
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_error('Error al consultar: ' . $err, 500);
    }

    $result = mysqli_stmt_get_result($stmt);
    $data = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$data) {
        json_error('No se encontró la solicitud', 404);
    }

    $itemsPayload = [
        'medical' => [],
        'complementary' => [],
        'totals' => [
            'subtotal' => 0.0,
            'currency_mix' => false,
            'currency' => '',
            'by_currency' => [],
        ],
        'has_items_table' => false,
        'has_pending_admin' => false,
        'can_authorize_pending_admin' => false,
    ];

    if (table_exists($conexion, 'booking_request_items')) {
        $itemsPayload['has_items_table'] = true;

        $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasItemsProposedPrice = table_has_column($conexion, 'booking_request_items', 'proposed_price');
        $hasItemsCurrency = table_has_column($conexion, 'booking_request_items', 'currency');
        $hasItemsUpdatedAt = table_has_column($conexion, 'booking_request_items', 'updated_at');
        $hasOfferSoftDelete = table_has_column($conexion, 'provider_service_offers', 'is_deleted');
        $hasProviderSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $hasComplementarySoftDelete = table_has_column($conexion, 'medtravel_services_catalog', 'is_deleted');
        $serviceProvidersTableExists = table_exists($conexion, 'service_providers');
        $hasServiceProviderSoftDelete = $serviceProvidersTableExists && table_has_column($conexion, 'service_providers', 'is_deleted');

        $serviceProviderNameExpr = "'Service Provider'";
        if ($serviceProvidersTableExists) {
            if (table_has_column($conexion, 'service_providers', 'provider_name')) {
                $serviceProviderNameExpr = 'sp.provider_name';
            } elseif (table_has_column($conexion, 'service_providers', 'name')) {
                $serviceProviderNameExpr = 'sp.name';
            }
        }

        $itemsSql = "SELECT
                        i.id,
                        i.booking_request_id,
                        i.item_type,
                        i.offer_id,
                        i.medtravel_service_id,
                        i.provider_id,
                        i.service_provider_id,
                        i.item_status,
                        " . ($hasItemsProposedPrice ? "i.proposed_price" : "NULL") . " AS item_price,
                        " . ($hasItemsCurrency ? "i.currency" : "NULL") . " AS item_currency,
                        " . ($hasItemsUpdatedAt ? "i.updated_at" : "NULL") . " AS item_updated_at,
                        COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), CONCAT('Offer #', i.offer_id)) AS medical_item_name,
                        COALESCE(NULLIF(ms.service_name, ''), CONCAT('Service #', i.medtravel_service_id)) AS complementary_item_name,
                        COALESCE(NULLIF(p.name, ''), CONCAT('Provider #', i.provider_id)) AS medical_provider_name,
                        COALESCE(NULLIF({$serviceProviderNameExpr}, ''), CONCAT('Service Provider #', i.service_provider_id)) AS complementary_provider_name,
                        COALESCE(NULLIF(cat.name, ''), 'General Medical') AS medical_category,
                        COALESCE(NULLIF(ms.service_type, ''), 'other') AS complementary_category,
                        o.price_from AS medical_price,
                        o.currency AS medical_currency,
                        ms.sale_price AS complementary_price,
                        ms.currency AS complementary_currency
                    FROM booking_request_items i
                    LEFT JOIN provider_service_offers o ON o.id = i.offer_id
                    LEFT JOIN providers p ON p.id = i.provider_id
                    LEFT JOIN service_catalog sc ON sc.id = o.service_id
                    LEFT JOIN service_categories cat ON cat.id = sc.category_id
                    LEFT JOIN medtravel_services_catalog ms ON ms.id = i.medtravel_service_id";
        if ($serviceProvidersTableExists) {
            $itemsSql .= " LEFT JOIN service_providers sp ON sp.id = i.service_provider_id";
        }
        $itemsSql .= "
                    WHERE i.booking_request_id = ?";

        if ($hasItemsSoftDelete) {
            $itemsSql .= " AND i.is_deleted = 0";
        }
        if ($hasOfferSoftDelete) {
            $itemsSql .= " AND (o.id IS NULL OR o.is_deleted = 0)";
        }
        if ($hasProviderSoftDelete) {
            $itemsSql .= " AND (p.id IS NULL OR p.is_deleted = 0)";
        }
        if ($hasComplementarySoftDelete) {
            $itemsSql .= " AND (ms.id IS NULL OR ms.is_deleted = 0)";
        }
        if ($hasServiceProviderSoftDelete && $serviceProvidersTableExists) {
            $itemsSql .= " AND (sp.id IS NULL OR sp.is_deleted = 0)";
        }
        $itemsSql .= " ORDER BY i.item_type ASC, i.id ASC";

        $stmtItems = mysqli_prepare($conexion, $itemsSql);
        if ($stmtItems) {
            mysqli_stmt_bind_param($stmtItems, 'i', $id);
            if (mysqli_stmt_execute($stmtItems)) {
                $itemsRes = mysqli_stmt_get_result($stmtItems);
                $totalsByCurrency = [];

                while ($itemRow = mysqli_fetch_assoc($itemsRes)) {
                    $itemType = (string)($itemRow['item_type'] ?? '');
                    $itemStatus = (string)($itemRow['item_status'] ?? '');
                    if ($itemStatus === 'pending_admin') {
                        $itemsPayload['has_pending_admin'] = true;
                    }

                    $price = 0.0;
                    $currency = '';
                    if (is_numeric($itemRow['item_price']) && (float)$itemRow['item_price'] > 0) {
                        $price = (float)$itemRow['item_price'];
                        $currency = trim((string)($itemRow['item_currency'] ?? ''));
                    } elseif ($itemType === 'medical_offer') {
                        if (is_numeric($itemRow['medical_price']) && (float)$itemRow['medical_price'] > 0) {
                            $price = (float)$itemRow['medical_price'];
                        }
                        $currency = trim((string)($itemRow['medical_currency'] ?? ''));
                    } elseif ($itemType === 'complementary_service') {
                        if (is_numeric($itemRow['complementary_price']) && (float)$itemRow['complementary_price'] > 0) {
                            $price = (float)$itemRow['complementary_price'];
                        }
                        $currency = trim((string)($itemRow['complementary_currency'] ?? ''));
                    }
                    if ($currency === '') {
                        $currency = 'USD';
                    }

                    if ($price > 0) {
                        if (!isset($totalsByCurrency[$currency])) {
                            $totalsByCurrency[$currency] = 0.0;
                        }
                        $totalsByCurrency[$currency] += $price;
                    }

                    if ($price > 0) {
                        $priceDisplay = $currency . ' $' . number_format($price, 2);
                    } else {
                        $priceDisplay = 'On request';
                    }

                    if ($itemType === 'medical_offer') {
                        $itemPayload = [
                            'id' => (int)$itemRow['id'],
                            'item_type' => 'medical_offer',
                            'item_type_label' => 'Medical',
                            'name' => (string)$itemRow['medical_item_name'],
                            'provider' => (string)$itemRow['medical_provider_name'],
                            'category' => (string)$itemRow['medical_category'],
                            'item_status' => $itemStatus,
                            'price' => $price,
                            'currency' => $currency,
                            'price_display' => $priceDisplay,
                        ];
                        $itemsPayload['medical'][] = $itemPayload;
                    } elseif ($itemType === 'complementary_service') {
                        $itemPayload = [
                            'id' => (int)$itemRow['id'],
                            'item_type' => 'complementary_service',
                            'item_type_label' => 'Complementary',
                            'name' => (string)$itemRow['complementary_item_name'],
                            'provider' => (string)$itemRow['complementary_provider_name'],
                            'category' => (string)$itemRow['complementary_category'],
                            'item_status' => $itemStatus,
                            'price' => $price,
                            'currency' => $currency,
                            'price_display' => $priceDisplay,
                        ];
                        $itemsPayload['complementary'][] = $itemPayload;
                    }
                }

                $subtotal = 0.0;
                foreach ($totalsByCurrency as $value) {
                    $subtotal += (float)$value;
                }
                $itemsPayload['totals']['subtotal'] = $subtotal;
                $itemsPayload['totals']['by_currency'] = $totalsByCurrency;
                $itemsPayload['totals']['currency_mix'] = (count($totalsByCurrency) > 1);
                if (count($totalsByCurrency) === 1) {
                    $keys = array_keys($totalsByCurrency);
                    $itemsPayload['totals']['currency'] = (string)$keys[0];
                }
            }
            mysqli_stmt_close($stmtItems);
        }

        if ($itemsPayload['has_pending_admin']) {
            // Solo mostrar acción de autorización cuando ya existen estados pending_admin.
            $itemsPayload['can_authorize_pending_admin'] = true;
        }
    }

    $legacySelectedOffers = [];
    if (!empty($data['selected_offers'])) {
        $decoded = json_decode((string)$data['selected_offers'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $legacyOfferId) {
                $legacyOfferId = intval($legacyOfferId);
                if ($legacyOfferId > 0) {
                    $legacySelectedOffers[] = $legacyOfferId;
                }
            }
        }
    }

    json_success([
        'data' => $data,
        'booking' => $data,
        'items' => $itemsPayload,
        'legacy' => [
            'selected_offers' => $legacySelectedOffers,
            'additional_notes' => (string)($data['additional_notes'] ?? ''),
            'selected_offers_raw' => (string)($data['selected_offers'] ?? ''),
        ],
    ]);
}

if ($action === 'get_offers_details') {
    $offer_ids = json_decode($_POST['offer_ids'] ?? '[]', true);
    if (empty($offer_ids) || !is_array($offer_ids)) {
        json_success(['data' => []]);
    }

    $offer_ids = array_values(array_filter(array_map('intval', $offer_ids), function ($id) {
        return $id > 0;
    }));

    if (empty($offer_ids)) {
        json_success(['data' => []]);
    }

    $ids_string = implode(',', $offer_ids);

    $hasOfferSoftDelete = table_has_column($conexion, 'provider_service_offers', 'is_deleted');
    $hasProviderSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');

    $query = "SELECT
                o.id, o.title, o.description, o.price_from, o.currency,
                p.name AS provider_name, p.city AS provider_city
              FROM provider_service_offers o
              INNER JOIN providers p ON o.provider_id = p.id
              WHERE o.id IN ($ids_string)";

    if ($hasOfferSoftDelete) {
        $query .= " AND o.is_deleted = 0";
    }
    if ($hasProviderSoftDelete) {
        $query .= " AND p.is_deleted = 0";
    }

    $result = mysqli_query($conexion, $query);
    if (!$result) {
        json_error('Error al cargar ofertas', 500);
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    json_success(['data' => $data]);
}

if ($action === 'update_status') {
    $id = intval($_POST['id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));

    $allowed_statuses = ['pending', 'contacted', 'confirmed', 'cancelled'];
    if ($id <= 0 || !in_array($status, $allowed_statuses, true)) {
        json_error('Datos inválidos');
    }

    $query = "UPDATE booking_requests SET status = ? WHERE id = ?";
    if ($hasSoftDelete) {
        $query .= " AND is_deleted = 0";
    }
    $stmt = mysqli_prepare($conexion, $query);
    if (!$stmt) {
        json_error('Error interno (prepare)', 500);
    }

    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_error('Error al actualizar: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        json_error('No se actualizó la solicitud (puede estar eliminada)', 404);
    }

    json_success(['message' => 'Estado actualizado']);
}

if ($action === 'force_cancel_item') {
    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_error('ID inválido');
    }
    if (!table_exists($conexion, 'booking_request_items')) {
        json_error('booking_request_items no disponible', 409);
    }
    if (!table_has_column($conexion, 'booking_request_items', 'item_status')) {
        json_error('item_status no disponible', 409);
    }

    $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasItemsUpdatedAt = table_has_column($conexion, 'booking_request_items', 'updated_at');

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET bri.item_status = 'cancelled'";
    if ($hasItemsUpdatedAt) {
        $sql .= ", bri.updated_at = NOW()";
    }
    $sql .= " WHERE bri.id = ?";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($hasSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_error('Error interno (prepare)', 500);
    }
    mysqli_stmt_bind_param($stmt, 'i', $itemId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_error('Error al cancelar item: ' . $err, 500);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        json_error('Item no encontrado o sin cambios', 404);
    }

    json_success(['message' => 'Item cancelado']);
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    $deletedBy = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

    if ($id <= 0) {
        json_error('ID inválido');
    }

    if (!$hasSoftDelete || !$hasDeletedAt || !$hasDeletedBy) {
        json_error('Soft delete no disponible en booking_requests. Ejecuta la migración SQL.', 409);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE booking_requests
         SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
         WHERE id = ? AND is_deleted = 0
         LIMIT 1"
    );
    if (!$stmt) {
        json_error('Error interno (prepare)', 500);
    }

    mysqli_stmt_bind_param($stmt, 'ii', $deletedBy, $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_error('Error al eliminar: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        json_error('Solicitud no encontrada o ya eliminada', 404);
    }

    json_success(['message' => 'Solicitud eliminada (soft)']);
}

if ($action === 'authorize_items') {
    $bookingId = intval($_POST['booking_id'] ?? 0);
    if ($bookingId <= 0) {
        json_error('ID inválido');
    }
    if (!table_exists($conexion, 'booking_request_items')) {
        json_error('booking_request_items no disponible', 409);
    }
    if (!table_has_column($conexion, 'booking_request_items', 'item_status')) {
        json_error('item_status no disponible', 409);
    }

    $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $whereSoft = $hasItemsSoftDelete ? ' AND is_deleted = 0' : '';

    $countSql = "SELECT COUNT(*) AS total
                 FROM booking_request_items
                 WHERE booking_request_id = ? AND item_status = 'pending_admin'{$whereSoft}";
    $stmtCount = mysqli_prepare($conexion, $countSql);
    if (!$stmtCount) {
        json_error('Error interno (prepare)', 500);
    }
    mysqli_stmt_bind_param($stmtCount, 'i', $bookingId);
    if (!mysqli_stmt_execute($stmtCount)) {
        $err = mysqli_stmt_error($stmtCount);
        mysqli_stmt_close($stmtCount);
        json_error('Error al consultar items: ' . $err, 500);
    }
    $resCount = mysqli_stmt_get_result($stmtCount);
    $countRow = $resCount ? mysqli_fetch_assoc($resCount) : null;
    mysqli_stmt_close($stmtCount);
    $pendingAdminCount = $countRow ? intval($countRow['total']) : 0;

    if ($pendingAdminCount <= 0) {
        json_success(['message' => 'No hay items en pending_admin para autorizar', 'updated' => 0]);
    }

    $setUpdatedAt = table_has_column($conexion, 'booking_request_items', 'updated_at') ? ', updated_at = NOW()' : '';
    $updateSql = "UPDATE booking_request_items
                  SET item_status = 'pending_provider'{$setUpdatedAt}
                  WHERE booking_request_id = ? AND item_status = 'pending_admin'{$whereSoft}";
    $stmtUpdate = mysqli_prepare($conexion, $updateSql);
    if (!$stmtUpdate) {
        json_error('Error interno (prepare)', 500);
    }
    mysqli_stmt_bind_param($stmtUpdate, 'i', $bookingId);
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        json_error('Error al autorizar items: ' . $err, 500);
    }
    $updated = mysqli_stmt_affected_rows($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);

    json_success(['message' => 'Items autorizados para proveedor', 'updated' => $updated]);
}

json_error('Acción no válida');
