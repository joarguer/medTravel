<?php
session_start();
include("../include/conexion.php");

function ensure_path_exists($dir) {
    if (is_dir($dir)) {
        return true;
    }
    return @mkdir($dir, 0755, true);
}
$resultados = array(); 
$tipo = $_REQUEST["tipo"];
if($tipo == 'get_home'){
    $busco_header = mysqli_query($conexion,"SELECT * FROM about_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
    if($rst_header = mysqli_fetch_array($busco_header)){
        $resultados['header'] = $rst_header;
    }
    //about_us
    $busco_about_us = mysqli_query($conexion,"SELECT * FROM about_us WHERE activo = '0' ORDER BY id ASC LIMIT 1");
    if($rst_about_us = mysqli_fetch_array($busco_about_us)){
        $resultados['about_us'] = $rst_about_us;
    }
    //spacialist
    $busca_specialist = mysqli_query($conexion,"SELECT * FROM specialist WHERE activo = '0' ORDER BY id ASC");
    while($rst_specialist = mysqli_fetch_array($busca_specialist)){
        $resultados['specialist'][] = $rst_specialist;
    }
    //specialist_list
    $busca_specialist_list = mysqli_query($conexion,"SELECT * FROM specialist_list WHERE activo = '0' ORDER BY id ASC");
    while($rst_specialist_list = mysqli_fetch_array($busca_specialist_list)){
        $resultados['specialist_list'][] = $rst_specialist_list;
    }
    //socia_media
    $busca_social_media = mysqli_query($conexion,"SELECT * FROM social_media WHERE activo = '0' ORDER BY id ASC");
    while($rst_social_media = mysqli_fetch_array($busca_social_media)){
        $resultados['social_media'][] = $rst_social_media;
    }
}
if($tipo == 'edit_input'){
    $id = $_REQUEST["id"];
    $text_come = $_REQUEST["text_come"];
    $input = $_REQUEST["input"];
    $busca = mysqli_query($conexion,"UPDATE about_header SET $input = '$text_come' WHERE id = '$id'");
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
    $ruta = "../../img/about_header/".$id."_".$title."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        if (!empty($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $resultados['status'] = 'upload_error:'.$_FILES['file']['error'];
            $resultados['ruta'] = $ruta;
            echo json_encode($resultados);
            exit;
        }
        $busco = mysqli_query($conexion,"SELECT img FROM about_header WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            $archivo_ = mysqli_fetch_array($busco);
            $archivo =  "../../".$archivo_['img'];
            //separamos archivo ?
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            $resultados['archivo'] = $archivo;
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        $dest_dir = dirname($ruta);
        if (!ensure_path_exists($dest_dir)) {
            $resultados['status'] = 'error_dir';
            $resultados['ruta'] = $ruta;
            echo json_encode($resultados);
            exit;
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/about_header/".$id."_".$title."_".$_FILES['file']['name']."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE about_header SET img = '$ruta' WHERE id = '$id'");
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
            $ruta   = "img/carrucel/".$title."_".$_FILES['file']['name']."?".rand();
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
        $resultados['text_go'] = $text_come;
    } else {
        $resultados['status'] = 'error';
        $resultados['text_go'] = $text_come;
    }
}

if($tipo == 'edit_about_us'){
    $id = $_REQUEST["id"];
    $text_come = $_REQUEST["text_come"];
    $input = $_REQUEST["input"];
    $guarda = mysqli_query($conexion,"UPDATE about_us SET $input = '$text_come' WHERE id = '$id'");
    if($guarda){
        $resultados['status'] = 'success';
        $resultados['text_go'] = $text_come;
    } else {
        $resultados['status'] = 'error';
        $resultados['text_go'] = $text_come;
    }
}
if($tipo == 'edit_specialist_list'){
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $titulo = isset($_POST['titulo']) ? $_POST['titulo'] : '';
    $subtitulo = isset($_POST['subtitulo']) ? $_POST['subtitulo'] : '';
    $facebook = isset($_POST['facebook']) ? $_POST['facebook'] : '';
    $instagram = isset($_POST['instagram']) ? $_POST['instagram'] : '';
    $twiter = isset($_POST['twiter']) ? $_POST['twiter'] : '';
    if($id <= 0){
        $resultados['status'] = 'error';
        $resultados['text_go'] = $titulo;
    } else {
        $stmt = mysqli_prepare($conexion,"UPDATE specialist_list SET titulo = ?, subtitulo = ?, facebook = ?, instagram = ?, twiter = ? WHERE id = ? LIMIT 1");
        if($stmt){
            mysqli_stmt_bind_param($stmt,'sssssi',$titulo,$subtitulo,$facebook,$instagram,$twiter,$id);
            $exec = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if($exec){
                $resultados['status'] = 'success';
                $resultados['text_go'] = $titulo;
            } else {
                $resultados['status'] = 'error';
                $resultados['text_go'] = $titulo;
            }
        } else {
            $resultados['status'] = 'error';
            $resultados['text_go'] = $titulo;
        }
    }
}
if($tipo == 'edit_img_specialist'){
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $title = isset($_POST['title']) ? $_POST['title'] : 'specialist';
    $ruta = "../../img/specialist/".$id."_".$title."_".$_FILES['file']['name'];
    if (($id <= 0) || !file_exists($_FILES['file']['tmp_name'])) {
        $resultados['status'] = 'error';
        echo json_encode($resultados);
        exit;
    }
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT img FROM specialist_list WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            $archivo_ = mysqli_fetch_array($busco);
            $archivo =  "../../".$archivo_['img'];
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        $dest_dir = dirname($ruta);
        ensure_path_exists($dest_dir);
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta_db   = "img/specialist/".$id."_".$title."_".$_FILES['file']['name']."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE specialist_list SET img = '$ruta_db' WHERE id = '$id'");
            $resultados['status'] = 'success';
            $resultados['ruta'] = $ruta_db;
        } else {
            $resultados['status'] = 'error';
        }
    } else {
        $resultados['status'] = 'error';
    }
}
if($tipo == 'add_specialist'){
    $titulo = isset($_POST['titulo']) ? mysqli_real_escape_string($conexion, $_POST['titulo']) : 'Especialista';
    $subtitulo = 'Especialista';
    $facebook = '#';
    $instagram = '#';
    $twiter = '#';
    $img_default = 'img/guide-1.jpg';
    $stmt = mysqli_prepare($conexion,"INSERT INTO specialist_list (titulo, subtitulo, facebook, twiter, instagram, img, activo) VALUES (?,?,?,?,?,?,0)");
    if($stmt){
        mysqli_stmt_bind_param($stmt,'ssssss',$titulo,$subtitulo,$facebook,$twiter,$instagram,$img_default);
        $exec = mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        if($exec){
            $resultados['status'] = 'success';
            $resultados['id'] = $new_id;
        } else {
            $resultados['status'] = 'error';
        }
    } else {
        $resultados['status'] = 'error';
    }
}
if($tipo == 'remove_specialist'){
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if($id <= 0){
        $resultados['status'] = 'error';
    } else {
        $elim = mysqli_prepare($conexion,"UPDATE specialist_list SET activo = 1 WHERE id = ? LIMIT 1");
        if($elim){
            mysqli_stmt_bind_param($elim,'i',$id);
            $exec = mysqli_stmt_execute($elim);
            mysqli_stmt_close($elim);
            if($exec){
                $resultados['status'] = 'success';
            } else {
                $resultados['status'] = 'error';
            }
        } else {
            $resultados['status'] = 'error';
        }
    }
}
if($tipo == 'edit_img_about_us'){
    $id = $_REQUEST["id"];
    $title = $_REQUEST["title"];
    $ruta = "../../img/about_us/img_".$id."_".$title."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT img FROM about_us WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            $archivo_ = mysqli_fetch_array($busco);
            $archivo =  "../../".$archivo_['img'];
            //separamos archivo ?
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            $resultados['archivo'] = $archivo;
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/about_us/img_".$id."_".$title."_".$_FILES['file']['name']."?".rand();
            $query = "UPDATE about_us SET img = '$ruta' WHERE id = '$id'";
            $busca  = mysqli_query($conexion, $query);
            if($busca){
                $resultados['status'] = 'success';
                $resultados['ruta'] = $ruta;
                $resultados['query'] = $query;
                $resultados['affected_rows'] = mysqli_affected_rows($conexion);
            } else {
                $resultados['status'] = 'error_update';
                $resultados['error'] = mysqli_error($conexion);
                $resultados['query'] = $query;
            }
        } else {
            $resultados['status'] = 'error1: '.mysqli_error($conexion);
            $resultados['ruta'] = $ruta;
        }
    } else {
        $resultados['status'] = 'error2: '.mysqli_error($conexion);
        $resultados['ruta'] = $ruta;
    }
}
if($tipo == 'edit_bg_about_us'){
    $id = $_REQUEST["id"];
    $title = $_REQUEST["title"];
    $ruta = "../../img/about_us/bg_".$id."_".$title."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT bg FROM about_us WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            $archivo_ = mysqli_fetch_array($busco);
            $archivo =  "../../".$archivo_['bg'];
            //separamos archivo ?
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            $resultados['archivo'] = $archivo;
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/about_us/bg_".$id."_".$title."_".$_FILES['file']['name']."?".rand();
            $query = "UPDATE about_us SET bg = '$ruta' WHERE id = '$id'";
            $busca  = mysqli_query($conexion, $query);
            if($busca){
                $resultados['status'] = 'success';
                $resultados['ruta'] = $ruta;
                $resultados['query'] = $query;
                $resultados['affected_rows'] = mysqli_affected_rows($conexion);
            } else {
                $resultados['status'] = 'error_update';
                $resultados['error'] = mysqli_error($conexion);
                $resultados['query'] = $query;
            }
        } else {
            $resultados['status'] = 'error1: '.mysqli_error($conexion);
            $resultados['ruta'] = $ruta;
        }
    } else {
        $resultados['status'] = 'error2: '.mysqli_error($conexion);
        $resultados['ruta'] = $ruta;
    }
}
if($tipo == 'edit_list'){
    $id = $_REQUEST["id"];
    $list = $_REQUEST["list"];
    $input = $_REQUEST["input"];
    $guarda = mysqli_query($conexion,"UPDATE about_us SET $input = '$list' WHERE id = '$id'");
    if($guarda){
        $resultados['status'] = 'success';
        $resultados['text_go'] = $list;
    } else {
        $resultados['status'] = 'error';
        $resultados['text_go'] = $list;
    }
}
if($tipo == 'add_list'){
    $list = $_REQUEST["list"];
    $id = $_REQUEST["id"];
    $actualizo = mysqli_query($conexion,"UPDATE about_us SET list = '$list' WHERE id = '$id'");
    if($actualizo){
        $resultados['status'] = 'success';
        $resultados['id'] = $id;
        $resultados['list'] = $list;
    } else {
        $resultados['status'] = 'error';
        $resultados['id'] = $id;
        $resultados['list'] = $list;
    }
}
echo json_encode($resultados);
?>
