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
        $sql = "SELECT u.id, u.usuario, u.nombre, u.email, u.role_id, u.rol, u.provider_id, u.empresa, u.activo, p.name AS provider_name, p.kind AS provider_kind FROM usuarios u LEFT JOIN providers p ON p.id = u.provider_id ORDER BY u.id DESC";
        $res = mysqli_query($conexion, $sql);
        if($res){
            while($r = mysqli_fetch_assoc($res)){
                $role_id = $r['role_id'] !== null ? intval($r['role_id']) : normalize_role_value($r['rol']);
                $rows[] = [
                    'id' => intval($r['id']),
                    'usuario' => $r['usuario'],
                    'nombre' => $r['nombre'],
                    'email' => $r['email'],
                    'role_id' => $role_id,
                    'role_name' => isset($roles[$role_id]) ? $roles[$role_id] : ($r['rol'] ?: ''),
                    'provider' => $r['provider_name'],
                    'provider_kind' => $r['provider_kind'],
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
