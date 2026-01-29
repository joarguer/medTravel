<?php
session_start();
include("../include/conexion.php");

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';

// Obtener datos del header
if ($tipo == 'get_header') {
    // Verificar si la tabla existe
    $table_check = mysqli_query($conexion, "SHOW TABLES LIKE 'services_header'");
    if (mysqli_num_rows($table_check) == 0) {
        echo json_encode([
            'header' => null, 
            'error' => 'tabla_no_existe',
            'message' => 'La tabla services_header no existe. Por favor ejecute el script SQL de instalación.'
        ]);
        exit;
    }
    
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
    
    if (!$header) {
        // Si no existe, intentar crear uno por defecto
        $insert = mysqli_query($conexion, "INSERT INTO services_header (title, subtitle_1, subtitle_2, activo) VALUES ('Our Medical Services', 'MEDICAL SERVICES', 'Discover quality medical services from verified providers', 0)");
        if ($insert) {
            $query = mysqli_query($conexion, "SELECT * FROM services_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
            $header = mysqli_fetch_assoc($query);
        }
    }
    
    echo json_encode(['header' => $header]);
    exit;
}

// Crear header
if ($tipo == 'create_header') {
    $title = isset($_POST['title']) ? $_POST['title'] : 'Our Medical Services';
    $subtitle_1 = isset($_POST['subtitle_1']) ? $_POST['subtitle_1'] : 'MEDICAL SERVICES';
    $subtitle_2 = isset($_POST['subtitle_2']) ? $_POST['subtitle_2'] : 'Discover quality medical services';
    
    $stmt = mysqli_prepare($conexion, "INSERT INTO services_header (title, subtitle_1, subtitle_2, activo) VALUES (?, ?, ?, 0)");
    mysqli_stmt_bind_param($stmt, 'sss', $title, $subtitle_1, $subtitle_2);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Configuración creada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// Editar campo individual
if ($tipo == 'edit_campo') {
    $campo = isset($_POST['campo']) ? $_POST['campo'] : '';
    $valor = isset($_POST['valor']) ? $_POST['valor'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    $campos_permitidos = ['title', 'subtitle_1', 'subtitle_2'];
    
    if (!in_array($campo, $campos_permitidos)) {
        echo json_encode(['success' => false, 'message' => 'Campo no válido']);
        exit;
    }
    
    $stmt = mysqli_prepare($conexion, "UPDATE services_header SET $campo = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $valor, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Campo actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// Subir imagen
if ($tipo == 'upload_image') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../img/site/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagen no válido']);
            exit;
        }
        
        $new_filename = 'services-header-' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $bg_image = 'img/site/' . $new_filename;
            
            $stmt = mysqli_prepare($conexion, "UPDATE services_header SET bg_image = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $bg_image, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Imagen actualizada correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar en BD']);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al subir la imagen']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No se recibió ninguna imagen']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Tipo de operación no válido']);
?>
