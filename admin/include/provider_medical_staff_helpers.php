<?php

if (!function_exists('provider_staff_table_exists')) {
    function provider_staff_table_exists($conexion)
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (!$conexion) {
            $cache = false;
            return $cache;
        }
        $res = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_medical_staff'");
        $cache = ($res && mysqli_num_rows($res) > 0);
        return $cache;
    }
}

if (!function_exists('provider_staff_table_has_column')) {
    function provider_staff_table_has_column($conexion, $column)
    {
        static $cache = [];
        $key = 'provider_medical_staff.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!$conexion || !provider_staff_table_exists($conexion)) {
            $cache[$key] = false;
            return $cache[$key];
        }
        $columnEsc = mysqli_real_escape_string($conexion, $column);
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM `provider_medical_staff` LIKE '{$columnEsc}'");
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        return $cache[$key];
    }
}

if (!function_exists('provider_staff_status_column_name')) {
    function provider_staff_status_column_name($conexion)
    {
        if (provider_staff_table_has_column($conexion, 'is_active')) {
            return 'is_active';
        }
        if (provider_staff_table_has_column($conexion, 'active')) {
            return 'active';
        }
        return '';
    }
}

if (!function_exists('provider_staff_has_legacy_active_column')) {
    function provider_staff_has_legacy_active_column($conexion)
    {
        return provider_staff_table_has_column($conexion, 'active');
    }
}

if (!function_exists('provider_staff_sort_column_name')) {
    function provider_staff_sort_column_name($conexion)
    {
        if (provider_staff_table_has_column($conexion, 'sort_order')) {
            return 'sort_order';
        }
        return '';
    }
}

if (!function_exists('provider_staff_status_select_expr')) {
    function provider_staff_status_select_expr($conexion, $alias = 'pms')
    {
        $column = provider_staff_status_column_name($conexion);
        if ($column === '') {
            return 'CAST(1 AS UNSIGNED)';
        }
        return $alias . '.`' . $column . '`';
    }
}

if (!function_exists('provider_staff_sort_select_expr')) {
    function provider_staff_sort_select_expr($conexion, $alias = 'pms')
    {
        $column = provider_staff_sort_column_name($conexion);
        if ($column === '') {
            return 'CAST(0 AS SIGNED)';
        }
        return $alias . '.`' . $column . '`';
    }
}

if (!function_exists('provider_staff_select_columns')) {
    function provider_staff_select_columns($conexion, $alias = 'pms')
    {
        $columns = [
            $alias . '.id',
            $alias . '.provider_id',
            $alias . '.full_name',
            provider_staff_table_has_column($conexion, 'role_title') ? ($alias . '.role_title') : "'' AS role_title",
            provider_staff_table_has_column($conexion, 'specialty') ? ($alias . '.specialty') : "'' AS specialty",
            provider_staff_table_has_column($conexion, 'bio_short')
                ? ($alias . '.bio_short')
                : (provider_staff_table_has_column($conexion, 'notes') ? ($alias . '.notes AS bio_short') : 'NULL AS bio_short'),
            provider_staff_table_has_column($conexion, 'photo') ? ($alias . '.photo') : 'NULL AS photo',
            provider_staff_table_has_column($conexion, 'phone') ? ($alias . '.phone') : "'' AS phone",
            provider_staff_table_has_column($conexion, 'email') ? ($alias . '.email') : "'' AS email",
            provider_staff_table_has_column($conexion, 'is_primary_doctor') ? ($alias . '.is_primary_doctor') : '0 AS is_primary_doctor',
            provider_staff_status_select_expr($conexion, $alias) . ' AS is_active',
            provider_staff_table_has_column($conexion, 'allow_home_publication') ? ($alias . '.allow_home_publication') : '0 AS allow_home_publication',
            provider_staff_sort_select_expr($conexion, $alias) . ' AS sort_order',
            provider_staff_table_has_column($conexion, 'professional_license') ? ($alias . '.professional_license') : 'NULL AS professional_license',
            provider_staff_table_has_column($conexion, 'clinic_name') ? ($alias . '.clinic_name') : 'NULL AS clinic_name',
            provider_staff_table_has_column($conexion, 'notes') ? ($alias . '.notes') : 'NULL AS notes',
            provider_staff_table_has_column($conexion, 'linked_user_id') ? ($alias . '.linked_user_id') : 'NULL AS linked_user_id',
            provider_staff_table_has_column($conexion, 'can_access_admin') ? ($alias . '.can_access_admin') : '0 AS can_access_admin',
            provider_staff_table_has_column($conexion, 'created_at') ? ($alias . '.created_at') : 'NULL AS created_at',
            provider_staff_table_has_column($conexion, 'updated_at') ? ($alias . '.updated_at') : 'NULL AS updated_at',
        ];
        if (provider_staff_has_legacy_active_column($conexion)) {
            $columns[] = $alias . '.active';
        }
        return $columns;
    }
}

if (!function_exists('provider_staff_normalize_row')) {
    function provider_staff_normalize_row($row)
    {
        $row = is_array($row) ? $row : [];
        $row['full_name'] = trim((string)($row['full_name'] ?? ''));
        $row['role_title'] = trim((string)($row['role_title'] ?? ''));
        $row['specialty'] = trim((string)($row['specialty'] ?? ''));
        $row['bio_short'] = trim((string)($row['bio_short'] ?? $row['notes'] ?? ''));
        $row['photo'] = trim((string)($row['photo'] ?? ''));
        $row['phone'] = trim((string)($row['phone'] ?? ''));
        $row['email'] = trim((string)($row['email'] ?? ''));
        $row['professional_license'] = trim((string)($row['professional_license'] ?? ''));
        $row['clinic_name'] = trim((string)($row['clinic_name'] ?? ''));
        $row['notes'] = (string)($row['notes'] ?? '');
        $row['is_primary_doctor'] = ((int)($row['is_primary_doctor'] ?? 0) === 1) ? 1 : 0;
        $row['is_active'] = ((int)($row['is_active'] ?? $row['active'] ?? 1) === 1) ? 1 : 0;
        $row['allow_home_publication'] = ((int)($row['allow_home_publication'] ?? 0) === 1) ? 1 : 0;
        $row['active'] = $row['is_active'];
        $row['sort_order'] = isset($row['sort_order']) && $row['sort_order'] !== null && $row['sort_order'] !== ''
            ? (int)$row['sort_order']
            : 0;
        $row['primary_label'] = $row['is_primary_doctor'] === 1 ? 'Sí' : 'No';
        $row['active_label'] = $row['is_active'] === 1 ? 'Activo' : 'Inactivo';
        return $row;
    }
}

if (!function_exists('provider_staff_fetch_basic_row')) {
    function provider_staff_fetch_basic_row($conexion, $staffId, $providerId = 0)
    {
        static $cache = [];
        $staffId = (int)$staffId;
        $providerId = (int)$providerId;
        if ($staffId <= 0 || !$conexion || !provider_staff_table_exists($conexion)) {
            return null;
        }
        $cacheKey = $staffId . ':' . $providerId;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $sql = 'SELECT ' . implode(', ', provider_staff_select_columns($conexion, 'pms')) . '
                FROM provider_medical_staff pms
                WHERE pms.id = ?';
        if ($providerId > 0) {
            $sql .= ' AND pms.provider_id = ?';
        }
        $sql .= ' LIMIT 1';

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            $cache[$cacheKey] = null;
            return null;
        }
        if ($providerId > 0) {
            mysqli_stmt_bind_param($stmt, 'ii', $staffId, $providerId);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $staffId);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        $cache[$cacheKey] = $row ? provider_staff_normalize_row($row) : null;
        return $cache[$cacheKey];
    }
}

if (!function_exists('provider_staff_apply_assignment_payload')) {
    function provider_staff_apply_assignment_payload($conexion, $row)
    {
        $row = is_array($row) ? $row : [];
        $assignedStaffId = (int)($row['assigned_staff_id'] ?? 0);
        if ($assignedStaffId <= 0) {
            return $row;
        }

        $providerId = (int)($row['provider_id'] ?? 0);
        $staff = provider_staff_fetch_basic_row($conexion, $assignedStaffId, $providerId);
        if (!$staff) {
            return $row;
        }

        $row['assigned_staff'] = [
            'id' => (int)$staff['id'],
            'full_name' => (string)$staff['full_name'],
            'role_title' => (string)($staff['role_title'] ?? ''),
            'specialty' => (string)($staff['specialty'] ?? ''),
            'photo' => (string)($staff['photo'] ?? ''),
            'clinic_name' => (string)($staff['clinic_name'] ?? ''),
            'is_primary_doctor' => (int)($staff['is_primary_doctor'] ?? 0),
            'is_active' => (int)($staff['is_active'] ?? 1),
        ];

        $assignedDoctor = trim((string)($row['assigned_doctor'] ?? ''));
        if ($assignedDoctor === '') {
            $row['assigned_doctor'] = (string)$staff['full_name'];
        }

        $clinic = trim((string)($row['clinic'] ?? ''));
        if ($clinic === '' && trim((string)($staff['clinic_name'] ?? '')) !== '') {
            $row['clinic'] = (string)$staff['clinic_name'];
        }

        return $row;
    }
}
