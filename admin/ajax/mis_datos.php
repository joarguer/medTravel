<?php
session_start();
include("../include/conexion.php");
$resultados = array();
$tipo = isset($_REQUEST["tipo"]) ? $_REQUEST["tipo"] : '';
$empresa = isset($_SESSION['rasocial']) ? $_SESSION['rasocial'] : '';

if($tipo == 'editar_usuario'){
    // Fecha para registros si se requiere
    $fecha = date("Y-m-d",time()-18000);
    $campo = isset($_REQUEST["campo"]) ? $_REQUEST["campo"] : '';
    $valor = isset($_REQUEST["valor"]) ? $_REQUEST["valor"] : '';
    $id = isset($_SESSION["id_usuario"]) ? (int)$_SESSION["id_usuario"] : 0;

    // Whitelist estricta de campos editables
    $allowed = array('nombre','email','ciudad','telefono','celular','cargo','avatar');
    if (!in_array($campo, $allowed, true)) {
        $resultados['ok'] = false;
        echo json_encode($resultados);
        exit();
    }

    // Prepared statement para evitar SQL injection
    $sql = "UPDATE usuarios SET $campo = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($conexion, $sql)) {
        mysqli_stmt_bind_param($stmt, 'si', $valor, $id);
        $exec = mysqli_stmt_execute($stmt);
        if ($exec === false) {
            $resultados['error'] = mysqli_stmt_error($stmt);
            $resultados['status'] = null;
        } else {
            $resultados['status'] = true;
            $resultados['id'] = $id;
        }
        mysqli_stmt_close($stmt);
    } else {
        $resultados['error'] = mysqli_error($conexion);
        $resultados['status'] = null;
    }
}

if($tipo == 'valida_pass'){
    $usrlogin = isset($_SESSION["usrlogin"]) ? $_SESSION["usrlogin"] : '';
    $pass = isset($_REQUEST["password_actual"]) ? $_REQUEST["password_actual"] : '';
    $token = isset($_SESSION["token"]) ? $_SESSION["token"] : '';
    //decodifico la contraseña
    $hash = hash('sha512', $token.$pass);
    $sql = "SELECT id FROM usuarios WHERE `usuario` = ? AND `password` = ? LIMIT 1";
    if ($stmt = mysqli_prepare($conexion, $sql)) {
        mysqli_stmt_bind_param($stmt, 'ss', $usrlogin, $hash);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) > 0) {
            $fila = mysqli_fetch_array($res);
            $resultados["status"] = $fila["id"];
        } else{
            $resultados["status"] = 0;
            $resultados['usuario'] = $usrlogin;
            $resultados['token'] = $token;
            $resultados['pass'] = $hash;
        }
        mysqli_stmt_close($stmt);
    } else {
        $resultados['status'] = 0;
    }
}

if($tipo == 'cambia_pass'){
    $usuario = isset($_REQUEST["usuario"]) && $_REQUEST["usuario"] !== '' ? $_REQUEST["usuario"] : (isset($_SESSION["usrlogin"]) ? $_SESSION["usrlogin"] : '');
    $pass = isset($_REQUEST["pass1"]) ? $_REQUEST["pass1"] : '';
    $token = isset($_SESSION["token"]) ? $_SESSION["token"] : '';
    $id_usuario = isset($_SESSION["id_usuario"]) ? (int)$_SESSION["id_usuario"] : 0;
    //decodifico la contraseña
    $hash = hash('sha512', $token.$pass);
    $sql = "UPDATE usuarios SET `usuario` = ?, `password` = ? WHERE `id` = ?";
    if ($stmt = mysqli_prepare($conexion, $sql)) {
        mysqli_stmt_bind_param($stmt, 'ssi', $usuario, $hash, $id_usuario);
        $exec = mysqli_stmt_execute($stmt);
        if ($exec === false) {
            $resultados['error'] = mysqli_stmt_error($stmt);
            $resultados['status'] = false;
        } else {
            $resultados['status'] = true;
        }
        mysqli_stmt_close($stmt);
    } else {
        $resultados['error'] = mysqli_error($conexion);
        $resultados['status'] = false;
    }
}

echo json_encode($resultados);
?>