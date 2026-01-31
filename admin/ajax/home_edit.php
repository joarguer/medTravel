<?php
session_start();
include("../include/conexion.php");
$resultados = array(); 
$tipo = $_REQUEST["tipo"];
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
    for($i=0;$i<$length*2;$i++){
        $result .= $characters[mt_rand(0,$max)];
    }
    return $result;
}
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

// Cómo Funciona
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

// Servicios Detallados
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
    $actualizo = mysqli_query($conexion,"UPDATE home_services SET icon_class = '$icon_class', title = '$title', description = '$description', badge = '$badge', badge_class = '$badge_class' WHERE id = '$id'");
    if($actualizo){
        $resultados['status'] = 'success';
    } else {
        $resultados['status'] = 'error';
    }
}
if($tipo == 'edit_service_img'){
    $id = $_REQUEST["id"];
    $title = $_REQUEST["title"];
    $ruta = "../../img/services/".$id."_".$title."_".$_FILES['file']['name'];
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
            $ruta   = "img/services/".$id."_".$title."_".$_FILES['file']['name']."?".rand();
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
// Booking widget
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
