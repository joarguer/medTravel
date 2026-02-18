<?php
require_once __DIR__ . '/session_security.php';
medtravel_session_start();
// Mobile_Detect may be in the project's root `include/` directory.
$mobileDetectPath = __DIR__ . '/../../include/Mobile_Detect.php';
if (file_exists($mobileDetectPath)) {
    include $mobileDetectPath;
}
include(__DIR__ . '/conexion.php');
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/password_utils.php';

// Helper seguro para acceso a arrays
function v($arr, $key, $default = '') {
    return (is_array($arr) && array_key_exists($key, $arr) && $arr[$key] !== null)
        ? $arr[$key]
        : $default;
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

function auth_is_dev_mode() {
    return defined('APP_ENV') && APP_ENV === 'dev';
}

function auth_dev_log($message) {
    if (auth_is_dev_mode()) {
        error_log('[AUTH] ' . $message);
    }
}

function auth_log_reason($reason, $userRow = array()) {
    if (!auth_is_dev_mode()) return;
    $userId = is_array($userRow) ? intval($userRow['id'] ?? 0) : 0;
    $roleId = is_array($userRow) ? (string)($userRow['role_id'] ?? '') : '';
    $providerId = is_array($userRow) ? intval($userRow['provider_id'] ?? 0) : 0;
    $serviceProviderId = is_array($userRow) ? intval($userRow['service_provider_id'] ?? 0) : 0;
    auth_dev_log("reason={$reason} user_id={$userId} role_id={$roleId} provider_id={$providerId} service_provider_id={$serviceProviderId}");
}

function login_is_debug_mode() {
    return isset($_GET['debug']) && (string)$_GET['debug'] === '1';
}

function login_debug_response($ok, $reason, $userRow = array(), $extra = array()) {
    http_response_code($ok ? 200 : 401);
    header('Content-Type: application/json; charset=utf-8');
    $payload = array(
        'ok' => (bool)$ok,
        'reason' => (string)$reason,
        'user_id' => is_array($userRow) ? intval($userRow['id'] ?? 0) : 0,
        'role_id' => is_array($userRow) ? intval($userRow['role_id'] ?? 0) : 0,
        'token_len' => is_array($userRow) ? strlen((string)($userRow['token'] ?? '')) : 0,
        'pass_len' => isset($GLOBALS['__auth_plain_pass_len']) ? intval($GLOBALS['__auth_plain_pass_len']) : 0,
    );
    foreach ($extra as $k => $v) {
        $payload[$k] = $v;
    }
    echo json_encode($payload);
    exit();
}

function login_redirect_error($reason, $legacyParams = array(), $userRow = array(), $httpCode = 302) {
    auth_log_reason($reason, $userRow);
    if (login_is_debug_mode()) {
        login_debug_response(false, $reason, $userRow);
    }
    $params = array('error' => $reason);
    foreach ($legacyParams as $k => $v) {
        if ($k === 'error') {
            $params['legacy_error'] = $v;
            continue;
        }
        $params[$k] = $v;
    }
    http_response_code((int)$httpCode);
    header("location:../../login.php?" . http_build_query($params));
    exit();
}

function login_table_has_column($conexion, $table, $column) {
    static $cache = array();
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

// Defensa de conexión a base de datos
if (!$conexion) {
    error_log('DB connection is null in log.php');
    login_redirect_error('db_connection_error', array('error' => 'db'));
}

function login_fetch_medical_provider_name($conexion, $provider_id) {
    $sql = "SELECT name FROM providers WHERE id = ?";
    if (login_table_has_column($conexion, 'providers', 'is_active')) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
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
    $sql = "SELECT provider_name FROM service_providers WHERE id = ?";
    if (login_table_has_column($conexion, 'service_providers', 'is_active')) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
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
// Recuperar input con compatibilidad de nombres legacy.
$user_candidates = array(
    isset($_POST["usuario"]) ? $_POST["usuario"] : '',
    isset($_POST["username"]) ? $_POST["username"] : '',
    isset($_POST["email"]) ? $_POST["email"] : '',
    isset($_POST["usrlogin"]) ? $_POST["usrlogin"] : '',
);
$raw_user = '';
foreach ($user_candidates as $candidate) {
    $candidate = trim((string)$candidate);
    if ($candidate !== '') {
        $raw_user = $candidate;
        break;
    }
}
$usrname = sanear_string($raw_user);
$password = '';
if (isset($_POST["password"])) {
    $password = trim((string)$_POST["password"]);
} elseif (isset($_POST["pass"])) {
    $password = trim((string)$_POST["pass"]);
}
$GLOBALS['__auth_plain_pass_len'] = strlen($password);

auth_dev_log("keys=" . implode(',', array_keys($_POST)) . " user_len=" . strlen($usrname) . " pass_len=" . strlen($password));

if ($usrname === '') {
    login_redirect_error('missing_username', array('usuario' => 'nulo'));
}

if ($password === '') {
    login_redirect_error('missing_password', array('pass' => 'vacio'));
}

$stmt_user = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE (usuario = ? OR email = ?) LIMIT 1");
if (!$stmt_user) {
    error_log('DB prepare error: '.mysqli_error($conexion));
    login_redirect_error('db_prepare_error', array('error' => 'db'));
}
mysqli_stmt_bind_param($stmt_user, 'ss', $usrname, $usrname);
if (!mysqli_stmt_execute($stmt_user)) {
    mysqli_stmt_close($stmt_user);
    error_log('DB execute error: '.mysqli_error($conexion));
    login_redirect_error('db_execute_error', array('error' => 'db'));
}
$busca_usua = mysqli_stmt_get_result($stmt_user);
if (!$busca_usua) {
    mysqli_stmt_close($stmt_user);
    error_log('DB result error: '.mysqli_error($conexion));
    login_redirect_error('db_result_error', array('error' => 'db'));
}
//empresa
if (mysqli_num_rows($busca_usua) > 0) {
    $fil = mysqli_fetch_array($busca_usua);
    mysqli_stmt_close($stmt_user);
    auth_dev_log("found_user_id=" . v($fil, 'id', 0) . " role_id=" . v($fil, 'role_id', 'null') . " token_len=" . strlen((string)v($fil, 'token', '')) . " pass_len=" . strlen((string)v($fil, 'password', '')));

    if (intval(v($fil, 'activo', 0)) !== 1) {
        login_redirect_error('user_inactive', array('usuario' => 'nulo'), $fil);
    }
    
    // Verificación canónica compartida con create/reset password.
    $password_valido = verify_password_for_user($password, $fil);
    
    if ($password_valido) {
        medtravel_session_mark_login();
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
                login_redirect_error('provider_scope_required', array('error' => 'empresa'), $fil);
            }
            $provider_name = login_fetch_medical_provider_name($conexion, $provider_id_val);
            if ($provider_name === null || $provider_name === '') {
                login_redirect_error('provider_invalid_or_inactive', array('error' => 'empresa'), $fil);
            }
            if ($rasocial === '') {
                $rasocial = $provider_name;
            }
            $fil['provider_id'] = $provider_id_val;
            $fil['service_provider_id'] = null;
        } elseif ($is_complementary_role) {
            if ($service_provider_id_val <= 0) {
                login_redirect_error('service_provider_scope_required', array('error' => 'empresa'), $fil);
            }
            $service_provider_name = login_fetch_active_service_provider_name($conexion, $service_provider_id_val);
            if ($service_provider_name === null || $service_provider_name === '') {
                login_redirect_error('service_provider_invalid_or_inactive', array('error' => 'empresa'), $fil);
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
            login_redirect_error('empresa_query_error', array('error' => 'query'), $fil);
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
                login_redirect_error('empresa_invalid', array('error' => 'empresa'), $fil);
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
                if (login_is_debug_mode()) {
                    login_debug_response(true, 'ok', $fil, array('next' => '../index.php#cambio_password'));
                }
                header("location:../index.php#cambio_password");
                exit();
            } else {
                if (login_is_debug_mode()) {
                    login_debug_response(true, 'ok', $fil, array('next' => '../index.php'));
                }
                header("location:../index.php");
                exit();
            }
        } else {
            login_redirect_error('session_conflict', array('session' => 'error'), $fil);
        }
    } else{
        login_redirect_error('password_mismatch', array('usuario' => 'nulo2'), $fil);
    }
} else {
    mysqli_stmt_close($stmt_user);
    login_redirect_error('user_not_found', array('usuario' => 'nulo'));
}
?>
