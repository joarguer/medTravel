<?php
require_once __DIR__ . '/session_security.php';
medtravel_session_start();
ob_start();

mysqli_report(MYSQLI_REPORT_OFF);

/**
 * Cargar .env si existe
 * (simple parser, sin librerías)
 */
$envPath = dirname(__DIR__, 2) . '/.env';
if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

require_once __DIR__ . '/../../inc/realtime.php';

/**
 * Detectar entorno
 */
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (
    $httpHost === '' ||
    $httpHost === 'localhost' ||
    str_contains($httpHost, '127.0.0.1')
);

define('APP_ENV', getenv('APP_ENV') ?: ($isLocal ? 'dev' : 'prod'));

/**
 * Cargar config local si existe (opcional)
 */
$config = null;
$localPath = __DIR__ . '/conexion.local.php';

if (APP_ENV === 'dev' && is_file($localPath)) {
    $config = require $localPath;
}

// En desarrollo, .env define la BD efectiva del workspace y puede sobreescribir
// credenciales legacy de conexion.local.php para evitar drift entre ambos.
$envConfig = [
    'DB_HOST' => getenv('DB_HOST'),
    'DB_PORT' => getenv('DB_PORT'),
    'DB_USER' => getenv('DB_USER'),
    'DB_PASS' => getenv('DB_PASS'),
    'DB_NAME' => getenv('DB_NAME'),
];
if (APP_ENV === 'dev' && $config) {
    foreach ($envConfig as $key => $value) {
        if ($value === false || $value === '') {
            continue;
        }
        $config[$key] = ($key === 'DB_PORT') ? (int)$value : $value;
    }
}

/**
 * Fallback a variables de entorno
 */
if (!$config) {
    $config = [
        'DB_HOST' => getenv('DB_HOST') ?: 'localhost',
        'DB_PORT' => (int)(getenv('DB_PORT') ?: 3306),
        'DB_USER' => getenv('DB_USER') ?: '',
        'DB_PASS' => getenv('DB_PASS') ?: '',
        'DB_NAME' => getenv('DB_NAME') ?: '',
    ];
}

/**
 * Validación mínima
 */
if (empty($config['DB_HOST']) || empty($config['DB_NAME'])) {
    die('Configuración de base de datos incompleta');
}

/**
 * Conexión GLOBAL (nombre correcto)
 */
$conexion = mysqli_connect(
    $config['DB_HOST'],
    $config['DB_USER'],
    $config['DB_PASS'],
    $config['DB_NAME'],
    $config['DB_PORT']
);

if (!$conexion) {
    die('Error DB (' . APP_ENV . '): ' . mysqli_connect_error());
}

mysqli_set_charset($conexion, 'utf8mb4');

if (!function_exists('mt_db_table_exists')) {
    function mt_db_table_exists($conexion, $table) {
        static $cache = [];
        if (!$conexion) return false;
        if (array_key_exists($table, $cache)) return $cache[$table];
        $tableEsc = mysqli_real_escape_string($conexion, $table);
        $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
        $cache[$table] = ($res && mysqli_num_rows($res) > 0);
        return $cache[$table];
    }
}

if (!function_exists('mt_db_table_has_column')) {
    function mt_db_table_has_column($conexion, $table, $column) {
        static $cache = [];
        if (!$conexion) return false;
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        if (!mt_db_table_exists($conexion, $table)) {
            $cache[$key] = false;
            return false;
        }
        $tableEsc = mysqli_real_escape_string($conexion, $table);
        $columnEsc = mysqli_real_escape_string($conexion, $column);
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        return $cache[$key];
    }
}

// Helper: requiere sesión válida para endpoints AJAX del admin
function require_login_ajax(){
	medtravel_session_start();
	$sessionState = medtravel_session_enforce_limits();
	if (empty($sessionState['ok'])) {
		medtravel_session_destroy();
		http_response_code(401);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['ok' => false, 'error' => 'SESSION_EXPIRED']);
		exit;
	}
	global $conexion;
	$user_id = null;
	// Prefer numeric session ids when available
	if (!empty(
			isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : null
	)) {
		$user_id = (int) $_SESSION['id_usuario'];
	} elseif (!empty(
			isset($_SESSION['id']) ? $_SESSION['id'] : null
	)) {
		$user_id = (int) $_SESSION['id'];
	} elseif (!empty(
			isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null
	)) {
		$user_id = (int) $_SESSION['user_id'];
	} elseif (!empty(
			isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null
	)) {
		// resolve username -> id from DB
		$uname = $_SESSION['usuario'];
		if ($conexion && mt_db_table_exists($conexion, 'usuarios')) {
			$sql = 'SELECT id FROM usuarios WHERE usuario = ?';
			$bindTypes = 's';
			if (mt_db_table_has_column($conexion, 'usuarios', 'usrlogin')) {
				$sql .= ' OR usrlogin = ?';
				$bindTypes .= 's';
			}
			$sql .= ' LIMIT 1';
			if ($stmt = mysqli_prepare($conexion, $sql)) {
				if ($bindTypes === 'ss') {
					mysqli_stmt_bind_param($stmt, $bindTypes, $uname, $uname);
				} else {
					mysqli_stmt_bind_param($stmt, $bindTypes, $uname);
				}
				if (mysqli_stmt_execute($stmt)) {
					mysqli_stmt_bind_result($stmt, $found_id);
					if (mysqli_stmt_fetch($stmt)) {
						$user_id = (int) $found_id;
					}
				} else {
					error_log('require_login_ajax: usuarios select execute error: ' . mysqli_error($conexion));
				}
				mysqli_stmt_close($stmt);
			} else {
				error_log('require_login_ajax: usuarios select prepare error: ' . mysqli_error($conexion));
			}
		} else {
			error_log('require_login_ajax: no DB connection to resolve usuario');
		}
	}

	if (empty($user_id)) {
		http_response_code(401);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED']);
		exit;
	}
	if (empty($_SESSION['id_usuario'])) {
		$_SESSION['id_usuario'] = (int)$user_id;
	}

		$is_protected_superuser = ((int)$user_id === 1);
		if ($is_protected_superuser) {
			if (isset($_SESSION['provider_id'])) unset($_SESSION['provider_id']);
			if (isset($_SESSION['service_provider_id'])) unset($_SESSION['service_provider_id']);
		}

		// hydrate provider_id/service_provider_id in session if not present
		if (
			!$is_protected_superuser
			&& $conexion
			&& mt_db_table_exists($conexion, 'usuarios')
			&& (empty($_SESSION['provider_id']) || empty($_SESSION['service_provider_id']))
		) {
			$selectParts = [];
			if (mt_db_table_has_column($conexion, 'usuarios', 'provider_id')) {
				$selectParts[] = 'provider_id';
			}
			if (mt_db_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
				$selectParts[] = 'service_provider_id';
			}
			if (!empty($selectParts) && ($ustmt = mysqli_prepare($conexion, 'SELECT ' . implode(', ', $selectParts) . ' FROM usuarios WHERE id = ? LIMIT 1'))) {
				$uid = (int) $user_id;
				mysqli_stmt_bind_param($ustmt, 'i', $uid);
			if (mysqli_stmt_execute($ustmt)) {
				$res = mysqli_stmt_get_result($ustmt);
				$row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
				if ($row) {
					$db_provider_id = isset($row['provider_id']) ? (int)$row['provider_id'] : 0;
					$db_service_provider_id = isset($row['service_provider_id']) ? (int)$row['service_provider_id'] : 0;
					if (empty($_SESSION['provider_id']) && $db_provider_id > 0) {
						$_SESSION['provider_id'] = $db_provider_id;
					}
					if (empty($_SESSION['service_provider_id']) && $db_service_provider_id > 0) {
						$_SESSION['service_provider_id'] = $db_service_provider_id;
					}
				}
			}
			mysqli_stmt_close($ustmt);
		}
	}

	// backward compatibility: old mapping table for medical providers
		if (
			!$is_protected_superuser
			&& empty($_SESSION['provider_id'])
			&& $conexion
			&& mt_db_table_exists($conexion, 'provider_users')
			&& mt_db_table_has_column($conexion, 'provider_users', 'provider_id')
			&& mt_db_table_has_column($conexion, 'provider_users', 'user_id')
		) {
			if ($pstmt = mysqli_prepare($conexion, "SELECT provider_id FROM provider_users WHERE user_id = ? LIMIT 1")) {
				$uid = (int) $user_id;
				mysqli_stmt_bind_param($pstmt, 'i', $uid);
			if (mysqli_stmt_execute($pstmt)) {
				mysqli_stmt_bind_result($pstmt, $pid);
				if (mysqli_stmt_fetch($pstmt)) {
					$_SESSION['provider_id'] = (int) $pid;
				}
			} else {
				error_log('require_login_ajax: provider_users select execute error: ' . mysqli_error($conexion));
			}
			mysqli_stmt_close($pstmt);
			} else {
				error_log('require_login_ajax: provider_users select prepare error: ' . mysqli_error($conexion));
				// también escribir en log local dev
				@file_put_contents(__DIR__ . '/../logs/dev.log', date('Y-m-d H:i:s') . " - require_login_ajax provider_users prepare error: " . mysqli_error($conexion) . "\n", FILE_APPEND | LOCK_EX);
			}
	}
}
?> 
