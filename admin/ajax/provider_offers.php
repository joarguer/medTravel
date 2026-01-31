<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . '/../include/conexion.php');
$devlog = __DIR__ . '/../logs/dev.log';
$req_dump = isset($_REQUEST) ? print_r($_REQUEST, true) : '[]';
$cookie_dump = isset($_COOKIE) ? print_r($_COOKIE, true) : '[]';
if (defined('APP_ENV') && APP_ENV === 'dev') {
    @file_put_contents($devlog, date('Y-m-d H:i:s') . " - provider_offers request: method=" . $_SERVER['REQUEST_METHOD'] . " req=" . substr($req_dump,0,800) . "\n", FILE_APPEND | LOCK_EX);
    // ensure session debug dump (may be empty until require_login_ajax runs)
    @file_put_contents($devlog, date('Y-m-d H:i:s') . " - COOKIES: " . substr($cookie_dump,0,800) . "\n", FILE_APPEND | LOCK_EX);
}
require_login_ajax();
// global error/exception handlers to capture fatal errors in dev log
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($devlog) {
    $msg = date('Y-m-d H:i:s') . " - ERROR [$errno] $errstr in $errfile:$errline\n";
    @file_put_contents($devlog, $msg, FILE_APPEND | LOCK_EX);
});
set_exception_handler(function($e) use ($devlog) {
    $msg = date('Y-m-d H:i:s') . " - EXCEPTION " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    @file_put_contents($devlog, $msg, FILE_APPEND | LOCK_EX);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'server_exception']);
    exit();
});
$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
if (!$provider_id) {
    // debug log: provider_id missing in session
    $sid = session_id();
    $possible = [
        'id_usuario' => isset($_SESSION['id_usuario'])?$_SESSION['id_usuario']:null,
        'id' => isset($_SESSION['id'])?$_SESSION['id']:null,
        'user_id' => isset($_SESSION['user_id'])?$_SESSION['user_id']:null,
        'usuario' => isset($_SESSION['usuario'])?$_SESSION['usuario']:null
    ];
    error_log('provider_offers: FORBIDDEN - no provider_id; session_id=' . $sid . ' keys=' . json_encode($possible));
    // adicional: escribir en log local para depuración en entorno dev
    if (defined('APP_ENV') && APP_ENV === 'dev') {
        $devlog = __DIR__ . '/../logs/dev.log';
        @file_put_contents($devlog, date('Y-m-d H:i:s') . " - FORBIDDEN no provider_id; session_id={$sid}; keys=" . json_encode($possible) . "\n", FILE_APPEND | LOCK_EX);
    }
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
    exit();
}
$tipo = isset($_REQUEST['tipo']) ? $_REQUEST['tipo'] : '';

function json_error($msg, $code = 400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit(); }

function generate_random_token($bytes_length = 6){
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes_length));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($bytes_length));
    }
    $result = '';
    for ($i = 0; $i < $bytes_length; $i++) {
        $result .= chr(mt_rand(0, 255));
    }
    return bin2hex($result);
}

function detect_mime_type($filepath){
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filepath);
    }
    if (function_exists('mime_content_type')) {
        return mime_content_type($filepath);
    }
    return '';
}

if ($tipo === 'list') {
    $sql = "SELECT o.id,o.title,o.price_from,o.currency,o.is_active, sc.name AS service_name, IFNULL(p.name,'') AS provider_name FROM provider_service_offers o JOIN service_catalog sc ON sc.id = o.service_id LEFT JOIN providers p ON p.id = o.provider_id WHERE o.provider_id = ? ORDER BY o.created_at DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row;
    }
    echo json_encode(['ok'=>true,'data'=>$data]);
    exit();
}

if ($tipo === 'get') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_error('INVALID_ID');
    $sql = "SELECT * FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $offer = mysqli_fetch_assoc($res);
    if (!$offer) json_error('NOT_FOUND',404);
    // media
    $mstmt = mysqli_prepare($conexion, "SELECT id,path,sort_order,is_active FROM offer_media WHERE offer_id = ? ORDER BY sort_order ASC, id ASC");
    mysqli_stmt_bind_param($mstmt, 'i', $id);
    mysqli_stmt_execute($mstmt);
    $mres = mysqli_stmt_get_result($mstmt);
    $media = [];
    while ($m = mysqli_fetch_assoc($mres)) $media[] = $m;
    $offer['media'] = $media;
    echo json_encode(['ok'=>true,'data'=>$offer]);
    exit();
}

if ($tipo === 'create' || $tipo === 'update') {
    $allowed = ['service_id','title','description','price_from','currency','is_active'];
    $data = [];
    foreach ($allowed as $k) {
        if (isset($_REQUEST[$k])) $data[$k] = $_REQUEST[$k];
    }
    // validation minimal
    $service_id = isset($data['service_id']) ? (int)$data['service_id'] : 0;
    if (!$service_id) json_error('INVALID_SERVICE');
    $title = isset($data['title']) ? substr(trim($data['title']),0,200) : null;
    $description = isset($data['description']) ? trim($data['description']) : null;
    $price_from = isset($data['price_from']) ? (float)$data['price_from'] : null;
    $currency = isset($data['currency']) ? substr(trim($data['currency']),0,5) : 'USD';
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;

    if ($tipo === 'create') {
        $sql = "INSERT INTO provider_service_offers (provider_id,service_id,title,description,price_from,currency,is_active) VALUES (?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iissdsi', $provider_id, $service_id, $title, $description, $price_from, $currency, $is_active);
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) json_error('DB_ERR:'.mysqli_error($conexion));
        $new_id = mysqli_insert_id($conexion);
        echo json_encode(['ok'=>true,'data'=>['id'=>$new_id]]);
        exit();
    } else {
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if (!$id) json_error('INVALID_ID');
        // ensure belongs to provider
        $chk = mysqli_prepare($conexion, "SELECT id FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, 'ii', $id, $provider_id);
        mysqli_stmt_execute($chk);
        $chkres = mysqli_stmt_get_result($chk);
        if (!mysqli_fetch_assoc($chkres)) json_error('FORBIDDEN',403);
        $sql = "UPDATE provider_service_offers SET service_id=?,title=?,description=?,price_from=?,currency=?,is_active=? WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'issdsii', $service_id, $title, $description, $price_from, $currency, $is_active, $id);
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) json_error('DB_ERR:'.mysqli_error($conexion));
        echo json_encode(['ok'=>true]);
        exit();
    }
}

if ($tipo === 'toggle') {
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    if (!$id) json_error('INVALID_ID');
    $chk = mysqli_prepare($conexion, "SELECT is_active FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, 'ii', $id, $provider_id);
    mysqli_stmt_execute($chk);
    $res = mysqli_stmt_get_result($chk);
    $row = mysqli_fetch_assoc($res);
    if (!$row) json_error('FORBIDDEN',403);
    $new = $row['is_active'] ? 0 : 1;
    $up = mysqli_prepare($conexion, "UPDATE provider_service_offers SET is_active = ? WHERE id = ?");
    mysqli_stmt_bind_param($up, 'ii', $new, $id);
    mysqli_stmt_execute($up);
    echo json_encode(['ok'=>true,'data'=>['is_active'=>$new]]);
    exit();
}

if ($tipo === 'upload_media') {
    $offer_id = isset($_REQUEST['offer_id']) ? (int)$_REQUEST['offer_id'] : 0;
    if (!$offer_id) json_error('INVALID_OFFER');
    // check ownership
    $chk = mysqli_prepare($conexion, "SELECT id FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, 'ii', $offer_id, $provider_id);
    mysqli_stmt_execute($chk);
    $cres = mysqli_stmt_get_result($chk);
    if (!mysqli_fetch_assoc($cres)) json_error('FORBIDDEN',403);

    if (empty($_FILES) || !isset($_FILES['file'])) json_error('NO_FILE');
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) json_error('UPLOAD_ERR');
    if ($f['size'] > 3 * 1024 * 1024) json_error('TOO_LARGE');
    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) json_error('BAD_EXT');
    $mime = detect_mime_type($f['tmp_name']);
    if (!$mime) {
        $ext_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp'
        ];
        $mime = isset($ext_map[$ext]) ? $ext_map[$ext] : '';
    }
    $m_allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $m_allowed)) json_error('BAD_MIME');

    $dir = __DIR__ . '/../../img/offers/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = time() . '_' . generate_random_token(6) . '.' . $ext;
    $dest = $dir . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) json_error('MOVE_ERR');
    $rel = 'img/offers/' . $name;
    $ins = mysqli_prepare($conexion, "INSERT INTO offer_media (offer_id,path,sort_order,is_active) VALUES (?,?,1,1)");
    mysqli_stmt_bind_param($ins, 'is', $offer_id, $rel);
    mysqli_stmt_execute($ins);
    $mid = mysqli_insert_id($conexion);
    echo json_encode(['ok'=>true,'data'=>['path'=>$rel,'id'=>$mid]]);
    exit();
}

json_error('INVALID_ACTION');
