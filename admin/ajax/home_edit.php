<?php
session_start();
include("../include/conexion.php");
$resultados = array(); 
$tipo = $_REQUEST["tipo"];
if($tipo == 'get_home'){
    $busco_carrucel = mysqli_query($conexion,"SELECT * FROM carrucel WHERE activo = '0' ORDER BY id ASC");
    while($rst_carrucel = mysqli_fetch_array($busco_carrucel)){
        $resultados[] = $rst_carrucel;
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
            //separamos archivo ?
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            $resultados['archivo'] = $archivo;
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/carrucel/".$id."_".$title."_".$_FILES['file']['name']."?".rand();
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
echo json_encode($resultados);
?>