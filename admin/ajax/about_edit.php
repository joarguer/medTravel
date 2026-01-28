<?php
session_start();
include("../include/conexion.php");
$resultados = array(); 
$tipo = $_REQUEST["tipo"];
if($tipo == 'get_home'){
    $busco_header = mysqli_query($conexion,"SELECT * FROM about_header WHERE activo = '0' ORDER BY id ASC");
    while($rst_header = mysqli_fetch_array($busco_header)){
        $resultados['header'] = $rst_header;
    }
    //about_us
    $busco_about_us = mysqli_query($conexion,"SELECT * FROM about_us WHERE activo = '0' ORDER BY id ASC");
    while($rst_about_us = mysqli_fetch_array($busco_about_us)){
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
            $busca  = mysqli_query($conexion,"UPDATE about_us SET img = '$ruta' WHERE id = '$id'");
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
if($tipo == 'edit_bg_about_us'){
    $id = $_REQUEST["id"];
    $title = $_REQUEST["title"];
    $ruta = "../../img/about_us/bg_".$id."_".$title."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT bg FROM about_us WHERE id = '$id'");
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
            $ruta   = "img/about_us/bg_".$id."_".$title."_".$_FILES['file']['name']."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE about_us SET bg = '$ruta' WHERE id = '$id'");
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