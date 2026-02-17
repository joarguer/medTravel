<?php
if (!function_exists('menu_current_script')) {
    function menu_current_script() {
        static $current = null;
        if ($current !== null) {
            return $current;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $current = basename((string)$path);

        if ($current === '' || $current === '.' || $current === '/') {
            $fallback = parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH);
            $current = basename((string)$fallback);
        }

        if ($current === '' || $current === '.' || $current === '/') {
            $current = 'index.php';
        }

        return $current;
    }
}

if (!function_exists('menu_normalize_target')) {
    function menu_normalize_target($target) {
        $path = parse_url((string)$target, PHP_URL_PATH);
        $normalized = basename((string)$path);
        if ($normalized === '' || $normalized === '.' || $normalized === '/') {
            return (string)$target;
        }
        return $normalized;
    }
}

if (!function_exists('menu_is_active')) {
    function menu_is_active($targets) {
        $current = menu_current_script();
        $targets = is_array($targets) ? $targets : array($targets);
        foreach ($targets as $target) {
            if (menu_normalize_target($target) === $current) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('menu_li_class')) {
    function menu_li_class($targets, $base = '') {
        $classes = array();
        $base = trim((string)$base);
        if ($base !== '') {
            $classes = preg_split('/\s+/', $base);
        }

        if (menu_is_active($targets)) {
            $classes[] = 'active';
            $targetCount = is_array($targets) ? count($targets) : 1;
            if ($targetCount > 1) {
                $classes[] = 'selected';
                $classes[] = 'open';
            }
        }

        $classes = array_values(array_unique(array_filter($classes)));
        return empty($classes) ? '' : ' class="' . implode(' ', $classes) . '"';
    }
}
