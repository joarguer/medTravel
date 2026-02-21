<?php

if (!function_exists('mt_testimonials_fetch_approved')) {
    function mt_testimonials_fetch_approved($conexion, $limit = 6)
    {
        if (!isset($conexion) || !$conexion) {
            return [];
        }
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 6;
        }
        if ($limit > 12) {
            $limit = 12;
        }
        $rows = [];
        $sql = "SELECT id, client_user_id, client_name, client_location, rating, comment, avatar_path, approved_at\n                FROM testimonials\n                WHERE status = 'approved'\n                ORDER BY approved_at DESC, id DESC\n                LIMIT {$limit}";
        $res = mysqli_query($conexion, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('mt_testimonials_avatar_path')) {
    function mt_testimonials_avatar_path(array $row)
    {
        return trim((string)($row['avatar_path'] ?? ''));
    }
}

if (!function_exists('mt_testimonials_initial')) {
    function mt_testimonials_initial($name)
    {
        $name = trim((string)$name);
        if ($name === '') {
            return 'M';
        }
        $initial = strtoupper(substr($name, 0, 1));
        return $initial !== '' ? $initial : 'M';
    }
}

if (!function_exists('mt_testimonials_escape')) {
    function mt_testimonials_escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mt_testimonials_render_stars')) {
    function mt_testimonials_render_stars($rating)
    {
        $rating = (int)$rating;
        if ($rating < 1) {
            $rating = 1;
        }
        if ($rating > 5) {
            $rating = 5;
        }
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $class = ($i <= $rating) ? 'text-primary' : 'text-muted';
            $html .= '<i class="fas fa-star ' . $class . '"></i>';
        }
        return $html;
    }
}
