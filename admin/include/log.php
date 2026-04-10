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

function login_table_exists($conexion, $table) {
    if (function_exists('mt_db_table_exists')) {
        return mt_db_table_exists($conexion, $table);
    }
    static $cache = array();
    if (array_key_exists($table, $cache)) return $cache[$table];
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$table];
}

function login_table_has_column($conexion, $table, $column) {
    if (function_exists('mt_db_table_has_column')) {
        return mt_db_table_has_column($conexion, $table, $column);
    }
    static $cache = array();
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!login_table_exists($conexion, $table)) {
        $cache[$key] = false;
        return false;
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function login_stmt_bind_params($stmt, $types, array $values) {
    $bind = array($stmt, $types);
    foreach ($values as $idx => $value) {
        $values[$idx] = $value;
        $bind[] = &$values[$idx];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function login_normalize_identifier($value) {
    return strtolower(trim((string)$value));
}

function login_candidate_match_rank($userRow, $identifier) {
    $needle = login_normalize_identifier($identifier);
    if ($needle === '') {
        return 99;
    }

    $usuario = login_normalize_identifier(v($userRow, 'usuario', ''));
    if ($usuario !== '' && $usuario === $needle) {
        return 0;
    }

    $usrlogin = login_normalize_identifier(v($userRow, 'usrlogin', ''));
    if ($usrlogin !== '' && $usrlogin === $needle) {
        return 1;
    }

    $email = login_normalize_identifier(v($userRow, 'email', ''));
    if ($email !== '' && $email === $needle) {
        return 2;
    }

    return 99;
}

function login_sort_user_candidates(array $rows, $identifier) {
    usort($rows, function ($a, $b) use ($identifier) {
        $rankA = login_candidate_match_rank($a, $identifier);
        $rankB = login_candidate_match_rank($b, $identifier);
        if ($rankA !== $rankB) {
            return $rankA - $rankB;
        }
        $idA = (int)v($a, 'id', 0);
        $idB = (int)v($b, 'id', 0);
        return $idA - $idB;
    });
    return $rows;
}

function login_fetch_user_candidates($conexion, $identifier) {
    if (!$conexion || !login_table_exists($conexion, 'usuarios')) {
        return array();
    }

    $select = array(
        'id',
        'usuario',
        'password',
        login_table_has_column($conexion, 'usuarios', 'nombre') ? 'nombre' : 'usuario AS nombre',
        login_table_has_column($conexion, 'usuarios', 'rol') ? 'rol' : "'' AS rol",
        login_table_has_column($conexion, 'usuarios', 'role_id') ? 'role_id' : 'NULL AS role_id',
        login_table_has_column($conexion, 'usuarios', 'email') ? 'email' : "'' AS email",
        login_table_has_column($conexion, 'usuarios', 'provider_id') ? 'provider_id' : 'NULL AS provider_id',
        login_table_has_column($conexion, 'usuarios', 'service_provider_id') ? 'service_provider_id' : 'NULL AS service_provider_id',
        login_table_has_column($conexion, 'usuarios', 'activo') ? 'activo' : '1 AS activo',
        login_table_has_column($conexion, 'usuarios', 'is_deleted') ? 'is_deleted' : '0 AS is_deleted',
        login_table_has_column($conexion, 'usuarios', 'terms_accepted') ? 'terms_accepted' : '1 AS terms_accepted',
        login_table_has_column($conexion, 'usuarios', 'token') ? 'token' : "'' AS token",
        login_table_has_column($conexion, 'usuarios', 'ppal') ? 'ppal' : '0 AS ppal',
        login_table_has_column($conexion, 'usuarios', 'usrlogin') ? 'usrlogin' : 'usuario AS usrlogin',
        login_table_has_column($conexion, 'usuarios', 'cambio_password') ? 'cambio_password' : '0 AS cambio_password',
        login_table_has_column($conexion, 'usuarios', 'cargo') ? 'cargo' : "'' AS cargo",
        login_table_has_column($conexion, 'usuarios', 'ciudad') ? 'ciudad' : "'' AS ciudad",
        login_table_has_column($conexion, 'usuarios', 'telefono') ? 'telefono' : "'' AS telefono",
        login_table_has_column($conexion, 'usuarios', 'celular') ? 'celular' : "'' AS celular",
        login_table_has_column($conexion, 'usuarios', 'avatar') ? 'avatar' : "'' AS avatar",
        login_table_has_column($conexion, 'usuarios', 'empresa') ? 'empresa' : "'' AS empresa"
    );

    $where = array('usuario = ?');
    $bindValues = array($identifier);
    if (login_table_has_column($conexion, 'usuarios', 'email')) {
        $where[] = 'email = ?';
        $bindValues[] = $identifier;
    }
    if (login_table_has_column($conexion, 'usuarios', 'usrlogin')) {
        $where[] = 'usrlogin = ?';
        $bindValues[] = $identifier;
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM usuarios WHERE (' . implode(' OR ', $where) . ')';
    if (login_table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= ' AND COALESCE(is_deleted, 0) = 0';
    }
    $sql .= ' ORDER BY id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log('DB prepare error: ' . mysqli_error($conexion));
        return array();
    }

    if (!login_stmt_bind_params($stmt, str_repeat('s', count($bindValues)), $bindValues)) {
        mysqli_stmt_close($stmt);
        error_log('DB bind error: ' . mysqli_error($conexion));
        return array();
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        error_log('DB execute error: ' . mysqli_error($conexion));
        return array();
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = array();
    while ($res && ($tmp = mysqli_fetch_assoc($res))) {
        $rows[] = $tmp;
    }
    mysqli_stmt_close($stmt);
    return login_sort_user_candidates($rows, $identifier);
}

function login_build_default_context($userRow) {
    $label = trim((string)v($userRow, 'nombre', ''));
    if ($label === '') {
        $label = trim((string)v($userRow, 'usuario', 'User'));
    }
    return array(
        'id' => 1,
        'rasocial' => $label !== '' ? $label : 'User',
        'nit' => '000000000',
        'activo' => 0,
        'logo' => ''
    );
}

function login_try_legacy_company_context($conexion, $userRow, $context) {
    $empresa = trim((string)v($userRow, 'empresa', ''));
    if (
        $empresa === ''
        || !login_table_exists($conexion, 'empresas')
        || !login_table_has_column($conexion, 'empresas', 'rasocial')
    ) {
        return $context;
    }

    $stmt = mysqli_prepare($conexion, 'SELECT * FROM empresas WHERE rasocial = ? LIMIT 1');
    if (!$stmt) {
        return $context;
    }
    mysqli_stmt_bind_param($stmt, 's', $empresa);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return $context;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return $context;
    }

    $context['id'] = (int)v($row, 'id', $context['id']);
    $context['rasocial'] = (string)v($row, 'rasocial', $context['rasocial']);
    $context['nit'] = (string)v($row, 'nit', $context['nit']);
    $context['activo'] = (int)v($row, 'activo', $context['activo']);
    $context['logo'] = (string)v($row, 'logo', $context['logo']);
    return $context;
}

function login_record_legacy_visit($conexion, $visitante, $usuario, $ip) {
    if (
        !$conexion
        || !login_table_exists($conexion, 'visitas')
        || !login_table_has_column($conexion, 'visitas', 'fecha')
        || !login_table_has_column($conexion, 'visitas', 'hora')
        || !login_table_has_column($conexion, 'visitas', 'hora2')
        || !login_table_has_column($conexion, 'visitas', 'visitante')
        || !login_table_has_column($conexion, 'visitas', 'usuario')
        || !login_table_has_column($conexion, 'visitas', 'dispositivo')
        || !login_table_has_column($conexion, 'visitas', 'ip')
    ) {
        return;
    }

    $fecha = date("Y-m-d", time() - 18000);
    $hora = date("H:i:s", time() - 18000);
    $detect = '';
    $sql = "INSERT INTO visitas(fecha,hora,hora2,visitante,usuario,dispositivo,ip) VALUES(?, ?, NULL, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ssssss', $fecha, $hora, $visitante, $usuario, $detect, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function login_cleanup_stale_legacy_sessions($conexion, $usuario) {
    if (
        !$conexion
        || trim((string)$usuario) === ''
        || !login_table_exists($conexion, 'sessiones_activas')
        || !login_table_has_column($conexion, 'sessiones_activas', 'usuario')
        || !login_table_has_column($conexion, 'sessiones_activas', 'fecha')
        || !login_table_has_column($conexion, 'sessiones_activas', 'hora')
    ) {
        return;
    }

    $cutoff = date('Y-m-d H:i:s', time() - 18000 - MEDTRAVEL_SESSION_MAX_LIFETIME);
    $stmt = mysqli_prepare(
        $conexion,
        "DELETE FROM sessiones_activas
          WHERE usuario = ?
            AND (
                fecha IS NULL
                OR hora IS NULL
                OR TIMESTAMP(fecha, hora) < ?
            )"
    );
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $usuario, $cutoff);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function login_replace_legacy_session($conexion, $usuario, $visitante, $ip) {
    if (
        !$conexion
        || trim((string)$usuario) === ''
        || !login_table_exists($conexion, 'sessiones_activas')
        || !login_table_has_column($conexion, 'sessiones_activas', 'usuario')
        || !login_table_has_column($conexion, 'sessiones_activas', 'fecha')
        || !login_table_has_column($conexion, 'sessiones_activas', 'hora')
        || !login_table_has_column($conexion, 'sessiones_activas', 'visitante')
        || !login_table_has_column($conexion, 'sessiones_activas', 'ip')
        || !login_table_has_column($conexion, 'sessiones_activas', 'latitud')
        || !login_table_has_column($conexion, 'sessiones_activas', 'longitud')
        || !login_table_has_column($conexion, 'sessiones_activas', 'cobrador')
        || !login_table_has_column($conexion, 'sessiones_activas', 'hora2')
    ) {
        return false;
    }

    $delete = mysqli_prepare($conexion, 'DELETE FROM sessiones_activas WHERE usuario = ?');
    if (!$delete) {
        return false;
    }
    mysqli_stmt_bind_param($delete, 's', $usuario);
    mysqli_stmt_execute($delete);
    mysqli_stmt_close($delete);

    $fecha = date("Y-m-d", time() - 18000);
    $hora = date("H:i:s", time() - 18000);
    $insert = mysqli_prepare($conexion, "INSERT INTO sessiones_activas(`fecha`, `hora`, `visitante`, `usuario`, `ip`, `latitud`, `longitud`, `cobrador`, `hora2`) VALUES(?, ?, ?, ?, ?, '0', '0', '0', '00:00:00')");
    if (!$insert) {
        return false;
    }
    mysqli_stmt_bind_param($insert, 'sssss', $fecha, $hora, $visitante, $usuario, $ip);
    $ok = mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);
    return (bool)$ok;
}

function login_register_legacy_session($conexion, $visitante, $usuario, $ip) {
    if (
        !$conexion
        || !login_table_exists($conexion, 'sessiones_activas')
        || !login_table_has_column($conexion, 'sessiones_activas', 'fecha')
        || !login_table_has_column($conexion, 'sessiones_activas', 'hora')
        || !login_table_has_column($conexion, 'sessiones_activas', 'visitante')
        || !login_table_has_column($conexion, 'sessiones_activas', 'usuario')
        || !login_table_has_column($conexion, 'sessiones_activas', 'ip')
        || !login_table_has_column($conexion, 'sessiones_activas', 'latitud')
        || !login_table_has_column($conexion, 'sessiones_activas', 'longitud')
        || !login_table_has_column($conexion, 'sessiones_activas', 'cobrador')
        || !login_table_has_column($conexion, 'sessiones_activas', 'hora2')
    ) {
        return true;
    }

    login_cleanup_stale_legacy_sessions($conexion, $usuario);

    $stmt = mysqli_prepare(
        $conexion,
        'SELECT id, visitante, usuario, ip, fecha, hora
           FROM sessiones_activas
          WHERE usuario = ?
          ORDER BY fecha DESC, hora DESC, id DESC
          LIMIT 1'
    );
    if (!$stmt) {
        return true;
    }
    mysqli_stmt_bind_param($stmt, 's', $usuario);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return true;
    }
    $res = mysqli_stmt_get_result($stmt);
    $existing = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);

    $existingUser = (string)($existing['usuario'] ?? '');
    $existingIp = (string)($existing['ip'] ?? '');
    if ($existing && $existingUser === $usuario) {
        return login_replace_legacy_session($conexion, $usuario, $visitante, $ip);
    }

    $fecha = date("Y-m-d", time() - 18000);
    $hora = date("H:i:s", time() - 18000);
    $insert = mysqli_prepare($conexion, "INSERT INTO sessiones_activas(`fecha`, `hora`, `visitante`, `usuario`, `ip`, `latitud`, `longitud`, `cobrador`, `hora2`) VALUES(?, ?, ?, ?, ?, '0', '0', '0', '00:00:00')");
    if (!$insert) {
        return true;
    }
    mysqli_stmt_bind_param($insert, 'sssss', $fecha, $hora, $visitante, $usuario, $ip);
    mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);
    return true;
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

$userCandidates = login_fetch_user_candidates($conexion, $usrname);
if (!$userCandidates) {
    login_redirect_error('user_not_found', array('usuario' => 'nulo'));
}

$fil = null;
foreach ($userCandidates as $candidate) {
    if ((int)v($candidate, 'activo', 1) !== 1) {
        continue;
    }
    if (verify_password_for_user($password, $candidate)) {
        $fil = $candidate;
        break;
    }
}

if (!$fil) {
    $fil = $userCandidates[0];
}

auth_dev_log(
    "found_user_id=" . v($fil, 'id', 0)
    . " role_id=" . v($fil, 'role_id', 'null')
    . " token_len=" . strlen((string)v($fil, 'token', ''))
    . " pass_len=" . strlen((string)v($fil, 'password', ''))
);

if (!login_table_has_column($conexion, 'usuarios', 'rol') && !login_table_has_column($conexion, 'usuarios', 'role_id')) {
    login_redirect_error('auth_schema_invalid', array('error' => 'schema'), $fil);
}

if ((int)v($fil, 'activo', 1) !== 1) {
    login_redirect_error('user_inactive', array('usuario' => 'nulo'), $fil);
}

if (!verify_password_for_user($password, $fil)) {
    login_redirect_error('password_mismatch', array('usuario' => 'nulo2'), $fil);
}

medtravel_session_mark_login();

$role_id_val = (isset($fil['role_id']) && is_numeric($fil['role_id'])) ? intval($fil['role_id']) : normalize_role_value(v($fil, 'rol', ''));
$provider_id_val = !empty($fil['provider_id']) ? intval($fil['provider_id']) : 0;
$service_provider_id_val = !empty($fil['service_provider_id']) ? intval($fil['service_provider_id']) : 0;
$linked_staff_context = function_exists('mt_db_fetch_linked_staff_context')
    ? mt_db_fetch_linked_staff_context($conexion, (int)v($fil, 'id', 0))
    : null;
$is_linked_staff_login = !empty($linked_staff_context['provider_id']);
$is_protected_superuser = ((int)v($fil, 'id', 0) === 1);
$is_global_admin_role = ($is_protected_superuser || $role_id_val === ROLE_ADMIN || (string)v($fil, 'ppal', '0') === '1');
$is_administrative_panel_role = ($role_id_val === ROLE_ADMINISTRATIVE);
$is_medical_role = in_array($role_id_val, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true);
$is_complementary_role = ($role_id_val === ROLE_COMPLEMENTARY_ADMIN);
$is_client_role = ($role_id_val === ROLE_CLIENT);

if ($provider_id_val <= 0 && $is_linked_staff_login) {
    $provider_id_val = (int)$linked_staff_context['provider_id'];
}

if ($is_protected_superuser) {
    $provider_id_val = 0;
    $service_provider_id_val = 0;
    $is_medical_role = false;
    $is_complementary_role = false;
    $is_client_role = false;
}

$fila = login_build_default_context($fil);
if ($is_medical_role) {
    if ($provider_id_val <= 0) {
        login_redirect_error('provider_scope_required', array('error' => 'empresa'), $fil);
    }
    $provider_name = login_fetch_medical_provider_name($conexion, $provider_id_val);
    if ($provider_name === null || $provider_name === '') {
        login_redirect_error('provider_invalid_or_inactive', array('error' => 'empresa'), $fil);
    }
    $fila['id'] = $provider_id_val;
    $fila['rasocial'] = $provider_name;
    $fila['nit'] = $provider_name;
} elseif ($is_complementary_role) {
    if ($service_provider_id_val <= 0) {
        login_redirect_error('service_provider_scope_required', array('error' => 'empresa'), $fil);
    }
    $service_provider_name = login_fetch_active_service_provider_name($conexion, $service_provider_id_val);
    if ($service_provider_name === null || $service_provider_name === '') {
        login_redirect_error('service_provider_invalid_or_inactive', array('error' => 'empresa'), $fil);
    }
    $fila['id'] = $service_provider_id_val;
    $fila['rasocial'] = $service_provider_name;
    $fila['nit'] = $service_provider_name;
} elseif (!$is_global_admin_role && !$is_administrative_panel_role) {
    $fila = login_try_legacy_company_context($conexion, $fil, $fila);
}

$_SESSION['nitEmpresa'] = v($fila, 'nit', '');
setcookie("usuario_nombre", v($fila, 'rasocial', ''));

$_SESSION["ppal"] = $is_global_admin_role ? '1' : '0';
$_SESSION['chatuser'] = v($fil, 'id', 0);
$_SESSION["tipo"] = 'empresa';
$_SESSION["id_empresa"] = v($fila, 'id', 1);
$_SESSION["id_usuario"] = v($fil, 'id', 0);
$_SESSION["nombre_usuario"] = (string)v($fil, 'nombre', v($fil, 'usuario', ''));
$_SESSION["usuario"] = (string)v($fil, 'usuario', '');
$_SESSION["token"] = (string)v($fil, 'token', '');
$_SESSION["rasocial"] = (string)v($fila, 'rasocial', '');
$_SESSION["nit"] = (string)v($fila, 'nit', '');
$_SESSION['usrlogin'] = (string)v($fil, 'usrlogin', v($fil, 'usuario', ''));
$_SESSION['logo'] = (string)v($fila, 'logo', '');
$_SESSION['avatar'] = (string)v($fil, 'avatar', '');
$_SESSION['nombre_perfil'] = (string)v($fil, 'nombre', '');
$_SESSION['rol'] = (string)v($fil, 'rol', '');
$_SESSION['role_id'] = $role_id_val;
$_SESSION["usuario_cargo"] = (string)v($fil, 'cargo', '');
$_SESSION["usuario_email"] = (string)v($fil, 'email', '');
$_SESSION["usuario_ciudad"] = (string)v($fil, 'ciudad', '');
$_SESSION["usuario_telefono"] = (string)v($fil, 'telefono', '');
$_SESSION["usuario_celular"] = (string)v($fil, 'celular', '');

$anio = date("Y", time() - 18000);
$_SESSION["anio_bd"] = 'ejemagic_admin_' . $anio;

if ($is_protected_superuser) {
    unset($_SESSION['provider_id'], $_SESSION['service_provider_id']);
} else {
    if ($provider_id_val > 0) {
        $_SESSION['provider_id'] = $provider_id_val;
    } else {
        unset($_SESSION['provider_id']);
        if (
            login_table_exists($conexion, 'provider_users')
            && login_table_has_column($conexion, 'provider_users', 'provider_id')
            && login_table_has_column($conexion, 'provider_users', 'user_id')
        ) {
            $stmt = mysqli_prepare($conexion, "SELECT provider_id FROM provider_users WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $uid = (int)$_SESSION["id_usuario"];
                mysqli_stmt_bind_param($stmt, "i", $uid);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_bind_result($stmt, $legacy_provider_id);
                    if (mysqli_stmt_fetch($stmt) && (int)$legacy_provider_id > 0) {
                        $_SESSION['provider_id'] = (int)$legacy_provider_id;
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }

        if (empty($_SESSION['provider_id']) && $is_linked_staff_login) {
            $_SESSION['provider_id'] = (int)$linked_staff_context['provider_id'];
        }
    }

    if ($service_provider_id_val > 0) {
        $_SESSION['service_provider_id'] = $service_provider_id_val;
    } else {
        unset($_SESSION['service_provider_id']);
    }
}

if (isset($_SERVER["HTTP_CLIENT_IP"])) {
    $ip = $_SERVER["HTTP_CLIENT_IP"];
} else if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
    $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else if (isset($_SERVER["HTTP_X_FORWARDED"])) {
    $ip = $_SERVER["HTTP_X_FORWARDED"];
} else if (isset($_SERVER["HTTP_FORWARDED_FOR"])) {
    $ip = $_SERVER["HTTP_FORWARDED_FOR"];
} else if (isset($_SERVER["HTTP_FORWARDED"])) {
    $ip = $_SERVER["HTTP_FORWARDED"];
} else {
    $ip = $_SERVER["REMOTE_ADDR"] ?? '';
}

$visitante = (string)v($fila, 'rasocial', '');
$usrlogin = (string)v($fil, 'usrlogin', v($fil, 'usuario', ''));
login_record_legacy_visit($conexion, $visitante, $usrlogin, $ip);

if (!login_register_legacy_session($conexion, $visitante, $usrlogin, $ip)) {
    login_redirect_error('session_conflict', array('session' => 'error'), $fil);
}

$default_next = $is_client_role
    ? '../../client/index.php'
    : ($is_linked_staff_login ? '../my_booking_requests.php' : '../index.php');
if ((int)v($fil, 'cambio_password', 0) === 1) {
    $next = $is_client_role
        ? '../../client/index.php?password_change=1'
        : '../index.php#cambio_password';
    if (login_is_debug_mode()) {
        login_debug_response(true, 'ok', $fil, array('next' => $next));
    }
    header("location:" . $next);
    exit();
}

if (login_is_debug_mode()) {
    login_debug_response(true, 'ok', $fil, array('next' => $default_next));
}
header("location:" . $default_next);
exit();
?>
