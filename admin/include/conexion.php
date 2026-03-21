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
		if ($conexion) {
			if ($stmt = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE usuario = ? OR usrlogin = ? LIMIT 1")) {
				mysqli_stmt_bind_param($stmt, 'ss', $uname, $uname);
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

		$is_protected_superuser = ((int)$user_id === 1);
		if ($is_protected_superuser) {
			if (isset($_SESSION['provider_id'])) unset($_SESSION['provider_id']);
			if (isset($_SESSION['service_provider_id'])) unset($_SESSION['service_provider_id']);
		}

		// hydrate provider_id/service_provider_id in session if not present
		if (!$is_protected_superuser && $conexion && (empty($_SESSION['provider_id']) || empty($_SESSION['service_provider_id']))) {
			if ($ustmt = mysqli_prepare($conexion, "SELECT provider_id, service_provider_id FROM usuarios WHERE id = ? LIMIT 1")) {
				$uid = (int) $user_id;
				mysqli_stmt_bind_param($ustmt, 'i', $uid);
			if (mysqli_stmt_execute($ustmt)) {
				mysqli_stmt_bind_result($ustmt, $db_provider_id, $db_service_provider_id);
				if (mysqli_stmt_fetch($ustmt)) {
					if (empty($_SESSION['provider_id']) && !empty($db_provider_id)) {
						$_SESSION['provider_id'] = (int) $db_provider_id;
					}
					if (empty($_SESSION['service_provider_id']) && !empty($db_service_provider_id)) {
						$_SESSION['service_provider_id'] = (int) $db_service_provider_id;
					}
				}
			}
			mysqli_stmt_close($ustmt);
		}
	}

	// backward compatibility: old mapping table for medical providers
		if (!$is_protected_superuser && empty($_SESSION['provider_id']) && $conexion) {
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
