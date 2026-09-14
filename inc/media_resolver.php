<?php

// Neutral, dependency-free media/photo path resolution. No DB, no session,
// no headers, no output. Shared between the public frontend
// (inc/public_specialists.php, "Our Specialists" section) and read-only
// backend consumers such as api/conectarbot/v1/index.php, so neither has to
// depend on the other's concerns.

if (!function_exists('mt_home_specialist_placeholder_photo')) {
    function mt_home_specialist_placeholder_photo()
    {
        $jpg = 'img/site/placeholder-medical.jpg';
        if (is_file(__DIR__ . '/../' . $jpg)) {
            return $jpg;
        }

        $svg = 'img/site/placeholder-medical.svg';
        if (is_file(__DIR__ . '/../' . $svg)) {
            return $svg;
        }

        return '';
    }
}

if (!function_exists('mt_home_specialist_resolve_photo')) {
    function mt_home_specialist_resolve_photo($photo)
    {
        $photo = trim((string)$photo);
        $fallback = mt_home_specialist_placeholder_photo();
        if ($photo === '') {
            return $fallback;
        }

        if (preg_match('~^https?://~i', $photo)) {
            return $photo;
        }

        $photoPath = parse_url($photo, PHP_URL_PATH);
        $photoPath = is_string($photoPath) ? ltrim($photoPath, '/') : '';
        if ($photoPath !== '' && is_file(__DIR__ . '/../' . $photoPath)) {
            return ltrim($photo, '/');
        }

        // Legacy avatars from admin profile are saved under admin/img/perfil.
        if ($photoPath !== '' && strpos($photoPath, 'img/perfil/') === 0) {
            $adminPath = 'admin/' . $photoPath;
            if (is_file(__DIR__ . '/../' . $adminPath)) {
                $query = parse_url($photo, PHP_URL_QUERY);
                return $adminPath . ($query ? ('?' . $query) : '');
            }
        }

        return $fallback !== '' ? $fallback : $photo;
    }
}

// Canonical provider logo path convention (also used by
// inc/public_specialists.php::mt_home_specialists_fetch()): providers.logo
// commonly stores a bare filename uploaded under img/providers/{provider_id}/.
// Already-absolute URLs and already-complete relative paths pass through
// untouched; only a bare filename gets the directory prefix.
if (!function_exists('mt_provider_logo_public_path')) {
    function mt_provider_logo_public_path($logo, int $providerId)
    {
        $logo = trim((string)$logo);
        if ($logo === '') {
            return '';
        }
        if (strpos($logo, '://') !== false || strpos($logo, '/') !== false || $providerId <= 0) {
            return $logo;
        }
        return 'img/providers/' . $providerId . '/' . $logo;
    }
}
