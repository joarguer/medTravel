<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/medtravel_services.log');

header('Content-Type: application/json; charset=utf-8');

require_once('../include/conexion.php');
require_once('../include/roles.php');

require_login_ajax();

// Hardening RBAC: servicios complementarios requieren permiso canónico explícito.
if (!is_role_admin_session() && !user_can(PERM_SERVICES_COMPLEMENTARY_MANAGE)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$id_usuario = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

if ($action === '') {
    json_err('invalid_action');
}

if (in_array($action, ['list', 'get'], true)) {
    ensure_view_permission();
} elseif (in_array($action, ['create', 'update', 'delete', 'toggle_status', 'upload_image'], true)) {
    ensure_edit_permission();
}

if (is_complementary_user_session() && current_service_provider_id() <= 0) {
    json_err('forbidden', 403);
}

try {
    switch($action) {
        case 'list':
            listServices($conexion);
            break;
        case 'get':
            getService($conexion);
            break;
        case 'create':
            createService($conexion, $id_usuario);
            break;
        case 'update':
            updateService($conexion);
            break;
        case 'delete':
            deleteService($conexion);
            break;
        case 'toggle_status':
            toggleStatus($conexion);
            break;
        case 'upload_image':
            uploadServiceImage();
            break;
        default:
            json_err('invalid_action');
    }
} catch(Exception $e) {
    error_log("MedTravel Services Error: " . $e->getMessage());
    json_err('server_error', 500);
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

function ensure_view_permission() {
    if (is_role_admin_session()) return;
    if (user_can(PERM_SERVICES_COMPLEMENTARY_MANAGE)) return;
    json_err('forbidden', 403);
}

function ensure_edit_permission() {
    if (is_role_admin_session()) return;
    if (user_can(PERM_SERVICES_COMPLEMENTARY_MANAGE)) return;
    json_err('forbidden', 403);
}

function get_scope_service_provider_id() {
    if (!is_complementary_user_session()) return 0;
    return current_service_provider_id();
}

function validate_active_service_provider($conexion, $providerId) {
    $stmt = mysqli_prepare($conexion, "SELECT id FROM service_providers WHERE id = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);
    if (!$ok) {
        json_err('invalid_or_inactive_provider', 422);
    }
}

function resolve_target_provider_id($conexion, $requestProviderId) {
    $scopeId = get_scope_service_provider_id();
    if ($scopeId > 0) {
        validate_active_service_provider($conexion, $scopeId);
        return $scopeId;
    }

    $providerId = intval($requestProviderId);
    if ($providerId <= 0) {
        json_err('provider_required', 422);
    }
    validate_active_service_provider($conexion, $providerId);
    return $providerId;
}

function assert_service_scope($conexion, $serviceId) {
    $scopeId = get_scope_service_provider_id();
    if ($scopeId <= 0) return;

    $stmt = mysqli_prepare($conexion, "SELECT provider_id FROM medtravel_services_catalog WHERE id = ? LIMIT 1");
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        json_err('not_found', 404);
    }
    if (intval($row['provider_id']) !== $scopeId) {
        json_err('forbidden', 403);
    }
}

function bind_stmt_params($stmt, $types, &$values) {
    $bind = [$types];
    foreach ($values as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function value_to_type($value) {
    if (is_int($value)) return 'i';
    if (is_float($value)) return 'd';
    return 's';
}

function buildServiceData($post, $forcedProviderId = null) {
    $providerId = ($forcedProviderId !== null)
        ? intval($forcedProviderId)
        : (isset($post['provider_id']) && $post['provider_id'] !== '' ? intval($post['provider_id']) : null);

    return [
        'service_type' => isset($post['service_type']) && $post['service_type'] !== '' ? trim($post['service_type']) : null,
        'service_name' => isset($post['service_name']) && $post['service_name'] !== '' ? trim($post['service_name']) : null,
        'service_code' => isset($post['service_code']) && $post['service_code'] !== '' ? trim($post['service_code']) : null,
        'description' => isset($post['description']) && $post['description'] !== '' ? trim($post['description']) : null,
        'short_description' => isset($post['short_description']) && $post['short_description'] !== '' ? trim($post['short_description']) : null,
        'provider_id' => $providerId,
        'provider_notes' => isset($post['provider_notes']) && $post['provider_notes'] !== '' ? trim($post['provider_notes']) : null,
        'cost_price_cop' => isset($post['cost_price_cop']) ? floatval($post['cost_price_cop']) : 0.00,
        'exchange_rate' => isset($post['exchange_rate']) && $post['exchange_rate'] !== '' ? floatval($post['exchange_rate']) : null,
        'sale_price' => isset($post['sale_price']) ? floatval($post['sale_price']) : 0.00,
        'currency' => isset($post['currency']) && $post['currency'] !== '' ? trim($post['currency']) : 'USD',
        'service_details' => isset($post['service_details']) && $post['service_details'] !== '' ? trim($post['service_details']) : null,
        'is_active' => isset($post['is_active']) ? 1 : 0,
        'availability_status' => isset($post['availability_status']) && $post['availability_status'] !== '' ? trim($post['availability_status']) : 'available',
        'stock_quantity' => isset($post['stock_quantity']) && $post['stock_quantity'] !== '' ? intval($post['stock_quantity']) : null,
        'booking_lead_time' => isset($post['booking_lead_time']) ? intval($post['booking_lead_time']) : 0,
        'icon_class' => isset($post['icon_class']) && $post['icon_class'] !== '' ? trim($post['icon_class']) : null,
        'image_url' => isset($post['image_url']) && $post['image_url'] !== '' ? trim($post['image_url']) : null,
        'display_order' => isset($post['display_order']) ? intval($post['display_order']) : 0,
        'featured' => isset($post['featured']) ? 1 : 0,
        'tags' => isset($post['tags']) && $post['tags'] !== '' ? trim($post['tags']) : null,
        'internal_notes' => isset($post['internal_notes']) && $post['internal_notes'] !== '' ? trim($post['internal_notes']) : null,
    ];
}

function listServices($conexion) {
    $scopeId = get_scope_service_provider_id();
    $sql = "SELECT 
        s.id,
        s.service_type,
        s.service_name,
        s.service_code,
        s.short_description,
        p.provider_name,
        s.cost_price,
        s.sale_price,
        s.currency,
        s.commission_amount,
        s.commission_percentage,
        s.is_active,
        s.availability_status,
        s.stock_quantity,
        s.featured,
        s.display_order,
        s.created_at
    FROM medtravel_services_catalog s
    LEFT JOIN service_providers p ON s.provider_id = p.id
    WHERE 1=1";

    $types = '';
    $params = [];
    if ($scopeId > 0) {
        $sql .= " AND s.provider_id = ?";
        $types .= 'i';
        $params[] = $scopeId;
    }
    $sql .= " ORDER BY s.service_type ASC, s.display_order ASC, s.service_name ASC";

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
    $services = [];
    while($row = mysqli_fetch_assoc($result)) {
        $services[] = $row;
    }
    mysqli_stmt_close($stmt);

    json_ok(['data' => $services]);
}

function getService($conexion) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if($id <= 0) json_err('invalid_id');

    assert_service_scope($conexion, $id);

    $stmt = mysqli_prepare($conexion, "SELECT * FROM medtravel_services_catalog WHERE id = ? LIMIT 1");
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $result = mysqli_stmt_get_result($stmt);
    $service = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if(!$service) json_err('not_found', 404);
    json_ok(['data' => $service]);
}

function createService($conexion, $id_usuario) {
    $service_type = isset($_POST['service_type']) ? trim((string)$_POST['service_type']) : '';
    $service_name = isset($_POST['service_name']) ? trim((string)$_POST['service_name']) : '';
    if ($service_type === '' || $service_name === '') {
        json_err('Service type and name are required');
    }

    $forcedProviderId = resolve_target_provider_id($conexion, $_POST['provider_id'] ?? 0);
    $data = buildServiceData($_POST, $forcedProviderId);
    $data['created_by'] = intval($id_usuario);
    $data['provider_id'] = $forcedProviderId;

    $fields = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO medtravel_services_catalog (`" . implode('`,`', $fields) . "`) VALUES ({$placeholders})";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) json_err('db_prepare_error');

    $values = [];
    $types = '';
    foreach ($fields as $field) {
        $values[] = $data[$field];
        $types .= value_to_type($data[$field]);
    }
    bind_stmt_params($stmt, $types, $values);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    $new_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    $getStmt = mysqli_prepare($conexion, "SELECT * FROM medtravel_services_catalog WHERE id = ? LIMIT 1");
    if (!$getStmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($getStmt, 'i', $new_id);
    mysqli_stmt_execute($getStmt);
    $res = mysqli_stmt_get_result($getStmt);
    $service = mysqli_fetch_assoc($res);
    mysqli_stmt_close($getStmt);

    json_ok([
        'message' => 'Service created successfully',
        'data' => $service
    ]);
}

function updateService($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) json_err('invalid_id');

    assert_service_scope($conexion, $id);

    $forcedProviderId = resolve_target_provider_id($conexion, $_POST['provider_id'] ?? 0);
    $data = buildServiceData($_POST, $forcedProviderId);
    $data['provider_id'] = $forcedProviderId;

    $sets = [];
    $values = [];
    $types = '';
    foreach ($data as $field => $value) {
        $sets[] = "`{$field}` = ?";
        $values[] = $value;
        $types .= value_to_type($value);
    }

    $sql = "UPDATE medtravel_services_catalog SET " . implode(', ', $sets) . " WHERE id = ?";
    $values[] = $id;
    $types .= 'i';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) json_err('db_prepare_error');
    bind_stmt_params($stmt, $types, $values);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    mysqli_stmt_close($stmt);

    $getStmt = mysqli_prepare($conexion, "SELECT * FROM medtravel_services_catalog WHERE id = ? LIMIT 1");
    if (!$getStmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($getStmt, 'i', $id);
    mysqli_stmt_execute($getStmt);
    $res = mysqli_stmt_get_result($getStmt);
    $service = mysqli_fetch_assoc($res);
    mysqli_stmt_close($getStmt);

    json_ok([
        'message' => 'Service updated successfully',
        'data' => $service
    ]);
}

function deleteService($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) json_err('invalid_id');

    assert_service_scope($conexion, $id);

    $stmt = mysqli_prepare($conexion, "DELETE FROM medtravel_services_catalog WHERE id = ?");
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    mysqli_stmt_close($stmt);

    json_ok(['message' => 'Service deleted successfully']);
}

function toggleStatus($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) json_err('invalid_id');

    assert_service_scope($conexion, $id);

    $stmt = mysqli_prepare($conexion, "UPDATE medtravel_services_catalog SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
    if (!$stmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err);
    }
    mysqli_stmt_close($stmt);

    $statusStmt = mysqli_prepare($conexion, "SELECT is_active FROM medtravel_services_catalog WHERE id = ? LIMIT 1");
    if (!$statusStmt) json_err('db_prepare_error');
    mysqli_stmt_bind_param($statusStmt, 'i', $id);
    mysqli_stmt_execute($statusStmt);
    mysqli_stmt_bind_result($statusStmt, $isActive);
    mysqli_stmt_fetch($statusStmt);
    mysqli_stmt_close($statusStmt);

    json_ok([
        'message' => 'Status updated',
        'is_active' => intval($isActive)
    ]);
}

function uploadServiceImage() {
    if(!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        json_err('Image file is required');
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if(!in_array($file['type'], $allowedTypes, true)) {
        json_err('Invalid file type. Use JPG, PNG, GIF or WEBP.');
    }

    $uploadDir = '../../img/services/';
    if(!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
    if(empty($extension)) $extension = 'jpg';

    $filename = 'service_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($extension);
    $filepath = $uploadDir . $filename;
    $dbPath = 'img/services/' . $filename;

    if(move_uploaded_file($file['tmp_name'], $filepath)) {
        json_ok(['path' => $dbPath]);
    }
    json_err('Error saving uploaded image');
}
?>
