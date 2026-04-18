<?php

if (!function_exists('mt_provider_public_link_specs')) {
    function mt_provider_public_link_specs()
    {
        return [
            'website' => [
                'column' => 'website',
                'label' => 'Website',
                'icon_class' => 'fas fa-globe-americas',
                'allowed_hosts' => [],
            ],
            'instagram_url' => [
                'column' => 'instagram_url',
                'label' => 'Instagram',
                'icon_class' => 'fab fa-instagram',
                'allowed_hosts' => ['instagram.com', 'www.instagram.com'],
            ],
            'facebook_url' => [
                'column' => 'facebook_url',
                'label' => 'Facebook',
                'icon_class' => 'fab fa-facebook-f',
                'allowed_hosts' => ['facebook.com', 'www.facebook.com', 'fb.me'],
            ],
            'linkedin_url' => [
                'column' => 'linkedin_url',
                'label' => 'LinkedIn',
                'icon_class' => 'fab fa-linkedin-in',
                'allowed_hosts' => ['linkedin.com', 'www.linkedin.com'],
            ],
            'youtube_url' => [
                'column' => 'youtube_url',
                'label' => 'YouTube',
                'icon_class' => 'fab fa-youtube',
                'allowed_hosts' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
            ],
            'whatsapp_url' => [
                'column' => 'whatsapp_url',
                'label' => 'WhatsApp',
                'icon_class' => 'fab fa-whatsapp',
                'allowed_hosts' => ['wa.me', 'api.whatsapp.com'],
            ],
        ];
    }
}

if (!function_exists('mt_provider_public_link_fields')) {
    function mt_provider_public_link_fields()
    {
        return array_keys(mt_provider_public_link_specs());
    }
}

if (!function_exists('mt_provider_public_link_label')) {
    function mt_provider_public_link_label($field)
    {
        $specs = mt_provider_public_link_specs();
        return isset($specs[$field]['label']) ? $specs[$field]['label'] : 'Provider link';
    }
}

if (!function_exists('mt_provider_public_link_host_allowed')) {
    function mt_provider_public_link_host_allowed($field, $host)
    {
        $specs = mt_provider_public_link_specs();
        if (!isset($specs[$field])) {
            return false;
        }

        $allowedHosts = isset($specs[$field]['allowed_hosts']) ? (array)$specs[$field]['allowed_hosts'] : [];
        if (empty($allowedHosts)) {
            return true;
        }

        $host = strtolower(trim((string)$host));
        return in_array($host, $allowedHosts, true);
    }
}

if (!function_exists('mt_provider_public_link_validate')) {
    function mt_provider_public_link_validate($field, $url)
    {
        $normalized = trim((string)$url);
        if ($normalized === '') {
            return [
                'valid' => true,
                'normalized' => '',
                'message' => '',
            ];
        }

        if (!filter_var($normalized, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'normalized' => $normalized,
                'message' => mt_provider_public_link_label($field) . ' debe ser una URL válida.',
            ];
        }

        $parts = @parse_url($normalized);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return [
                'valid' => false,
                'normalized' => $normalized,
                'message' => mt_provider_public_link_label($field) . ' debe iniciar con http o https.',
            ];
        }

        if (!mt_provider_public_link_host_allowed($field, $host)) {
            return [
                'valid' => false,
                'normalized' => $normalized,
                'message' => mt_provider_public_link_label($field) . ' debe usar un dominio permitido para ese canal.',
            ];
        }

        return [
            'valid' => true,
            'normalized' => $normalized,
            'message' => '',
        ];
    }
}

if (!function_exists('mt_provider_public_card_links')) {
    function mt_provider_public_card_links(array $row, array $fields = ['website', 'instagram_url'])
    {
        $specs = mt_provider_public_link_specs();
        $links = [];

        foreach ($fields as $field) {
            if (!isset($specs[$field])) {
                continue;
            }

            $column = $specs[$field]['column'];
            $rawValue = isset($row[$column]) ? $row[$column] : (isset($row[$field]) ? $row[$field] : '');
            $validation = mt_provider_public_link_validate($field, $rawValue);
            if (!$validation['valid'] || $validation['normalized'] === '') {
                continue;
            }

            $links[] = [
                'key' => $field,
                'url' => $validation['normalized'],
                'label' => $specs[$field]['label'],
                'icon_class' => $specs[$field]['icon_class'],
            ];
        }

        return $links;
    }
}
