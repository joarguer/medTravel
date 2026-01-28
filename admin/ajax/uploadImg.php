<?php
session_start();
include("../include/conexion.php");
$resultados = array();

// determinar id de usuario destino
$id = isset($_REQUEST['id']) && trim($_REQUEST['id']) !== '' ? (int)$_REQUEST['id'] : (isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0);

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo null;
    exit();
}

// límites y validaciones
$maxBytes = 3 * 1024 * 1024; // 3MB
$tmpName = $_FILES['file']['tmp_name'];
$origName = basename($_FILES['file']['name']);
$size = $_FILES['file']['size'];

if ($size > $maxBytes) {
    echo false;
    exit();
}

// Extensiones permitidas y mapeo mime
$allowedExt = array('jpg','jpeg','png','webp');
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmpName);
finfo_close($finfo);

$mimeAllow = array(
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp'
);

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    echo false;
    exit();
}

// comprobar que mime coincide con extension permitida
$validMime = false;
foreach ($mimeAllow as $e => $m) {
    if ($ext === $e && $mime === $m) {
        $validMime = true;
        break;
    }
}

if (!$validMime) {
    echo false;
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
    echo $rutaResp;
    exit();
} else {
    echo null;
    exit();
}
?>