<?php
// Habilitar reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Iniciar sesión
@session_start();

// Intentar incluir conexión
try {
    include_once("../include/conexion.php");
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'Error al incluir conexión: ' . $e->getMessage()));
    exit();
}

// Verificar conexión
if (!isset($conexion)) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'Variable de conexión no definida'));
    exit();
}

// Verificar conexión
if (!isset($conexion)) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'Variable de conexión no definida'));
    exit();
}

// Establecer header JSON
header('Content-Type: application/json');

// Verificar sesión activa
if (!isset($_SESSION["usuario"]) || empty($_SESSION["usuario"])) {
    echo json_encode(array('ok' => false, 'error' => 'Sesión no válida'));
    exit();
}

// Verificar provider_id en sesión
if (!isset($_SESSION['provider_id']) || empty($_SESSION['provider_id'])) {
    echo json_encode(array('ok' => false, 'error' => 'No tiene permisos de prestador'));
    exit();
}

$provider_id = (int)$_SESSION['provider_id'];
$tipo = isset($_REQUEST["tipo"]) ? $_REQUEST["tipo"] : '';
$resultados = array();

if ($tipo == 'actualizar_empresa') {
    // Whitelist estricta de campos editables
    $allowed_fields = array('name', 'description', 'city', 'address', 'phone', 'email', 'website');
    
    $updates = array();
    
    // Construir UPDATE con valores escapados manualmente
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            $value = mysqli_real_escape_string($conexion, $_POST[$field]);
            $updates[] = "`$field` = '$value'";
        }
    }
    
    if (empty($updates)) {
        echo json_encode(array('ok' => false, 'error' => 'No hay campos para actualizar'));
        exit();
    }
    
    // Construir y ejecutar SQL
    $sql = "UPDATE providers SET " . implode(', ', $updates) . " WHERE id = " . intval($provider_id);
    
    $exec = mysqli_query($conexion, $sql);
    
    if ($exec === false) {
        $resultados['ok'] = false;
        $resultados['error'] = 'Error al actualizar: ' . mysqli_error($conexion);
    } else {
        $resultados['ok'] = true;
        $resultados['message'] = 'Datos actualizados correctamente';
    }
    
    echo json_encode($resultados);
    exit();
}

if ($tipo == 'upload_logo') {
    // Validar que se subió un archivo
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(array('ok' => false, 'error' => 'No se recibió archivo o hubo un error'));
        exit();
    }
    
    $file = $_FILES['logo'];
    
    // Validar tamaño (máximo 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(array('ok' => false, 'error' => 'El archivo excede el tamaño máximo de 2MB'));
        exit();
    }
    
    // Validar tipo MIME
    $allowed_types = array('image/jpeg', 'image/png', 'image/webp');
    
    // Verificar si finfo está disponible
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        // Fallback usando el tipo del archivo
        $mime = $file['type'];
    }
    
    if (!in_array($mime, $allowed_types)) {
        echo json_encode(array('ok' => false, 'error' => 'Formato no permitido. Use JPG, PNG o WEBP'));
        exit();
    }
    
    // Definir extensión
    $ext_map = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    );
    $ext = isset($ext_map[$mime]) ? $ext_map[$mime] : 'jpg';
    
    // Crear directorio si no existe - ruta correcta desde ajax/
    $upload_dir = "../../img/providers/" . $provider_id . "/";
    if (!is_dir($upload_dir)) {
        if (!@mkdir($upload_dir, 0755, true)) {
            echo json_encode(array('ok' => false, 'error' => 'No se pudo crear el directorio'));
            exit();
        }
    }
    
    // Nombre del archivo: logo_{timestamp}.{ext}
    $filename = 'logo_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // Mover archivo
    if (!@move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(array('ok' => false, 'error' => 'Error al guardar el archivo'));
        exit();
    }
    
    // Actualizar base de datos usando query simple
    $filename_esc = mysqli_real_escape_string($conexion, $filename);
    $sql = "UPDATE providers SET logo = '$filename_esc' WHERE id = " . intval($provider_id);
    $exec = mysqli_query($conexion, $sql);
    
    if ($exec === false) {
        // Eliminar archivo si falla la BD
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
        echo json_encode(array('ok' => false, 'error' => 'Error al actualizar la base de datos: ' . mysqli_error($conexion)));
    } else {
        echo json_encode(array(
            'ok' => true,
            'message' => 'Logo actualizado correctamente',
            'filename' => $filename,
            'url' => '../img/providers/' . $provider_id . '/' . $filename
        ));
    }
    exit();
}

// Si no se reconoce el tipo, devolver error
if (empty($resultados)) {
    $resultados = array(
        'ok' => false,
        'error' => 'Tipo de operación no válido'
    );
}

echo json_encode($resultados);
?>
