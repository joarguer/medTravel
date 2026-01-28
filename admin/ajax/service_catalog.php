<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
session_start();
include('../include/conexion.php');
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');
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

try{
    if($tipo == 'list'){
        $rows = [];
        $category_filter = isset($_REQUEST['category_id']) ? (int)$_REQUEST['category_id'] : 0;
        $sql = "SELECT sc.id, sc.category_id, c.name AS category_name, sc.name, sc.slug, sc.short_description, sc.sort_order, sc.is_active, sc.created_at FROM service_catalog sc LEFT JOIN service_categories c ON sc.category_id = c.id";
        if($category_filter > 0){
            $sql .= " WHERE sc.category_id = " . $category_filter;
        }
        $sql .= " ORDER BY sc.sort_order ASC, sc.id DESC";
        $res = mysqli_query($conexion, $sql);
        if(mysqli_errno($conexion)){
            error_log('service_catalog list error: '.mysqli_error($conexion));
            echo json_encode(['ok'=>false,'error'=>'db']); exit;
        }
        while($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        echo json_encode(['ok'=>true,'data'=>$rows]); exit;
    }

    if($tipo == 'create'){
        $category_id = isset($_REQUEST['category_id']) ? (int)$_REQUEST['category_id'] : 0;
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        $short_description = isset($_REQUEST['short_description']) ? trim($_REQUEST['short_description']) : null;
        $sort_order = isset($_REQUEST['sort_order']) ? (int)$_REQUEST['sort_order'] : 1;
        $is_active = isset($_REQUEST['is_active']) ? (int)$_REQUEST['is_active'] : 0;
        if($category_id <= 0 || $name === ''){ echo json_encode(['ok'=>false,'error'=>'category_or_name_required']); exit; }
        // check category exists
        $chk = mysqli_prepare($conexion, "SELECT id FROM service_categories WHERE id = ? AND is_active = 1 LIMIT 1");
        mysqli_stmt_bind_param($chk, 'i', $category_id);
        mysqli_stmt_execute($chk);
        $cres = mysqli_stmt_get_result($chk);
        if(!$cres || mysqli_num_rows($cres) == 0){ echo json_encode(['ok'=>false,'error'=>'invalid_category']); exit; }
        mysqli_stmt_close($chk);
        $base_slug = slugify($name);
        // ensure unique
        $slug = $base_slug; $i = 1;
        while(true){
            $s = mysqli_prepare($conexion, "SELECT id FROM service_catalog WHERE slug = ? LIMIT 1");
            mysqli_stmt_bind_param($s, 's', $slug);
            mysqli_stmt_execute($s);
            $r = mysqli_stmt_get_result($s);
            $exists = ($r && mysqli_num_rows($r) > 0);
            mysqli_stmt_close($s);
            if(!$exists) break;
            $slug = $base_slug . '-' . $i; $i++;
        }
        $ins = mysqli_prepare($conexion, "INSERT INTO service_catalog (category_id, name, slug, short_description, sort_order, is_active) VALUES (?,?,?,?,?,?)");
        if($ins){
            mysqli_stmt_bind_param($ins, 'isssii', $category_id, $name, $slug, $short_description, $sort_order, $is_active);
            $exec = mysqli_stmt_execute($ins);
            if(!$exec){ error_log('service_catalog create error: '.mysqli_stmt_error($ins)); echo json_encode(['ok'=>false,'error'=>'db_insert']); mysqli_stmt_close($ins); exit; }
            $id = mysqli_insert_id($conexion);
            mysqli_stmt_close($ins);
            echo json_encode(['ok'=>true,'id'=>$id]); exit;
        } else { error_log('service_catalog create prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'update'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id <= 0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $allowed = ['category_id','name','short_description','sort_order','is_active'];
        $fields = [];$values = [];
        foreach($allowed as $k){ if(isset($_REQUEST[$k])){ if($k == 'category_id' || $k == 'sort_order' || $k == 'is_active') $values[] = (int)$_REQUEST[$k]; else $values[] = trim($_REQUEST[$k]); $fields[] = "$k = ?"; } }
        if(empty($fields)){ echo json_encode(['ok'=>false,'error'=>'nothing_to_update']); exit; }
        $regenerate_slug = isset($_REQUEST['name']);
        if($regenerate_slug){
            $base_slug = slugify(trim($_REQUEST['name']));
            $slug = $base_slug; $i = 1;
            while(true){
                $s = mysqli_prepare($conexion, "SELECT id FROM service_catalog WHERE slug = ? AND id != ? LIMIT 1");
                mysqli_stmt_bind_param($s, 'si', $slug, $id);
                mysqli_stmt_execute($s);
                $r = mysqli_stmt_get_result($s);
                $exists = ($r && mysqli_num_rows($r) > 0);
                mysqli_stmt_close($s);
                if(!$exists) break;
                $slug = $base_slug . '-' . $i; $i++;
            }
            array_unshift($fields, 'slug = ?'); array_unshift($values, $slug);
        }
        $sql = 'UPDATE service_catalog SET '.implode(', ', $fields).' WHERE id = ? LIMIT 1';
        $values[] = $id;
        $types = '';
        foreach($values as $v){ $types .= is_int($v)?'i':'s'; }
        if($stmt = mysqli_prepare($conexion, $sql)){
            $bind_names[] = $types;
            for($i=0;$i<count($values);$i++){ $bind_name = 'bind'.$i; $$bind_name = $values[$i]; $bind_names[] = &$$bind_name; }
            call_user_func_array(array($stmt,'bind_param'), $bind_names);
            $exec = mysqli_stmt_execute($stmt);
            if(!$exec){ error_log('service_catalog update error: '.mysqli_stmt_error($stmt)); echo json_encode(['ok'=>false,'error'=>'db_update']); mysqli_stmt_close($stmt); exit; }
            mysqli_stmt_close($stmt);
            echo json_encode(['ok'=>true]); exit;
        } else { error_log('service_catalog update prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'toggle'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $val = isset($_REQUEST['val']) ? (int)$_REQUEST['val'] : 0;
        if($id <= 0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $stmt = mysqli_prepare($conexion, "UPDATE service_catalog SET is_active = ? WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $val, $id);
        $exec = mysqli_stmt_execute($stmt);
        if(!$exec){ error_log('service_catalog toggle error: '.mysqli_stmt_error($stmt)); echo json_encode(['ok'=>false,'error'=>'db_toggle']); mysqli_stmt_close($stmt); exit; }
        mysqli_stmt_close($stmt);
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown_tipo']); exit;
} catch(Exception $e){ error_log('service_catalog exception: '.$e->getMessage()); echo json_encode(['ok'=>false,'error'=>'exception']); exit; }
