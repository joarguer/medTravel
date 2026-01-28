<?php
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
        $sql = "SELECT p.id, p.type, p.name, p.slug, p.city, p.is_verified, p.is_active, p.created_at FROM providers p ORDER BY p.created_at DESC";
        $res = mysqli_query($conexion, $sql);
        if(mysqli_errno($conexion)){ error_log('providers list error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db']); exit; }
        while($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        echo json_encode(['ok'=>true,'data'=>$rows]); exit;
    }

    if($tipo == 'get'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id <= 0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $sql = "SELECT * FROM providers WHERE id = ? LIMIT 1";
        if($st = mysqli_prepare($conexion, $sql)){
            mysqli_stmt_bind_param($st, 'i', $id);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($st);
            if(!$row){ echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
            // categories
            $cats = [];
            $s = mysqli_prepare($conexion, "SELECT category_id FROM provider_categories WHERE provider_id = ?");
            mysqli_stmt_bind_param($s, 'i', $id); mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s);
            while($cc = mysqli_fetch_assoc($r)) $cats[] = (int)$cc['category_id']; mysqli_stmt_close($s);
            // services
            $sv = [];
            $s2 = mysqli_prepare($conexion, "SELECT service_id FROM provider_catalog_services WHERE provider_id = ?");
            mysqli_stmt_bind_param($s2, 'i', $id); mysqli_stmt_execute($s2); $r2 = mysqli_stmt_get_result($s2);
            while($ss = mysqli_fetch_assoc($r2)) $sv[] = (int)$ss['service_id']; mysqli_stmt_close($s2);

            echo json_encode(['ok'=>true,'data'=>['provider'=>$row,'category_ids'=>$cats,'service_ids'=>$sv]]); exit;
        } else { error_log('providers get prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'create'){
        $type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        if($type === '' || ($type != 'medico' && $type != 'clinica') || $name === ''){ echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit; }
        $description = isset($_REQUEST['description']) ? trim($_REQUEST['description']) : null;
        $city = isset($_REQUEST['city']) ? trim($_REQUEST['city']) : null;
        $address = isset($_REQUEST['address']) ? trim($_REQUEST['address']) : null;
        $phone = isset($_REQUEST['phone']) ? trim($_REQUEST['phone']) : null;
        $email = isset($_REQUEST['email']) ? trim($_REQUEST['email']) : null;
        $website = isset($_REQUEST['website']) ? trim($_REQUEST['website']) : null;
        $is_verified = isset($_REQUEST['is_verified']) ? (int)$_REQUEST['is_verified'] : 0;
        $is_active = isset($_REQUEST['is_active']) ? (int)$_REQUEST['is_active'] : 0;

        $base_slug = slugify($name);
        $slug = $base_slug; $i = 1;
        while(true){ $s = mysqli_prepare($conexion, "SELECT id FROM providers WHERE slug = ? LIMIT 1"); mysqli_stmt_bind_param($s, 's', $slug); mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s); $exists = ($r && mysqli_num_rows($r)>0); mysqli_stmt_close($s); if(!$exists) break; $slug = $base_slug . '-' . $i; $i++; }

        $ins = mysqli_prepare($conexion, "INSERT INTO providers (type,name,slug,description,city,address,phone,email,website,is_verified,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        if($ins){ mysqli_stmt_bind_param($ins,'sssss sssss i', $type, $name, $slug, $description, $city, $address, $phone, $email, $website, $is_verified, $is_active); $exec = mysqli_stmt_execute($ins);
            if(!$exec){ error_log('providers create error: '.mysqli_stmt_error($ins)); echo json_encode(['ok'=>false,'error'=>'db_insert']); mysqli_stmt_close($ins); exit; }
            $id = mysqli_insert_id($conexion); mysqli_stmt_close($ins);
            // relations
            // category_ids and service_ids arrays
            $category_ids = isset($_REQUEST['category_ids']) && is_array($_REQUEST['category_ids']) ? $_REQUEST['category_ids'] : [];
            $service_ids = isset($_REQUEST['service_ids']) && is_array($_REQUEST['service_ids']) ? $_REQUEST['service_ids'] : [];
            // insert categories
            if(!empty($category_ids)){
                $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_categories (provider_id, category_id) VALUES (?,?)");
                foreach($category_ids as $cid){ $cid = (int)$cid; mysqli_stmt_bind_param($stmt,'ii',$id,$cid); mysqli_stmt_execute($stmt); }
                mysqli_stmt_close($stmt);
            }
            if(!empty($service_ids)){
                $stmt2 = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_catalog_services (provider_id, service_id) VALUES (?,?)");
                foreach($service_ids as $sid){ $sid = (int)$sid; mysqli_stmt_bind_param($stmt2,'ii',$id,$sid); mysqli_stmt_execute($stmt2); }
                mysqli_stmt_close($stmt2);
            }
            echo json_encode(['ok'=>true,'id'=>$id]); exit;
        } else { error_log('providers create prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'update'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0; if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $allowed = ['type','name','description','city','address','phone','email','website','is_verified','is_active'];
        $fields=[]; $values=[];
        foreach($allowed as $k){ if(isset($_REQUEST[$k])){ if(in_array($k,['is_verified','is_active'])) $values[] = (int)$_REQUEST[$k]; else $values[] = trim($_REQUEST[$k]); $fields[] = "$k = ?"; } }
        if(empty($fields)){ echo json_encode(['ok'=>false,'error'=>'nothing_to_update']); exit; }
        $regenerate_slug = isset($_REQUEST['name']);
        if($regenerate_slug){ $base_slug = slugify(trim($_REQUEST['name'])); $slug = $base_slug; $i=1; while(true){ $s = mysqli_prepare($conexion, "SELECT id FROM providers WHERE slug = ? AND id != ? LIMIT 1"); mysqli_stmt_bind_param($s,'si',$slug,$id); mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s); $exists = ($r && mysqli_num_rows($r)>0); mysqli_stmt_close($s); if(!$exists) break; $slug = $base_slug . '-' . $i; $i++; } array_unshift($fields,'slug = ?'); array_unshift($values,$slug); }
        $sql = 'UPDATE providers SET '.implode(', ', $fields).' WHERE id = ? LIMIT 1'; $values[] = $id; $types=''; foreach($values as $v){ $types .= is_int($v)?'i':'s'; }
        if($stmt = mysqli_prepare($conexion, $sql)){
            $bind_names = array(); $bind_names[] = $types; for($i=0;$i<count($values);$i++){ $bind_name = 'b'.$i; $$bind_name = $values[$i]; $bind_names[] = &$$bind_name; }
            call_user_func_array(array($stmt,'bind_param'), $bind_names);
            $exec = mysqli_stmt_execute($stmt);
            if(!$exec){ error_log('providers update error: '.mysqli_stmt_error($stmt)); echo json_encode(['ok'=>false,'error'=>'db_update']); mysqli_stmt_close($stmt); exit; }
            mysqli_stmt_close($stmt);
            // update relations: delete existing and insert new arrays
            $category_ids = isset($_REQUEST['category_ids']) && is_array($_REQUEST['category_ids']) ? $_REQUEST['category_ids'] : [];
            $service_ids = isset($_REQUEST['service_ids']) && is_array($_REQUEST['service_ids']) ? $_REQUEST['service_ids'] : [];
            // delete existing
            $d1 = mysqli_prepare($conexion, "DELETE FROM provider_categories WHERE provider_id = ?"); mysqli_stmt_bind_param($d1,'i',$id); mysqli_stmt_execute($d1); mysqli_stmt_close($d1);
            $d2 = mysqli_prepare($conexion, "DELETE FROM provider_catalog_services WHERE provider_id = ?"); mysqli_stmt_bind_param($d2,'i',$id); mysqli_stmt_execute($d2); mysqli_stmt_close($d2);
            // reinsert
            if(!empty($category_ids)){
                $ins = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_categories (provider_id, category_id) VALUES (?,?)");
                foreach($category_ids as $cid){ $cid = (int)$cid; mysqli_stmt_bind_param($ins,'ii',$id,$cid); mysqli_stmt_execute($ins); }
                mysqli_stmt_close($ins);
            }
            if(!empty($service_ids)){
                $ins2 = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_catalog_services (provider_id, service_id) VALUES (?,?)");
                foreach($service_ids as $sid){ $sid = (int)$sid; mysqli_stmt_bind_param($ins2,'ii',$id,$sid); mysqli_stmt_execute($ins2); }
                mysqli_stmt_close($ins2);
            }
            echo json_encode(['ok'=>true]); exit;
        } else { error_log('providers update prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'toggle'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0; $val = isset($_REQUEST['val']) ? (int)$_REQUEST['val'] : 0; if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $st = mysqli_prepare($conexion, "UPDATE providers SET is_active = ? WHERE id = ? LIMIT 1"); mysqli_stmt_bind_param($st,'ii',$val,$id); $exec = mysqli_stmt_execute($st); if(!$exec){ error_log('providers toggle error: '.mysqli_stmt_error($st)); echo json_encode(['ok'=>false,'error'=>'db_toggle']); mysqli_stmt_close($st); exit; } mysqli_stmt_close($st); echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown_tipo']); exit;
} catch(Exception $e){ error_log('providers exception: '.$e->getMessage()); echo json_encode(['ok'=>false,'error'=>'exception']); exit; }
