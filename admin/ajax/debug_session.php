<?php
@ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
// Only available in dev and from localhost
if (!defined('APP_ENV')) include __DIR__ . '/../include/conexion.php';
if (defined('APP_ENV') && APP_ENV !== 'dev') { http_response_code(404); exit; }
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1','::1'])) { http_response_code(404); exit; }
include(__DIR__ . '/../include/conexion.php');
require_login_ajax();
$keys = ['id_usuario','id','user_id','usuario','provider_id','usrlogin','nombre_usuario'];
$out = [];
foreach($keys as $k) $out[$k] = isset($_SESSION[$k]) ? $_SESSION[$k] : null;
echo json_encode(['ok'=>true,'session'=>$out]);
exit;
