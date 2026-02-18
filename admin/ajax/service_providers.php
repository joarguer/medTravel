<?php
include("../include/conexion.php");
require_once("../include/roles.php");

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$isAdmin = is_role_admin_session();
$isComplementaryUser = is_complementary_user_session();
$serviceProviderScopeId = current_service_provider_id();
$canGetSelfScopedProvider = (
    $action === 'get_self' &&
    $isComplementaryUser &&
    $serviceProviderScopeId > 0 &&
    user_can(PERM_SERVICES_COMPLEMENTARY_MANAGE)
);

// Hardening RBAC: endpoints complementarios requieren permiso canónico explícito.
if (!$isAdmin && !user_can(PERM_PROVIDERS_COMPLEMENTARY_MANAGE) && !$canGetSelfScopedProvider) {
    json_err('forbidden', 403);
}

if ($isComplementaryUser && $serviceProviderScopeId <= 0) {
    json_err('forbidden', 403);
}

switch($action) {
    case 'list':
        ensure_view_permission();
        listProviders();
        break;
    case 'get':
        ensure_view_permission();
        getProvider();
        break;
    case 'create':
        ensure_edit_permission();
        createProvider();
        break;
    case 'update':
        ensure_edit_permission();
        updateProvider();
        break;
    case 'delete':
        ensure_edit_permission();
        deleteProvider();
        break;
    case 'toggle_status':
        ensure_edit_permission();
        toggleStatus();
        break;
    case 'get_self':
        getSelfProvider();
        break;
    default:
        json_err('invalid_action');
}

function json_ok($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function json_err($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function table_has_column($conexion, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function ensure_view_permission() {
    if (is_role_admin_session()) return;
    if (user_can(PERM_PROVIDERS_COMPLEMENTARY_MANAGE)) return;
    json_err('forbidden', 403);
}

function ensure_edit_permission() {
    if (is_role_admin_session()) return;
    if (user_can(PERM_PROVIDERS_COMPLEMENTARY_MANAGE)) return;
    json_err('forbidden', 403);
}

function current_scope_id() {
    return current_service_provider_id();
}

function is_scoped_complementary_user() {
    return is_complementary_user_session();
}

function assert_owned_provider_id($providerId) {
    if (!is_scoped_complementary_user()) return;
    $scopeId = current_scope_id();
    if ($scopeId <= 0 || intval($providerId) !== $scopeId) {
        json_err('forbidden', 403);
    }
}

function normalize_nullable_text($value) {
    if ($value === null) return null;
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function bind_stmt_params($stmt, $types, &$values) {
    $bind = [$types];
    foreach ($values as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function buildProviderData() {
    return [
        'provider_name' => trim((string)($_POST['provider_name'] ?? '')),
        'provider_type' => normalize_nullable_text($_POST['provider_type'] ?? null),
        'tax_id' => normalize_nullable_text($_POST['tax_id'] ?? null),
        'country' => trim((string)($_POST['country'] ?? 'Colombia')),
        'city' => normalize_nullable_text($_POST['city'] ?? null),
        'contact_name' => normalize_nullable_text($_POST['contact_name'] ?? null),
        'contact_position' => normalize_nullable_text($_POST['contact_position'] ?? null),
        'contact_email' => normalize_nullable_text($_POST['contact_email'] ?? null),
        'contact_phone' => normalize_nullable_text($_POST['contact_phone'] ?? null),
        'contact_mobile' => normalize_nullable_text($_POST['contact_mobile'] ?? null),
        'website' => normalize_nullable_text($_POST['website'] ?? null),
        'payment_terms' => normalize_nullable_text($_POST['payment_terms'] ?? null),
        'bank_account' => normalize_nullable_text($_POST['bank_account'] ?? null),
        'preferred_payment_method' => trim((string)($_POST['preferred_payment_method'] ?? 'transfer')),
        'rating' => floatval($_POST['rating'] ?? 0),
        'is_active' => intval($_POST['is_active'] ?? 1),
        'is_preferred' => intval($_POST['is_preferred'] ?? 0),
        'notes' => normalize_nullable_text($_POST['notes'] ?? null),
        'contract_details' => normalize_nullable_text($_POST['contract_details'] ?? null)
    ];
}

function listProviders() {
    global $conexion;

    $activeOnly = isset($_GET['active_only']) ? intval($_GET['active_only']) : 0;
    $type = trim((string)($_GET['type'] ?? ''));
    $hasSoftDelete = table_has_column($conexion, 'service_providers', 'is_deleted');

    $sql = "SELECT 
                id, provider_name, provider_type, country, city,
                contact_name, contact_email, contact_phone,
                rating, is_active, is_preferred, created_at
            FROM service_providers
            WHERE 1=1";

    $types = '';
    $params = [];

    if ($activeOnly) {
        $sql .= " AND is_active = 1";
    }
    if ($hasSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    if ($type !== '') {
        $sql .= " AND provider_type = ?";
        $types .= 's';
        $params[] = $type;
    }
    if (is_scoped_complementary_user()) {
        $scopeId = current_scope_id();
        $sql .= " AND id = ?";
        $types .= 'i';
        $params[] = $scopeId;
    }

    $sql .= " ORDER BY is_preferred DESC, provider_name ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) json_err('db_prepare_error');
    if ($types !== '') {
        bind_stmt_params($stmt, $types, $params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }

    $result = mysqli_stmt_get_result($stmt);
    $providers = [];
    while($row = mysqli_fetch_assoc($result)) {
        $providers[] = $row;
    }
    mysqli_stmt_close($stmt);

    json_ok(['data' => $providers]);
}

function getSelfProvider() {
    global $conexion;
    $scopeId = current_scope_id();
    if ($scopeId <= 0) {
        json_err('forbidden', 403);
    }

    $hasSoftDelete = table_has_column($conexion, 'service_providers', 'is_deleted');
    $sql = "SELECT * FROM service_providers WHERE id = ?";
    if ($hasSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'i', $scopeId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $result = mysqli_stmt_get_result($stmt);
    $provider = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$provider) {
        json_err('not_found', 404);
    }

    json_ok(['data' => $provider]);
}

function getProvider() {
    global $conexion;

    $id = intval($_GET['id'] ?? 0);
    if($id <= 0) json_err('invalid_id');

    assert_owned_provider_id($id);
    $hasSoftDelete = table_has_column($conexion, 'service_providers', 'is_deleted');

    if ($hasSoftDelete) {
        $deletedStmt = mysqli_prepare($conexion, "SELECT id FROM service_providers WHERE id = ? AND is_deleted = 1 LIMIT 1");
        if ($deletedStmt) {
            mysqli_stmt_bind_param($deletedStmt, 'i', $id);
            mysqli_stmt_execute($deletedStmt);
            $deletedRes = mysqli_stmt_get_result($deletedStmt);
            $isDeleted = ($deletedRes && mysqli_fetch_assoc($deletedRes)) ? true : false;
            mysqli_stmt_close($deletedStmt);
            if ($isDeleted) {
                json_err('registro eliminado', 410);
            }
        }
    }

    $sql = "SELECT * FROM service_providers WHERE id = ?";
    if ($hasSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $result = mysqli_stmt_get_result($stmt);
    $provider = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$provider) json_err('not_found', 404);
    json_ok(['data' => $provider]);
}

function createProvider() {
    global $conexion;

    if (is_scoped_complementary_user()) {
        json_err('forbidden', 403);
    }

    $data = buildProviderData();
    if ($data['provider_name'] === '') {
        json_err('El nombre del proveedor es obligatorio');
    }

    $userId = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
    $stmt = mysqli_prepare($conexion, "INSERT INTO service_providers (
                provider_name, provider_type, tax_id, country, city,
                contact_name, contact_position, contact_email, contact_phone, contact_mobile,
                website, payment_terms, bank_account, preferred_payment_method,
                rating, is_active, is_preferred, notes, contract_details, created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) json_err('db_prepare_error');

    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssssssssdiissi',
        $data['provider_name'],
        $data['provider_type'],
        $data['tax_id'],
        $data['country'],
        $data['city'],
        $data['contact_name'],
        $data['contact_position'],
        $data['contact_email'],
        $data['contact_phone'],
        $data['contact_mobile'],
        $data['website'],
        $data['payment_terms'],
        $data['bank_account'],
        $data['preferred_payment_method'],
        $data['rating'],
        $data['is_active'],
        $data['is_preferred'],
        $data['notes'],
        $data['contract_details'],
        $userId
    );

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $newId = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    json_ok([
        'message' => 'Proveedor creado exitosamente',
        'id' => $newId
    ]);
}

function updateProvider() {
    global $conexion;

    $id = intval($_POST['id'] ?? 0);
    if($id <= 0) json_err('invalid_id');

    assert_owned_provider_id($id);
    $hasSoftDelete = table_has_column($conexion, 'service_providers', 'is_deleted');

    $data = buildProviderData();
    if ($data['provider_name'] === '') {
        json_err('El nombre del proveedor es obligatorio');
    }

    $sql = "UPDATE service_providers SET
                provider_name = ?,
                provider_type = ?,
                tax_id = ?,
                country = ?,
                city = ?,
                contact_name = ?,
                contact_position = ?,
                contact_email = ?,
                contact_phone = ?,
                contact_mobile = ?,
                website = ?,
                payment_terms = ?,
                bank_account = ?,
                preferred_payment_method = ?,
                rating = ?,
                is_active = ?,
                is_preferred = ?,
                notes = ?,
                contract_details = ?
            WHERE id = ?";
    if ($hasSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) json_err('db_prepare_error');

    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssssssssdiissi',
        $data['provider_name'],
        $data['provider_type'],
        $data['tax_id'],
        $data['country'],
        $data['city'],
        $data['contact_name'],
        $data['contact_position'],
        $data['contact_email'],
        $data['contact_phone'],
        $data['contact_mobile'],
        $data['website'],
        $data['payment_terms'],
        $data['bank_account'],
        $data['preferred_payment_method'],
        $data['rating'],
        $data['is_active'],
        $data['is_preferred'],
        $data['notes'],
        $data['contract_details'],
        $id
    );

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($hasSoftDelete && $affected < 1) {
        json_err('registro eliminado', 410);
    }

    json_ok(['message' => 'Proveedor actualizado exitosamente']);
}

function deleteProvider() {
    global $conexion;

    if (is_scoped_complementary_user()) {
        json_err('forbidden', 403);
    }

    $id = intval($_POST['id'] ?? 0);
    if($id <= 0) json_err('invalid_id');
    $hasSoftDelete = table_has_column($conexion, 'service_providers', 'is_deleted');
    $hasDeletedAt = table_has_column($conexion, 'service_providers', 'deleted_at');
    $hasDeletedBy = table_has_column($conexion, 'service_providers', 'deleted_by');
    if (!$hasSoftDelete || !$hasDeletedAt || !$hasDeletedBy) {
        json_err('soft_delete_columns_missing');
    }

    $checkStmt = mysqli_prepare($conexion, "SELECT COUNT(*) FROM medtravel_services_catalog WHERE provider_id = ?");
    if (!$checkStmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($checkStmt, 'i', $id);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_bind_result($checkStmt, $count);
    mysqli_stmt_fetch($checkStmt);
    mysqli_stmt_close($checkStmt);

    if (intval($count) > 0) {
        json_err("No se puede eliminar. El proveedor tiene {$count} servicio(s) asociado(s)");
    }

    $sessionUserId = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
    $stmt = mysqli_prepare($conexion, "UPDATE service_providers SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, is_active = 0 WHERE id = ? AND is_deleted = 0");
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'ii', $sessionUserId, $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected < 1) {
        json_err('registro eliminado', 410);
    }

    json_ok(['message' => 'Proveedor eliminado (soft) exitosamente']);
}

function toggleStatus() {
    global $conexion;

    $id = intval($_POST['id'] ?? 0);
    if($id <= 0) json_err('invalid_id');

    assert_owned_provider_id($id);
    $hasSoftDelete = table_has_column($conexion, 'service_providers', 'is_deleted');

    $valRaw = $_POST['val'] ?? null;
    $hasExplicitValue = ($valRaw !== null && $valRaw !== '');
    if ($hasExplicitValue) {
        $val = intval($valRaw);
        if (!in_array($val, [0, 1], true)) {
            json_err('invalid_val', 422);
        }
        $sql = "UPDATE service_providers SET is_active = ? WHERE id = ?";
        if ($hasSoftDelete) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) json_err('db_prepare_error');
        mysqli_stmt_bind_param($stmt, 'ii', $val, $id);
    } else {
        $sql = "UPDATE service_providers SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?";
        if ($hasSoftDelete) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) json_err('db_prepare_error');
        mysqli_stmt_bind_param($stmt, 'i', $id);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($hasSoftDelete && $affected < 1) {
        json_err('registro eliminado', 410);
    }

    $statusSql = "SELECT is_active FROM service_providers WHERE id = ?";
    if ($hasSoftDelete) {
        $statusSql .= " AND is_deleted = 0";
    }
    $statusSql .= " LIMIT 1";
    $statusStmt = mysqli_prepare($conexion, $statusSql);
    if (!$statusStmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($statusStmt, 'i', $id);
    mysqli_stmt_execute($statusStmt);
    mysqli_stmt_bind_result($statusStmt, $isActive);
    mysqli_stmt_fetch($statusStmt);
    mysqli_stmt_close($statusStmt);

    json_ok([
        'message' => 'Estado actualizado',
        'is_active' => intval($isActive)
    ]);
}
?>
