<?php
include '../include/conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$tipo = $_POST['tipo'] ?? '';

// GET HEADER
if ($tipo === 'get_header') {
    $query = mysqli_query($conexion, "SELECT * FROM booking_wizard_header WHERE activo = '0' LIMIT 1");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró información del header']);
    }
}

// EDIT HEADER
elseif ($tipo === 'edit_header') {
    $field = mysqli_real_escape_string($conexion, $_POST['field'] ?? '');
    $value = mysqli_real_escape_string($conexion, $_POST['value'] ?? '');
    
    // Campos permitidos para actualizar
    $allowed_fields = ['title', 'subtitle_1', 'subtitle_2'];
    
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['success' => false, 'message' => 'Campo no permitido']);
        exit;
    }
    
    $query = mysqli_query($conexion, "UPDATE booking_wizard_header SET $field = '$value' WHERE activo = '0'");
    
    if ($query) {
        echo json_encode(['success' => true, 'message' => 'Campo actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($conexion)]);
    }
}

// UPLOAD HEADER IMAGE
elseif ($tipo === 'upload_header_image') {
    // Aceptar tanto 'file' como 'image' como nombre del campo
    $file_key = isset($_FILES['image']) ? 'image' : (isset($_FILES['file']) ? 'file' : null);
    
    if ($file_key && isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_key];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo JPG, PNG o GIF']);
            exit;
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'wizard_header_' . time() . '.' . $extension;
        $upload_path = '../../img/site/';
        $full_path = $upload_path . $filename;
        
        // Crear directorio si no existe
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // Actualizar en la base de datos
            $db_path = 'img/site/' . $filename;
            $query = mysqli_query($conexion, "UPDATE booking_wizard_header SET bg_image = '$db_path' WHERE activo = '0'");
            
            if ($query) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Imagen subida correctamente',
                    'file_path' => $db_path
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la ruta en la BD']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al mover el archivo']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo o hubo un error']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Tipo de operación no válido']);
}
?>
