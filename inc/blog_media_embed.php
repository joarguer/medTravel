<?php

if (!function_exists('blog_normalize_video_url')) {
    function blog_normalize_video_url($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $url)) {
            return false;
        }

        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }

        $host = strtolower((string)$parts['host']);
        $host = preg_replace('~^www\.~', '', $host);
        $path = isset($parts['path']) ? trim((string)$parts['path']) : '';
        $pathSegments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        if ($host === 'youtu.be') {
            $videoId = trim((string)($pathSegments[0] ?? ''));
            return preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId)
                ? 'https://www.youtube.com/watch?v=' . $videoId
                : false;
        }

        if (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
            $videoId = '';
            if ($path === '/watch' && !empty($parts['query'])) {
                parse_str($parts['query'], $query);
                $videoId = trim((string)($query['v'] ?? ''));
            } elseif (($pathSegments[0] ?? '') === 'embed' || ($pathSegments[0] ?? '') === 'shorts') {
                $videoId = trim((string)($pathSegments[1] ?? ''));
            }
            return preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId)
                ? 'https://www.youtube.com/watch?v=' . $videoId
                : false;
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            $videoId = '';
            if (($pathSegments[0] ?? '') === 'video') {
                $videoId = trim((string)($pathSegments[1] ?? ''));
            } else {
                $videoId = trim((string)($pathSegments[count($pathSegments) - 1] ?? ''));
            }
            return preg_match('~^\d+$~', $videoId)
                ? 'https://vimeo.com/' . $videoId
                : false;
        }

        if (in_array($host, ['instagram.com', 'm.instagram.com', 'instagr.am'], true)) {
            $resourceType = strtolower(trim((string)($pathSegments[0] ?? '')));
            $shortcode = trim((string)($pathSegments[1] ?? ''));
            if (!in_array($resourceType, ['p', 'reel'], true)) {
                return false;
            }
            if (!preg_match('~^[A-Za-z0-9_-]+$~', $shortcode)) {
                return false;
            }
            return 'https://www.instagram.com/' . $resourceType . '/' . $shortcode . '/';
        }

        return false;
    }
}

if (!function_exists('blog_resolve_video_embed')) {
    function blog_resolve_video_embed($url)
    {
        $payload = [
            'provider' => '',
            'normalized_url' => '',
            'embed_mode' => '',
            'embed_url' => '',
            'external_url' => '',
            'requires_instagram_script' => false,
            'instagram_kind' => '',
            'instagram_permalink' => '',
        ];

        $normalized = blog_normalize_video_url($url);
        if ($normalized === '' || $normalized === false) {
            return $payload;
        }

        $payload['normalized_url'] = $normalized;
        $payload['external_url'] = $normalized;

        $parts = parse_url($normalized);
        $host = strtolower((string)($parts['host'] ?? ''));
        $host = preg_replace('~^www\.~', '', $host);
        $path = isset($parts['path']) ? trim((string)$parts['path']) : '';
        $pathSegments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        if ($host === 'youtube.com' && !empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $videoId = trim((string)($query['v'] ?? ''));
            if (preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId)) {
                $payload['provider'] = 'youtube';
                $payload['embed_mode'] = 'iframe';
                $payload['embed_url'] = 'https://www.youtube.com/embed/' . rawurlencode($videoId);
            }
            return $payload;
        }

        if ($host === 'vimeo.com') {
            $videoId = trim((string)basename((string)($parts['path'] ?? '')));
            if (preg_match('~^\d+$~', $videoId)) {
                $payload['provider'] = 'vimeo';
                $payload['embed_mode'] = 'iframe';
                $payload['embed_url'] = 'https://player.vimeo.com/video/' . rawurlencode($videoId);
            }
            return $payload;
        }

        if ($host === 'instagram.com') {
            $resourceType = strtolower(trim((string)($pathSegments[0] ?? '')));
            if (in_array($resourceType, ['p', 'reel'], true)) {
                $payload['provider'] = 'instagram';
                $payload['embed_mode'] = 'instagram';
                $payload['requires_instagram_script'] = true;
                $payload['instagram_kind'] = $resourceType === 'reel' ? 'reel' : 'post';
                $payload['instagram_permalink'] = $normalized . '?utm_source=ig_embed&utm_campaign=loading';
            }
            return $payload;
        }

        return $payload;
    }
}
