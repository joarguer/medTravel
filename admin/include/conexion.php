<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
ob_start();
// Environment: 'dev' or 'prod' (fallback to getenv)
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'dev');
}
$dbHost = 'localhost';
$dbUsername = 'bolsacar_jorge';
$dbPassword = '7492690';
$dbName = 'bolsacar_medtravel';
mysqli_report(MYSQLI_REPORT_OFF);

// MAMP default MySQL settings
$dbHost = '127.0.0.1';
$dbPort = 8889; // MAMP MySQL port
$dbUsername = 'root';
$dbPassword = 'root';
$conexion = @mysqli_connect($dbHost, $dbUsername, $dbPassword, $dbName, $dbPort);
if (!$conexion) {
	error_log("DB connection failed: " . mysqli_connect_error());
	$conexion = null;
} else {
	mysqli_set_charset($conexion, 'utf8');
}

	// Helper: requiere sesión válida para endpoints AJAX del admin
	function require_login_ajax(){
		if (session_status() === PHP_SESSION_NONE) { session_start(); }
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

		// hydrate provider_id in session if not present
		if (empty($_SESSION['provider_id']) && $conexion) {
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