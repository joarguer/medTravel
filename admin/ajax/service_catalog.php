<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
session_start();
include('../include/conexion.php');
require_once('../include/roles.php');

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

$tipo = isset($_REQUEST['tipo']) ? trim((string)$_REQUEST['tipo']) : '';
$isAdminPrincipal = is_role_admin_session();
$scopedProviderId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;

if (!$isAdminPrincipal && !user_can(PERM_SERVICES_MEDICAL_MANAGE) && !user_can(PERM_PROVIDERS_MEDICAL_MANAGE)) {
    json_err('forbidden', 403);
}
if (!$isAdminPrincipal && $scopedProviderId <= 0) {
    json_err('forbidden', 403);
}

function json_ok($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function json_err($error, $status = 400) {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

function bind_stmt_params($stmt, $types, &$values) {
    $bind = [$types];
    foreach ($values as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function service_catalog_table_has_column($conexion, $table, $column) {
    $sql = "SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row && intval($row['c']) > 0;
}

function service_catalog_description_column($conexion) {
    if (service_catalog_table_has_column($conexion, 'service_catalog', 'short_description')) {
        return 'short_description';
    }
    if (service_catalog_table_has_column($conexion, 'service_catalog', 'description')) {
        return 'description';
    }
    return '';
}

function slugify($text){
    $text = preg_replace('~[^\pL0-9]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) return 'n-a';
    return $text;
}

function validate_active_category($conexion, $categoryId) {
    $stmt = mysqli_prepare($conexion, "SELECT id FROM service_categories WHERE id = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) json_err('db_prepare');
    mysqli_stmt_bind_param($stmt, 'i', $categoryId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);
    if (!$ok) json_err('invalid_category', 422);
}

function validate_active_medical_provider($conexion, $providerId) {
    $stmt = mysqli_prepare($conexion, "SELECT id FROM providers WHERE id = ? AND kind = 'medical' AND is_active = 1 LIMIT 1");
    if (!$stmt) json_err('db_prepare');
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);
    if (!$ok) json_err('invalid_or_inactive_provider', 422);
}

function resolve_target_provider_id($conexion, $isAdminPrincipal, $scopedProviderId) {
    if (!$isAdminPrincipal) {
        validate_active_medical_provider($conexion, $scopedProviderId);
        return $scopedProviderId;
    }

    $requestProviderId = isset($_REQUEST['provider_id']) ? intval($_REQUEST['provider_id']) : 0;
    if ($requestProviderId <= 0) {
        json_err('provider_required', 422);
    }
    validate_active_medical_provider($conexion, $requestProviderId);
    return $requestProviderId;
}

function assert_owned_service_for_scoped_provider($conexion, $isAdminPrincipal, $scopedProviderId, $serviceId) {
    if ($isAdminPrincipal) return;
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM provider_catalog_services WHERE provider_id = ? AND service_id = ? LIMIT 1");
    if (!$stmt) json_err('db_prepare');
    mysqli_stmt_bind_param($stmt, 'ii', $scopedProviderId, $serviceId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);
    if (!$ok) json_err('forbidden', 403);
}

function ensure_provider_service_link($conexion, $providerId, $serviceId) {
    $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_catalog_services (provider_id, service_id) VALUES (?, ?)");
    if (!$stmt) json_err('db_prepare');
    mysqli_stmt_bind_param($stmt, 'ii', $providerId, $serviceId);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        error_log('service_catalog provider link error: ' . $err);
        json_err('db_link');
    }
}

try{
    if ($tipo === 'list') {
        $rows = [];
        $categoryFilter = isset($_REQUEST['category_id']) ? intval($_REQUEST['category_id']) : 0;
        $targetProviderId = 0;
        $targetProviderName = '';
        $hasOfferProviderCatalogServiceId = service_catalog_table_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id');
        $descriptionColumn = service_catalog_description_column($conexion);

        if ($isAdminPrincipal) {
            $targetProviderId = isset($_REQUEST['provider_id']) ? intval($_REQUEST['provider_id']) : 0;
            if ($targetProviderId <= 0) {
                json_ok([
                    'data' => [],
                    'require_provider_context' => true,
                    'provider_id' => null,
                    'provider_name' => '',
                ]);
            }
            validate_active_medical_provider($conexion, $targetProviderId);

            $providerStmt = mysqli_prepare($conexion, "SELECT id, name FROM providers WHERE id = ? LIMIT 1");
            if (!$providerStmt) json_err('db_prepare');
            mysqli_stmt_bind_param($providerStmt, 'i', $targetProviderId);
            mysqli_stmt_execute($providerStmt);
            $providerRes = mysqli_stmt_get_result($providerStmt);
            $providerRow = $providerRes ? mysqli_fetch_assoc($providerRes) : null;
            mysqli_stmt_close($providerStmt);
            if (!$providerRow) json_err('invalid_or_inactive_provider', 422);
            $targetProviderName = trim((string)($providerRow['name'] ?? ''));
        } else {
            $targetProviderId = $scopedProviderId;
        }

        $sql = "SELECT DISTINCT
                    sc.id,
                    pcs.id AS provider_catalog_service_id,
                    sc.category_id,
                    c.name AS category_name,
                    sc.name,
                    sc.slug,
                    " . ($descriptionColumn !== '' ? ('sc.' . $descriptionColumn . ' AS short_description') : "'' AS short_description") . ",
                    sc.sort_order,
                    sc.is_active,
                    sc.created_at,
                    pcs.provider_id,
                    p.name AS provider_name,
                    " . ($hasOfferProviderCatalogServiceId
                        ? "(SELECT COUNT(*) FROM provider_service_offers o WHERE o.provider_id = pcs.provider_id AND o.provider_catalog_service_id = pcs.id)"
                        : "(SELECT COUNT(*) FROM provider_service_offers o WHERE o.provider_id = pcs.provider_id AND o.service_id = pcs.service_id)") . " AS offer_count
                FROM service_catalog sc
                INNER JOIN provider_catalog_services pcs
                    ON pcs.service_id = sc.id
                LEFT JOIN service_categories c ON sc.category_id = c.id
                LEFT JOIN providers p ON p.id = pcs.provider_id
                WHERE pcs.provider_id = ?";

        $types = 'i';
        $params = [$targetProviderId];

        if ($categoryFilter > 0) {
            $sql .= " AND sc.category_id = ?";
            $types .= 'i';
            $params[] = $categoryFilter;
        }

        $sql .= " ORDER BY sc.sort_order ASC, sc.id DESC";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) json_err('db_prepare');
        if ($types !== '') {
            bind_stmt_params($stmt, $types, $params);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            error_log('service_catalog list error: ' . $err);
            json_err('db');
        }

        $res = mysqli_stmt_get_result($stmt);
        while($r = mysqli_fetch_assoc($res)) {
            $r['provider_catalog_service_id'] = isset($r['provider_catalog_service_id']) ? (int)$r['provider_catalog_service_id'] : 0;
            $r['offer_count'] = isset($r['offer_count']) ? (int)$r['offer_count'] : 0;
            $rows[] = $r;
        }
        mysqli_stmt_close($stmt);

        json_ok([
            'data' => $rows,
            'require_provider_context' => false,
            'provider_id' => $targetProviderId > 0 ? $targetProviderId : null,
            'provider_name' => $targetProviderName,
        ]);
    }

    if ($tipo === 'create') {
        $categoryId = isset($_REQUEST['category_id']) ? intval($_REQUEST['category_id']) : 0;
        $name = isset($_REQUEST['name']) ? trim((string)$_REQUEST['name']) : '';
        $shortDescription = isset($_REQUEST['short_description']) ? trim((string)$_REQUEST['short_description']) : null;
        $sortOrder = isset($_REQUEST['sort_order']) ? intval($_REQUEST['sort_order']) : 1;
        $isActive = isset($_REQUEST['is_active']) ? intval($_REQUEST['is_active']) : 0;
        $descriptionColumn = service_catalog_description_column($conexion);

        if ($categoryId <= 0 || $name === '') {
            json_err('category_or_name_required');
        }
        if ($descriptionColumn === '') {
            json_err('service_description_column_missing', 500);
        }

        validate_active_category($conexion, $categoryId);
        $targetProviderId = resolve_target_provider_id($conexion, $isAdminPrincipal, $scopedProviderId);

        $baseSlug = slugify($name);
        $slug = $baseSlug;
        $i = 1;
        while(true){
            $s = mysqli_prepare($conexion, "SELECT id FROM service_catalog WHERE slug = ? LIMIT 1");
            if (!$s) json_err('db_prepare');
            mysqli_stmt_bind_param($s, 's', $slug);
            mysqli_stmt_execute($s);
            $r = mysqli_stmt_get_result($s);
            $exists = ($r && mysqli_num_rows($r) > 0);
            mysqli_stmt_close($s);
            if(!$exists) break;
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        $ins = mysqli_prepare($conexion, "INSERT INTO service_catalog (category_id, name, slug, " . $descriptionColumn . ", sort_order, is_active) VALUES (?,?,?,?,?,?)");
        if (!$ins) json_err('db_prepare');
        mysqli_stmt_bind_param($ins, 'isssii', $categoryId, $name, $slug, $shortDescription, $sortOrder, $isActive);
        if (!mysqli_stmt_execute($ins)) {
            $err = mysqli_stmt_error($ins);
            mysqli_stmt_close($ins);
            error_log('service_catalog create error: ' . $err);
            json_err('db_insert');
        }
        $id = mysqli_insert_id($conexion);
        mysqli_stmt_close($ins);

        ensure_provider_service_link($conexion, $targetProviderId, $id);

        json_ok(['id' => $id, 'provider_id' => $targetProviderId]);
    }

    if ($tipo === 'update') {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        if ($id <= 0) json_err('invalid_id');

        assert_owned_service_for_scoped_provider($conexion, $isAdminPrincipal, $scopedProviderId, $id);
        $targetProviderId = resolve_target_provider_id($conexion, $isAdminPrincipal, $scopedProviderId);
        $descriptionColumn = service_catalog_description_column($conexion);

        $allowed = ['category_id','name','short_description','sort_order','is_active'];
        $fields = [];
        $values = [];

        foreach($allowed as $k){
            if (!isset($_REQUEST[$k])) continue;
            if ($k === 'category_id' || $k === 'sort_order' || $k === 'is_active') {
                $val = intval($_REQUEST[$k]);
                if ($k === 'category_id') {
                    validate_active_category($conexion, $val);
                }
                $values[] = $val;
            } else {
                $values[] = trim((string)$_REQUEST[$k]);
            }
            if ($k === 'short_description') {
                if ($descriptionColumn === '') {
                    json_err('service_description_column_missing', 500);
                }
                $fields[] = $descriptionColumn . ' = ?';
                continue;
            }
            $fields[] = "$k = ?";
        }
        if (empty($fields)) {
            ensure_provider_service_link($conexion, $targetProviderId, $id);
            json_ok();
        }

        if (isset($_REQUEST['name'])) {
            $baseSlug = slugify(trim((string)$_REQUEST['name']));
            $slug = $baseSlug;
            $i = 1;
            while(true){
                $s = mysqli_prepare($conexion, "SELECT id FROM service_catalog WHERE slug = ? AND id != ? LIMIT 1");
                if (!$s) json_err('db_prepare');
                mysqli_stmt_bind_param($s, 'si', $slug, $id);
                mysqli_stmt_execute($s);
                $r = mysqli_stmt_get_result($s);
                $exists = ($r && mysqli_num_rows($r) > 0);
                mysqli_stmt_close($s);
                if(!$exists) break;
                $slug = $baseSlug . '-' . $i;
                $i++;
            }
            array_unshift($fields, 'slug = ?');
            array_unshift($values, $slug);
        }

        $sql = 'UPDATE service_catalog SET ' . implode(', ', $fields) . ' WHERE id = ? LIMIT 1';
        $values[] = $id;
        $types = '';
        foreach ($values as $v) {
            $types .= is_int($v) ? 'i' : 's';
        }

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) json_err('db_prepare');
        bind_stmt_params($stmt, $types, $values);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            error_log('service_catalog update error: ' . $err);
            json_err('db_update');
        }
        mysqli_stmt_close($stmt);

        ensure_provider_service_link($conexion, $targetProviderId, $id);

        json_ok();
    }

    if ($tipo === 'toggle') {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $val = isset($_REQUEST['val']) ? intval($_REQUEST['val']) : 0;
        if ($id <= 0) json_err('invalid_id');

        assert_owned_service_for_scoped_provider($conexion, $isAdminPrincipal, $scopedProviderId, $id);

        $stmt = mysqli_prepare($conexion, "UPDATE service_catalog SET is_active = ? WHERE id = ? LIMIT 1");
        if (!$stmt) json_err('db_prepare');
        mysqli_stmt_bind_param($stmt, 'ii', $val, $id);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            error_log('service_catalog toggle error: ' . $err);
            json_err('db_toggle');
        }
        mysqli_stmt_close($stmt);
        json_ok();
    }

    json_err('unknown_tipo');
} catch(Exception $e){
    error_log('service_catalog exception: ' . $e->getMessage());
    json_err('exception', 500);
}
