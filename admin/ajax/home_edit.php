<?php
session_start();
include("../include/conexion.php");

header('Content-Type: application/json; charset=utf-8');

$resultados = array();
$tipo = isset($_REQUEST["tipo"]) ? $_REQUEST["tipo"] : '';
$id_usuario = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

function booking_random_suffix($length = 4){
    if(function_exists('random_bytes')){
        return bin2hex(random_bytes($length));
    }
    if(function_exists('openssl_random_pseudo_bytes')){
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
    $characters = '0123456789abcdef';
    $max = strlen($characters) - 1;
    $result = '';
    for($i = 0; $i < $length * 2; $i++){
        $result .= $characters[mt_rand(0, $max)];
    }
    return $result;
}

function home_hero_parse_ini_size($value){
    $value = trim((string)$value);
    if($value === ''){
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    switch($unit){
        case 'g':
            $number *= 1024;
        case 'm':
            $number *= 1024;
        case 'k':
            $number *= 1024;
            break;
    }
    return (int)$number;
}

function home_hero_upload_error_message($error_code){
    switch((int)$error_code){
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the server upload limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'The server temporary folder is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not write the uploaded file.';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the upload.';
        default:
            return 'Upload failed.';
    }
}

if(
    $tipo === ''
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && !empty($_SERVER['CONTENT_LENGTH'])
){
    $content_length = intval($_SERVER['CONTENT_LENGTH']);
    $post_max_size_bytes = home_hero_parse_ini_size(ini_get('post_max_size'));
    if($post_max_size_bytes > 0 && $content_length > $post_max_size_bytes){
        echo json_encode([
            'status' => 'error',
            'message' => 'The uploaded request exceeds post_max_size on the server.',
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
        ]);
        exit;
    }
}

function home_hero_default_settings(){
    return [
        'id' => 0,
        'is_enabled' => '1',
        'media_type' => 'carousel',
        'video_url' => '',
        'video_poster' => '',
        'title' => '',
        'subtitle' => '',
        'cta_text' => '',
        'cta_url' => '',
        'updated_at' => null,
        'updated_by' => 0,
    ];
}

function home_hero_table_exists($conexion){
    static $exists = null;
    if($exists !== null){
        return $exists;
    }
    $query = mysqli_query($conexion, "SHOW TABLES LIKE 'home_hero_settings'");
    $exists = ($query && mysqli_num_rows($query) > 0);
    if($query instanceof mysqli_result){
        mysqli_free_result($query);
    }
    return $exists;
}

function home_hero_column_exists($conexion, $column_name){
    static $cache = [];
    $column_name = (string)$column_name;
    if(isset($cache[$column_name])){
        return $cache[$column_name];
    }
    $safe_column = mysqli_real_escape_string($conexion, $column_name);
    $query = mysqli_query(
        $conexion,
        "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'home_hero_settings' AND COLUMN_NAME = '" . $safe_column . "'"
    );
    $exists = false;
    if($query){
        $row = mysqli_fetch_assoc($query);
        $exists = !empty($row) && intval($row['total']) > 0;
    }
    if($query instanceof mysqli_result){
        mysqli_free_result($query);
    }
    $cache[$column_name] = $exists;
    return $exists;
}

function home_hero_limit_text($value, $max_length){
    $value = trim((string)$value);
    if($value === ''){
        return '';
    }
    if(function_exists('mb_substr')){
        return mb_substr($value, 0, $max_length);
    }
    return substr($value, 0, $max_length);
}

function home_hero_is_safe_relative_path($value){
    if($value === '' || strpos($value, '..') !== false || strpos($value, '\\') !== false){
        return false;
    }
    return (bool)preg_match('~^[A-Za-z0-9/_\-.?=&%]+$~', $value);
}

function home_hero_is_valid_cta_url($value){
    $value = trim((string)$value);
    if($value === ''){
        return true;
    }
    if(preg_match('~^https?://~i', $value)){
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
    if(strpos($value, '#') === 0){
        return (bool)preg_match('~^#[A-Za-z0-9\-_]+$~', $value);
    }
    return home_hero_is_safe_relative_path($value);
}

function home_hero_is_valid_video_url($value){
    $value = trim((string)$value);
    if($value === ''){
        return false;
    }
    if(preg_match('~^https?://~i', $value)){
        if(filter_var($value, FILTER_VALIDATE_URL) === false){
            return false;
        }
        $path = parse_url($value, PHP_URL_PATH);
        return is_string($path) && preg_match('~\.(mp4|m4v)$~i', $path);
    }
    return home_hero_is_safe_relative_path($value) && preg_match('~\.(mp4|m4v)(\?.*)?$~i', $value);
}

function home_hero_is_valid_poster($value){
    $value = trim((string)$value);
    if($value === ''){
        return true;
    }
    if(preg_match('~^https?://~i', $value)){
        if(filter_var($value, FILTER_VALIDATE_URL) === false){
            return false;
        }
        $path = parse_url($value, PHP_URL_PATH);
        return is_string($path) && preg_match('~\.(jpe?g|png|gif|webp)$~i', $path);
    }
    return home_hero_is_safe_relative_path($value) && preg_match('~\.(jpe?g|png|gif|webp)(\?.*)?$~i', $value);
}

function home_hero_get_settings($conexion){
    $settings = home_hero_default_settings();
    if(!home_hero_table_exists($conexion)){
        return $settings;
    }
    $query = mysqli_query($conexion, "SELECT id, is_enabled, media_type, video_url, video_poster, title, subtitle, cta_text, cta_url, updated_at, updated_by FROM home_hero_settings ORDER BY id DESC LIMIT 1");
    if($query && mysqli_num_rows($query) > 0){
        $row = mysqli_fetch_assoc($query);
        if(is_array($row)){
            $settings = array_merge($settings, $row);
        }
    }
    if($query instanceof mysqli_result){
        mysqli_free_result($query);
    }
    return $settings;
}

function home_hero_create_default_row($conexion, $updated_by){
    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO home_hero_settings (is_enabled, media_type, video_url, video_poster, title, subtitle, cta_text, cta_url, updated_at, updated_by) VALUES (1, 'carousel', '', '', '', '', '', '', NOW(), ?)"
    );
    if(!$stmt){
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $updated_by);
    $ok = mysqli_stmt_execute($stmt);
    $new_id = $ok ? mysqli_insert_id($conexion) : 0;
    mysqli_stmt_close($stmt);
    return $new_id;
}

function home_hero_get_or_create_id($conexion, $updated_by){
    if(!home_hero_table_exists($conexion)){
        return 0;
    }
    $settings = home_hero_get_settings($conexion);
    $id = isset($settings['id']) ? intval($settings['id']) : 0;
    if($id > 0){
        return $id;
    }
    return home_hero_create_default_row($conexion, $updated_by);
}

function home_hero_remove_managed_file($stored_path){
    $stored_path = trim((string)$stored_path);
    if($stored_path === ''){
        return;
    }
    $cleaned = preg_replace('/\\?.*$/', '', $stored_path);
    $cleaned = str_replace('\\', '/', $cleaned);
    $cleaned = ltrim($cleaned, '/');
    if($cleaned === '' || strpos($cleaned, 'img/home_hero/') !== 0 || strpos($cleaned, '..') !== false){
        return;
    }

    $root_dir = dirname(__DIR__, 2);
    $uploads_dir = realpath($root_dir . '/img/home_hero');
    if(!$uploads_dir){
        return;
    }

    $target_path = $root_dir . '/' . $cleaned;
    $resolved_path = realpath($target_path);
    if($resolved_path && strpos(str_replace('\\', '/', $resolved_path), str_replace('\\', '/', $uploads_dir)) === 0 && is_file($resolved_path)){
        unlink($resolved_path);
    }
}

function home_hero_update_media_field($conexion, $id, $field, $value, $updated_by){
    $stmt = mysqli_prepare($conexion, "UPDATE home_hero_settings SET {$field} = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
    if(!$stmt){
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'sii', $value, $updated_by, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

if($tipo == 'get_home'){
    $busco_carrucel = mysqli_query($conexion,"SELECT * FROM carrucel WHERE activo = '0' ORDER BY id ASC");
    while($rst_carrucel = mysqli_fetch_array($busco_carrucel)){
        $resultados[] = $rst_carrucel;
    }
}

if($tipo == 'get_hero_settings'){
    $resultados['status'] = 'success';
    $resultados['table_exists'] = home_hero_table_exists($conexion);
    $resultados['settings'] = home_hero_get_settings($conexion);
}

if($tipo == 'get_detailed_services_settings'){
    $resultados['status'] = 'success';
    $resultados['table_exists'] = home_hero_table_exists($conexion) && home_hero_column_exists($conexion, 'detailed_services_enabled');
    $resultados['detailed_services_enabled'] = '1';

    if($resultados['table_exists']){
        $query = mysqli_query($conexion, "SELECT id, detailed_services_enabled FROM home_hero_settings ORDER BY id DESC LIMIT 1");
        if($query && mysqli_num_rows($query) > 0){
            $row = mysqli_fetch_assoc($query);
            $resultados['id'] = intval($row['id'] ?? 0);
            $resultados['detailed_services_enabled'] = (string)($row['detailed_services_enabled'] ?? '1');
        }
        if($query instanceof mysqli_result){
            mysqli_free_result($query);
        }
    }
}

if($tipo == 'save_detailed_services_settings'){
    if(!home_hero_table_exists($conexion) || !home_hero_column_exists($conexion, 'detailed_services_enabled')){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Run the detailed services visibility migration before saving this setting.';
        echo json_encode($resultados);
        exit;
    }

    $enabled = (isset($_POST['detailed_services_enabled']) && intval($_POST['detailed_services_enabled']) === 0) ? 0 : 1;
    $settings_id = 0;
    $query = mysqli_query($conexion, "SELECT id FROM home_hero_settings ORDER BY id DESC LIMIT 1");
    if($query && mysqli_num_rows($query) > 0){
        $row = mysqli_fetch_assoc($query);
        $settings_id = intval($row['id'] ?? 0);
    }
    if($query instanceof mysqli_result){
        mysqli_free_result($query);
    }

    if($settings_id <= 0){
        $insert = mysqli_prepare($conexion, "INSERT INTO home_hero_settings (is_enabled, media_type, detailed_services_enabled, updated_at, updated_by) VALUES (1, 'carousel', ?, NOW(), ?)");
        if(!$insert){
            $resultados['status'] = 'error';
            $resultados['message'] = mysqli_error($conexion);
            echo json_encode($resultados);
            exit;
        }
        mysqli_stmt_bind_param($insert, 'ii', $enabled, $id_usuario);
        if(!mysqli_stmt_execute($insert)){
            $resultados['status'] = 'error';
            $resultados['message'] = mysqli_error($conexion);
            mysqli_stmt_close($insert);
            echo json_encode($resultados);
            exit;
        }
        $settings_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($insert);
    } else {
        $update = mysqli_prepare($conexion, "UPDATE home_hero_settings SET detailed_services_enabled = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
        if(!$update){
            $resultados['status'] = 'error';
            $resultados['message'] = mysqli_error($conexion);
            echo json_encode($resultados);
            exit;
        }
        mysqli_stmt_bind_param($update, 'iii', $enabled, $id_usuario, $settings_id);
        if(!mysqli_stmt_execute($update)){
            $resultados['status'] = 'error';
            $resultados['message'] = mysqli_error($conexion);
            mysqli_stmt_close($update);
            echo json_encode($resultados);
            exit;
        }
        mysqli_stmt_close($update);
    }

    $resultados['status'] = 'success';
    $resultados['detailed_services_enabled'] = (string)$enabled;
}

if($tipo == 'save_hero_settings'){
    if(!home_hero_table_exists($conexion)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'The home_hero_settings table does not exist yet. Run the SQL migration first.';
        echo json_encode($resultados);
        exit;
    }

    $is_enabled = isset($_POST['is_enabled']) && intval($_POST['is_enabled']) === 1 ? 1 : 0;
    $media_type = isset($_POST['media_type']) ? trim($_POST['media_type']) : 'carousel';
    $video_url = home_hero_limit_text($_POST['video_url'] ?? '', 500);
    $video_poster = home_hero_limit_text($_POST['video_poster'] ?? '', 500);
    $title = home_hero_limit_text($_POST['title'] ?? '', 255);
    $subtitle = home_hero_limit_text($_POST['subtitle'] ?? '', 255);
    $cta_text = home_hero_limit_text($_POST['cta_text'] ?? '', 100);
    $cta_url = home_hero_limit_text($_POST['cta_url'] ?? '', 500);

    if($media_type !== 'carousel' && $media_type !== 'video'){
        $media_type = 'carousel';
    }

    if($is_enabled === 1 && $media_type === 'video' && !home_hero_is_valid_video_url($video_url)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Provide a valid MP4 URL or upload an MP4 file for the hero video.';
        echo json_encode($resultados);
        exit;
    }

    if(!home_hero_is_valid_poster($video_poster)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Provide a valid poster image URL or upload a poster image.';
        echo json_encode($resultados);
        exit;
    }

    if(!home_hero_is_valid_cta_url($cta_url)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Provide a valid CTA link.';
        echo json_encode($resultados);
        exit;
    }

    $hero_id = home_hero_get_or_create_id($conexion, $id_usuario);
    if($hero_id <= 0){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Unable to initialize hero settings.';
        echo json_encode($resultados);
        exit;
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE home_hero_settings SET is_enabled = ?, media_type = ?, video_url = ?, video_poster = ?, title = ?, subtitle = ?, cta_text = ?, cta_url = ?, updated_at = NOW(), updated_by = ? WHERE id = ?"
    );

    if(!$stmt){
        $resultados['status'] = 'error';
        $resultados['message'] = mysqli_error($conexion);
        echo json_encode($resultados);
        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isssssssii',
        $is_enabled,
        $media_type,
        $video_url,
        $video_poster,
        $title,
        $subtitle,
        $cta_text,
        $cta_url,
        $id_usuario,
        $hero_id
    );

    if(mysqli_stmt_execute($stmt)){
        $resultados['status'] = 'success';
        $resultados['settings'] = home_hero_get_settings($conexion);
    } else {
        $resultados['status'] = 'error';
        $resultados['message'] = mysqli_error($conexion);
    }
    mysqli_stmt_close($stmt);
}

if($tipo == 'upload_hero_video'){
    if(!home_hero_table_exists($conexion)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'The home_hero_settings table does not exist yet. Run the SQL migration first.';
        echo json_encode($resultados);
        exit;
    }
    $upload_error = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if(!isset($_FILES['file']) || !is_array($_FILES['file']) || $upload_error !== UPLOAD_ERR_OK){
        $resultados['status'] = 'error';
        $resultados['message'] = home_hero_upload_error_message($upload_error);
        $resultados['upload_max_filesize'] = ini_get('upload_max_filesize');
        $resultados['post_max_size'] = ini_get('post_max_size');
        echo json_encode($resultados);
        exit;
    }

    $safe_name = basename($_FILES['file']['name']);
    $extension = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['mp4', 'm4v'];
    $allowed_mimes = ['video/mp4', 'video/x-m4v', 'application/octet-stream'];

    $detected_mime = isset($_FILES['file']['type']) ? trim((string)$_FILES['file']['type']) : '';
    if(!in_array($extension, $allowed_extensions, true) || ($detected_mime !== '' && !in_array($detected_mime, $allowed_mimes, true))){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Only MP4 video files are allowed.';
        echo json_encode($resultados);
        exit;
    }

    $upload_dir = "../../img/home_hero";
    if(!is_dir($upload_dir)){
        @mkdir($upload_dir, 0755, true);
    }
    if(!is_dir($upload_dir) || !is_writable($upload_dir)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Hero upload directory is not writable.';
        echo json_encode($resultados);
        exit;
    }

    $filename = 'hero_video_' . time() . '_' . booking_random_suffix(4) . '.' . $extension;
    $target_path = $upload_dir . '/' . $filename;
    if(!move_uploaded_file($_FILES['file']['tmp_name'], $target_path)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Unable to move the uploaded video.';
        echo json_encode($resultados);
        exit;
    }

    $hero_id = home_hero_get_or_create_id($conexion, $id_usuario);
    if($hero_id <= 0){
        @unlink($target_path);
        $resultados['status'] = 'error';
        $resultados['message'] = 'Unable to initialize hero settings.';
        echo json_encode($resultados);
        exit;
    }

    $settings = home_hero_get_settings($conexion);
    $stored_path = 'img/home_hero/' . $filename . '?' . rand();
    if(home_hero_update_media_field($conexion, $hero_id, 'video_url', $stored_path, $id_usuario)){
        home_hero_remove_managed_file($settings['video_url'] ?? '');
        $resultados['status'] = 'success';
        $resultados['path'] = $stored_path;
        $resultados['settings'] = home_hero_get_settings($conexion);
    } else {
        @unlink($target_path);
        $resultados['status'] = 'error';
        $resultados['message'] = mysqli_error($conexion);
    }
}

if($tipo == 'upload_hero_poster'){
    if(!home_hero_table_exists($conexion)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'The home_hero_settings table does not exist yet. Run the SQL migration first.';
        echo json_encode($resultados);
        exit;
    }
    $upload_error = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if(!isset($_FILES['file']) || !is_array($_FILES['file']) || $upload_error !== UPLOAD_ERR_OK){
        $resultados['status'] = 'error';
        $resultados['message'] = home_hero_upload_error_message($upload_error);
        $resultados['upload_max_filesize'] = ini_get('upload_max_filesize');
        $resultados['post_max_size'] = ini_get('post_max_size');
        echo json_encode($resultados);
        exit;
    }

    $safe_name = basename($_FILES['file']['name']);
    $extension = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp'];

    $detected_mime = isset($_FILES['file']['type']) ? trim((string)$_FILES['file']['type']) : '';
    if(!in_array($extension, $allowed_extensions, true) || ($detected_mime !== '' && !in_array($detected_mime, $allowed_mimes, true))){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Only JPG, PNG, GIF, or WEBP poster images are allowed.';
        echo json_encode($resultados);
        exit;
    }

    $upload_dir = "../../img/home_hero";
    if(!is_dir($upload_dir)){
        @mkdir($upload_dir, 0755, true);
    }
    if(!is_dir($upload_dir) || !is_writable($upload_dir)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Hero upload directory is not writable.';
        echo json_encode($resultados);
        exit;
    }

    $filename = 'hero_poster_' . time() . '_' . booking_random_suffix(4) . '.' . $extension;
    $target_path = $upload_dir . '/' . $filename;
    if(!move_uploaded_file($_FILES['file']['tmp_name'], $target_path)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Unable to move the uploaded poster.';
        echo json_encode($resultados);
        exit;
    }

    $hero_id = home_hero_get_or_create_id($conexion, $id_usuario);
    if($hero_id <= 0){
        @unlink($target_path);
        $resultados['status'] = 'error';
        $resultados['message'] = 'Unable to initialize hero settings.';
        echo json_encode($resultados);
        exit;
    }

    $settings = home_hero_get_settings($conexion);
    $stored_path = 'img/home_hero/' . $filename . '?' . rand();
    if(home_hero_update_media_field($conexion, $hero_id, 'video_poster', $stored_path, $id_usuario)){
        home_hero_remove_managed_file($settings['video_poster'] ?? '');
        $resultados['status'] = 'success';
        $resultados['path'] = $stored_path;
        $resultados['settings'] = home_hero_get_settings($conexion);
    } else {
        @unlink($target_path);
        $resultados['status'] = 'error';
        $resultados['message'] = mysqli_error($conexion);
    }
}

if($tipo == 'edit_input'){
    $id = $_REQUEST["id"];
    $text_come = $_REQUEST["text_come"];
    $input = $_REQUEST["input"];
    $busca = mysqli_query($conexion,"UPDATE carrucel SET $input = '$text_come' WHERE id = '$id'");
    if($busca){
        $resultados['status'] = 'success';
        $resultados['text_go'] = $text_come;
    } else {
        $resultados['status'] = 'error';
        $resultados['text_go'] = $text_come;
    }
}

if($tipo == 'edit_img'){
    $id = $_REQUEST["id"];
    $title = $_REQUEST["title"];
    $ruta = "../../img/carrucel/".$id."_".$title."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT img FROM carrucel WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            $archivo_ = mysqli_fetch_array($busco);
            $archivo =  "../../".$archivo_['img'];
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            $resultados['archivo'] = $archivo;
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/carrucel/".$id."_".$title."_".$_FILES["file"]["name"]."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE carrucel SET img = '$ruta' WHERE id = '$id'");
            $resultados['status'] = 'success';
            $resultados['ruta'] = $ruta;
        } else {
            $resultados['status'] = 'error1: '.mysqli_error($conexion);
            $resultados['ruta'] = $ruta;
        }
    } else {
        $resultados['status'] = 'error2: '.mysqli_error($conexion);
        $resultados['ruta'] = $ruta;
    }
}

if($tipo == 'add_img'){
    $title = $_REQUEST["title"];
    $ruta = "../../img/carrucel/".$title."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/carrucel/".$title."_".$_FILES["file"]["name"]."?".rand();
            $busca  = mysqli_query($conexion,"INSERT INTO carrucel (img) VALUES ('$ruta')");
            $id = mysqli_insert_id($conexion);
            $resultados['status'] = 'success';
            $resultados['ruta'] = $ruta;
            $resultados['id'] = $id;
        } else {
            $resultados['status'] = 'error1: '.mysqli_error($conexion);
            $resultados['ruta'] = $ruta;
        }
    } else {
        $resultados['status'] = 'error2: '.mysqli_error($conexion);
        $resultados['ruta'] = $ruta;
    }
}

if($tipo == 'add_input'){
    $id = $_REQUEST["id"];
    $over_title = $_REQUEST["over_title"];
    $title = $_REQUEST["title"];
    $parrafo = $_REQUEST["parrafo"];
    $btn = $_REQUEST["btn"];
    $actualizo = mysqli_query($conexion,"UPDATE carrucel SET over_title = '$over_title', title = '$title', parrafo = '$parrafo', btn = '$btn' WHERE id = '$id'");
    if($actualizo){
        $resultados['status'] = 'success';
    } else {
        $resultados['status'] = 'error';
    }
}

if($tipo == 'get_como_funciona'){
    $id = $_REQUEST["id"];
    $busco = mysqli_query($conexion,"SELECT * FROM home_como_funciona WHERE id = '$id'");
    if(mysqli_num_rows($busco) > 0){
        $resultados = mysqli_fetch_array($busco);
    }
}

if($tipo == 'edit_como_funciona'){
    $id = $_REQUEST["id"];
    $icon_class = $_REQUEST["icon_class"];
    $title = $_REQUEST["title"];
    $description = $_REQUEST["description"];
    $actualizo = mysqli_query($conexion,"UPDATE home_como_funciona SET icon_class = '$icon_class', title = '$title', description = '$description' WHERE id = '$id'");
    if($actualizo){
        $resultados['status'] = 'success';
    } else {
        $resultados['status'] = 'error';
    }
}

if($tipo == 'get_services'){
    $id = $_REQUEST["id"];
    $busco = mysqli_query($conexion,"SELECT * FROM home_services WHERE id = '$id'");
    if(mysqli_num_rows($busco) > 0){
        $resultados = mysqli_fetch_array($busco);
    }
}

if($tipo == 'edit_service'){
    $id = $_REQUEST["id"];
    $icon_class = $_REQUEST["icon_class"];
    $title = $_REQUEST["title"];
    $description = $_REQUEST["description"];
    $badge = $_REQUEST["badge"];
    $badge_class = $_REQUEST["badge_class"];
    $activo_value = (isset($_REQUEST["visible"]) && intval($_REQUEST["visible"]) === 0) ? '1' : '0';
    $actualizo = mysqli_query($conexion,"UPDATE home_services SET icon_class = '$icon_class', title = '$title', description = '$description', badge = '$badge', badge_class = '$badge_class', activo = '$activo_value' WHERE id = '$id'");
    if($actualizo){
        $resultados['status'] = 'success';
        $resultados['activo'] = $activo_value;
    } else {
        $resultados['status'] = 'error';
    }
}

if($tipo == 'edit_service_img'){
    $id = $_REQUEST["id"];
    $title = $_REQUEST["title"];
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        $resultados['status'] = 'error2: missing_file';
        echo json_encode($resultados);
        exit;
    }
    if (!empty($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $resultados['status'] = 'error2: upload_error';
        $resultados['error_code'] = $_FILES['file']['error'];
        $resultados['file_size'] = $_FILES['file']['size'] ?? 0;
        $resultados['upload_max_filesize'] = ini_get('upload_max_filesize');
        $resultados['post_max_size'] = ini_get('post_max_size');
        echo json_encode($resultados);
        exit;
    }
    $safeName = basename($_FILES['file']['name']);
    $upload_dir = "../../img/services";
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
        $resultados['status'] = 'error2: upload_path_not_writable';
        $resultados['ruta'] = $upload_dir;
        echo json_encode($resultados);
        exit;
    }
    $ruta = $upload_dir."/".$id."_".$title."_".$safeName;
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT img FROM home_services WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            $archivo_ = mysqli_fetch_array($busco);
            $archivo =  "../../".$archivo_['img'];
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/services/".$id."_".$title."_".$safeName."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE home_services SET img = '$ruta' WHERE id = '$id'");
            $resultados['status'] = 'success';
            $resultados['ruta'] = $ruta;
        } else {
            $resultados['status'] = 'error1: '.mysqli_error($conexion);
            $resultados['ruta'] = $ruta;
        }
    } else {
        $resultados['status'] = 'error2: '.mysqli_error($conexion);
        $resultados['ruta'] = $ruta;
    }
}

if($tipo == 'get_booking'){
    $busca = mysqli_query($conexion,"SELECT id,intro_title,intro_paragraph,secondary_paragraph,background_img,cta_text,cta_subtext FROM home_booking WHERE activo = '1' ORDER BY id DESC LIMIT 1");
    if(mysqli_num_rows($busca) > 0){
        $resultados = mysqli_fetch_assoc($busca);
    } else {
        $resultados = [
            'id' => 0,
            'intro_title' => 'Online Booking',
            'intro_paragraph' => 'Tell us about the care you need, your travel preferences, and any special requests so our medical concierge can assemble a seamless experience from consultation to recovery.',
            'secondary_paragraph' => 'Complete the form to request your custom proposal, and we’ll respond with trusted providers, tailored schedules, and concierge support for your trip to Colombia.',
            'background_img' => 'img/tour-booking-bg.jpg',
            'cta_text' => 'Submit your request',
            'cta_subtext' => 'Our coordinating team replies within 24 hours.',
        ];
    }
}

if($tipo == 'edit_booking_img'){
    $id = isset($_REQUEST["id"]) ? (int)$_REQUEST["id"] : 0;
    $resultados['status'] = 'error';
    if($id <= 0 || !isset($_FILES['file'])){
        echo json_encode($resultados);
        exit;
    }
    $allowed_types = ['image/jpeg','image/pjpeg','image/png','image/gif','image/webp'];
    $file_type = $_FILES['file']['type'];
    if(!in_array($file_type, $allowed_types)){
        $resultados['status'] = 'error';
        $resultados['message'] = 'Invalid file type';
        echo json_encode($resultados);
        exit;
    }
    $upload_dir = "../../img/booking";
    if(!is_dir($upload_dir)){
        mkdir($upload_dir, 0755, true);
    }
    $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $filename = 'booking_bg_' . time() . '_' . booking_random_suffix(4) . '.' . $extension;
    $target_path = $upload_dir . '/' . $filename;
    if(move_uploaded_file($_FILES['file']['tmp_name'], $target_path)){
        $busca_old = mysqli_query($conexion,"SELECT background_img FROM home_booking WHERE id = '$id'");
        if($busca_old && mysqli_num_rows($busca_old) > 0){
            $old = mysqli_fetch_assoc($busca_old);
            if(!empty($old['background_img']) && strpos($old['background_img'], 'tour-booking-bg.jpg') === false){
                $old_file = "../../" . $old['background_img'];
                if(file_exists($old_file)){
                    unlink($old_file);
                }
            }
        }
        $ruta = "img/booking/" . $filename . "?" . rand();
        mysqli_query($conexion,"UPDATE home_booking SET background_img = '$ruta' WHERE id = $id");
        $resultados['status'] = 'success';
        $resultados['ruta'] = $ruta;
    } else {
        $resultados['status'] = 'error';
        $resultados['message'] = 'Unable to move file';
    }
}

if($tipo == 'edit_booking'){
    $id = isset($_REQUEST["id"]) ? (int)$_REQUEST["id"] : 0;
    $field = $_REQUEST["field"];
    $value = isset($_REQUEST["value"]) ? mysqli_real_escape_string($conexion, $_REQUEST["value"]) : '';
    $allowed = ['intro_title','intro_paragraph','secondary_paragraph','cta_text','cta_subtext'];
    if($id > 0 && in_array($field, $allowed)){
        $update = mysqli_query($conexion,"UPDATE home_booking SET $field = '$value' WHERE id = $id");
        if($update){
            $resultados['status'] = 'success';
        } else {
            $resultados['status'] = 'error';
        }
    } else {
        $resultados['status'] = 'error';
    }
}

echo json_encode($resultados);
?>
