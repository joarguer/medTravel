<?php
include_once __DIR__ . '/../include/include.php';
header('Content-Type: application/json; charset=utf-8');
if (!is_role_admin_session()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Access denied']);
    exit;
}
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

function json_ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function json_err($msg){ echo json_encode(['success'=>false,'error'=>$msg]); exit; }
function parse_permission_ids($input){
    if ($input === null) return [];
    if (is_array($input)) $raw = $input; else $raw = explode(',', $input);
    $ids = [];
    foreach ($raw as $v){
        $n = intval($v);
        if($n > 0) $ids[] = $n;
    }
    return array_values(array_unique($ids));
}

switch($action){
    case 'list':
        $rows = [];
        $res = mysqli_query($conexion, "SELECT id, slug, name, description FROM roles ORDER BY id ASC");
        if($res){ while($r = mysqli_fetch_assoc($res)) $rows[] = $r; }
        json_ok(['data'=>$rows]);
        break;
    case 'list_permissions':
        $rows = [];
        $res = mysqli_query($conexion, "SELECT id, slug, name, description FROM permissions ORDER BY name ASC");
        if($res){ while($r = mysqli_fetch_assoc($res)) $rows[] = $r; }
        json_ok(['data'=>$rows]);
        break;
    case 'role_permissions':
        $role_id = intval($_GET['role_id'] ?? $_GET['id'] ?? 0);
        if($role_id <= 0) json_err('Invalid role');
        $stmt = mysqli_prepare($conexion, "SELECT permission_id FROM role_permissions WHERE role_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $role_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ids = [];
        while($row = mysqli_fetch_assoc($res)){ $ids[] = intval($row['permission_id']); }
        json_ok(['permission_ids'=>$ids]);
        break;
    case 'save_permissions':
        $role_id = intval($_POST['role_id'] ?? 0);
        if($role_id <= 0) json_err('Invalid role');
        $perm_ids = parse_permission_ids($_POST['permissions'] ?? []);
        mysqli_begin_transaction($conexion);
        $del = mysqli_prepare($conexion, "DELETE FROM role_permissions WHERE role_id = ?");
        mysqli_stmt_bind_param($del, 'i', $role_id);
        if(!mysqli_stmt_execute($del)){
            mysqli_rollback($conexion);
            json_err('DB error clearing perms: '.mysqli_error($conexion));
        }
        if(!empty($perm_ids)){
            $ins = mysqli_prepare($conexion, "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach($perm_ids as $pid){
                mysqli_stmt_bind_param($ins, 'ii', $role_id, $pid);
                if(!mysqli_stmt_execute($ins)){
                    mysqli_rollback($conexion);
                    json_err('DB error saving perms: '.mysqli_error($conexion));
                }
            }
        }
        mysqli_commit($conexion);
        json_ok();
        break;
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if($id <= 0) json_err('Invalid id');
        $stmt = mysqli_prepare($conexion, "SELECT id, slug, name, description FROM roles WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        json_ok(['data'=>$row]);
        break;
    case 'create':
        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if($slug === '' || $name === '') json_err('Slug and name required');
        // determine id if not provided
        $id = isset($_POST['id']) && is_numeric($_POST['id']) ? intval($_POST['id']) : 0;
        if($id <= 0){
            $r = mysqli_query($conexion, "SELECT COALESCE(MAX(id),0)+1 AS next_id FROM roles");
            $rr = mysqli_fetch_assoc($r);
            $id = intval($rr['next_id']);
        }
        $stmt = mysqli_prepare($conexion, "INSERT INTO roles (id, slug, name, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE slug=VALUES(slug), name=VALUES(name), description=VALUES(description)");
        mysqli_stmt_bind_param($stmt, 'isss', $id, $slug, $name, $desc);
        if(mysqli_stmt_execute($stmt)) json_ok(['id'=>$id]);
        json_err('DB error: '.mysqli_error($conexion));
        break;
    case 'update':
        $id = intval($_POST['id'] ?? 0);
        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if($id <= 0) json_err('Invalid id');
        $stmt = mysqli_prepare($conexion, "UPDATE roles SET slug=?, name=?, description=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssi', $slug, $name, $desc, $id);
        if(mysqli_stmt_execute($stmt)) json_ok();
        json_err('DB error: '.mysqli_error($conexion));
        break;
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if($id <= 0) json_err('Invalid id');
        if(in_array($id, [1])) json_err('Protected role, cannot delete');
        $stmt = mysqli_prepare($conexion, "DELETE FROM roles WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if(mysqli_stmt_execute($stmt)) json_ok();
        json_err('DB error: '.mysqli_error($conexion));
        break;
    default:
        json_err('Unknown action');
}
