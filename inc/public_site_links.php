<?php

if (!function_exists('mt_public_terms_url')) {
    function mt_public_terms_url(): string
    {
        return '/terms.php';
    }
}

if (!function_exists('mt_public_privacy_url')) {
    function mt_public_privacy_url(): string
    {
        return '/privacy/';
    }
}

if (!function_exists('mt_public_social_links')) {
    function mt_public_social_links(): array
    {
        return [
            [
                'key' => 'facebook',
                'label' => 'Facebook',
                'icon' => 'fa-facebook-f',
                'url' => 'https://www.facebook.com/share/181t1WGHUw/?mibextid=wwXIfr',
            ],
            [
                'key' => 'instagram',
                'label' => 'Instagram',
                'icon' => 'fa-instagram',
                'url' => 'https://www.instagram.com/medtravel.usa?igsh=NzJoc2Y1dTdmdDBx&utm_source=qr',
            ],
            [
                'key' => 'whatsapp',
                'label' => 'WhatsApp',
                'icon' => 'fa-whatsapp',
                'url' => 'https://wa.me/573502431667',
            ],
        ];
    }
}

if (!function_exists('mt_role_id_from_user_row')) {
    function mt_role_id_from_user_row(array $row): ?int
    {
        if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '' && is_numeric($row['role_id'])) {
            return (int)$row['role_id'];
        }

        $rol = isset($row['rol']) ? strtolower(trim((string)$row['rol'])) : '';
        if ($rol === '') {
            return null;
        }
        if (is_numeric($rol)) {
            return (int)$rol;
        }
        if (strpos($rol, 'cliente') !== false || strpos($rol, 'client') !== false) {
            return 3;
        }
        if (strpos($rol, 'provider_admin') !== false || strpos($rol, 'prestador_admin') !== false) {
            return 12;
        }
        if (strpos($rol, 'provider') !== false || strpos($rol, 'prestador') !== false) {
            return 4;
        }
        if (strpos($rol, 'administrative') !== false) {
            return 2;
        }
        if (strpos($rol, 'admin') !== false) {
            return 1;
        }

        return null;
    }
}

if (!function_exists('mt_user_has_pending_client_terms')) {
    function mt_user_has_pending_client_terms(array $row): bool
    {
        $roleId = mt_role_id_from_user_row($row);
        if ($roleId !== 3) {
            return false;
        }
        if (!empty($row['provider_id']) || !empty($row['service_provider_id'])) {
            return false;
        }
        if (isset($row['ppal']) && (int)$row['ppal'] === 1) {
            return false;
        }

        return (int)($row['terms_accepted'] ?? 1) !== 1;
    }
}

if (!function_exists('mt_pending_terms_notice_payload')) {
    function mt_pending_terms_notice_payload(): array
    {
        return [
            'title' => 'First-time portal activation',
            'body' => 'After signing in, you will be asked to review and accept the MedTravel Terms and Conditions and Privacy Policy to complete activation of your patient portal.',
            'terms_url' => mt_public_terms_url(),
            'privacy_url' => mt_public_privacy_url(),
        ];
    }
}
