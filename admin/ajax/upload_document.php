<?php
// admin/ajax/upload_document.php - Upload de documentos para verificación
session_start();
header('Content-Type: application/json; charset=utf-8');

// Validación de sesión
if(!isset($_SESSION['id_usuario'])){
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}

require_once('../include/conexion.php');

$id_usuario = $_SESSION['id_usuario'];

// Configuración de upload
$upload_dir = '../uploads/provider_documents/';
$max_file_size = 10 * 1024 * 1024; // 10MB
$allowed_types = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];

try {
    // Validar que llegó un archivo
    if(!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No se ha seleccionado ningún archivo');
    }
    
    $file = $_FILES['document'];
    
    // Validar errores de upload
    if($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo: ' . $file['error']);
    }
    
    // Validar tamaño
    if($file['size'] > $max_file_size) {
        throw new Exception('El archivo excede el tamaño máximo permitido (10MB)');
    }
    
    // Validar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if(!in_array($mime_type, $allowed_types)) {
        throw new Exception('Tipo de archivo no permitido. Use: PDF, JPG, PNG, DOC');
    }
    
    // Validar extensión
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Extensión de archivo no permitida');
    }
    
    // Obtener parámetros POST
    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : 0;
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $document_type = isset($_POST['document_type']) ? mysqli_real_escape_string($conexion, $_POST['document_type']) : 'other';
    $title = isset($_POST['title']) ? mysqli_real_escape_string($conexion, $_POST['title']) : '';
    $description = isset($_POST['description']) ? mysqli_real_escape_string($conexion, $_POST['description']) : '';
    
    if($provider_id === 0) {
        throw new Exception('ID de proveedor no válido');
    }
    
    // Crear directorio si no existe
    if(!is_dir($upload_dir)) {
        if(!mkdir($upload_dir, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de uploads');
        }
    }
    
    // Crear subdirectorio por proveedor
    $provider_dir = $upload_dir . 'provider_' . $provider_id . '/';
    if(!is_dir($provider_dir)) {
        if(!mkdir($provider_dir, 0755, true)) {
            throw new Exception('No se pudo crear el directorio del proveedor');
        }
    }
    
    // Generar nombre único para el archivo
    $filename = uniqid('doc_' . $provider_id . '_') . '.' . $file_extension;
    $file_path = 'provider_' . $provider_id . '/' . $filename;
    $full_path = $provider_dir . $filename;
    
    // Mover archivo
    if(!move_uploaded_file($file['tmp_name'], $full_path)) {
        throw new Exception('Error al guardar el archivo');
    }
    
    // Guardar metadata en base de datos
    $original_filename = mysqli_real_escape_string($conexion, $file['name']);
    $file_size = $file['size'];
    
    $sql = "INSERT INTO provider_documents (
        provider_id,
        document_type,
        file_path,
        filename,
        original_filename,
        file_size,
        mime_type,
        file_extension,
        title,
        description,
        uploaded_by,
        uploaded_at
    ) VALUES (
        $provider_id,
        '$document_type',
        '$file_path',
        '$filename',
        '$original_filename',
        $file_size,
        '$mime_type',
        '$file_extension',
        " . ($title ? "'$title'" : "NULL") . ",
        " . ($description ? "'$description'" : "NULL") . ",
        $id_usuario,
        NOW()
    )";
    
    if(!mysqli_query($conexion, $sql)) {
        // Si falla el INSERT, eliminar el archivo subido
        unlink($full_path);
        throw new Exception('Error al guardar metadata: ' . mysqli_error($conexion));
    }
    
    $document_id = mysqli_insert_id($conexion);
    
    // Si hay un item_id, actualizar la referencia en provider_verification_items
    if($item_id > 0) {
        $update_sql = "UPDATE provider_verification_items 
                       SET evidence_document_id = $document_id,
                           evidence_type = 'document'
                       WHERE id = $item_id AND provider_id = $provider_id";
        mysqli_query($conexion, $update_sql);
    }
    
    // Respuesta exitosa
    echo json_encode([
        'ok' => true,
        'message' => 'Documento subido exitosamente',
        'data' => [
            'document_id' => $document_id,
            'filename' => $filename,
            'original_filename' => $original_filename,
            'file_size' => $file_size,
            'mime_type' => $mime_type
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en upload_document.php: " . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>
