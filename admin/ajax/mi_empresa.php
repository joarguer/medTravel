<?php
session_start();
include("../include/conexion.php");

// Verificar sesión activa
if (!isset($_SESSION["usuario"]) || empty($_SESSION["usuario"])) {
    echo json_encode(['ok' => false, 'error' => 'Sesión no válida']);
    exit();
}

// Verificar provider_id en sesión
if (!isset($_SESSION['provider_id']) || empty($_SESSION['provider_id'])) {
    echo json_encode(['ok' => false, 'error' => 'No tiene permisos de prestador']);
    exit();
}

$provider_id = (int)$_SESSION['provider_id'];
$tipo = isset($_REQUEST["tipo"]) ? $_REQUEST["tipo"] : '';
$resultados = array();

if ($tipo == 'actualizar_empresa') {
    // Whitelist estricta de campos editables
    $allowed_fields = array('name', 'description', 'city', 'address', 'phone', 'email', 'website');
    
    $updates = array();
    $params = array();
    $types = '';
    
    // Construir UPDATE dinámico solo con campos permitidos
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['ok' => false, 'error' => 'No hay campos para actualizar']);
        exit();
    }
    
    // Agregar provider_id al final
    $params[] = $provider_id;
    $types .= 'i';
    
    $sql = "UPDATE providers SET " . implode(', ', $updates) . " WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conexion, $sql)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $exec = mysqli_stmt_execute($stmt);
        
        if ($exec === false) {
            $resultados['ok'] = false;
            $resultados['error'] = 'Error al actualizar: ' . mysqli_stmt_error($stmt);
        } else {
            $resultados['ok'] = true;
            $resultados['message'] = 'Datos actualizados correctamente';
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $resultados['ok'] = false;
        $resultados['error'] = 'Error de preparación: ' . mysqli_error($conexion);
    }
}

if ($tipo == 'upload_logo') {
    // Validar que se subió un archivo
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'No se recibió archivo o hubo un error']);
        exit();
    }
    
    $file = $_FILES['logo'];
    
    // Validar tamaño (máximo 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'El archivo excede el tamaño máximo de 2MB']);
        exit();
    }
    
    // Validar tipo MIME
    $allowed_types = array('image/jpeg', 'image/png', 'image/webp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_types)) {
        echo json_encode(['ok' => false, 'error' => 'Formato no permitido. Use JPG, PNG o WEBP']);
        exit();
    }
    
    // Definir extensión
    $ext_map = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    );
    $ext = $ext_map[$mime];
    
    // Crear directorio si no existe
    $upload_dir = "../img/providers/$provider_id/";
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo crear el directorio']);
            exit();
        }
    }
    
    // Nombre del archivo: logo_{timestamp}.{ext}
    $filename = 'logo_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['ok' => false, 'error' => 'Error al guardar el archivo']);
        exit();
    }
    
    // Actualizar base de datos
    $sql = "UPDATE providers SET logo = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($conexion, $sql)) {
        mysqli_stmt_bind_param($stmt, 'si', $filename, $provider_id);
        $exec = mysqli_stmt_execute($stmt);
        
        if ($exec === false) {
            // Eliminar archivo si falla la BD
            unlink($filepath);
            $resultados['ok'] = false;
            $resultados['error'] = 'Error al actualizar la base de datos';
        } else {
            $resultados['ok'] = true;
            $resultados['message'] = 'Logo actualizado correctamente';
            $resultados['filename'] = $filename;
            $resultados['url'] = '../img/providers/' . $provider_id . '/' . $filename;
        }
        
        mysqli_stmt_close($stmt);
    } else {
        unlink($filepath);
        $resultados['ok'] = false;
        $resultados['error'] = 'Error de preparación';
    }
}

echo json_encode($resultados);
?>
