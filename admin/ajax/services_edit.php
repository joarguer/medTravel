<?php
include("../include/include.php");
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $subtitle_1 = isset($_POST['subtitle_1']) ? trim($_POST['subtitle_1']) : '';
    $subtitle_2 = isset($_POST['subtitle_2']) ? trim($_POST['subtitle_2']) : '';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'El título es requerido']);
        exit;
    }
    
    // Manejar el upload de imagen
    $bg_image = null;
    if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../img/site/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagen no válido. Use JPG, PNG, GIF o WEBP']);
            exit;
        }
        
        // Generar nombre único
        $new_filename = 'services-header-' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $upload_path)) {
            $bg_image = 'img/site/' . $new_filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al subir la imagen']);
            exit;
        }
    }
    
    if ($id > 0) {
        // Actualizar
        if ($bg_image) {
            $stmt = mysqli_prepare($conexion, "UPDATE services_header SET title = ?, subtitle_1 = ?, subtitle_2 = ?, bg_image = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssssi', $title, $subtitle_1, $subtitle_2, $bg_image, $id);
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE services_header SET title = ?, subtitle_1 = ?, subtitle_2 = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sssi', $title, $subtitle_1, $subtitle_2, $id);
        }
    } else {
        // Insertar
        if ($bg_image) {
            $stmt = mysqli_prepare($conexion, "INSERT INTO services_header (title, subtitle_1, subtitle_2, bg_image, activo) VALUES (?, ?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt, 'ssss', $title, $subtitle_1, $subtitle_2, $bg_image);
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO services_header (title, subtitle_1, subtitle_2, activo) VALUES (?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt, 'sss', $title, $subtitle_1, $subtitle_2);
        }
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Configuración guardada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
