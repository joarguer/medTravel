<?php
session_start();
include("../include/conexion.php");
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');
$resp = ['ok' => false];
$tipo = isset($_REQUEST['tipo']) ? $_REQUEST['tipo'] : '';

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

function ensure_unique_slug($conexion, $base){
    $slug = $base;
    $i = 1;
    while(true){
        $sql = "SELECT id FROM service_categories WHERE slug = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($stmt);
        if(!$exists) return $slug;
        $slug = $base . '-' . $i;
        $i++;
    }
}

try {
    if($tipo == 'list'){
        $rows = [];
        $q = "SELECT id, name, slug, description, image, sort_order, is_active, created_at FROM service_categories ORDER BY sort_order ASC, id DESC";
        $res = mysqli_query($conexion, $q);
        if(mysqli_errno($conexion)){
            error_log('service_categories list error: '.mysqli_error($conexion));
            echo json_encode(['ok'=>false,'error'=>'db']); exit;
        }
        while($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        echo json_encode(['ok'=>true,'data'=>$rows]);
        exit;
    }

    if($tipo == 'create'){
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        $description = isset($_REQUEST['description']) ? trim($_REQUEST['description']) : null;
        $sort_order = isset($_REQUEST['sort_order']) ? (int)$_REQUEST['sort_order'] : 1;
        $is_active = isset($_REQUEST['is_active']) ? (int)$_REQUEST['is_active'] : 0;
        if($name === ''){
            echo json_encode(['ok'=>false,'error'=>'name_required']); exit;
        }
        $base_slug = slugify($name);
        $slug = ensure_unique_slug($conexion, $base_slug);
        $sql = "INSERT INTO service_categories (name, slug, description, sort_order, is_active) VALUES (?,?,?,?,?)";
        if($stmt = mysqli_prepare($conexion, $sql)){
            mysqli_stmt_bind_param($stmt, 'ssiii', $name, $slug, $description, $sort_order, $is_active);
            $exec = mysqli_stmt_execute($stmt);
            if(!$exec){ error_log('service_categories create error: '.mysqli_stmt_error($stmt)); echo json_encode(['ok'=>false,'error'=>'db_insert']); mysqli_stmt_close($stmt); exit; }
            $id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
            echo json_encode(['ok'=>true,'id'=>$id]); exit;
        } else {
            error_log('service_categories create prepare error: '.mysqli_error($conexion));
            echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit;
        }
    }

    if($tipo == 'update'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id <= 0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        // whitelist
        $allowed = ['name','description','sort_order','is_active'];
        $fields = [];
        $types = '';
        $values = [];
        foreach($allowed as $k){
            if(isset($_REQUEST[$k])){
                if($k == 'sort_order' || $k == 'is_active'){
                    $values[] = (int)$_REQUEST[$k];
                } else {
                    $values[] = trim($_REQUEST[$k]);
                }
                $fields[] = "$k = ?";
            }
        }
        if(empty($fields)) { echo json_encode(['ok'=>false,'error'=>'nothing_to_update']); exit; }
        // handle slug regeneration if name changed
        $regenerate_slug = isset($_REQUEST['name']);
        if($regenerate_slug){
            $base_slug = slugify(trim($_REQUEST['name']));
            // ensure uniqueness but skipping current id
            $slug = $base_slug;
            $i = 1;
            while(true){
                $sqls = "SELECT id FROM service_categories WHERE slug = ? AND id != ? LIMIT 1";
                $stm = mysqli_prepare($conexion, $sqls);
                mysqli_stmt_bind_param($stm, 'si', $slug, $id);
                mysqli_stmt_execute($stm);
                $resu = mysqli_stmt_get_result($stm);
                $exists = ($resu && mysqli_num_rows($resu) > 0);
                mysqli_stmt_close($stm);
                if(!$exists) break;
                $slug = $base_slug . '-' . $i; $i++;
            }
            // prepend slug update
            array_unshift($fields, 'slug = ?');
            array_unshift($values, $slug);
        }
        // build query
        $sql = 'UPDATE service_categories SET '.implode(', ', $fields).' WHERE id = ? LIMIT 1';
        $values[] = $id;
        // build types string
        $types = '';
        foreach($values as $v){
            if(is_int($v)) $types .= 'i'; else $types .= 's';
        }
        if($stmt = mysqli_prepare($conexion, $sql)){
            // bind dynamically
            $bind_names[] = $types;
            for($i=0;$i<count($values);$i++){
                $bind_name = 'bind' . $i;
                $$bind_name = $values[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array(array($stmt,'bind_param'), $bind_names);
            $exec = mysqli_stmt_execute($stmt);
            if(!$exec){ error_log('service_categories update error: '.mysqli_stmt_error($stmt)); echo json_encode(['ok'=>false,'error'=>'db_update']); mysqli_stmt_close($stmt); exit; }
            mysqli_stmt_close($stmt);
            echo json_encode(['ok'=>true]); exit;
        } else { error_log('service_categories update prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'toggle'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $val = isset($_REQUEST['val']) ? (int)$_REQUEST['val'] : 0;
        if($id <= 0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $sql = "UPDATE service_categories SET is_active = ? WHERE id = ? LIMIT 1";
        if($stmt = mysqli_prepare($conexion, $sql)){
            mysqli_stmt_bind_param($stmt, 'ii', $val, $id);
            $exec = mysqli_stmt_execute($stmt);
            if(!$exec){ error_log('service_categories toggle error: '.mysqli_stmt_error($stmt)); echo json_encode(['ok'=>false,'error'=>'db_toggle']); mysqli_stmt_close($stmt); exit; }
            mysqli_stmt_close($stmt);
            echo json_encode(['ok'=>true]); exit;
        } else { error_log('service_categories toggle prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    // unknown tipo
    echo json_encode(['ok'=>false,'error'=>'unknown_tipo']); exit;
} catch(Exception $e){
    error_log('service_categories exception: '.$e->getMessage());
    echo json_encode(['ok'=>false,'error'=>'exception']); exit;
}
