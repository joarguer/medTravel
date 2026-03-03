<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/commission_gate.php';

function client_json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

$clientUserId = get_client_user_id();
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($requestId <= 0) {
    client_json_error('invalid_request_id');
}

if (!isset($conexion) || !$conexion || !client_table_exists($conexion, 'booking_requests')) {
    client_json_error('booking_requests_not_available', 409);
}
$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_json_error('booking_owner_scope_unavailable', 409);
}

$hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasTimeline = client_table_has_column($conexion, 'booking_requests', 'timeline');
$hasStatus = client_table_has_column($conexion, 'booking_requests', 'status');
$hasSpecialRequest = client_table_has_column($conexion, 'booking_requests', 'special_request');
$hasAdditionalNotes = client_table_has_column($conexion, 'booking_requests', 'additional_notes');
$hasTermsAccepted = client_table_has_column($conexion, 'booking_requests', 'terms_accepted');
$hasTermsVersion = client_table_has_column($conexion, 'booking_requests', 'terms_version');
$hasTermsAcceptedAt = client_table_has_column($conexion, 'booking_requests', 'terms_accepted_at');
$hasTermsAcceptedDate = client_table_has_column($conexion, 'booking_requests', 'terms_accepted_date');

$bookingSql = "SELECT br.id, br.created_at, br.destination";
$bookingSql .= $hasTimeline ? ", br.timeline" : ", '' AS timeline";
$bookingSql .= $hasStatus ? ", br.status" : ", 'pending' AS status";
$bookingSql .= $hasSpecialRequest ? ", br.special_request" : ", '' AS special_request";
$bookingSql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
$bookingSql .= $hasTermsAccepted ? ", br.terms_accepted" : ", 0 AS terms_accepted";
if ($hasTermsAcceptedAt) {
    $bookingSql .= ", br.terms_accepted_at";
} elseif ($hasTermsAcceptedDate) {
    $bookingSql .= ", br.terms_accepted_date AS terms_accepted_at";
} else {
    $bookingSql .= ", NULL AS terms_accepted_at";
}
$bookingSql .= $hasTermsVersion ? ", br.terms_version" : ", '' AS terms_version";
$bookingSql .= " FROM booking_requests br WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
if ($hasBookingSoftDelete) {
    $bookingSql .= " AND br.is_deleted = 0";
}
$bookingSql .= " LIMIT 1";

$stmtBooking = mysqli_prepare($conexion, $bookingSql);
if (!$stmtBooking) {
    client_json_error('prepare_failed', 500);
}
$bookingTypes = 'i' . $ownerScope['types'];
$bookingParams = array_merge([$requestId], $ownerScope['params']);
if (!client_bind_params($stmtBooking, $bookingTypes, $bookingParams) || !mysqli_stmt_execute($stmtBooking)) {
    mysqli_stmt_close($stmtBooking);
    client_json_error('execute_failed', 500);
}
$bookingRes = mysqli_stmt_get_result($stmtBooking);
$booking = $bookingRes ? mysqli_fetch_assoc($bookingRes) : null;
mysqli_stmt_close($stmtBooking);

if (!$booking) {
    client_json_error('request_not_found', 404);
}

$itemsPayload = [
    'medical' => [],
    'complementary' => [],
    'totals' => [
        'subtotal' => 0.0,
        'currency_mix' => false,
        'currency' => '',
        'by_currency' => [],
        'display' => 'On request',
    ],
];

if (client_table_exists($conexion, 'booking_request_items')) {
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasItemsStatus = client_table_has_column($conexion, 'booking_request_items', 'item_status');
    $hasItemsPrice = client_table_has_column($conexion, 'booking_request_items', 'proposed_price');
    $hasItemsCurrency = client_table_has_column($conexion, 'booking_request_items', 'currency');

    $hasOffersTable = client_table_exists($conexion, 'provider_service_offers');
    $hasProvidersTable = client_table_exists($conexion, 'providers');
    $hasCatalogTable = client_table_exists($conexion, 'service_catalog');
    $hasCategoriesTable = client_table_exists($conexion, 'service_categories');
    $hasComplementaryTable = client_table_exists($conexion, 'medtravel_services_catalog');
    $hasServiceProvidersTable = client_table_exists($conexion, 'service_providers');

    $medicalNameExpr = "CONCAT('Offer #', i.offer_id)";
    if ($hasOffersTable && $hasCatalogTable) {
        $medicalNameExpr = "COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), CONCAT('Offer #', i.offer_id))";
    } elseif ($hasOffersTable) {
        $medicalNameExpr = "COALESCE(NULLIF(o.title, ''), CONCAT('Offer #', i.offer_id))";
    }

    $complementaryNameExpr = "CONCAT('Service #', i.medtravel_service_id)";
    if ($hasComplementaryTable) {
        $complementaryNameExpr = "COALESCE(NULLIF(ms.service_name, ''), CONCAT('Service #', i.medtravel_service_id))";
    }

    $medicalProviderExpr = "CONCAT('Provider #', i.provider_id)";
    if ($hasProvidersTable) {
        $medicalProviderExpr = "COALESCE(NULLIF(p.name, ''), CONCAT('Provider #', i.provider_id))";
    }

    $complementaryProviderExpr = "CONCAT('Service Provider #', i.service_provider_id)";
    if ($hasServiceProvidersTable) {
        $providerNameField = client_table_has_column($conexion, 'service_providers', 'provider_name') ? 'sp.provider_name' : 'sp.name';
        $complementaryProviderExpr = "COALESCE(NULLIF({$providerNameField}, ''), CONCAT('Service Provider #', i.service_provider_id))";
    }

    $medicalCategoryExpr = "'Medical'";
    if ($hasCategoriesTable) {
        $medicalCategoryExpr = "COALESCE(NULLIF(cat.name, ''), 'Medical')";
    }

    $complementaryCategoryExpr = "'Complementary'";
    if ($hasComplementaryTable && client_table_has_column($conexion, 'medtravel_services_catalog', 'service_type')) {
        $complementaryCategoryExpr = "COALESCE(NULLIF(ms.service_type, ''), 'Complementary')";
    }

    $itemStatusExpr = $hasItemsStatus
        ? "CASE WHEN i.item_status IS NULL OR i.item_status = '' OR i.item_status IN ('pending_admin','pending_review') THEN 'pending_provider' ELSE i.item_status END"
        : "'pending_provider'";

    $itemPriceExpr = $hasItemsPrice ? 'i.proposed_price' : 'NULL';
    $itemCurrencyExpr = $hasItemsCurrency ? 'i.currency' : 'NULL';

    $itemsSql = "SELECT
                    i.id,
                    i.item_type,
                    {$itemStatusExpr} AS item_status,
                    {$itemPriceExpr} AS item_price,
                    {$itemCurrencyExpr} AS item_currency,
                    {$medicalNameExpr} AS medical_name,
                    {$complementaryNameExpr} AS complementary_name,
                    {$medicalProviderExpr} AS medical_provider,
                    {$complementaryProviderExpr} AS complementary_provider,
                    {$medicalCategoryExpr} AS medical_category,
                    {$complementaryCategoryExpr} AS complementary_category";
    if ($hasOffersTable && client_table_has_column($conexion, 'provider_service_offers', 'price_from')) {
        $itemsSql .= ", o.price_from AS medical_price";
    } else {
        $itemsSql .= ", NULL AS medical_price";
    }
    if ($hasOffersTable && client_table_has_column($conexion, 'provider_service_offers', 'currency')) {
        $itemsSql .= ", o.currency AS medical_currency";
    } else {
        $itemsSql .= ", NULL AS medical_currency";
    }
    if ($hasComplementaryTable && client_table_has_column($conexion, 'medtravel_services_catalog', 'sale_price')) {
        $itemsSql .= ", ms.sale_price AS complementary_price";
    } else {
        $itemsSql .= ", NULL AS complementary_price";
    }
    if ($hasComplementaryTable && client_table_has_column($conexion, 'medtravel_services_catalog', 'currency')) {
        $itemsSql .= ", ms.currency AS complementary_currency";
    } else {
        $itemsSql .= ", NULL AS complementary_currency";
    }
    $itemsSql .= " FROM booking_request_items i";
    if ($hasOffersTable) {
        $itemsSql .= " LEFT JOIN provider_service_offers o ON o.id = i.offer_id";
    }
    if ($hasProvidersTable) {
        $itemsSql .= " LEFT JOIN providers p ON p.id = i.provider_id";
    }
    if ($hasCatalogTable && $hasOffersTable) {
        $itemsSql .= " LEFT JOIN service_catalog sc ON sc.id = o.service_id";
    }
    if ($hasCategoriesTable && $hasCatalogTable && $hasOffersTable) {
        $itemsSql .= " LEFT JOIN service_categories cat ON cat.id = sc.category_id";
    }
    if ($hasComplementaryTable) {
        $itemsSql .= " LEFT JOIN medtravel_services_catalog ms ON ms.id = i.medtravel_service_id";
    }
    if ($hasServiceProvidersTable) {
        $itemsSql .= " LEFT JOIN service_providers sp ON sp.id = i.service_provider_id";
    }
    $itemsSql .= " WHERE i.booking_request_id = ?";
    if ($hasItemsSoftDelete) {
        $itemsSql .= " AND i.is_deleted = 0";
    }
    $itemsSql .= " ORDER BY i.id ASC";

    $stmtItems = mysqli_prepare($conexion, $itemsSql);
    if ($stmtItems) {
        mysqli_stmt_bind_param($stmtItems, 'i', $requestId);
        if (mysqli_stmt_execute($stmtItems)) {
            $itemsRes = mysqli_stmt_get_result($stmtItems);
            $totalsByCurrency = [];
            while ($itemsRes && ($item = mysqli_fetch_assoc($itemsRes))) {
                $itemType = (string)($item['item_type'] ?? '');
                $price = 0.0;
                $currency = '';
                if (is_numeric($item['item_price']) && (float)$item['item_price'] > 0) {
                    $price = (float)$item['item_price'];
                    $currency = trim((string)($item['item_currency'] ?? ''));
                } elseif ($itemType === 'medical_offer') {
                    $price = is_numeric($item['medical_price']) ? (float)$item['medical_price'] : 0.0;
                    $currency = trim((string)($item['medical_currency'] ?? ''));
                } elseif ($itemType === 'complementary_service') {
                    $price = is_numeric($item['complementary_price']) ? (float)$item['complementary_price'] : 0.0;
                    $currency = trim((string)($item['complementary_currency'] ?? ''));
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

                // Commission gate: enabled + unpaid → redact sensitive contact fields only.
                // Provider name / title / specialty remain ALWAYS visible (Stage 1 negotiation
                // must stay open). Free-form chat messages are never filtered here.
                $commissionGate = commission_gate_status($conexion, $requestId, (int)$item['id']);
                $providerLocked = !empty($commissionGate['enabled']) && empty($commissionGate['paid']);

                $payloadItem = [
                    'id' => (int)$item['id'],
                    'item_status' => client_status_label($item['item_status'] ?? ''),
                    'price' => $price,
                    'currency' => $currency,
                    'price_display' => $price > 0 ? ($currency . ' $' . number_format($price, 2)) : 'On request',
                    'provider_locked' => $providerLocked ? 1 : 0,
                ];

                if ($itemType === 'medical_offer') {
                    $payloadItem['item_type'] = 'medical_offer';
                    $payloadItem['item_type_label'] = 'Medical';
                    $payloadItem['name'] = (string)($item['medical_name'] ?? 'Medical service');
                    // Name is always visible; strip any embedded contact details when unpaid.
                    $rawProvider = (string)($item['medical_provider'] ?? 'Provider');
                    $payloadItem['provider'] = $providerLocked
                        ? commission_gate_redact_sensitive($rawProvider)
                        : $rawProvider;
                    $payloadItem['category'] = (string)($item['medical_category'] ?? 'Medical');
                    $itemsPayload['medical'][] = $payloadItem;
                } else {
                    $payloadItem['item_type'] = 'complementary_service';
                    $payloadItem['item_type_label'] = 'Complementary';
                    $payloadItem['name'] = (string)($item['complementary_name'] ?? 'Complementary service');
                    // Same policy: name stays, contact details redacted until paid.
                    $rawProvider = (string)($item['complementary_provider'] ?? 'Service provider');
                    $payloadItem['provider'] = $providerLocked
                        ? commission_gate_redact_sensitive($rawProvider)
                        : $rawProvider;
                    $payloadItem['category'] = (string)($item['complementary_category'] ?? 'Complementary');
                    $itemsPayload['complementary'][] = $payloadItem;
                }
            }

            $subtotal = 0.0;
            foreach ($totalsByCurrency as $amount) {
                $subtotal += (float)$amount;
            }
            $itemsPayload['totals']['subtotal'] = $subtotal;
            $itemsPayload['totals']['by_currency'] = $totalsByCurrency;
            $itemsPayload['totals']['currency_mix'] = (count($totalsByCurrency) > 1);
            if (count($totalsByCurrency) === 1) {
                $k = array_keys($totalsByCurrency);
                $itemsPayload['totals']['currency'] = (string)$k[0];
                $itemsPayload['totals']['display'] = $k[0] . ' $' . number_format((float)$totalsByCurrency[$k[0]], 2);
            } elseif (count($totalsByCurrency) > 1) {
                $parts = [];
                foreach ($totalsByCurrency as $ccy => $amount) {
                    $parts[] = $ccy . ' $' . number_format((float)$amount, 2);
                }
                $itemsPayload['totals']['display'] = implode(' / ', $parts);
            }
        }
        mysqli_stmt_close($stmtItems);
    }
}

echo json_encode([
    'ok' => true,
    'booking' => [
        'id' => (int)$booking['id'],
        'created_at' => (string)($booking['created_at'] ?? ''),
        'destination' => (string)($booking['destination'] ?? ''),
        'timeline' => (string)($booking['timeline'] ?? ''),
        'status' => client_status_label($booking['status'] ?? ''),
        'special_request' => (string)($booking['special_request'] ?? ''),
        'additional_notes' => (string)($booking['additional_notes'] ?? ''),
        'terms_accepted' => isset($booking['terms_accepted']) ? (int)$booking['terms_accepted'] : 0,
        'terms_version' => (string)($booking['terms_version'] ?? ''),
        'terms_accepted_at' => (string)($booking['terms_accepted_at'] ?? ''),
    ],
    'items' => $itemsPayload,
]);
