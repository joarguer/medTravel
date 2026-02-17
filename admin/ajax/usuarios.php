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
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET role_id = ?, rol = ? WHERE id = ? LIMIT 1");
        $rol_txt = (string)$role_id;
        mysqli_stmt_bind_param($stmt, 'isi', $role_id, $rol_txt, $id);
        if(!mysqli_stmt_execute($stmt)) json_err('db_error: '.mysqli_error($conexion));
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
