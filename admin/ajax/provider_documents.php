<?php
// admin/ajax/provider_documents.php - API para gestionar documentos de proveedores
session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['id_usuario'])){
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}

require_once('../include/conexion.php');

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$id_usuario = $_SESSION['id_usuario'];

try {
    switch($action) {
        case 'list':
            listDocuments($conexion);
            break;
        
        case 'get':
            getDocument($conexion);
            break;
        
        case 'delete':
            deleteDocument($conexion);
            break;
        
        case 'verify':
            verifyDocument($conexion, $id_usuario);
            break;
        
        default:
            echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    error_log("Error en provider_documents.php: " . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

// ===================================================================
// LISTAR DOCUMENTOS DE UN PROVEEDOR
// ===================================================================
function listDocuments($conexion) {
    $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
    
    if($provider_id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID de proveedor no válido']);
        return;
    }
    
    $sql = "SELECT 
        id,
        document_type,
        file_path,
        filename,
        original_filename,
        file_size,
        mime_type,
        title,
        description,
        document_date,
        expiration_date,
        is_verified,
        verified_by,
        verified_at,
        uploaded_at
    FROM provider_documents
    WHERE provider_id = $provider_id
    ORDER BY uploaded_at DESC";
    
    $result = mysqli_query($conexion, $sql);
    
    if(!$result) {
        throw new Exception("Error en consulta: " . mysqli_error($conexion));
    }
    
    $documents = [];
    while($row = mysqli_fetch_assoc($result)) {
        // Formatear tamaño de archivo
        $row['file_size_formatted'] = formatFileSize($row['file_size']);
        
        // URL de descarga
        $row['download_url'] = '../uploads/provider_documents/' . $row['file_path'];
        
        $documents[] = $row;
    }
    
    echo json_encode([
        'ok' => true,
        'data' => $documents
    ]);
}

// ===================================================================
// OBTENER UN DOCUMENTO
// ===================================================================
function getDocument($conexion) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    $sql = "SELECT * FROM provider_documents WHERE id = $id";
    $result = mysqli_query($conexion, $sql);
    
    if(!$result) {
        throw new Exception("Error en consulta: " . mysqli_error($conexion));
    }
    
    $document = mysqli_fetch_assoc($result);
    
    if(!$document) {
        echo json_encode(['ok' => false, 'message' => 'Documento no encontrado']);
        return;
    }
    
    $document['download_url'] = '../uploads/provider_documents/' . $document['file_path'];
    
    echo json_encode([
        'ok' => true,
        'data' => $document
    ]);
}

// ===================================================================
// ELIMINAR DOCUMENTO
// ===================================================================
function deleteDocument($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    // Obtener info del documento antes de eliminar
    $sql = "SELECT file_path FROM provider_documents WHERE id = $id";
    $result = mysqli_query($conexion, $sql);
    $document = mysqli_fetch_assoc($result);
    
    if(!$document) {
        echo json_encode(['ok' => false, 'message' => 'Documento no encontrado']);
        return;
    }
    
    // Eliminar de base de datos
    $delete_sql = "DELETE FROM provider_documents WHERE id = $id";
    
    if(mysqli_query($conexion, $delete_sql)) {
        // Intentar eliminar archivo físico
        $file_path = '../uploads/provider_documents/' . $document['file_path'];
        if(file_exists($file_path)) {
            unlink($file_path);
        }
        
        echo json_encode([
            'ok' => true,
            'message' => 'Documento eliminado exitosamente'
        ]);
    } else {
        throw new Exception("Error al eliminar documento: " . mysqli_error($conexion));
    }
}

// ===================================================================
// VERIFICAR/APROBAR DOCUMENTO
// ===================================================================
function verifyDocument($conexion, $id_usuario) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_verified = isset($_POST['is_verified']) ? intval($_POST['is_verified']) : 0;
    $notes = isset($_POST['verification_notes']) ? mysqli_real_escape_string($conexion, $_POST['verification_notes']) : '';
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    $sql = "UPDATE provider_documents SET
        is_verified = $is_verified,
        verified_by = " . ($is_verified ? $id_usuario : "NULL") . ",
        verified_at = " . ($is_verified ? "NOW()" : "NULL") . ",
        verification_notes = " . ($notes ? "'$notes'" : "NULL") . "
    WHERE id = $id";
    
    if(mysqli_query($conexion, $sql)) {
        echo json_encode([
            'ok' => true,
            'message' => $is_verified ? 'Documento verificado' : 'Verificación removida'
        ]);
    } else {
        throw new Exception("Error al verificar documento: " . mysqli_error($conexion));
    }
}

// ===================================================================
// HELPER: FORMATEAR TAMAÑO DE ARCHIVO
// ===================================================================
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
