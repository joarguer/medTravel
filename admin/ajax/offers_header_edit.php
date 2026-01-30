<?php
session_start();
include("../include/conexion.php");

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';

// Obtener datos del header de offers.php
if ($tipo == 'get_header') {
    $query = mysqli_query($conexion, "SELECT * FROM services_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
    
    if (!$query) {
        echo json_encode([
            'header' => null,
            'error' => 'query_error',
            'message' => mysqli_error($conexion)
        ]);
        exit;
    }
    
    $header = mysqli_fetch_assoc($query);
    echo json_encode(['header' => $header]);
    exit;
}

// Editar header
if ($tipo == 'edit_header') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $field = isset($_POST['field']) ? $_POST['field'] : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    
    $allowed_fields = ['title', 'subtitle_1', 'subtitle_2'];
    
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['status' => 'error', 'message' => 'Campo no válido']);
        exit;
    }
    
    $field_escaped = mysqli_real_escape_string($conexion, $field);
    $value_escaped = mysqli_real_escape_string($conexion, $value);
    
    $query = "UPDATE services_header SET `$field_escaped` = '$value_escaped' WHERE id = $id";
    
    if (mysqli_query($conexion, $query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conexion)]);
    }
    exit;
}

// Upload header image
if ($tipo == 'upload_header_image') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Error al subir la imagen']);
        exit;
    }
    
    $file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Tipo de archivo no permitido']);
        exit;
    }
    
    // Crear directorio si no existe
    $upload_dir = '../../img/site/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'offers_header_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    $db_path = 'img/site/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $db_path_escaped = mysqli_real_escape_string($conexion, $db_path);
        $query = "UPDATE services_header SET bg_image = '$db_path_escaped' WHERE id = $id";
        
        if (mysqli_query($conexion, $query)) {
            echo json_encode(['status' => 'success', 'path' => $db_path]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la base de datos']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al mover el archivo']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Tipo de operación no válido']);
?>
