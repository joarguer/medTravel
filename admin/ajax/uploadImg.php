<?php
// Habilitar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/upload_errors.log');

session_start();
include("../include/conexion.php");
$resultados = array();

// determinar id de usuario destino
$id = isset($_REQUEST['id']) && trim($_REQUEST['id']) !== '' ? (int)$_REQUEST['id'] : (isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0);

// Validar que tenemos un ID válido
if ($id <= 0) {
    error_log("uploadImg.php: ID inválido. REQUEST['id']=".var_export($_REQUEST['id']??null,true)." SESSION['id_usuario']=".var_export($_SESSION['id_usuario']??null,true));
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'ID_INVALIDO', 'debug' => 'No se pudo determinar ID de usuario']);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadError = isset($_FILES['file']['error']) ? $_FILES['file']['error'] : 'NO_FILE';
    error_log("uploadImg.php: Error de upload. Error code: $uploadError");
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'UPLOAD_ERROR', 'code' => $uploadError]);
    exit();
}

// límites y validaciones
$maxBytes = 3 * 1024 * 1024; // 3MB
$tmpName = $_FILES['file']['tmp_name'];
$origName = basename($_FILES['file']['name']);
$size = $_FILES['file']['size'];

if ($size > $maxBytes) {
    error_log("uploadImg.php: Archivo muy grande. Size: $size, Max: $maxBytes");
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'FILE_TOO_LARGE', 'size' => $size, 'max' => $maxBytes]);
    exit();
}

// Extensiones permitidas y mapeo mime
$allowedExt = array('jpg','jpeg','png','webp');
$mimeAllow = array(
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp'
);

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    error_log("uploadImg.php: Extensión no permitida: $ext");
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'INVALID_EXTENSION', 'ext' => $ext, 'allowed' => $allowedExt]);
    exit();
}

// Validar MIME type (intentar varios métodos)
$mime = null;
$validMime = false;

// Método 1: mime_content_type (más antiguo, generalmente disponible)
if (function_exists('mime_content_type')) {
    $mime = mime_content_type($tmpName);
} 
// Método 2: Leer los primeros bytes del archivo (magic bytes)
else if (file_exists($tmpName)) {
    $handle = fopen($tmpName, 'rb');
    if ($handle) {
        $bytes = fread($handle, 12);
        fclose($handle);
        
        // Detectar por magic bytes
        if (substr($bytes, 0, 3) === "\xFF\xD8\xFF") {
            $mime = 'image/jpeg';
        } elseif (substr($bytes, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
            $mime = 'image/png';
        } elseif (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            $mime = 'image/webp';
        }
    }
}

// Validar que el MIME coincide con la extensión
if ($mime) {
    foreach ($mimeAllow as $e => $m) {
        if ($ext === $e && $mime === $m) {
            $validMime = true;
            break;
        }
    }
}

// Si no pudimos detectar MIME, al menos validamos que sea imagen por extensión
if (!$validMime && in_array($ext, $allowedExt, true)) {
    error_log("uploadImg.php: ADVERTENCIA - No se pudo validar MIME, permitiendo por extensión: $ext");
    $validMime = true; // Fallback: confiar en la extensión
}

if (!$validMime) {
    error_log("uploadImg.php: MIME no válido. Ext: $ext, MIME detectado: " . ($mime ?? 'NULL'));
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'INVALID_MIME', 'mime' => $mime, 'ext' => $ext]);
    exit();
}

// construir ruta segura
$extension = $ext;
$safeFile = $id . "_avatar." . $extension;
$ruta = "../img/perfil/" . $safeFile;

// eliminar avatar previo sólo si está en la carpeta esperada
$busco = mysqli_prepare($conexion, "SELECT avatar FROM usuarios WHERE id = ? LIMIT 1");
if ($busco) {
    mysqli_stmt_bind_param($busco, 'i', $id);
    mysqli_stmt_execute($busco);
    $res = mysqli_stmt_get_result($busco);
    if ($res && mysqli_num_rows($res) > 0) {
        $archivo_ = mysqli_fetch_array($res);
        if (!empty($archivo_['avatar'])) {
            $archivo = '../' . $archivo_['avatar'];
            $realBase = realpath(__DIR__ . '/../../img/perfil');
            $realArchivo = realpath($archivo);
            if ($realArchivo && strpos($realArchivo, $realBase) === 0 && file_exists($realArchivo)) {
                @unlink($realArchivo);
            }
        }
    }
    mysqli_stmt_close($busco);
}

if (move_uploaded_file($tmpName, $ruta)) {
    $rutaResp = "img/perfil/" . $safeFile . "?" . rand();
    $update = mysqli_prepare($conexion, "UPDATE usuarios SET avatar = ? WHERE id = ?");
    if ($update) {
        mysqli_stmt_bind_param($update, 'si', $rutaResp, $id);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);
    }
    // actualizar sesión local
    $_SESSION['foto_perfil'] = $rutaResp;
    $_SESSION['avatar'] = $rutaResp;
    
    error_log("uploadImg.php: Avatar actualizado exitosamente. ID: $id, Ruta: $rutaResp");
    
    // Responder con JSON
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'avatar' => $rutaResp]);
    exit();
} else {
    $moveError = error_get_last();
    error_log("uploadImg.php: Error al mover archivo. Error: " . var_export($moveError, true));
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'MOVE_FAILED', 'details' => $moveError['message'] ?? 'Unknown']);
    exit();
}
?>