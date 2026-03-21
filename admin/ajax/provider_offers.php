<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . '/../include/conexion.php');
require_once __DIR__ . '/../include/roles.php';
$devlog = __DIR__ . '/../logs/dev.log';
$req_dump = isset($_REQUEST) ? print_r($_REQUEST, true) : '[]';
$cookie_dump = isset($_COOKIE) ? print_r($_COOKIE, true) : '[]';
if (defined('APP_ENV') && APP_ENV === 'dev') {
    @file_put_contents($devlog, date('Y-m-d H:i:s') . " - provider_offers request: method=" . $_SERVER['REQUEST_METHOD'] . " req=" . substr($req_dump,0,800) . "\n", FILE_APPEND | LOCK_EX);
    // ensure session debug dump (may be empty until require_login_ajax runs)
    @file_put_contents($devlog, date('Y-m-d H:i:s') . " - COOKIES: " . substr($cookie_dump,0,800) . "\n", FILE_APPEND | LOCK_EX);
}
require_login_ajax();
// global error/exception handlers to capture fatal errors in dev log
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($devlog) {
    $msg = date('Y-m-d H:i:s') . " - ERROR [$errno] $errstr in $errfile:$errline\n";
    @file_put_contents($devlog, $msg, FILE_APPEND | LOCK_EX);
});
set_exception_handler(function($e) use ($devlog) {
    $msg = date('Y-m-d H:i:s') . " - EXCEPTION " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    @file_put_contents($devlog, $msg, FILE_APPEND | LOCK_EX);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'server_exception']);
    exit();
});
$session_provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$tipo = isset($_REQUEST['tipo']) ? $_REQUEST['tipo'] : '';
$is_admin = function_exists('is_role_admin_session') ? is_role_admin_session() : false;

function json_error($msg, $code = 400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit(); }

function table_has_column($conexion, $table, $column){
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cache[$key] = $res && mysqli_fetch_row($res) ? true : false;
    mysqli_stmt_close($stmt);
    return $cache[$key];
}

function bind_stmt_params($stmt, $types, array &$params){
    if ($types === '' || empty($params)) {
        return;
    }
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $idx => $value) {
        $refs[] = &$params[$idx];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function provider_offers_provider_is_medical($conexion, $providerId){
    $providerId = (int)$providerId;
    if ($providerId <= 0) {
        return false;
    }

    $sql = 'SELECT id FROM providers WHERE id = ?';
    if (table_has_column($conexion, 'providers', 'kind')) {
        $sql .= " AND kind = 'medical'";
    }
    if (table_has_column($conexion, 'providers', 'is_deleted')) {
        $sql .= ' AND is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (bool)$row;
}

function provider_offers_fetch_provider_name($conexion, $providerId){
    $providerId = (int)$providerId;
    if ($providerId <= 0) {
        return '';
    }

    $stmt = mysqli_prepare($conexion, 'SELECT name FROM providers WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row && isset($row['name']) ? (string)$row['name'] : '';
}

function provider_offers_resolve_context_provider_id($conexion, $isAdmin, $sessionProviderId, $requestedProviderId, $requireExplicitForAdmin = true){
    $requestedProviderId = (int)$requestedProviderId;
    $sessionProviderId = (int)$sessionProviderId;

    if ($isAdmin) {
        if ($requestedProviderId <= 0) {
            return [0, $requireExplicitForAdmin ? 'REQUIRE_PROVIDER_CONTEXT' : null];
        }
        if (!provider_offers_provider_is_medical($conexion, $requestedProviderId)) {
            return [0, 'INVALID_PROVIDER_CONTEXT'];
        }
        return [$requestedProviderId, null];
    }

    if ($sessionProviderId <= 0) {
        return [0, 'FORBIDDEN'];
    }
    if (!provider_offers_provider_is_medical($conexion, $sessionProviderId)) {
        return [0, 'INVALID_PROVIDER_CONTEXT'];
    }
    return [$sessionProviderId, null];
}

function provider_offers_context_error_status($error){
    return $error === 'FORBIDDEN' ? 403 : 400;
}

function provider_offers_find_provider_service($conexion, $providerId, $providerCatalogServiceId = 0, $serviceId = 0, $activeOnly = true){
    if ($providerId <= 0) {
        return [null, 'INVALID_PROVIDER'];
    }
    if (
        !table_has_column($conexion, 'provider_catalog_services', 'id') ||
        !table_has_column($conexion, 'provider_catalog_services', 'provider_id') ||
        !table_has_column($conexion, 'provider_catalog_services', 'service_id') ||
        !table_has_column($conexion, 'service_catalog', 'id')
    ) {
        return [null, 'PROVIDER_SERVICE_RELATION_UNAVAILABLE'];
    }
    if ($providerCatalogServiceId <= 0 && $serviceId <= 0) {
        return [null, 'INVALID_SERVICE_REFERENCE'];
    }

    $hasPcsCategory = table_has_column($conexion, 'provider_catalog_services', 'category_id');
    $hasPcsActive = table_has_column($conexion, 'provider_catalog_services', 'is_active');
    $hasPcsSortOrder = table_has_column($conexion, 'provider_catalog_services', 'sort_order');
    $hasScCategory = table_has_column($conexion, 'service_catalog', 'category_id');
    $hasScActive = table_has_column($conexion, 'service_catalog', 'is_active');
    $hasScDeleted = table_has_column($conexion, 'service_catalog', 'is_deleted');
    $hasScSortOrder = table_has_column($conexion, 'service_catalog', 'sort_order');

    $select = [
        'pcs.id AS provider_catalog_service_id',
        'pcs.provider_id',
        'pcs.service_id',
        "COALESCE(sc.name, CONCAT('Servicio ', pcs.service_id)) AS service_name",
        $hasPcsCategory ? 'pcs.category_id' : ($hasScCategory ? 'sc.category_id' : 'NULL AS category_id'),
        $hasPcsActive ? 'pcs.is_active' : ($hasScActive ? 'sc.is_active' : '1 AS is_active'),
        $hasPcsSortOrder ? 'pcs.sort_order' : ($hasScSortOrder ? 'sc.sort_order' : '1 AS sort_order')
    ];

    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM provider_catalog_services pcs
            LEFT JOIN service_catalog sc ON sc.id = pcs.service_id
            WHERE pcs.provider_id = ?';
    $types = 'i';
    $params = [$providerId];

    if ($providerCatalogServiceId > 0) {
        $sql .= ' AND pcs.id = ?';
        $types .= 'i';
        $params[] = $providerCatalogServiceId;
    } else {
        $sql .= ' AND pcs.service_id = ?';
        $types .= 'i';
        $params[] = $serviceId;
    }

    if ($activeOnly) {
        if ($hasPcsActive) {
            $sql .= ' AND pcs.is_active = 1';
        }
        if ($hasScActive) {
            $sql .= ' AND sc.is_active = 1';
        }
        if ($hasScDeleted) {
            $sql .= ' AND sc.is_deleted = 0';
        }
    }

    $sql .= ' ORDER BY ' . ($hasPcsSortOrder ? 'pcs.sort_order' : ($hasScSortOrder ? 'sc.sort_order' : 'pcs.id')) . ' ASC, sc.name ASC, pcs.id ASC LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [null, 'DB_PREPARE_PROVIDER_SERVICE'];
    }
    bind_stmt_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        return [null, $providerCatalogServiceId > 0 ? 'INVALID_PROVIDER_CATALOG_SERVICE' : 'PROVIDER_SERVICE_NOT_ENABLED'];
    }

    $row['provider_catalog_service_id'] = (int)$row['provider_catalog_service_id'];
    $row['provider_id'] = (int)$row['provider_id'];
    $row['service_id'] = (int)$row['service_id'];
    $row['category_id'] = isset($row['category_id']) && $row['category_id'] !== null ? (int)$row['category_id'] : null;
    $row['is_active'] = isset($row['is_active']) ? (int)$row['is_active'] : 1;
    $row['sort_order'] = isset($row['sort_order']) ? (int)$row['sort_order'] : 1;

    return [$row, null];
}

function provider_offers_find_unique_provider_service_by_service_id($conexion, $providerId, $serviceId, $activeOnly = true){
    $providerId = (int)$providerId;
    $serviceId = (int)$serviceId;
    if ($providerId <= 0) {
        return [null, 'INVALID_PROVIDER'];
    }
    if ($serviceId <= 0) {
        return [null, 'INVALID_SERVICE_REFERENCE'];
    }
    if (
        !table_has_column($conexion, 'provider_catalog_services', 'id') ||
        !table_has_column($conexion, 'provider_catalog_services', 'provider_id') ||
        !table_has_column($conexion, 'provider_catalog_services', 'service_id') ||
        !table_has_column($conexion, 'service_catalog', 'id')
    ) {
        return [null, 'PROVIDER_SERVICE_RELATION_UNAVAILABLE'];
    }

    $hasPcsActive = table_has_column($conexion, 'provider_catalog_services', 'is_active');
    $hasScActive = table_has_column($conexion, 'service_catalog', 'is_active');
    $hasScDeleted = table_has_column($conexion, 'service_catalog', 'is_deleted');

    $sql = 'SELECT pcs.id AS provider_catalog_service_id
            FROM provider_catalog_services pcs
            LEFT JOIN service_catalog sc ON sc.id = pcs.service_id
            WHERE pcs.provider_id = ? AND pcs.service_id = ?';
    if ($activeOnly) {
        if ($hasPcsActive) {
            $sql .= ' AND pcs.is_active = 1';
        }
        if ($hasScActive) {
            $sql .= ' AND sc.is_active = 1';
        }
        if ($hasScDeleted) {
            $sql .= ' AND sc.is_deleted = 0';
        }
    }
    $sql .= ' ORDER BY pcs.id ASC LIMIT 2';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [null, 'DB_PREPARE_PROVIDER_SERVICE'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $providerId, $serviceId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $candidateIds = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $candidateIds[] = (int)$row['provider_catalog_service_id'];
    }
    mysqli_stmt_close($stmt);

    if (count($candidateIds) === 0) {
        return [null, 'PROVIDER_SERVICE_NOT_ENABLED'];
    }
    if (count($candidateIds) > 1) {
        return [null, 'AMBIGUOUS_PROVIDER_SERVICE_MATCH'];
    }

    return provider_offers_find_provider_service($conexion, $providerId, $candidateIds[0], 0, $activeOnly);
}

function provider_offers_resolve_requested_service($conexion, $providerId, $providerCatalogServiceId, $serviceId, $activeOnly = true){
    $providerCatalogServiceId = (int)$providerCatalogServiceId;
    $serviceId = (int)$serviceId;

    if ($providerCatalogServiceId > 0) {
        list($resolved, $error) = provider_offers_find_provider_service($conexion, $providerId, $providerCatalogServiceId, 0, $activeOnly);
        if (!$resolved) {
            return [null, $error ?: 'INVALID_PROVIDER_CATALOG_SERVICE'];
        }
        if ($serviceId > 0 && (int)$resolved['service_id'] !== $serviceId) {
            return [null, 'SERVICE_MISMATCH_WITH_PROVIDER_SERVICE'];
        }
        return [$resolved, null];
    }

    if ($serviceId > 0) {
        list($resolved, $error) = provider_offers_find_unique_provider_service_by_service_id($conexion, $providerId, $serviceId, $activeOnly);
        if (!$resolved) {
            return [null, $error ?: 'PROVIDER_SERVICE_NOT_ENABLED'];
        }
        return [$resolved, null];
    }

    return [null, 'INVALID_SERVICE_REFERENCE'];
}

function provider_offers_hydrate_offer_service($conexion, $providerId, array $offer){
    $providerCatalogServiceId = isset($offer['provider_catalog_service_id']) ? (int)$offer['provider_catalog_service_id'] : 0;
    $serviceId = isset($offer['service_id']) ? (int)$offer['service_id'] : 0;

    if ($providerCatalogServiceId > 0) {
        list($resolved) = provider_offers_find_provider_service($conexion, $providerId, $providerCatalogServiceId, 0, false);
        if ($resolved) {
            $offer['provider_catalog_service_id'] = $resolved['provider_catalog_service_id'];
            $offer['service_id'] = $resolved['service_id'];
            $offer['service_name'] = $resolved['service_name'];
            if (!isset($offer['category_id']) || $offer['category_id'] === null || $offer['category_id'] === '') {
                $offer['category_id'] = $resolved['category_id'];
            }
            return $offer;
        }
    }

    if ($serviceId > 0) {
        list($resolved) = provider_offers_find_provider_service($conexion, $providerId, 0, $serviceId, false);
        if ($resolved) {
            $offer['provider_catalog_service_id'] = $resolved['provider_catalog_service_id'];
            $offer['service_id'] = $resolved['service_id'];
            $offer['service_name'] = $resolved['service_name'];
            if (!isset($offer['category_id']) || $offer['category_id'] === null || $offer['category_id'] === '') {
                $offer['category_id'] = $resolved['category_id'];
            }
        }
    }

    if (!isset($offer['provider_catalog_service_id']) || $offer['provider_catalog_service_id'] === null || $offer['provider_catalog_service_id'] === '') {
        $offer['provider_catalog_service_id'] = null;
    } else {
        $offer['provider_catalog_service_id'] = (int)$offer['provider_catalog_service_id'];
    }
    if (isset($offer['service_id'])) {
        $offer['service_id'] = (int)$offer['service_id'];
    }
    return $offer;
}

function generate_random_token($bytes_length = 6){
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes_length));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($bytes_length));
    }
    $result = '';
    for ($i = 0; $i < $bytes_length; $i++) {
        $result .= chr(mt_rand(0, 255));
    }
    return bin2hex($result);
}

function detect_mime_type($filepath){
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filepath);
    }
    if (function_exists('mime_content_type')) {
        return mime_content_type($filepath);
    }
    return '';
}

function resolve_offer_media_file_path($stored_path){
    $relative = ltrim(str_replace('\\', '/', (string)$stored_path), '/');
    if ($relative === '') return '';
    if (strpos($relative, 'img/offers/') !== 0) return '';
    if (strpos($relative, '..') !== false) return '';
    $base_dir = realpath(__DIR__ . '/../../img/offers');
    if ($base_dir === false) return '';
    $full = __DIR__ . '/../../' . $relative;
    if (!file_exists($full)) return '';
    $real_file = realpath($full);
    if ($real_file === false) return '';
    $base_prefix = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($real_file, $base_prefix) !== 0) return '';
    return $real_file;
}

if ($tipo === 'list') {
    $requestedProviderId = isset($_REQUEST['provider_id']) ? (int)$_REQUEST['provider_id'] : 0;
    list($provider_id, $contextError) = provider_offers_resolve_context_provider_id(
        $conexion,
        $is_admin,
        $session_provider_id,
        $requestedProviderId,
        true
    );
    if ($contextError) {
        if ($is_admin && $contextError === 'REQUIRE_PROVIDER_CONTEXT') {
            echo json_encode(['ok' => true, 'data' => [], 'require_provider_context' => true]);
            exit();
        }
        json_error($contextError, provider_offers_context_error_status($contextError));
    }

    $hasOfferProviderCatalogServiceId = table_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id');
    $selectProviderCatalogServiceId = $hasOfferProviderCatalogServiceId ? 'o.provider_catalog_service_id,' : 'NULL AS provider_catalog_service_id,';
    $sql = "SELECT o.id, o.title, o.price_from, o.currency, o.is_active, o.service_id, {$selectProviderCatalogServiceId} sc.name AS service_name, IFNULL(p.name,'') AS provider_name FROM provider_service_offers o LEFT JOIN service_catalog sc ON sc.id = o.service_id LEFT JOIN providers p ON p.id = o.provider_id WHERE o.provider_id = ? ORDER BY o.created_at DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row = provider_offers_hydrate_offer_service($conexion, $provider_id, $row);
        $data[] = $row;
    }
    echo json_encode(['ok'=>true,'data'=>$data, 'provider_name' => provider_offers_fetch_provider_name($conexion, $provider_id)]);
    exit();
}

if ($tipo === 'list_provider_services') {
    $requestedProviderId = isset($_REQUEST['provider_id']) ? (int)$_REQUEST['provider_id'] : 0;
    list($provider_id, $contextError) = provider_offers_resolve_context_provider_id(
        $conexion,
        $is_admin,
        $session_provider_id,
        $requestedProviderId,
        true
    );
    if ($contextError) {
        if ($is_admin && $contextError === 'REQUIRE_PROVIDER_CONTEXT') {
            echo json_encode(['ok' => true, 'data' => [], 'require_provider_context' => true]);
            exit();
        }
        json_error($contextError, provider_offers_context_error_status($contextError));
    }

    if (
        !table_has_column($conexion, 'provider_catalog_services', 'id') ||
        !table_has_column($conexion, 'provider_catalog_services', 'provider_id') ||
        !table_has_column($conexion, 'provider_catalog_services', 'service_id')
    ) {
        json_error('PROVIDER_SERVICE_RELATION_UNAVAILABLE', 500);
    }

    $hasPcsCategory = table_has_column($conexion, 'provider_catalog_services', 'category_id');
    $hasPcsActive = table_has_column($conexion, 'provider_catalog_services', 'is_active');
    $hasPcsSortOrder = table_has_column($conexion, 'provider_catalog_services', 'sort_order');
    $hasScCategory = table_has_column($conexion, 'service_catalog', 'category_id');
    $hasScActive = table_has_column($conexion, 'service_catalog', 'is_active');
    $hasScDeleted = table_has_column($conexion, 'service_catalog', 'is_deleted');
    $hasScSortOrder = table_has_column($conexion, 'service_catalog', 'sort_order');

    $sql = 'SELECT pcs.id AS provider_catalog_service_id,
                   pcs.service_id,
                   COALESCE(sc.name, CONCAT(\'Servicio \' , pcs.service_id)) AS service_name,
                   ' . ($hasPcsCategory ? 'pcs.category_id' : ($hasScCategory ? 'sc.category_id' : 'NULL AS category_id')) . ',
                   ' . ($hasPcsActive ? 'pcs.is_active' : ($hasScActive ? 'sc.is_active' : '1 AS is_active')) . ',
                   ' . ($hasPcsSortOrder ? 'pcs.sort_order' : ($hasScSortOrder ? 'sc.sort_order' : '1 AS sort_order')) . '
            FROM provider_catalog_services pcs
            INNER JOIN service_catalog sc ON sc.id = pcs.service_id
            WHERE pcs.provider_id = ?';

    if ($hasPcsActive) {
        $sql .= ' AND pcs.is_active = 1';
    }
    if ($hasScActive) {
        $sql .= ' AND sc.is_active = 1';
    }
    if ($hasScDeleted) {
        $sql .= ' AND sc.is_deleted = 0';
    }

    $sql .= ' ORDER BY ' . ($hasPcsSortOrder ? 'pcs.sort_order' : ($hasScSortOrder ? 'sc.sort_order' : 'pcs.id')) . ' ASC, sc.name ASC, pcs.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_error('DB_PREPARE_PROVIDER_SERVICES', 500);
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row['provider_catalog_service_id'] = (int)$row['provider_catalog_service_id'];
        $row['service_id'] = (int)$row['service_id'];
        $row['category_id'] = isset($row['category_id']) && $row['category_id'] !== null ? (int)$row['category_id'] : null;
        $row['is_active'] = isset($row['is_active']) ? (int)$row['is_active'] : 1;
        $row['sort_order'] = isset($row['sort_order']) ? (int)$row['sort_order'] : 1;
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['ok' => true, 'data' => $data]);
    exit();
}

if ($tipo === 'get') {
    $requestedProviderId = isset($_REQUEST['provider_id']) ? (int)$_REQUEST['provider_id'] : 0;
    list($provider_id, $contextError) = provider_offers_resolve_context_provider_id(
        $conexion,
        $is_admin,
        $session_provider_id,
        $requestedProviderId,
        true
    );
    if ($contextError) {
        json_error($contextError, provider_offers_context_error_status($contextError));
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_error('INVALID_ID');
    $hasOfferProviderCatalogServiceId = table_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id');
    $selectProviderCatalogServiceId = $hasOfferProviderCatalogServiceId ? 'o.provider_catalog_service_id,' : 'NULL AS provider_catalog_service_id,';
    $sql = "SELECT o.*, {$selectProviderCatalogServiceId} sc.name AS service_name FROM provider_service_offers o LEFT JOIN service_catalog sc ON sc.id = o.service_id WHERE o.id = ? AND o.provider_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $offer = mysqli_fetch_assoc($res);
    if (!$offer) json_error('NOT_FOUND',404);
    $offer = provider_offers_hydrate_offer_service($conexion, $provider_id, $offer);
    // media
    $mstmt = mysqli_prepare($conexion, "SELECT id,path,sort_order,is_active FROM offer_media WHERE offer_id = ? ORDER BY sort_order ASC, id ASC");
    mysqli_stmt_bind_param($mstmt, 'i', $id);
    mysqli_stmt_execute($mstmt);
    $mres = mysqli_stmt_get_result($mstmt);
    $media = [];
    while ($m = mysqli_fetch_assoc($mres)) $media[] = $m;
    $offer['media'] = $media;
    echo json_encode(['ok'=>true,'data'=>$offer]);
    exit();
}

if ($tipo === 'create' || $tipo === 'update') {
    $requestedProviderId = isset($_REQUEST['provider_id']) ? (int)$_REQUEST['provider_id'] : 0;
    list($provider_id, $contextError) = provider_offers_resolve_context_provider_id(
        $conexion,
        $is_admin,
        $session_provider_id,
        $requestedProviderId,
        true
    );
    if ($contextError) {
        json_error($contextError, provider_offers_context_error_status($contextError));
    }

    $allowed = ['provider_catalog_service_id','service_id','title','description','price_from','currency','is_active'];
    $data = [];
    foreach ($allowed as $k) {
        if (isset($_REQUEST[$k])) $data[$k] = $_REQUEST[$k];
    }
    $title = isset($data['title']) ? substr(trim($data['title']),0,200) : null;
    $description = isset($data['description']) ? trim($data['description']) : null;
    $price_from = isset($data['price_from']) ? (float)$data['price_from'] : null;
    $currency = isset($data['currency']) ? substr(trim($data['currency']),0,5) : 'USD';
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;
    $requestedProviderCatalogServiceId = isset($data['provider_catalog_service_id']) ? (int)$data['provider_catalog_service_id'] : 0;
    $requestedServiceId = isset($data['service_id']) ? (int)$data['service_id'] : 0;

    if ($tipo === 'create') {
        if (!table_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id')) {
            json_error('MIGRATION_REQUIRED_PROVIDER_CATALOG_SERVICE_ID', 500);
        }
        list($resolvedService, $resolveError) = provider_offers_resolve_requested_service(
            $conexion,
            $provider_id,
            $requestedProviderCatalogServiceId,
            $requestedServiceId,
            true
        );
        if (!$resolvedService) {
            json_error($resolveError ?: 'INVALID_SERVICE');
        }
        $service_id = (int)$resolvedService['service_id'];
        $provider_catalog_service_id = (int)$resolvedService['provider_catalog_service_id'];
        $sql = "INSERT INTO provider_service_offers (provider_id,provider_catalog_service_id,service_id,title,description,price_from,currency,is_active) VALUES (?,?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiissdsi', $provider_id, $provider_catalog_service_id, $service_id, $title, $description, $price_from, $currency, $is_active);
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) json_error('DB_ERR:'.mysqli_error($conexion));
        $new_id = mysqli_insert_id($conexion);
        echo json_encode(['ok'=>true,'data'=>['id'=>$new_id, 'provider_catalog_service_id' => $provider_catalog_service_id, 'service_id' => $service_id]]);
        exit();
    } else {
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if (!$id) json_error('INVALID_ID');
        $hasOfferProviderCatalogServiceId = table_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id');
        $selectProviderCatalogServiceId = $hasOfferProviderCatalogServiceId ? 'provider_catalog_service_id,' : 'NULL AS provider_catalog_service_id,';
        $chk = mysqli_prepare($conexion, "SELECT id, provider_id, service_id, {$selectProviderCatalogServiceId} title FROM provider_service_offers WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        $chkres = mysqli_stmt_get_result($chk);
        $offer = mysqli_fetch_assoc($chkres);
        if (!$offer) json_error('NOT_FOUND',404);
        if ((int)$offer['provider_id'] !== $provider_id) json_error('FORBIDDEN',403);

        $offer = provider_offers_hydrate_offer_service($conexion, $provider_id, $offer);
        $currentProviderCatalogServiceId = isset($offer['provider_catalog_service_id']) && $offer['provider_catalog_service_id'] !== null ? (int)$offer['provider_catalog_service_id'] : 0;
        $currentServiceId = isset($offer['service_id']) ? (int)$offer['service_id'] : 0;
        if ($requestedProviderCatalogServiceId > 0 && $currentProviderCatalogServiceId > 0 && $requestedProviderCatalogServiceId !== $currentProviderCatalogServiceId) {
            json_error('SERVICE_REBIND_NOT_ALLOWED');
        }
        if ($requestedServiceId > 0 && $currentServiceId > 0 && $requestedServiceId !== $currentServiceId) {
            json_error('SERVICE_REBIND_NOT_ALLOWED');
        }

        $setProviderCatalogServiceSql = '';
        $providerCatalogServiceBindType = '';
        $providerCatalogServiceBindValue = 0;
        if ($hasOfferProviderCatalogServiceId && $currentProviderCatalogServiceId <= 0 && $currentServiceId > 0) {
            list($resolvedLegacyService) = provider_offers_find_unique_provider_service_by_service_id(
                $conexion,
                $provider_id,
                $currentServiceId,
                false
            );
            if ($resolvedLegacyService && !empty($resolvedLegacyService['provider_catalog_service_id'])) {
                $providerCatalogServiceBindValue = (int)$resolvedLegacyService['provider_catalog_service_id'];
                $setProviderCatalogServiceSql = 'provider_catalog_service_id=?,';
                $providerCatalogServiceBindType = 'i';
            }
        }

        $sql = "UPDATE provider_service_offers SET {$setProviderCatalogServiceSql} title=?,description=?,price_from=?,currency=?,is_active=? WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($providerCatalogServiceBindType !== '') {
            mysqli_stmt_bind_param($stmt, $providerCatalogServiceBindType . 'ssdsii', $providerCatalogServiceBindValue, $title, $description, $price_from, $currency, $is_active, $id);
        } else {
            mysqli_stmt_bind_param($stmt, 'ssdsii', $title, $description, $price_from, $currency, $is_active, $id);
        }
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) json_error('DB_ERR:'.mysqli_error($conexion));
        echo json_encode(['ok'=>true]);
        exit();
    }
}

if ($tipo === 'toggle') {
    $requestedProviderId = isset($_REQUEST['provider_id']) ? (int)$_REQUEST['provider_id'] : 0;
    list($provider_id, $contextError) = provider_offers_resolve_context_provider_id(
        $conexion,
        $is_admin,
        $session_provider_id,
        $requestedProviderId,
        true
    );
    if ($contextError) {
        json_error($contextError, provider_offers_context_error_status($contextError));
    }

    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    if (!$id) json_error('INVALID_ID');
    $chk = mysqli_prepare($conexion, "SELECT is_active FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, 'ii', $id, $provider_id);
    mysqli_stmt_execute($chk);
    $res = mysqli_stmt_get_result($chk);
    $row = mysqli_fetch_assoc($res);
    if (!$row) json_error('FORBIDDEN',403);
    $new = $row['is_active'] ? 0 : 1;
    $up = mysqli_prepare($conexion, "UPDATE provider_service_offers SET is_active = ? WHERE id = ?");
    mysqli_stmt_bind_param($up, 'ii', $new, $id);
    mysqli_stmt_execute($up);
    echo json_encode(['ok'=>true,'data'=>['is_active'=>$new]]);
    exit();
}

if ($tipo === 'upload_media') {
    $requestedProviderId = isset($_REQUEST['provider_id']) ? (int)$_REQUEST['provider_id'] : 0;
    list($provider_id, $contextError) = provider_offers_resolve_context_provider_id(
        $conexion,
        $is_admin,
        $session_provider_id,
        $requestedProviderId,
        true
    );
    if ($contextError) {
        json_error($contextError, provider_offers_context_error_status($contextError));
    }

    $offer_id = isset($_REQUEST['offer_id']) ? (int)$_REQUEST['offer_id'] : 0;
    if (!$offer_id) json_error('INVALID_OFFER');
    // check ownership
    $chk = mysqli_prepare($conexion, "SELECT id FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, 'ii', $offer_id, $provider_id);
    mysqli_stmt_execute($chk);
    $cres = mysqli_stmt_get_result($chk);
    if (!mysqli_fetch_assoc($cres)) json_error('FORBIDDEN',403);

    if (empty($_FILES) || !isset($_FILES['file'])) json_error('NO_FILE');
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) json_error('UPLOAD_ERR');
    if ($f['size'] > 3 * 1024 * 1024) json_error('TOO_LARGE');
    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) json_error('BAD_EXT');
    $mime = detect_mime_type($f['tmp_name']);
    if (!$mime) {
        $ext_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp'
        ];
        $mime = isset($ext_map[$ext]) ? $ext_map[$ext] : '';
    }
    $m_allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $m_allowed)) json_error('BAD_MIME');

    $dir = __DIR__ . '/../../img/offers/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = time() . '_' . generate_random_token(6) . '.' . $ext;
    $dest = $dir . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) json_error('MOVE_ERR');
    $rel = 'img/offers/' . $name;
    $ins = mysqli_prepare($conexion, "INSERT INTO offer_media (offer_id,path,sort_order,is_active) VALUES (?,?,1,1)");
    mysqli_stmt_bind_param($ins, 'is', $offer_id, $rel);
    mysqli_stmt_execute($ins);
    $mid = mysqli_insert_id($conexion);
    echo json_encode(['ok'=>true,'data'=>['path'=>$rel,'id'=>$mid]]);
    exit();
}

if ($tipo === 'delete_media') {
    $requestedProviderId = isset($_REQUEST['provider_id']) ? (int)$_REQUEST['provider_id'] : 0;
    list($provider_id, $contextError) = provider_offers_resolve_context_provider_id(
        $conexion,
        $is_admin,
        $session_provider_id,
        $requestedProviderId,
        true
    );
    if ($contextError) {
        json_error($contextError, provider_offers_context_error_status($contextError));
    }

    $media_id = isset($_REQUEST['image_id']) ? (int)$_REQUEST['image_id'] : 0;
    if (!$media_id && isset($_REQUEST['offer_image_id'])) $media_id = (int)$_REQUEST['offer_image_id'];
    if (!$media_id) json_error('INVALID_IMAGE');
    $offer_id = isset($_REQUEST['offer_id']) ? (int)$_REQUEST['offer_id'] : 0;

    $sel = mysqli_prepare(
        $conexion,
        "SELECT m.id,m.offer_id,m.path,o.provider_id
         FROM offer_media m
         INNER JOIN provider_service_offers o ON o.id = m.offer_id
         WHERE m.id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($sel, 'i', $media_id);
    mysqli_stmt_execute($sel);
    $sres = mysqli_stmt_get_result($sel);
    $media = mysqli_fetch_assoc($sres);
    if (!$media) json_error('NOT_FOUND',404);

    if ($offer_id && (int)$media['offer_id'] !== $offer_id) json_error('INVALID_OFFER');
    if ((int)$media['provider_id'] !== $provider_id) {
        json_error($is_admin ? 'INVALID_PROVIDER_CONTEXT' : 'FORBIDDEN', $is_admin ? 400 : 403);
    }

    $del = mysqli_prepare($conexion, "DELETE FROM offer_media WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($del, 'i', $media_id);
    $ok = mysqli_stmt_execute($del);
    if (!$ok) json_error('DB_ERR:'.mysqli_error($conexion));

    $path = (string)$media['path'];
    if ($path !== '') {
        $cnt_stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS c FROM offer_media WHERE path = ?");
        mysqli_stmt_bind_param($cnt_stmt, 's', $path);
        mysqli_stmt_execute($cnt_stmt);
        $cnt_res = mysqli_stmt_get_result($cnt_stmt);
        $cnt_row = mysqli_fetch_assoc($cnt_res);
        $in_use = $cnt_row ? (int)$cnt_row['c'] : 0;
        if ($in_use === 0) {
            $file_path = resolve_offer_media_file_path($path);
            if ($file_path !== '' && is_file($file_path)) {
                @unlink($file_path);
            }
        }
    }

    echo json_encode(['ok'=>true]);
    exit();
}

json_error('INVALID_ACTION');
