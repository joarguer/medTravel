<?php
/**
 * Password utilities shared by login, user creation and password reset.
 * Canonical hash for MedTravel users: sha512(token + password).
 * Bcrypt is only supported in verify for backwards compatibility.
 */

if (!function_exists('is_bcrypt_hash')) {
    function is_bcrypt_hash($hash) {
        if (!is_string($hash) || $hash === '') {
            return false;
        }
        return (bool) preg_match('/^\$2[aby]\$/', $hash);
    }
}

if (!function_exists('generate_user_token')) {
    function generate_user_token() {
        // Historical token shape in usuarios: md5(uniqid(...)) => 32 chars.
        return md5(uniqid(rand(), true));
    }
}

if (!function_exists('hash_password')) {
    function hash_password($plain, $token) {
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
            $token = generate_user_token();
        }
        return $token;
    }
}

if (!function_exists('verify_password')) {
    function verify_password($plain, $token, $stored_hash) {
        $stored = (string)$stored_hash;
        if ($stored === '') {
            return false;
        }
        if (is_bcrypt_hash($stored)) {
            return password_verify((string)$plain, $stored);
        }
        $expected = hash_password($plain, $token);
        return hash_equals($stored, $expected);
    }
}

if (!function_exists('hash_password_for_storage')) {
    function hash_password_for_storage($plain, $userRow = array()) {
        $plain = (string)$plain;
        $token = ensure_password_token($userRow);
        return array(
            'password' => hash_password($plain, $token),
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

        $token = '';
        if (is_array($userRow) && isset($userRow['token']) && $userRow['token'] !== null) {
            $token = (string)$userRow['token'];
        }
        return verify_password($plain, $token, $storedPassword);
    }
}
