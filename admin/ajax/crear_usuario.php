<?php
session_start();
include("../include/include.php");
$resultados = array();
$tipo = isset($_REQUEST["tipo"]) ? $_REQUEST["tipo"] : '';
$empresa = isset($_REQUEST['empresa']) ? $_REQUEST['empresa'] : '';
$token 	    = 	md5(uniqid(rand(), true));
$email_req = isset($_REQUEST["email"]) ? $_REQUEST["email"] : '';
$password  	= 	$email_req !== '' ? hash('sha512', $token.$email_req) : '';
if($tipo == 'crear_usuario'){
    if (!is_role_admin_session() && !user_can('users.create')) {
        http_response_code(403);
        $resultados['error'] = 'forbidden';
        echo json_encode($resultados);
        return;
    }
    //`fecha`, `nombre`, `id_empresa`, `empresa`, `email`, `celular`, `tipo`, `cedula`, `usuario`, `password`, `token`, `avatar`, `cambio_password`, `estado`, `activo`, `rol`, `ppal`
    $fecha = date("Y-m-d",time()-18000);
        // validar unicidad de usuario (email usado como usuario)
        $email = isset($_REQUEST['email']) ? trim($_REQUEST['email']) : '';
        $empresa_id = isset($_REQUEST['empresa']) ? (int)$_REQUEST['empresa'] : 0;
        $rasocial = isset($_REQUEST['rasocial']) ? trim($_REQUEST['rasocial']) : '';
        $nombre = isset($_REQUEST['nombre']) ? trim($_REQUEST['nombre']) : '';
        $apellido = isset($_REQUEST['apellido']) ? trim($_REQUEST['apellido']) : '';
        $fullnombre = trim($nombre . ' ' . $apellido);
        $celular = isset($_REQUEST['celular']) ? trim($_REQUEST['celular']) : '';
        $tipo_u = isset($_REQUEST['tipo']) ? trim($_REQUEST['tipo']) : '';
        $cedula = isset($_REQUEST['cedula']) ? trim($_REQUEST['cedula']) : '';
        $rol = isset($_REQUEST['role']) ? trim($_REQUEST['role']) : (isset($_REQUEST['rol']) ? trim($_REQUEST['rol']) : '');
        $ppal = isset($_REQUEST['ppal']) ? (int)$_REQUEST['ppal'] : 0;
        $provider_id = isset($_REQUEST['provider_id']) && $_REQUEST['provider_id'] !== '' ? (int)$_REQUEST['provider_id'] : null;
        $provider_session_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : null;

        // normalize role_id if provided numeric
        $role_id_val = null;
        if (is_numeric($rol)) {
            $role_id_val = intval($rol);
        }

        // If user is provider-scoped, force role and provider_id to session context
        if (!is_role_admin_session() && $provider_session_id) {
            $provider_id = $provider_session_id;
            $role_id_val = ROLE_PROVIDER;
            $rol = (string)ROLE_PROVIDER;
        }

        // Ajustar campos según rol proveedor
        if ($role_id_val === ROLE_PROVIDER || $rol === (string)ROLE_PROVIDER) {
            if ($provider_id === null) {
                $resultados['error'] = 'provider_required';
                $resultados['status'] = null;
                echo json_encode($resultados);
                return;
            }
            // Si rasocial viene vacío, obtenerlo del proveedor seleccionado
            if ($rasocial === '') {
                $pquery = mysqli_prepare($conexion, "SELECT name FROM providers WHERE id = ? LIMIT 1");
                if ($pquery) {
                    mysqli_stmt_bind_param($pquery, 'i', $provider_id);
                    mysqli_stmt_execute($pquery);
                    $pres = mysqli_stmt_get_result($pquery);
                    if ($pres && $prow = mysqli_fetch_assoc($pres)) {
                        $rasocial = $prow['name'];
                    }
                    mysqli_stmt_close($pquery);
                }
            }
            // Empresa texto se rellena con el nombre del proveedor
        } else {
            // No proveedor: asegúrese de limpiar provider_id
            $provider_id = null;
        }

        // comprobar usuario único
        $sql_check = "SELECT id FROM usuarios WHERE usuario = ? LIMIT 1";
        if ($stmtc = mysqli_prepare($conexion, $sql_check)) {
            mysqli_stmt_bind_param($stmtc, 's', $email);
            mysqli_stmt_execute($stmtc);
            $resc = mysqli_stmt_get_result($stmtc);
            if ($resc && mysqli_num_rows($resc) > 0) {
                $resultados['error'] = 'usuario_existente';
                $resultados['status'] = null;
                mysqli_stmt_close($stmtc);
                // responder y salir sin exit() que corte el flujo inesperadamente
                echo json_encode($resultados);
                return;
            }
            mysqli_stmt_close($stmtc);
        }

        // Preparar inserción con prepared statement
        $avatar_default = 'img/perfil/default.png';
        $cambio_password = 1;
        $estado = 1;
        $activo = 1;

        // Detect if usuarios table has role_id column
        $has_role_id = false;
        $colcheck = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE 'role_id'");
        if ($colcheck && mysqli_num_rows($colcheck) > 0) $has_role_id = true;

        // Ajustar INSERT a las columnas reales de la tabla `usuarios` en esta instalación
        if ($has_role_id) {
            $sql_ins = "INSERT INTO usuarios (usuario, password, avatar, nombre, activo, token, empresa, ppal, usrlogin, rol, role_id, cargo, email, ciudad, telefono, celular, cambio_password, provider_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        } else {
            $sql_ins = "INSERT INTO usuarios (usuario, password, avatar, nombre, activo, token, empresa, ppal, usrlogin, rol, cargo, email, ciudad, telefono, celular, cambio_password, provider_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        }
        if ($stmin = mysqli_prepare($conexion, $sql_ins)) {
            $usuario_val = $email; // campo `usuario`
            $usrlogin_val = $email; // campo `usrlogin`
            $cargo_val = isset($_REQUEST['cargo']) ? trim($_REQUEST['cargo']) : '';
            $ciudad_val = isset($_REQUEST['ciudad']) ? trim($_REQUEST['ciudad']) : '';
            $telefono_val = isset($_REQUEST['telefono']) ? trim($_REQUEST['telefono']) : '';
            // tipos por columna: usuario(s), password(s), avatar(s), nombre(s), activo(i), token(s), empresa(s), ppal(i), usrlogin(s), rol(s), [role_id(i)?], cargo(s), email(s), ciudad(s), telefono(s), celular(s), cambio_password(i), provider_id(i)
            if ($has_role_id) {
                $types = 'ssssissississsssii'; // added one 'i' for role_id after rol
                mysqli_stmt_bind_param($stmin, $types, $usuario_val, $password, $avatar_default, $fullnombre, $activo, $token, $rasocial, $ppal, $usrlogin_val, $rol, $role_id_val, $cargo_val, $email, $ciudad_val, $telefono_val, $celular, $cambio_password, $provider_id);
            } else {
                $types = 'ssssississsssssii';
                mysqli_stmt_bind_param($stmin, $types, $usuario_val, $password, $avatar_default, $fullnombre, $activo, $token, $rasocial, $ppal, $usrlogin_val, $rol, $cargo_val, $email, $ciudad_val, $telefono_val, $celular, $cambio_password, $provider_id);
            }
        }
}

if($tipo == 'rol'){
    $id_usuario = $_REQUEST['id_usuario'];
    $rol        = $_REQUEST['rol'];
    // update both rol and role_id if available
    $role_id_val = is_numeric($rol) ? intval($rol) : null;
    $colcheck = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE 'role_id'");
    if ($colcheck && mysqli_num_rows($colcheck) > 0 && $role_id_val !== null) {
        $busca = mysqli_query($conexion, "UPDATE usuarios SET rol = '".mysqli_real_escape_string($conexion,$rol)."', role_id = {$role_id_val} WHERE id = '".intval($id_usuario)."'");
    } else {
        $busca = mysqli_query($conexion, "UPDATE usuarios SET rol = '".mysqli_real_escape_string($conexion,$rol)."' WHERE id = '".intval($id_usuario)."'");
    }
    if (mysqli_error($conexion)) {
        $resultados["status"]   = null;
        $resultados['error']    = mysqli_error($conexion);
    } else {
        $resultados["status"]   = true;
    }
}

if($tipo == 'crear_avatar'){
    $id = $_REQUEST['id_usuario'];
    $ruta = "../img/perfil/".$id."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT avatar FROM usuarios WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            //buscamos la ruta del archivo existente para eliminar
            $archivo_ = mysqli_fetch_array($busco);
            $archivo = '../'.$archivo_['imagen'];
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/perfil/".$id."_".$_FILES['file']['name']."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE usuarios SET avatar = '$ruta' WHERE id='".$id."'");
            if($_SESSION['id_usuario'] == $id){
                $_SESSION['foto_perfil']    = $ruta;
            }
            $resultados["status"]       = true;
            $resultados["ruta"]         = $ruta;
        } else {
            $resultados["status"]       = null;
        }
    } else {
        $resultados["status"]       = false;
    }
}

if($_REQUEST['tipo'] == 'crear_password'){
    $id = $_REQUEST['id_usuario'];
    $usrclave2 	= 	md5(uniqid(rand(), true));
    $usrclave  	= 	hash('sha512', $usrclave2.$_REQUEST["pass1"]);
    $usuario    =   $_REQUEST["usuarios"];
    mysqli_query($conexion, "UPDATE usuarios 
                                SET `password`  = '$usrclave', 
                                    `token` = '$usrclave2',
                                    `cambio_password` = 1
                              WHERE id = '$id'");
    if (mysqli_error($conexion)) {
        $resultados["status"]   = null;
        $resultados['error']    = mysqli_error($conexion);
    } else {
        $resultados["status"]   = true;
    }
}

if($_REQUEST["tipo"] == 'eliminar'){
    $busca = mysqli_query($conexion, "DELETE FROM certificado WHERE id_usuario = '".$_REQUEST["id"]."'");
    if (mysqli_error($conexion)) {
        $resultados["status"]   = null;
    } else {
        $resultados["status"]   = true;
    }
}

if($_REQUEST["tipo"] == 'listar_empresas'){
    $busca = mysqli_query($conexion, "SELECT * FROM empresas WHERE estado = 1");
    if (mysqli_error($conexion)) {
        $resultados["status"]   = null;
    } else {
        $resultados["status"]   = true;
        while($row = mysqli_fetch_array($busca)){
            $resultados["empresas"][] = $row;
        }
    }
}

echo json_encode($resultados);
?> 