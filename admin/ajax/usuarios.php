<?php
include_once __DIR__ . '/../include/include.php';
header('Content-Type: application/json; charset=utf-8');

if (!user_can('users.view')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'forbidden']);
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

function json_ok($data=[]) { echo json_encode(array_merge(['success'=>true], $data)); exit; }
function json_err($msg, $code=400){ http_response_code($code); echo json_encode(['success'=>false,'error'=>$msg]); exit; }

function usuarios_has_column($conexion, $column){
    $column = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE '{$column}'");
    return $q && mysqli_num_rows($q) > 0;
}

function fetch_active_service_provider($conexion, $serviceProviderId){
    $stmt = mysqli_prepare($conexion, "SELECT id, provider_name FROM service_providers WHERE id = ? AND is_active = 1 LIMIT 1");
    if(!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $serviceProviderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_roles($conexion){
    $roles = [];
    $res = mysqli_query($conexion, "SELECT id, name FROM roles ORDER BY id ASC");
    if($res){ while($r = mysqli_fetch_assoc($res)){ $roles[intval($r['id'])] = $r['name']; } }
    if(empty($roles)){
        foreach(get_available_roles() as $id=>$name){ $roles[$id] = $name; }
    }
    return $roles;
}

switch($action){
    case 'list_roles':
        $roles = fetch_roles($conexion);
        $data = [];
        foreach($roles as $id=>$name){ $data[] = ['id'=>$id, 'name'=>$name]; }
        json_ok(['data'=>$data]);
        break;

    case 'list_service_providers':
        $rows = [];
        $stmt = mysqli_prepare($conexion, "SELECT id, provider_name FROM service_providers WHERE is_active = 1 ORDER BY provider_name ASC");
        if(!$stmt) json_err('db_prepare_error');
        if(!mysqli_stmt_execute($stmt)) json_err('db_error: '.mysqli_error($conexion));
        $res = mysqli_stmt_get_result($stmt);
        while($r = mysqli_fetch_assoc($res)){
            $rows[] = [
                'id' => intval($r['id']),
                'provider_name' => $r['provider_name']
            ];
        }
        mysqli_stmt_close($stmt);
        json_ok(['data' => $rows]);
        break;

    case 'list':
        $rows = [];
        $roles = fetch_roles($conexion);
        $has_service_provider_id = false;
        $colRes = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE 'service_provider_id'");
        if ($colRes && mysqli_num_rows($colRes) > 0) $has_service_provider_id = true;
        if ($has_service_provider_id) {
            $sql = "SELECT u.id, u.usuario, u.nombre, u.email, u.role_id, u.rol, u.provider_id, u.service_provider_id, u.empresa, u.activo, p.name AS provider_name, p.kind AS provider_kind, sp.provider_name AS service_provider_name
                    FROM usuarios u
                    LEFT JOIN providers p ON p.id = u.provider_id
                    LEFT JOIN service_providers sp ON sp.id = u.service_provider_id
                    ORDER BY u.id DESC";
        } else {
            $sql = "SELECT u.id, u.usuario, u.nombre, u.email, u.role_id, u.rol, u.provider_id, u.empresa, u.activo, p.name AS provider_name, p.kind AS provider_kind
                    FROM usuarios u
                    LEFT JOIN providers p ON p.id = u.provider_id
                    ORDER BY u.id DESC";
        }
        $res = mysqli_query($conexion, $sql);
        if($res){
            while($r = mysqli_fetch_assoc($res)){
                $role_id = $r['role_id'] !== null ? intval($r['role_id']) : normalize_role_value($r['rol']);
                $provider_name = $r['provider_name'] ?? '';
                $provider_kind = $r['provider_kind'] ?? '';
                if ($has_service_provider_id && !empty($r['service_provider_name'])) {
                    $provider_name = $r['service_provider_name'];
                    $provider_kind = 'partner';
                }
                $rows[] = [
                    'id' => intval($r['id']),
                    'usuario' => $r['usuario'],
                    'nombre' => $r['nombre'],
                    'email' => $r['email'],
                    'role_id' => $role_id,
                    'role_name' => isset($roles[$role_id]) ? $roles[$role_id] : ($r['rol'] ?: ''),
                    'provider' => $provider_name,
                    'provider_kind' => $provider_kind,
                    'provider_id' => isset($r['provider_id']) ? intval($r['provider_id']) : 0,
                    'service_provider_id' => ($has_service_provider_id && isset($r['service_provider_id']) && $r['service_provider_id'] !== null) ? intval($r['service_provider_id']) : null,
                    'empresa' => $r['empresa'],
                    'activo' => intval($r['activo'])
                ];
            }
        }
        json_ok(['data'=>$rows]);
        break;

    case 'update_role':
        if (!user_can('users.edit')) json_err('forbidden', 403);
        $id = intval($_POST['id'] ?? 0);
        $role_id = intval($_POST['role_id'] ?? 0);
        if($id<=0 || $role_id<=0) json_err('invalid_input');
        $roles = fetch_roles($conexion);
        if(!isset($roles[$role_id])) json_err('role_not_found');
        $has_service_provider_id = usuarios_has_column($conexion, 'service_provider_id');
        $rol_txt = (string)$role_id;
        $service_provider_id = isset($_POST['service_provider_id']) && $_POST['service_provider_id'] !== ''
            ? intval($_POST['service_provider_id'])
            : null;

        if ($role_id === ROLE_PROVIDER_ADMIN) {
            if (!$has_service_provider_id) json_err('service_provider_column_missing', 422);
            if ($service_provider_id === null || $service_provider_id <= 0) {
                json_err('service_provider_required', 422);
            }
            $activeProvider = fetch_active_service_provider($conexion, $service_provider_id);
            if (!$activeProvider) {
                json_err('Invalid or inactive complementary provider', 422);
            }
            $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET role_id = ?, rol = ?, service_provider_id = ?, provider_id = NULL WHERE id = ? LIMIT 1");
            if(!$stmt) json_err('db_prepare_error');
            mysqli_stmt_bind_param($stmt, 'isii', $role_id, $rol_txt, $service_provider_id, $id);
            if(!mysqli_stmt_execute($stmt)) json_err('db_error: '.mysqli_error($conexion));
            mysqli_stmt_close($stmt);
            json_ok(['service_provider_id' => $service_provider_id, 'provider_name' => $activeProvider['provider_name']]);
        }

        if ($has_service_provider_id) {
            $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET role_id = ?, rol = ?, service_provider_id = NULL WHERE id = ? LIMIT 1");
            if(!$stmt) json_err('db_prepare_error');
            mysqli_stmt_bind_param($stmt, 'isi', $role_id, $rol_txt, $id);
            if(!mysqli_stmt_execute($stmt)) json_err('db_error: '.mysqli_error($conexion));
            mysqli_stmt_close($stmt);
            json_ok(['service_provider_id' => null]);
        }

        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET role_id = ?, rol = ? WHERE id = ? LIMIT 1");
        if(!$stmt) json_err('db_prepare_error');
        mysqli_stmt_bind_param($stmt, 'isi', $role_id, $rol_txt, $id);
        if(!mysqli_stmt_execute($stmt)) json_err('db_error: '.mysqli_error($conexion));
        mysqli_stmt_close($stmt);
        json_ok();
        break;

    case 'toggle_active':
        if (!user_can('users.edit')) json_err('forbidden', 403);
        $id = intval($_POST['id'] ?? 0);
        $val = isset($_POST['val']) ? intval($_POST['val']) : 0;
        if($id<=0) json_err('invalid_input');
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET activo = ? WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $val, $id);
        if(!mysqli_stmt_execute($stmt)) json_err('db_error: '.mysqli_error($conexion));
        json_ok();
        break;

    default:
        json_err('unknown_action');
}

?>
