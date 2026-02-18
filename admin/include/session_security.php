<?php

if (!defined('MEDTRAVEL_SESSION_MAX_LIFETIME')) {
    define('MEDTRAVEL_SESSION_MAX_LIFETIME', 86400); // 24h
}

if (!defined('MEDTRAVEL_SESSION_IDLE_TIMEOUT')) {
    define('MEDTRAVEL_SESSION_IDLE_TIMEOUT', 1800); // 30 min idle
}

if (!function_exists('medtravel_session_is_https')) {
    function medtravel_session_is_https()
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        return false;
    }
}

if (!function_exists('medtravel_session_apply_cookie_params')) {
    function medtravel_session_apply_cookie_params()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        @ini_set('session.gc_maxlifetime', (string)MEDTRAVEL_SESSION_MAX_LIFETIME);
        @ini_set('session.cookie_lifetime', (string)MEDTRAVEL_SESSION_MAX_LIFETIME);
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.use_only_cookies', '1');

        $secure = medtravel_session_is_https();
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => MEDTRAVEL_SESSION_MAX_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return;
        }

        // PHP < 7.3 fallback
        session_set_cookie_params(
            MEDTRAVEL_SESSION_MAX_LIFETIME,
            '/; samesite=Lax',
            '',
            $secure,
            true
        );
    }
}

if (!function_exists('medtravel_session_start')) {
    function medtravel_session_start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        medtravel_session_apply_cookie_params();
        session_start();
    }
}

if (!function_exists('medtravel_session_destroy')) {
    function medtravel_session_destroy()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                !empty($params['secure']),
                !empty($params['httponly'])
            );
        }
        session_destroy();
    }
}

if (!function_exists('medtravel_session_mark_login')) {
    function medtravel_session_mark_login()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            medtravel_session_start();
        }
        session_regenerate_id(true);
        $now = time();
        $_SESSION['created_at'] = $now;
        $_SESSION['last_activity'] = $now;
    }
}

if (!function_exists('medtravel_session_enforce_limits')) {
    function medtravel_session_enforce_limits()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['ok' => false, 'reason' => 'no_session'];
        }

        $now = time();
        if (empty($_SESSION['created_at']) || !is_numeric($_SESSION['created_at'])) {
            $_SESSION['created_at'] = $now;
        }
        if (empty($_SESSION['last_activity']) || !is_numeric($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = $now;
        }

        if (($now - (int)$_SESSION['created_at']) > MEDTRAVEL_SESSION_MAX_LIFETIME) {
            return ['ok' => false, 'reason' => 'absolute_expired'];
        }

        if (MEDTRAVEL_SESSION_IDLE_TIMEOUT > 0 && ($now - (int)$_SESSION['last_activity']) > MEDTRAVEL_SESSION_IDLE_TIMEOUT) {
            return ['ok' => false, 'reason' => 'idle_expired'];
        }

        // Sliding activity update
        $_SESSION['last_activity'] = $now;
        return ['ok' => true, 'reason' => 'ok'];
    }
}
