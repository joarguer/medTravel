<?php
session_start();
include("conexion.php");
$fecha	=	date("Y-m-d",time()-18000);
$salida	=	date("H:i:s",time()-18000);
$rasocial = $_SESSION["rasocial"];
$usuario  = $_SESSION["usrlogin"];

mysqli_query($conexion,"DELETE FROM sessiones_activas WHERE usuario = '$usuario'");
//registramos la salida
if($usuario!=''){
	mysqli_query($conexion,"UPDATE visitas SET hora2 = '$salida' WHERE usuario='$usuario' AND hora2 = '00:00:00'");
}
unset($_SESSION["tipoUsuario"]);
unset($_SESSION["usuario"]);
session_destroy();
setcookie("usuario_nombre","",36000);
setcookie("pais","",36000);
$_SESSION["registration_ids"] = $registration_ids;
if($_REQUEST["error"] == "1"){
	header("location:../../index.php?error=1");
	exit();
} else {
	header("location:../../login.php");
	exit();
}
?> 