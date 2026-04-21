<?php

if (!function_exists('booking_notification_table_exists')) {
    function booking_notification_table_exists($conexion, $table)
    {
        static $cache = [];
        $table = trim((string)$table);
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $tableEsc = mysqli_real_escape_string($conexion, $table);
        $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
        $cache[$table] = ($q && mysqli_num_rows($q) > 0);
        return $cache[$table];
    }
}

if (!function_exists('booking_notification_table_has_column')) {
    function booking_notification_table_has_column($conexion, $table, $column)
    {
        static $cache = [];
        $key = trim((string)$table) . '.' . trim((string)$column);
        if ($key === '.') {
            return false;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $tableEsc = mysqli_real_escape_string($conexion, (string)$table);
        $columnEsc = mysqli_real_escape_string($conexion, (string)$column);
        $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
        $cache[$key] = ($q && mysqli_num_rows($q) > 0);
        return $cache[$key];
    }
}

if (!function_exists('booking_notification_provider_owner_mapping_table_ready')) {
    function booking_notification_provider_owner_mapping_table_ready($conexion)
    {
        return booking_notification_table_exists($conexion, 'provider_users')
            && booking_notification_table_has_column($conexion, 'provider_users', 'provider_id')
            && booking_notification_table_has_column($conexion, 'provider_users', 'user_id')
            && booking_notification_table_has_column($conexion, 'provider_users', 'role_in_provider');
    }
}

if (!function_exists('booking_notification_provider_owner_role_priority_sql')) {
    function booking_notification_provider_owner_role_priority_sql()
    {
        return "CASE LOWER(COALESCE(NULLIF(TRIM(pu.role_in_provider), ''), 'owner'))
                    WHEN 'owner' THEN 0
                    WHEN 'primary' THEN 1
                    WHEN 'principal' THEN 2
                    WHEN 'admin' THEN 3
                    WHEN 'administrator' THEN 4
                    ELSE 10
                END";
    }
}

if (!function_exists('booking_notification_fetch_provider_owner_user')) {
    function booking_notification_fetch_provider_owner_user($conexion, $providerId)
    {
        $providerId = (int)$providerId;
        if ($providerId <= 0 || !booking_notification_table_has_column($conexion, 'usuarios', 'id')) {
            return null;
        }

        $roleProviderAdmin = defined('ROLE_PROVIDER_ADMIN') ? (int)ROLE_PROVIDER_ADMIN : 12;
        $roleProvider = defined('ROLE_PROVIDER') ? (int)ROLE_PROVIDER : 4;

        $select = [
            'u.id',
            booking_notification_table_has_column($conexion, 'usuarios', 'nombre') ? 'u.nombre' : "'' AS nombre",
            booking_notification_table_has_column($conexion, 'usuarios', 'usuario') ? 'u.usuario' : "'' AS usuario",
            booking_notification_table_has_column($conexion, 'usuarios', 'email') ? 'u.email' : "'' AS email",
            booking_notification_table_has_column($conexion, 'usuarios', 'telefono') ? 'u.telefono' : "'' AS telefono",
            booking_notification_table_has_column($conexion, 'usuarios', 'activo') ? 'u.activo' : '1 AS activo',
            booking_notification_table_has_column($conexion, 'usuarios', 'provider_id') ? 'u.provider_id' : 'NULL AS provider_id',
            booking_notification_table_has_column($conexion, 'usuarios', 'service_provider_id') ? 'u.service_provider_id' : 'NULL AS service_provider_id',
            booking_notification_table_has_column($conexion, 'usuarios', 'role_id') ? 'u.role_id' : 'NULL AS role_id',
            booking_notification_table_has_column($conexion, 'usuarios', 'rol') ? 'u.rol' : 'NULL AS rol',
            booking_notification_table_has_column($conexion, 'usuarios', 'ppal') ? 'u.ppal' : '0 AS ppal',
        ];

        if (booking_notification_provider_owner_mapping_table_ready($conexion)) {
            $sql = 'SELECT ' . implode(', ', $select) . ', pu.role_in_provider
                      FROM provider_users pu
                      INNER JOIN usuarios u ON u.id = pu.user_id
                     WHERE pu.provider_id = ?
                       AND u.id <> 1';
            if (booking_notification_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
                $sql .= ' AND COALESCE(u.service_provider_id, 0) = 0';
            }
            if (booking_notification_table_has_column($conexion, 'usuarios', 'is_deleted')) {
                $sql .= ' AND COALESCE(u.is_deleted, 0) = 0';
            }
            $sql .= ' ORDER BY ' . booking_notification_provider_owner_role_priority_sql() . ', u.id ASC LIMIT 1';

            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $providerId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if ($row) {
                    $row['owner_source'] = 'provider_users';
                    return $row;
                }
            }
        }

        if (!booking_notification_table_has_column($conexion, 'usuarios', 'provider_id')) {
            return null;
        }

        $sql = 'SELECT ' . implode(', ', $select) . "
                  FROM usuarios u
                 WHERE u.provider_id = ?
                   AND u.id <> 1";
        if (booking_notification_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
            $sql .= ' AND COALESCE(u.service_provider_id, 0) = 0';
        }
        if (booking_notification_table_has_column($conexion, 'usuarios', 'is_deleted')) {
            $sql .= ' AND COALESCE(u.is_deleted, 0) = 0';
        }

        $ppalPriority = booking_notification_table_has_column($conexion, 'usuarios', 'ppal')
            ? 'CASE WHEN COALESCE(u.ppal, 0) = 1 THEN 0 ELSE 1 END'
            : '1';
        $rolePriority = booking_notification_table_has_column($conexion, 'usuarios', 'role_id')
            ? 'CASE WHEN u.role_id = ' . $roleProviderAdmin . ' THEN 0 WHEN u.role_id = ' . $roleProvider . ' THEN 1 ELSE 5 END'
            : '5';
        $sql .= ' ORDER BY ' . $ppalPriority . ', ' . $rolePriority . ', u.id ASC LIMIT 1';

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $providerId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            $row['owner_source'] = 'legacy_fallback';
        }
        return $row ?: null;
    }
}
