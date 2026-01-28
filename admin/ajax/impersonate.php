<?php
@ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
// Only available in dev and from localhost
if (!defined('APP_ENV')) include __DIR__ . '/../include/conexion.php';
if (defined('APP_ENV') && APP_ENV !== 'dev') { http_response_code(404); exit; }
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1','::1'])) { http_response_code(404); exit; }
session_start();
$uid = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$pid = isset($_GET['provider']) ? (int)$_GET['provider'] : 0;
if ($uid <= 0) { echo json_encode(['ok'=>false,'error'=>'missing_user']); exit; }
$_SESSION['id_usuario'] = $uid;
$_SESSION['id'] = $uid;
$_SESSION['usuario'] = 'impersonated';
if ($pid > 0) $_SESSION['provider_id'] = $pid;
echo json_encode(['ok'=>true,'session'=>['id_usuario'=>$_SESSION['id_usuario'],'provider_id'=>isset($_SESSION['provider_id'])?$_SESSION['provider_id']:null]]);
