<?php
require_once __DIR__ . '/session_security.php';
medtravel_session_start();
include("conexion.php");
$fecha	=	date("Y-m-d",time()-18000);
$salida	=	date("H:i:s",time()-18000);
$rasocial = isset($_SESSION["rasocial"]) ? $_SESSION["rasocial"] : '';
$usuario  = isset($_SESSION["usrlogin"]) ? $_SESSION["usrlogin"] : '';

mysqli_query($conexion,"DELETE FROM sessiones_activas WHERE usuario = '$usuario'");
//registramos la salida
if($usuario!=''){
	mysqli_query($conexion,"UPDATE visitas SET hora2 = '$salida' WHERE usuario='$usuario' AND hora2 = '00:00:00'");
}
unset($_SESSION["tipoUsuario"]);
unset($_SESSION["usuario"]);
medtravel_session_destroy();
setcookie("usuario_nombre","",36000);
setcookie("pais","",36000);
$errorParam = isset($_REQUEST["error"]) ? (string)$_REQUEST["error"] : '';
if($errorParam == "1"){
	header("location:../../index.php?error=1");
	exit();
} else {
	header("location:../../login.php");
	exit();
}
?> 
