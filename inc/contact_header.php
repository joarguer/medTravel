<?php

if (!function_exists('mt_contact_header_defaults')) {
    function mt_contact_header_defaults()
    {
        return [
            'id' => 0,
            'title' => 'Contact Us',
            'subtitle' => 'Talk to MedTravel about providers, coordination, and booking support for your medical journey.',
            'bg_image' => '',
            'activo' => 0,
        ];
    }
}

if (!function_exists('mt_contact_header_table_exists')) {
    function mt_contact_header_table_exists($conexion)
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $exists = false;
        if (!$conexion) {
            return false;
        }

        $res = mysqli_query($conexion, "SHOW TABLES LIKE 'contact_header'");
        if ($res && mysqli_num_rows($res) > 0) {
            $exists = true;
        }
        if ($res) {
            mysqli_free_result($res);
        }

        return $exists;
    }
}

if (!function_exists('mt_contact_header_fetch')) {
    function mt_contact_header_fetch($conexion)
    {
        $defaults = mt_contact_header_defaults();
        if (!$conexion || !mt_contact_header_table_exists($conexion)) {
            return $defaults;
        }

        $query = mysqli_query($conexion, "SELECT id, title, subtitle, bg_image, activo FROM contact_header WHERE activo = 0 ORDER BY id ASC LIMIT 1");
        if (!$query) {
            return $defaults;
        }

        $row = mysqli_fetch_assoc($query);
        mysqli_free_result($query);
        if (!$row) {
            return $defaults;
        }

        return [
            'id' => isset($row['id']) ? (int)$row['id'] : 0,
            'title' => trim((string)($row['title'] ?? '')) !== '' ? (string)$row['title'] : $defaults['title'],
            'subtitle' => trim((string)($row['subtitle'] ?? '')) !== '' ? (string)$row['subtitle'] : $defaults['subtitle'],
            'bg_image' => (string)($row['bg_image'] ?? ''),
            'activo' => isset($row['activo']) ? (int)$row['activo'] : 0,
        ];
    }
}
