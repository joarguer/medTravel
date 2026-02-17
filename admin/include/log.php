<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Mobile_Detect may be in the project's root `include/` directory.
$mobileDetectPath = __DIR__ . '/../../include/Mobile_Detect.php';
if (file_exists($mobileDetectPath)) {
    include $mobileDetectPath;
}
include(__DIR__ . '/conexion.php');
require_once __DIR__ . '/roles.php';

// Helper seguro para acceso a arrays
function v($arr, $key, $default = '') {
    return (is_array($arr) && array_key_exists($key, $arr) && $arr[$key] !== null)
        ? $arr[$key]
        : $default;
}

// Defensa de conexión a base de datos
if (!$conexion) {
    error_log('DB connection is null in log.php');
    header("location:../../login.php?error=db");
    exit();
}
function sanear_string($string){

    $string = trim($string);

    $string = str_replace(
        array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'),
        array('A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A'),
        $string
    );

    $string = str_replace(
        array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'),
        array('E', 'E', 'E', 'E', 'E', 'E', 'E', 'E'),
        $string
    );

    $string = str_replace(
        array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'),
        array('I', 'I', 'I', 'I', 'I', 'I', 'I', 'I'),
        $string
    );

    $string = str_replace(
        array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'),
        array('O', 'O', 'O', 'O', 'O', 'O', 'O', 'O'),
        $string
    );

    $string = str_replace(
        array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'),
        array('U', 'U', 'U', 'U', 'U', 'U', 'U', 'U'),
        $string
    );

    $string = str_replace(
        array('ñ', 'Ñ', 'ç', 'Ç'),
        array('Ñ', 'Ñ', 'c', 'C'),
        $string
    );

    //Esta parte se encarga de eliminar cualquier caracter extraño
    $string = str_replace(
        array("\\", "¨", "º", "~",
             "#", "|", "!", "\"",
             "·", "$", "%", "&", "/",
             "(", ")", "?", "'", "¡",
             "¿", "[", "^", "`", "]",
             "+", "}", "{", "¨", "´",
             ">", "< ", ";", ",", ":"),
        '',
        $string
    );


    return $string;
}

function login_fetch_medical_provider_name($conexion, $provider_id) {
    $stmt = mysqli_prepare($conexion, "SELECT name FROM providers WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $name = null;
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = $row['name'];
    }
    mysqli_stmt_close($stmt);
    return $name;
}

function login_fetch_active_service_provider_name($conexion, $service_provider_id) {
    $stmt = mysqli_prepare($conexion, "SELECT provider_name FROM service_providers WHERE id = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $service_provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $name = null;
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = $row['provider_name'];
    }
    mysqli_stmt_close($stmt);
    return $name;
}
// Recuperar y sanear input
$usrname  = isset($_POST["username"]) ? sanear_string($_POST["username"]) : '';
$password = isset($_POST["password"]) ? $_POST["password"] : '';

$sql_user = "SELECT * FROM usuarios WHERE usuario = '".mysqli_real_escape_string($conexion, $usrname)."' AND activo = '1'";
$busca_usua = mysqli_query($conexion, $sql_user);
if (!$busca_usua) {
    error_log('DB error: '.mysqli_error($conexion));
    header("location:../../login.php?error=db");
    exit();
}
//empresa
if (mysqli_num_rows($busca_usua) > 0) {
    $fil = mysqli_fetch_array($busca_usua);
    
    // NUEVO: Verificar contraseña (soporta hash antiguo SHA512 y nuevo bcrypt)
    $password_valido = false;
    $stored_password = v($fil,'password','');
    
    // Método 1: Nuevo sistema con bcrypt (password_hash/password_verify)
    if (substr($stored_password, 0, 4) === '$2y$' || substr($stored_password, 0, 4) === '$2a$') {
        // Es un hash bcrypt
        $password_valido = password_verify($password, $stored_password);
    } 
    // Método 2: Sistema antiguo con SHA512 + token
    else {
        $password_valido = ($stored_password === hash('sha512', v($fil,'token','').$password));
    }
    
    if ($password_valido) {
        //cREAMOS USUARIO Y CLAVE PARA ACCESO A DOC
        $rasocial = v($fil,'empresa','');
        $role_id_val = (isset($fil['role_id']) && is_numeric($fil['role_id'])) ? intval($fil['role_id']) : normalize_role_value(v($fil, 'rol', ''));
        $is_global_admin_role = ($role_id_val === ROLE_ADMIN || $role_id_val === ROLE_ADMINISTRATIVE || v($fil, 'ppal', '') === '1');
        $is_medical_role = in_array($role_id_val, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true);
        $is_complementary_role = ($role_id_val === ROLE_COMPLEMENTARY_ADMIN);
        $provider_id_val = !empty($fil['provider_id']) ? intval($fil['provider_id']) : 0;
        $service_provider_id_val = !empty($fil['service_provider_id']) ? intval($fil['service_provider_id']) : 0;

        if ($is_medical_role) {
            if ($provider_id_val <= 0) {
                header("location:../../login.php?error=empresa");
                exit();
            }
            $provider_name = login_fetch_medical_provider_name($conexion, $provider_id_val);
            if ($provider_name === null || $provider_name === '') {
                header("location:../../login.php?error=empresa");
                exit();
            }
            if ($rasocial === '') {
                $rasocial = $provider_name;
            }
            $fil['provider_id'] = $provider_id_val;
            $fil['service_provider_id'] = null;
        } elseif ($is_complementary_role) {
            if ($service_provider_id_val <= 0) {
                header("location:../../login.php?error=empresa");
                exit();
            }
            $service_provider_name = login_fetch_active_service_provider_name($conexion, $service_provider_id_val);
            if ($service_provider_name === null || $service_provider_name === '') {
                header("location:../../login.php?error=empresa");
                exit();
            }
            if ($rasocial === '') {
                $rasocial = $service_provider_name;
            }
            $fil['service_provider_id'] = $service_provider_id_val;
            $fil['provider_id'] = null;
        }

        $rasocial_esc = mysqli_real_escape_string($conexion, $rasocial);
        
        // Resolver contexto "empresa virtual" según dominio del usuario.
        if (empty($rasocial) && !empty($fil['provider_id'])) {
            $query = mysqli_query($conexion, "SELECT id, name as rasocial, name as nit, 0 as activo, '' as logo FROM providers WHERE id = ".(int)$fil['provider_id']." LIMIT 1");
        } elseif (empty($rasocial) && !empty($fil['service_provider_id'])) {
            $query = mysqli_query($conexion, "SELECT id, provider_name as rasocial, provider_name as nit, 0 as activo, '' as logo FROM service_providers WHERE id = ".(int)$fil['service_provider_id']." AND is_active = 1 LIMIT 1");
        } else {
            $query = mysqli_query($conexion, "SELECT * FROM empresas WHERE rasocial = '".$rasocial_esc."' LIMIT 1");
        }
        
        if (!$query) {
            error_log('DB error: '.mysqli_error($conexion));
            header("location:../../login.php?error=query");
            exit();
        }
        if (mysqli_num_rows($query) == 0) {
            // Para admin global y dominios provider/complementary, permitir empresa virtual.
            if ($is_global_admin_role || $is_medical_role || $is_complementary_role || empty($rasocial)) {
                $fila = [
                    'id' => 1,
                    'rasocial' => v($fil,'nombre','Usuario'),
                    'nit' => '000000000',
                    'activo' => 0,
                    'logo' => ''
                ];
            } else {
                header("location:../../login.php?error=empresa");
                exit();
            }
        } else {
            $fila = mysqli_fetch_array($query);
        }
        $_SESSION['nitEmpresa'] = v($fila,'nit','');
        if (v($fila,'activo',0) == 1) {
            header("location:../../index.php?usuario=nulo1");
            exit();
        }
        //-----------------------------------------
        setcookie("usuario_nombre", v($fila,'rasocial',''));
        if (v($fil,'ppal','')=="1") {
            $_SESSION["ppal"]='ppal';
        } else {
            $_SESSION["ppal"]='agente';
        }
        $_SESSION['chatuser']		=	v($fil,'id',0);
        $_SESSION['usrlogin']		=	v($fil,'usrlogin','');
        $_SESSION["tipo"]			=	'empresa';
        $fecha						=	date("Y-m-d", time()-18000);
        $time						=	date("H:i:s", time()-18000);
        $id						=	v($fila,'id',0);
        $usrlogin				=	v($fil,'usrlogin','');
        $usu					=	v($fila,'rasocial','');
        $_SESSION["usrlogin"]		=	$usrlogin;
        
        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $ip	= $_SERVER["HTTP_CLIENT_IP"];
        } else if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip	= $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_X_FORWARDED"])) {
            $ip	= $_SERVER["HTTP_X_FORWARDED"];
        } else if (isset($_SERVER["HTTP_FORWARDED_FOR"])) {
            $ip	= $_SERVER["HTTP_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_FORWARDED"])) {
            $ip	= $_SERVER["HTTP_FORWARDED"];
        } else {
            $ip	= $_SERVER["REMOTE_ADDR"];
        }
        
        $detect = '';// new Mobile_Detect;
        $insert_vis_sql = "INSERT INTO visitas(fecha,hora,hora2,visitante, usuario, dispositivo, ip) VALUES('".mysqli_real_escape_string($conexion,$fecha)."', '".mysqli_real_escape_string($conexion,$time)."', NULL, '".mysqli_real_escape_string($conexion,$usu)."', '".mysqli_real_escape_string($conexion,$usrlogin)."', '".mysqli_real_escape_string($conexion,$detect)."', '".mysqli_real_escape_string($conexion,$ip)."')";
        $insert_vis = mysqli_query($conexion, $insert_vis_sql);
        if (!$insert_vis) {
            error_log('DB error (visitas insert): '.mysqli_error($conexion));
        }
        //, INET_NTOA(ip) AS ips
        $ver_sessiones_activas_sql = "SELECT * FROM sessiones_activas WHERE visitante='".mysqli_real_escape_string($conexion, v($fila,'rasocial',''))."' AND usuario='".mysqli_real_escape_string($conexion, v($fil,'usrlogin',''))."'";
        $ver_sessiones_activas = mysqli_query($conexion, $ver_sessiones_activas_sql);
        $sessiones_activas = [];
        if ($ver_sessiones_activas && mysqli_num_rows($ver_sessiones_activas) > 0) {
            $sessiones_activas = mysqli_fetch_array($ver_sessiones_activas);
        }
        $_SESSION["id_empresa"]		=   v($fila,'id',0);
        $_SESSION["id_usuario"]		=   v($fil,'id',0);
        // Mapear user -> provider (si existe) y guardar provider_id en sesión
        // NUEVO: Leer provider_id directamente de la tabla usuarios
        if (!empty($fil['provider_id']) && (int)$fil['provider_id'] > 0) {
            $_SESSION['provider_id'] = (int)$fil['provider_id'];
        } else {
            // Fallback: buscar en tabla provider_users (sistema antiguo)
            $provider_id = null;
            if (isset($conexion) && is_int((int)$_SESSION["id_usuario"])) {
                $stmt = mysqli_prepare($conexion, "SELECT provider_id FROM provider_users WHERE user_id = ? LIMIT 1");
                if ($stmt) {
                    $uid = (int) $_SESSION["id_usuario"];
                    mysqli_stmt_bind_param($stmt, "i", $uid);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $p_id);
                    if (mysqli_stmt_fetch($stmt)) {
                        $_SESSION['provider_id'] = (int) $p_id;
                    } else {
                        if (isset($_SESSION['provider_id'])) unset($_SESSION['provider_id']);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        // Ownership complementario: service_provider_id -> service_providers.id
        if (!empty($fil['service_provider_id']) && (int)$fil['service_provider_id'] > 0) {
            $_SESSION['service_provider_id'] = (int)$fil['service_provider_id'];
        } else {
            if (isset($_SESSION['service_provider_id'])) unset($_SESSION['service_provider_id']);
        }
        $_SESSION["nombre_usuario"]	=   v($fil,'nombre','');
        $_SESSION["usuario"]		=   v($fil,'usuario','');
        $_SESSION["token"]		    =   v($fil,'token','');
        $_SESSION["rasocial"]		=   v($fila,'rasocial','');
        $_SESSION["nit"]			=   v($fila,'nit','');
        $_SESSION['usrlogin']		=   v($fil,'usuario','');
        $_SESSION['logo']			=   v($fila,'logo','');

        $anio = date("Y",time()-18000);
        $_SESSION["anio_bd"] = 'ejemagic_admin_'.$anio;
        
        $_SESSION['avatar']    =   v($fil,'avatar','');
        $_SESSION['nombre_perfil']  =   v($fil,'nombre','');
        $_SESSION['rol']            =   v($fil,'rol','');
        $_SESSION['role_id']        =   $role_id_val;
        $_SESSION['ppal']           =   v($fil,'ppal','');
        $_SESSION["usuario_cargo"]  =   v($fil,'cargo','');
        $_SESSION["usuario_email"]	=   v($fil,'email','');
        $_SESSION["usuario_ciudad"]	=   v($fil,'ciudad','');
        $_SESSION["usuario_telefono"]	=   v($fil,'telefono','');
        $_SESSION["usuario_celular"]	=   v($fil,'celular','');
        $visitante = v($fila,'rasocial','');
        //echo $sessiones_activas["visitante"].' != '.$fila["rasocial"].' && '.$sessiones_activas["usuario"].' != '.$fil["id"].' && '.$sessiones_activas["ips"].' != '.$ip;
        //exit();
        $sess_user = v($sessiones_activas,'usuario','');
        $sess_ips = v($sessiones_activas,'ips','');
        if($sess_user != v($fil,'id','') && $sess_ips != $ip) {
            mysqli_query($conexion,"INSERT INTO sessiones_activas(`fecha`, `hora`, `visitante`, `usuario`, `ip`, `latitud`, `longitud`, `cobrador`, `hora2`) VALUES('".mysqli_real_escape_string($conexion,$fecha)."', '".mysqli_real_escape_string($conexion,$time)."', '".mysqli_real_escape_string($conexion,$visitante)."', '".mysqli_real_escape_string($conexion,$usrlogin)."', '".mysqli_real_escape_string($conexion,$ip)."', '0', '0', '0', '00:00:00')");
            if(v($fil,'cambio_password',0) == 1){
                header("location:../index.php#cambio_password");
                exit();
            } else {
                header("location:../index.php");
                exit();
            }
        } else {
            header("location:../../login.php?session=error");
            exit();
        }
    } else{
        header("location:../../login.php?usuario=nulo2");
        exit();
    }
} else {
    header("location:../../login.php?usuario=nulo");
    exit();
}
?>
