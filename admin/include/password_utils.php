<?php
/**
 * Password utilities shared by login, user creation and password reset.
 * Compatible with legacy sha512(token+password) and bcrypt hashes.
 */

if (!function_exists('is_bcrypt_hash')) {
    function is_bcrypt_hash($hash) {
        if (!is_string($hash) || $hash === '') {
            return false;
        }
        return (bool) preg_match('/^\$2[aby]\$/', $hash);
    }
}

if (!function_exists('build_legacy_password_hash')) {
    function build_legacy_password_hash($plain, $token) {
        return hash('sha512', (string)$token . (string)$plain);
    }
}

if (!function_exists('ensure_password_token')) {
    function ensure_password_token($userRow = array()) {
        $token = '';
        if (is_array($userRow) && isset($userRow['token']) && $userRow['token'] !== null) {
            $token = trim((string)$userRow['token']);
        }
        if ($token === '') {
            $token = md5(uniqid(rand(), true));
        }
        return $token;
    }
}

if (!function_exists('hash_password_for_storage')) {
    function hash_password_for_storage($plain, $userRow = array()) {
        $plain = (string)$plain;
        $currentHash = '';
        if (is_array($userRow) && isset($userRow['password']) && $userRow['password'] !== null) {
            $currentHash = (string)$userRow['password'];
        }

        $token = ensure_password_token($userRow);

        // Preserve bcrypt users in bcrypt, keep legacy users in legacy hash format.
        if (is_bcrypt_hash($currentHash)) {
            return array(
                'password' => password_hash($plain, PASSWORD_DEFAULT),
                'token' => $token,
                'algorithm' => 'bcrypt',
            );
        }

        return array(
            'password' => build_legacy_password_hash($plain, $token),
            'token' => $token,
            'algorithm' => 'legacy_sha512_token',
        );
    }
}

if (!function_exists('verify_password_for_user')) {
    function verify_password_for_user($plain, $userRow = array()) {
        $storedPassword = '';
        if (is_array($userRow) && isset($userRow['password']) && $userRow['password'] !== null) {
            $storedPassword = (string)$userRow['password'];
        }
        if ($storedPassword === '') {
            return false;
        }

        if (is_bcrypt_hash($storedPassword)) {
            return password_verify((string)$plain, $storedPassword);
        }

        $token = '';
        if (is_array($userRow) && isset($userRow['token']) && $userRow['token'] !== null) {
            $token = (string)$userRow['token'];
        }
        $expected = build_legacy_password_hash($plain, $token);
        return hash_equals($storedPassword, $expected);
    }
}

