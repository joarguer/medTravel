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

if (!function_exists('booking_notification_fetch_assigned_staff_linked_user')) {
    function booking_notification_fetch_assigned_staff_linked_user($conexion, $staffId, $providerId = 0)
    {
        $staffId = (int)$staffId;
        $providerId = (int)$providerId;
        if ($staffId <= 0
            || !booking_notification_table_exists($conexion, 'provider_medical_staff')
            || !booking_notification_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')
            || !booking_notification_table_has_column($conexion, 'usuarios', 'id')
            || !booking_notification_table_has_column($conexion, 'usuarios', 'email')
        ) {
            return null;
        }

        $select = [
            'pms.id AS staff_id',
            booking_notification_table_has_column($conexion, 'provider_medical_staff', 'provider_id') ? 'pms.provider_id' : 'NULL AS provider_id',
            booking_notification_table_has_column($conexion, 'provider_medical_staff', 'full_name') ? 'pms.full_name' : "'' AS staff_name",
            'pms.linked_user_id',
            'u.id AS user_id',
            booking_notification_table_has_column($conexion, 'usuarios', 'email') ? 'u.email' : "'' AS email",
            booking_notification_table_has_column($conexion, 'usuarios', 'nombre') ? 'u.nombre' : "'' AS user_name",
            booking_notification_table_has_column($conexion, 'usuarios', 'activo') ? 'u.activo' : '1 AS activo',
        ];

        $sql = 'SELECT ' . implode(', ', $select) . '
                  FROM provider_medical_staff pms
                  INNER JOIN usuarios u ON u.id = pms.linked_user_id
                 WHERE pms.id = ?';
        if ($providerId > 0 && booking_notification_table_has_column($conexion, 'provider_medical_staff', 'provider_id')) {
            $sql .= ' AND pms.provider_id = ?';
        }
        if (booking_notification_table_has_column($conexion, 'usuarios', 'is_deleted')) {
            $sql .= ' AND COALESCE(u.is_deleted, 0) = 0';
        }
        if (booking_notification_table_has_column($conexion, 'usuarios', 'activo')) {
            $sql .= ' AND COALESCE(u.activo, 0) = 1';
        }
        $sql .= ' AND u.id <> 1 LIMIT 1';

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return null;
        }
        if ($providerId > 0 && booking_notification_table_has_column($conexion, 'provider_medical_staff', 'provider_id')) {
            mysqli_stmt_bind_param($stmt, 'ii', $staffId, $providerId);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $staffId);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return null;
        }

        $row['staff_id'] = (int)($row['staff_id'] ?? 0);
        $row['provider_id'] = (int)($row['provider_id'] ?? 0);
        $row['linked_user_id'] = (int)($row['linked_user_id'] ?? 0);
        $row['user_id'] = (int)($row['user_id'] ?? 0);
        $row['email'] = strtolower(trim((string)($row['email'] ?? '')));
        $row['staff_name'] = trim((string)($row['staff_name'] ?? ''));
        $row['user_name'] = trim((string)($row['user_name'] ?? ''));
        return $row;
    }
}

if (!function_exists('booking_notification_fetch_provider_institutional_email')) {
    function booking_notification_fetch_provider_institutional_email($conexion, $providerId)
    {
        $providerId = (int)$providerId;
        if ($providerId <= 0
            || !booking_notification_table_exists($conexion, 'providers')
            || !booking_notification_table_has_column($conexion, 'providers', 'email')
        ) {
            return '';
        }

        $stmt = mysqli_prepare($conexion, "SELECT email FROM providers WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'i', $providerId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $email = strtolower(trim((string)($row['email'] ?? '')));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('booking_notification_fetch_item_medical_owner_context')) {
    function booking_notification_fetch_item_medical_owner_context($conexion, $itemId)
    {
        $itemId = (int)$itemId;
        if ($itemId <= 0 || !booking_notification_table_exists($conexion, 'booking_request_items')) {
            return [];
        }

        $select = [];
        if (booking_notification_table_has_column($conexion, 'booking_request_items', 'provider_id')) {
            $select[] = 'provider_id';
        }
        if (booking_notification_table_has_column($conexion, 'booking_request_items', 'assigned_staff_id')) {
            $select[] = 'assigned_staff_id';
        }
        if (empty($select)) {
            return [];
        }

        $stmt = mysqli_prepare($conexion, 'SELECT ' . implode(', ', $select) . ' FROM booking_request_items WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $itemId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return is_array($row) ? $row : [];
    }
}

if (!function_exists('booking_notification_resolve_medical_offer_recipient')) {
    function booking_notification_resolve_medical_offer_recipient($conexion, $itemId, array $item = [])
    {
        $itemId = (int)$itemId;
        $context = array_merge(
            booking_notification_fetch_item_medical_owner_context($conexion, $itemId),
            $item
        );

        $providerId = (int)($context['provider_id'] ?? 0);
        $assignedStaffId = (int)($context['assigned_staff_id'] ?? 0);

        $result = [
            'ok' => false,
            'email' => '',
            'recipient_type' => '',
            'source' => '',
            'actor_id' => 0,
            'item_id' => $itemId,
            'provider_id' => $providerId,
            'assigned_staff_id' => $assignedStaffId,
            'skip_reason' => 'medical_offer_recipient_not_found',
        ];

        if ($assignedStaffId > 0) {
            $staffUser = booking_notification_fetch_assigned_staff_linked_user($conexion, $assignedStaffId, $providerId);
            $staffEmail = strtolower(trim((string)($staffUser['email'] ?? '')));
            if (filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
                $result['ok'] = true;
                $result['email'] = $staffEmail;
                $result['recipient_type'] = 'assigned_staff';
                $result['source'] = 'provider_medical_staff.linked_user_id -> usuarios.email';
                $result['actor_id'] = (int)($staffUser['staff_id'] ?? $assignedStaffId);
                $result['linked_user_id'] = (int)($staffUser['linked_user_id'] ?? 0);
                $result['skip_reason'] = '';
                return $result;
            }
        }

        if ($providerId > 0) {
            $ownerUser = booking_notification_fetch_provider_owner_user($conexion, $providerId);
            $ownerEmail = strtolower(trim((string)($ownerUser['email'] ?? '')));
            if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $result['ok'] = true;
                $result['email'] = $ownerEmail;
                $result['recipient_type'] = 'provider_owner_admin';
                $result['source'] = 'provider_owner_admin.' . (string)($ownerUser['owner_source'] ?? 'unknown');
                $result['actor_id'] = (int)($ownerUser['id'] ?? 0);
                $result['skip_reason'] = '';
                return $result;
            }

            $providerEmail = booking_notification_fetch_provider_institutional_email($conexion, $providerId);
            if (filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
                $result['ok'] = true;
                $result['email'] = $providerEmail;
                $result['recipient_type'] = 'provider_institutional';
                $result['source'] = 'providers.email';
                $result['actor_id'] = $providerId;
                $result['skip_reason'] = '';
                return $result;
            }
        }

        if ($providerId <= 0) {
            $result['skip_reason'] = 'medical_offer_provider_missing';
        } elseif ($assignedStaffId > 0) {
            $result['skip_reason'] = 'medical_offer_staff_owner_provider_email_not_found';
        } else {
            $result['skip_reason'] = 'medical_offer_owner_provider_email_not_found';
        }

        return $result;
    }
}
