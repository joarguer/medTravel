<?php

if (!function_exists('dd_table_exists')) {
    function dd_table_exists($conexion, $table)
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $tableEsc = mysqli_real_escape_string($conexion, $table);
        $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
        $cache[$table] = ($res && mysqli_num_rows($res) > 0);
        return $cache[$table];
    }
}

if (!function_exists('dd_column_exists')) {
    function dd_column_exists($conexion, $table, $column)
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!dd_table_exists($conexion, $table)) {
            $cache[$key] = false;
            return false;
        }
        $tableEsc = mysqli_real_escape_string($conexion, $table);
        $columnEsc = mysqli_real_escape_string($conexion, $column);
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        return $cache[$key];
    }
}

if (!function_exists('dd_bind_stmt_params')) {
    function dd_bind_stmt_params($stmt, $types, &$values)
    {
        if ($types === '' || empty($values)) {
            return true;
        }
        $bind = [$types];
        foreach ($values as $k => &$v) {
            $bind[] = &$v;
        }
        return call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!function_exists('dd_safe_trim')) {
    function dd_safe_trim($value, $maxLen = 0)
    {
        $value = trim((string)$value);
        if ($maxLen > 0 && strlen($value) > $maxLen) {
            return substr($value, 0, $maxLen);
        }
        return $value;
    }
}

if (!function_exists('dd_normalize_phone')) {
    function dd_normalize_phone($phone)
    {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        return $digits === null ? '' : $digits;
    }
}

if (!function_exists('dd_phone_digits_sql')) {
    function dd_phone_digits_sql($columnExpr)
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(IFNULL({$columnExpr}, '')), ' ', ''), '+', ''), '-', ''), '(', ''), ')', ''), '.', '')";
    }
}

if (!function_exists('dd_build_in_clause')) {
    function dd_build_in_clause($values, &$types, &$params, $paramType = 'i')
    {
        $values = array_values(array_filter($values, function ($v) {
            return $v !== null && $v !== '';
        }));
        if (empty($values)) {
            return '';
        }
        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = '?';
            $types .= $paramType;
            $params[] = $value;
        }
        return implode(',', $placeholders);
    }
}

if (!function_exists('dd_unique_int_ids')) {
    function dd_unique_int_ids($values)
    {
        $values = array_values(array_unique(array_map('intval', (array)$values)));
        return array_values(array_filter($values, function ($v) {
            return $v > 0;
        }));
    }
}

if (!function_exists('dd_select_ids_by_identifiers')) {
    function dd_select_ids_by_identifiers($idsByEmail, $idsByPhone, $hasEmail, $hasPhone)
    {
        $idsByEmail = dd_unique_int_ids($idsByEmail);
        $idsByPhone = dd_unique_int_ids($idsByPhone);

        if ($hasEmail && $hasPhone) {
            if (!empty($idsByEmail) && !empty($idsByPhone)) {
                $intersection = dd_unique_int_ids(array_values(array_intersect($idsByEmail, $idsByPhone)));
                if (!empty($intersection)) {
                    return [$intersection, 'both_intersection'];
                }
                return [[], 'both_conflict_no_intersection'];
            }
            if (!empty($idsByEmail)) {
                return [$idsByEmail, 'email_only_fallback'];
            }
            if (!empty($idsByPhone)) {
                return [$idsByPhone, 'phone_only_fallback'];
            }
            return [[], 'none'];
        }

        if ($hasEmail) {
            return [$idsByEmail, 'email_only'];
        }
        if ($hasPhone) {
            return [$idsByPhone, 'phone_only'];
        }
        return [[], 'none'];
    }
}

if (!function_exists('dd_exec_stmt')) {
    function dd_exec_stmt($conexion, $sql, $types = '', $params = [], $returnResult = false)
    {
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new RuntimeException('db_prepare_error');
        }
        if ($types !== '') {
            dd_bind_stmt_params($stmt, $types, $params);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException('db_execute_error: ' . $err);
        }
        if ($returnResult) {
            $res = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
            return $res;
        }
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected;
    }
}

if (!function_exists('dd_ensure_requests_schema')) {
    function dd_ensure_requests_schema($conexion)
    {
        if (!dd_table_exists($conexion, 'data_deletion_requests')) {
            $createSql = "CREATE TABLE data_deletion_requests (
                id INT(11) NOT NULL AUTO_INCREMENT,
                request_id VARCHAR(64) NOT NULL,
                request_email VARCHAR(255) DEFAULT NULL,
                request_phone VARCHAR(80) DEFAULT NULL,
                request_name VARCHAR(255) DEFAULT NULL,
                request_message TEXT DEFAULT NULL,
                request_ip VARCHAR(64) DEFAULT NULL,
                request_user_agent VARCHAR(512) DEFAULT NULL,
                status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
                processed_at DATETIME DEFAULT NULL,
                processed_by_user_id INT(11) DEFAULT NULL,
                result_summary TEXT DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_data_deletion_request_id (request_id),
                KEY idx_data_deletion_status_created (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            if (!mysqli_query($conexion, $createSql)) {
                throw new RuntimeException('schema_create_failed');
            }
            return;
        }

        $alterStatements = [];
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'request_id')) {
            $alterStatements[] = "ADD COLUMN request_id VARCHAR(64) NOT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'request_email')) {
            $alterStatements[] = "ADD COLUMN request_email VARCHAR(255) DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'request_phone')) {
            $alterStatements[] = "ADD COLUMN request_phone VARCHAR(80) DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'request_name')) {
            $alterStatements[] = "ADD COLUMN request_name VARCHAR(255) DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'request_message')) {
            $alterStatements[] = "ADD COLUMN request_message TEXT DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'request_ip')) {
            $alterStatements[] = "ADD COLUMN request_ip VARCHAR(64) DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'request_user_agent')) {
            $alterStatements[] = "ADD COLUMN request_user_agent VARCHAR(512) DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'status')) {
            $alterStatements[] = "ADD COLUMN status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending'";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'processed_at')) {
            $alterStatements[] = "ADD COLUMN processed_at DATETIME DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'processed_by_user_id')) {
            $alterStatements[] = "ADD COLUMN processed_by_user_id INT(11) DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'result_summary')) {
            $alterStatements[] = "ADD COLUMN result_summary TEXT DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'last_error')) {
            $alterStatements[] = "ADD COLUMN last_error TEXT DEFAULT NULL";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'created_at')) {
            $alterStatements[] = "ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'updated_at')) {
            $alterStatements[] = "ADD COLUMN updated_at DATETIME DEFAULT NULL";
        }

        if (!empty($alterStatements)) {
            $alterSql = "ALTER TABLE data_deletion_requests " . implode(', ', $alterStatements);
            if (!mysqli_query($conexion, $alterSql)) {
                throw new RuntimeException('schema_alter_failed');
            }
        }

        $indexCheck = mysqli_query($conexion, "SHOW INDEX FROM data_deletion_requests WHERE Key_name = 'uq_data_deletion_request_id'");
        if (!$indexCheck || mysqli_num_rows($indexCheck) < 1) {
            @mysqli_query($conexion, "ALTER TABLE data_deletion_requests ADD UNIQUE KEY uq_data_deletion_request_id (request_id)");
        }

        $statusIndexCheck = mysqli_query($conexion, "SHOW INDEX FROM data_deletion_requests WHERE Key_name = 'idx_data_deletion_status_created'");
        if (!$statusIndexCheck || mysqli_num_rows($statusIndexCheck) < 1) {
            @mysqli_query($conexion, "ALTER TABLE data_deletion_requests ADD INDEX idx_data_deletion_status_created (status, created_at)");
        }
    }
}

if (!function_exists('dd_generate_request_id')) {
    function dd_generate_request_id()
    {
        return 'DDR-' . date('Ymd-His') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('dd_create_request')) {
    function dd_create_request($conexion, $payload)
    {
        dd_ensure_requests_schema($conexion);

        $requestId = dd_generate_request_id();
        $email = dd_safe_trim($payload['email'] ?? '', 255);
        $phone = dd_safe_trim($payload['phone'] ?? '', 80);
        $name = dd_safe_trim($payload['name'] ?? '', 255);
        $message = dd_safe_trim($payload['message'] ?? '', 5000);
        $ip = dd_safe_trim($payload['ip'] ?? '', 64);
        $ua = dd_safe_trim($payload['user_agent'] ?? '', 512);

        $sql = "INSERT INTO data_deletion_requests
                (request_id, request_email, request_phone, request_name, request_message, request_ip, request_user_agent, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())";
        $params = [$requestId, $email, $phone, $name, $message, $ip, $ua];
        dd_exec_stmt($conexion, $sql, 'sssssss', $params);

        return $requestId;
    }
}

if (!function_exists('dd_fetch_requests')) {
    function dd_fetch_requests($conexion, $limit = 200)
    {
        dd_ensure_requests_schema($conexion);

        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 200;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        $sql = "SELECT
                    id,
                    request_id,
                    request_email,
                    request_phone,
                    request_name,
                    request_message,
                    status,
                    created_at,
                    processed_at,
                    processed_by_user_id,
                    result_summary,
                    last_error
                FROM data_deletion_requests
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}";
        $res = mysqli_query($conexion, $sql);
        if (!$res) {
            throw new RuntimeException('db_query_error');
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('dd_count_open_requests')) {
    function dd_count_open_requests($conexion)
    {
        if (!dd_table_exists($conexion, 'data_deletion_requests')) {
            return 0;
        }
        if (!dd_column_exists($conexion, 'data_deletion_requests', 'status')) {
            return 0;
        }
        $sql = "SELECT COUNT(*) AS total
                FROM data_deletion_requests
                WHERE status IN ('pending', 'failed')";
        $res = mysqli_query($conexion, $sql);
        if (!$res) {
            return 0;
        }
        $row = mysqli_fetch_assoc($res);
        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('dd_fetch_recent_open_requests')) {
    function dd_fetch_recent_open_requests($conexion, $limit = 5)
    {
        if (!dd_table_exists($conexion, 'data_deletion_requests')) {
            return [];
        }
        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 5;
        }
        if ($limit > 20) {
            $limit = 20;
        }
        $sql = "SELECT request_id, status, created_at
                FROM data_deletion_requests
                WHERE status IN ('pending', 'failed')
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}";
        $res = mysqli_query($conexion, $sql);
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('dd_mask_email')) {
    function dd_mask_email($email)
    {
        $email = trim((string)$email);
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }
        $parts = explode('@', $email, 2);
        $local = $parts[0];
        $domain = $parts[1];
        if ($local === '') {
            return '***@' . $domain;
        }
        if (strlen($local) <= 2) {
            return substr($local, 0, 1) . '***@' . $domain;
        }
        return substr($local, 0, 2) . '***@' . $domain;
    }
}

if (!function_exists('dd_mask_phone')) {
    function dd_mask_phone($phone)
    {
        $digits = dd_normalize_phone($phone);
        if ($digits === '') {
            return '';
        }
        $tail = substr($digits, -4);
        return '***' . $tail;
    }
}

if (!function_exists('dd_is_client_candidate')) {
    function dd_is_client_candidate($row)
    {
        if (!is_array($row)) {
            return false;
        }
        if (isset($row['ppal']) && (int)$row['ppal'] === 1) {
            return false;
        }
        if (isset($row['provider_id']) && (int)$row['provider_id'] > 0) {
            return false;
        }
        if (isset($row['service_provider_id']) && (int)$row['service_provider_id'] > 0) {
            return false;
        }
        if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
            $rid = (int)$row['role_id'];
            $clientRole = defined('ROLE_CLIENT') ? (int)ROLE_CLIENT : 3;
            if ($rid > 0 && $rid !== $clientRole) {
                return false;
            }
        }
        $rol = strtolower(trim((string)($row['rol'] ?? '')));
        if ($rol !== '') {
            if (strpos($rol, 'admin') !== false || strpos($rol, 'prestador') !== false || strpos($rol, 'provider') !== false || strpos($rol, 'contab') !== false || strpos($rol, 'complement') !== false) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('dd_safe_error_message')) {
    function dd_safe_error_message($message)
    {
        $message = trim((string)$message);
        if ($message === '') {
            return 'unknown_error';
        }
        $message = preg_replace('/\s+/', ' ', $message);
        if ($message === null) {
            $message = 'unknown_error';
        }
        if (strlen($message) > 350) {
            $message = substr($message, 0, 350);
        }
        return $message;
    }
}

if (!function_exists('dd_collect_user_ids')) {
    function dd_collect_user_ids($conexion, $email, $phoneDigits)
    {
        $ids = [];
        if (!dd_table_exists($conexion, 'usuarios')) {
            return $ids;
        }

        $hasEmail = dd_column_exists($conexion, 'usuarios', 'email');
        $hasUsuario = dd_column_exists($conexion, 'usuarios', 'usuario');
        $hasTelefono = dd_column_exists($conexion, 'usuarios', 'telefono');
        $hasCelular = dd_column_exists($conexion, 'usuarios', 'celular');

        $conditions = [];
        $types = '';
        $params = [];

        if ($email !== '' && $hasEmail) {
            $conditions[] = "LOWER(TRIM(IFNULL(email, ''))) = LOWER(TRIM(?))";
            $types .= 's';
            $params[] = $email;
        }
        if ($email !== '' && $hasUsuario) {
            $conditions[] = "LOWER(TRIM(IFNULL(usuario, ''))) = LOWER(TRIM(?))";
            $types .= 's';
            $params[] = $email;
        }
        if ($phoneDigits !== '') {
            $phone10 = substr($phoneDigits, -10);
            if ($hasTelefono) {
                $digitsExpr = dd_phone_digits_sql('telefono');
                $conditions[] = "({$digitsExpr} = ? OR RIGHT({$digitsExpr}, 10) = ?)";
                $types .= 'ss';
                $params[] = $phoneDigits;
                $params[] = $phone10;
            }
            if ($hasCelular) {
                $digitsExpr = dd_phone_digits_sql('celular');
                $conditions[] = "({$digitsExpr} = ? OR RIGHT({$digitsExpr}, 10) = ?)";
                $types .= 'ss';
                $params[] = $phoneDigits;
                $params[] = $phone10;
            }
        }

        if (empty($conditions)) {
            return $ids;
        }

        $sql = "SELECT id FROM usuarios WHERE " . implode(' OR ', $conditions);
        $res = dd_exec_stmt($conexion, $sql, $types, $params, true);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $ids[] = (int)$row['id'];
        }
        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('dd_collect_booking_ids')) {
    function dd_collect_booking_ids($conexion, $email, $phoneDigits, $userIds = [])
    {
        $bookingIds = [];
        $clientIds = [];
        if (!dd_table_exists($conexion, 'booking_requests')) {
            return [$bookingIds, $clientIds];
        }

        $hasClientUserId = dd_column_exists($conexion, 'booking_requests', 'client_user_id');
        $hasEmail = dd_column_exists($conexion, 'booking_requests', 'email');
        $hasPhone = dd_column_exists($conexion, 'booking_requests', 'phone');

        $conditions = [];
        $types = '';
        $params = [];

        if ($hasClientUserId && !empty($userIds)) {
            $inTypes = '';
            $inParams = [];
            $inClause = dd_build_in_clause($userIds, $inTypes, $inParams, 'i');
            if ($inClause !== '') {
                $conditions[] = "client_user_id IN ({$inClause})";
                $types .= $inTypes;
                $params = array_merge($params, $inParams);
            }
        }

        if ($email !== '' && $hasEmail) {
            $conditions[] = "LOWER(TRIM(IFNULL(email, ''))) = LOWER(TRIM(?))";
            $types .= 's';
            $params[] = $email;
        }

        if ($phoneDigits !== '' && $hasPhone) {
            $phone10 = substr($phoneDigits, -10);
            $digitsExpr = dd_phone_digits_sql('phone');
            $conditions[] = "({$digitsExpr} = ? OR RIGHT({$digitsExpr}, 10) = ?)";
            $types .= 'ss';
            $params[] = $phoneDigits;
            $params[] = $phone10;
        }

        if (empty($conditions)) {
            return [$bookingIds, $clientIds];
        }

        $sql = "SELECT id" . ($hasClientUserId ? ", client_user_id" : "") . "
                FROM booking_requests
                WHERE " . implode(' OR ', $conditions);
        $res = dd_exec_stmt($conexion, $sql, $types, $params, true);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $bookingIds[] = (int)$row['id'];
            if ($hasClientUserId && isset($row['client_user_id']) && (int)$row['client_user_id'] > 0) {
                $clientIds[] = (int)$row['client_user_id'];
            }
        }
        return [
            array_values(array_unique(array_filter($bookingIds))),
            array_values(array_unique(array_filter($clientIds))),
        ];
    }
}

if (!function_exists('dd_fetch_users_by_ids')) {
    function dd_fetch_users_by_ids($conexion, $userIds)
    {
        $rows = [];
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds) || !dd_table_exists($conexion, 'usuarios')) {
            return $rows;
        }

        $fields = ['id'];
        $optional = [
            'usuario', 'nombre', 'email', 'telefono', 'celular',
            'role_id', 'rol', 'ppal', 'provider_id', 'service_provider_id'
        ];
        foreach ($optional as $col) {
            if (dd_column_exists($conexion, 'usuarios', $col)) {
                $fields[] = $col;
            }
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($userIds, $types, $params, 'i');
        if ($inClause === '') {
            return $rows;
        }

        $sql = "SELECT " . implode(', ', $fields) . " FROM usuarios WHERE id IN ({$inClause})";
        $res = dd_exec_stmt($conexion, $sql, $types, $params, true);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('dd_collect_crm_client_ids')) {
    function dd_collect_crm_client_ids($conexion, $email, $phoneDigits)
    {
        $ids = [];
        if (!dd_table_exists($conexion, 'clientes')) {
            return $ids;
        }

        $hasEmail = dd_column_exists($conexion, 'clientes', 'email');
        $hasTelefono = dd_column_exists($conexion, 'clientes', 'telefono');
        $hasWhatsapp = dd_column_exists($conexion, 'clientes', 'whatsapp');

        $conditions = [];
        $types = '';
        $params = [];

        if ($email !== '' && $hasEmail) {
            $conditions[] = "LOWER(TRIM(IFNULL(email, ''))) = LOWER(TRIM(?))";
            $types .= 's';
            $params[] = $email;
        }

        if ($phoneDigits !== '') {
            $phone10 = substr($phoneDigits, -10);
            if ($hasTelefono) {
                $digitsExpr = dd_phone_digits_sql('telefono');
                $conditions[] = "({$digitsExpr} = ? OR RIGHT({$digitsExpr}, 10) = ?)";
                $types .= 'ss';
                $params[] = $phoneDigits;
                $params[] = $phone10;
            }
            if ($hasWhatsapp) {
                $digitsExpr = dd_phone_digits_sql('whatsapp');
                $conditions[] = "({$digitsExpr} = ? OR RIGHT({$digitsExpr}, 10) = ?)";
                $types .= 'ss';
                $params[] = $phoneDigits;
                $params[] = $phone10;
            }
        }

        if (empty($conditions)) {
            return $ids;
        }

        $sql = "SELECT id FROM clientes WHERE " . implode(' OR ', $conditions);
        $res = dd_exec_stmt($conexion, $sql, $types, $params, true);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $ids[] = (int)$row['id'];
        }
        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('dd_collect_booking_client_ids_by_booking_ids')) {
    function dd_collect_booking_client_ids_by_booking_ids($conexion, $bookingIds)
    {
        $clientIds = [];
        $bookingIds = dd_unique_int_ids($bookingIds);
        if (empty($bookingIds) || !dd_table_exists($conexion, 'booking_requests') || !dd_column_exists($conexion, 'booking_requests', 'client_user_id')) {
            return $clientIds;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($bookingIds, $types, $params, 'i');
        if ($inClause === '') {
            return $clientIds;
        }

        $sql = "SELECT client_user_id
                FROM booking_requests
                WHERE id IN ({$inClause})
                  AND client_user_id IS NOT NULL
                  AND client_user_id > 0";
        $res = dd_exec_stmt($conexion, $sql, $types, $params, true);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $clientIds[] = (int)$row['client_user_id'];
        }
        return dd_unique_int_ids($clientIds);
    }
}

if (!function_exists('dd_resolve_deletion_target')) {
    function dd_resolve_deletion_target($conexion, $emailRaw, $phoneRaw)
    {
        $email = dd_safe_trim($emailRaw, 255);
        $phoneDigits = dd_normalize_phone($phoneRaw);
        $hasEmail = ($email !== '');
        $hasPhone = ($phoneDigits !== '');
        if (!$hasEmail && !$hasPhone) {
            throw new RuntimeException('invalid_target_contact');
        }

        $usersByEmail = $hasEmail ? dd_collect_user_ids($conexion, $email, '') : [];
        $usersByPhone = $hasPhone ? dd_collect_user_ids($conexion, '', $phoneDigits) : [];
        [$baseUserIds, $userMatchMode] = dd_select_ids_by_identifiers($usersByEmail, $usersByPhone, $hasEmail, $hasPhone);

        $clientsByEmail = $hasEmail ? dd_collect_crm_client_ids($conexion, $email, '') : [];
        $clientsByPhone = $hasPhone ? dd_collect_crm_client_ids($conexion, '', $phoneDigits) : [];
        [$crmClientIds, $clientMatchMode] = dd_select_ids_by_identifiers($clientsByEmail, $clientsByPhone, $hasEmail, $hasPhone);

        $bookingsByEmail = [];
        $bookingsByPhone = [];
        if ($hasEmail) {
            [$bookingsByEmail] = dd_collect_booking_ids($conexion, $email, '', []);
        }
        if ($hasPhone) {
            [$bookingsByPhone] = dd_collect_booking_ids($conexion, '', $phoneDigits, []);
        }
        [$bookingIds, $bookingMatchMode] = dd_select_ids_by_identifiers($bookingsByEmail, $bookingsByPhone, $hasEmail, $hasPhone);

        $bookingClientIds = dd_collect_booking_client_ids_by_booking_ids($conexion, $bookingIds);
        $candidateUserIds = dd_unique_int_ids(array_merge($baseUserIds, $bookingClientIds));

        if (!empty($candidateUserIds)) {
            [$bookingsByResolvedUsers, $clientIdsByResolvedUsers] = dd_collect_booking_ids($conexion, '', '', $candidateUserIds);
            $bookingIds = dd_unique_int_ids(array_merge($bookingIds, $bookingsByResolvedUsers));
            $candidateUserIds = dd_unique_int_ids(array_merge($candidateUserIds, $clientIdsByResolvedUsers));
        }

        $userRows = dd_fetch_users_by_ids($conexion, $candidateUserIds);
        $clientRows = [];
        $clientUserIds = [];
        foreach ($userRows as $row) {
            if (!dd_is_client_candidate($row)) {
                continue;
            }
            $clientRows[] = $row;
            $clientUserIds[] = (int)$row['id'];
        }
        $clientUserIds = dd_unique_int_ids($clientUserIds);

        if (!empty($clientUserIds)) {
            [$bookingsByFinalUsers] = dd_collect_booking_ids($conexion, '', '', $clientUserIds);
            $bookingIds = dd_unique_int_ids(array_merge($bookingIds, $bookingsByFinalUsers));
        }

        return [
            'request_email' => $email,
            'request_phone_digits' => $phoneDigits,
            'crm_client_ids' => dd_unique_int_ids($crmClientIds),
            'client_user_ids' => $clientUserIds,
            'client_rows' => $clientRows,
            'booking_ids' => dd_unique_int_ids($bookingIds),
            'user_match_mode' => $userMatchMode,
            'client_match_mode' => $clientMatchMode,
            'booking_match_mode' => $bookingMatchMode,
            'identifier_conflict' => (
                $userMatchMode === 'both_conflict_no_intersection'
                || $clientMatchMode === 'both_conflict_no_intersection'
                || $bookingMatchMode === 'both_conflict_no_intersection'
            ),
        ];
    }
}

if (!function_exists('dd_delete_inbox_messages')) {
    function dd_delete_inbox_messages($conexion, $bookingIds, $userIds)
    {
        if (!dd_table_exists($conexion, 'inbox_messages')) {
            return 0;
        }
        $hasRequestId = dd_column_exists($conexion, 'inbox_messages', 'request_id');
        $hasSenderUserId = dd_column_exists($conexion, 'inbox_messages', 'sender_user_id');

        $conditions = [];
        $types = '';
        $params = [];

        if ($hasRequestId && !empty($bookingIds)) {
            $inTypes = '';
            $inParams = [];
            $inClause = dd_build_in_clause($bookingIds, $inTypes, $inParams, 'i');
            if ($inClause !== '') {
                $conditions[] = "request_id IN ({$inClause})";
                $types .= $inTypes;
                $params = array_merge($params, $inParams);
            }
        }

        if ($hasSenderUserId && !empty($userIds)) {
            $inTypes = '';
            $inParams = [];
            $inClause = dd_build_in_clause($userIds, $inTypes, $inParams, 'i');
            if ($inClause !== '') {
                $conditions[] = "sender_user_id IN ({$inClause})";
                $types .= $inTypes;
                $params = array_merge($params, $inParams);
            }
        }

        if (empty($conditions)) {
            return 0;
        }

        $sql = "DELETE FROM inbox_messages WHERE " . implode(' OR ', $conditions);
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_delete_inbox_reads')) {
    function dd_delete_inbox_reads($conexion, $userIds)
    {
        if (empty($userIds) || !dd_table_exists($conexion, 'inbox_thread_reads') || !dd_column_exists($conexion, 'inbox_thread_reads', 'reader_user_id')) {
            return 0;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($userIds, $types, $params, 'i');
        if ($inClause === '') {
            return 0;
        }

        $sql = "DELETE FROM inbox_thread_reads WHERE reader_user_id IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_delete_calendar_events')) {
    function dd_delete_calendar_events($conexion, $bookingIds, $userIds)
    {
        if (!dd_table_exists($conexion, 'calendar_events')) {
            return 0;
        }

        $hasRequestId = dd_column_exists($conexion, 'calendar_events', 'request_id');
        $hasClientUserId = dd_column_exists($conexion, 'calendar_events', 'client_user_id');

        $conditions = [];
        $types = '';
        $params = [];

        if ($hasRequestId && !empty($bookingIds)) {
            $inTypes = '';
            $inParams = [];
            $inClause = dd_build_in_clause($bookingIds, $inTypes, $inParams, 'i');
            if ($inClause !== '') {
                $conditions[] = "request_id IN ({$inClause})";
                $types .= $inTypes;
                $params = array_merge($params, $inParams);
            }
        }

        if ($hasClientUserId && !empty($userIds)) {
            $inTypes = '';
            $inParams = [];
            $inClause = dd_build_in_clause($userIds, $inTypes, $inParams, 'i');
            if ($inClause !== '') {
                $conditions[] = "client_user_id IN ({$inClause})";
                $types .= $inTypes;
                $params = array_merge($params, $inParams);
            }
        }

        if (empty($conditions)) {
            return 0;
        }

        $sql = "DELETE FROM calendar_events WHERE " . implode(' OR ', $conditions);
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_anonymize_bookings')) {
    function dd_anonymize_bookings($conexion, $bookingIds)
    {
        if (empty($bookingIds) || !dd_table_exists($conexion, 'booking_requests')) {
            return 0;
        }

        $setParts = [];
        if (dd_column_exists($conexion, 'booking_requests', 'name')) {
            $setParts[] = "name = 'Deleted User'";
        }
        if (dd_column_exists($conexion, 'booking_requests', 'email')) {
            $setParts[] = "email = CONCAT('deleted_booking_', id, '@redacted.local')";
        }
        if (dd_column_exists($conexion, 'booking_requests', 'phone')) {
            $setParts[] = "phone = ''";
        }
        if (dd_column_exists($conexion, 'booking_requests', 'origin')) {
            $setParts[] = "origin = ''";
        }
        if (dd_column_exists($conexion, 'booking_requests', 'special_request')) {
            $setParts[] = "special_request = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'booking_requests', 'additional_notes')) {
            $setParts[] = "additional_notes = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'booking_requests', 'client_user_id')) {
            $setParts[] = "client_user_id = NULL";
        }
        if (dd_column_exists($conexion, 'booking_requests', 'updated_at')) {
            $setParts[] = "updated_at = NOW()";
        }

        if (empty($setParts)) {
            return 0;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($bookingIds, $types, $params, 'i');
        if ($inClause === '') {
            return 0;
        }

        $sql = "UPDATE booking_requests SET " . implode(', ', $setParts) . " WHERE id IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_delete_provider_users')) {
    function dd_delete_provider_users($conexion, $userIds)
    {
        if (empty($userIds) || !dd_table_exists($conexion, 'provider_users') || !dd_column_exists($conexion, 'provider_users', 'user_id')) {
            return 0;
        }
        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($userIds, $types, $params, 'i');
        if ($inClause === '') {
            return 0;
        }
        $sql = "DELETE FROM provider_users WHERE user_id IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_get_allowed_file_roots')) {
    function dd_get_allowed_file_roots($rootDir)
    {
        $roots = [];
        $candidates = [
            $rootDir . '/uploads',
            $rootDir . '/img',
            $rootDir . '/admin/uploads',
            $rootDir . '/admin/img',
            $rootDir . '/client/uploads',
            $rootDir . '/client/img',
        ];
        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real !== false && is_dir($real)) {
                $roots[] = rtrim($real, '/');
            }
        }
        return array_values(array_unique($roots));
    }
}

if (!function_exists('dd_is_path_in_allowed_roots')) {
    function dd_is_path_in_allowed_roots($realFilePath, $allowedRoots)
    {
        $realFilePath = rtrim((string)$realFilePath, '/');
        foreach ((array)$allowedRoots as $root) {
            $root = rtrim((string)$root, '/');
            if ($root === '') {
                continue;
            }
            if ($realFilePath === $root || strpos($realFilePath, $root . '/') === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('dd_resolve_safe_project_file_path')) {
    function dd_resolve_safe_project_file_path($rootDir, $rawPath, $allowedRoots)
    {
        $rawPath = trim((string)$rawPath);
        if ($rawPath === '') {
            return '';
        }
        $rawPath = str_replace("\0", '', $rawPath);
        $rawPath = str_replace('\\', '/', $rawPath);
        $cleanPath = strtok($rawPath, '?');
        if ($cleanPath === false) {
            return '';
        }
        $cleanPath = trim($cleanPath);
        if ($cleanPath === '') {
            return '';
        }

        $isAbsolute = (bool)preg_match('/^([A-Za-z]:)?\//', $cleanPath);
        if ($isAbsolute) {
            $candidate = $cleanPath;
        } else {
            $candidate = rtrim((string)$rootDir, '/') . '/' . ltrim($cleanPath, '/');
        }

        $realCandidate = realpath($candidate);
        if ($realCandidate === false || !is_file($realCandidate)) {
            return '';
        }
        if (!dd_is_path_in_allowed_roots($realCandidate, $allowedRoots)) {
            return '';
        }
        return $realCandidate;
    }
}

if (!function_exists('dd_cleanup_certificates')) {
    function dd_cleanup_certificates($conexion, $userIds)
    {
        $result = [
            'rows_deleted' => 0,
            'files_deleted' => 0,
        ];
        if (empty($userIds) || !dd_table_exists($conexion, 'certificado') || !dd_column_exists($conexion, 'certificado', 'id_usuario') || !dd_column_exists($conexion, 'certificado', 'archivo')) {
            return $result;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($userIds, $types, $params, 'i');
        if ($inClause === '') {
            return $result;
        }

        $rootDir = realpath(dirname(__DIR__, 2));
        $allowedRoots = $rootDir ? dd_get_allowed_file_roots($rootDir) : [];
        $selSql = "SELECT id, archivo FROM certificado WHERE id_usuario IN ({$inClause})";
        $res = dd_exec_stmt($conexion, $selSql, $types, $params, true);
        $filePaths = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rawPath = trim((string)($row['archivo'] ?? ''));
            if ($rawPath !== '') {
                $cleanPath = strtok($rawPath, '?');
                if ($cleanPath !== false && $cleanPath !== '') {
                    $filePaths[] = $cleanPath;
                }
            }
        }

        foreach ($filePaths as $storedPath) {
            if (!$rootDir || empty($allowedRoots)) {
                break;
            }
            $realCandidate = dd_resolve_safe_project_file_path($rootDir, $storedPath, $allowedRoots);
            if ($realCandidate === '') {
                continue;
            }
            if (@unlink($realCandidate)) {
                $result['files_deleted']++;
            }
        }

        $delSql = "DELETE FROM certificado WHERE id_usuario IN ({$inClause})";
        $result['rows_deleted'] = dd_exec_stmt($conexion, $delSql, $types, $params);
        return $result;
    }
}

if (!function_exists('dd_cleanup_client_documents')) {
    function dd_cleanup_client_documents($conexion, $clientIds)
    {
        $result = [
            'rows_deleted' => 0,
            'files_deleted' => 0,
        ];
        if (empty($clientIds) || !dd_table_exists($conexion, 'client_documents') || !dd_column_exists($conexion, 'client_documents', 'client_id')) {
            return $result;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($clientIds, $types, $params, 'i');
        if ($inClause === '') {
            return $result;
        }

        $rootDir = realpath(dirname(__DIR__, 2));
        $allowedRoots = $rootDir ? dd_get_allowed_file_roots($rootDir) : [];
        $hasFilePath = dd_column_exists($conexion, 'client_documents', 'file_path');
        if ($rootDir && $hasFilePath) {
            $selSql = "SELECT file_path FROM client_documents WHERE client_id IN ({$inClause})";
            $res = dd_exec_stmt($conexion, $selSql, $types, $params, true);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                if (empty($allowedRoots)) {
                    continue;
                }
                $realCandidate = dd_resolve_safe_project_file_path($rootDir, (string)($row['file_path'] ?? ''), $allowedRoots);
                if ($realCandidate === '') {
                    continue;
                }
                if (@unlink($realCandidate)) {
                    $result['files_deleted']++;
                }
            }
        }

        $delSql = "DELETE FROM client_documents WHERE client_id IN ({$inClause})";
        $result['rows_deleted'] = dd_exec_stmt($conexion, $delSql, $types, $params);
        return $result;
    }
}

if (!function_exists('dd_anonymize_crm_clients')) {
    function dd_anonymize_crm_clients($conexion, $clientIds, $requestIdTag)
    {
        if (empty($clientIds) || !dd_table_exists($conexion, 'clientes')) {
            return 0;
        }

        $setParts = [];
        if (dd_column_exists($conexion, 'clientes', 'nombre')) {
            $setParts[] = "nombre = 'Deleted'";
        }
        if (dd_column_exists($conexion, 'clientes', 'apellido')) {
            $setParts[] = "apellido = 'Client'";
        }
        if (dd_column_exists($conexion, 'clientes', 'email')) {
            $tag = preg_replace('/[^A-Za-z0-9]/', '', (string)$requestIdTag);
            $tag = strtolower(substr((string)$tag, -8));
            if ($tag === '') {
                $tag = 'req';
            }
            $setParts[] = "email = CONCAT('deleted_client_', id, '_{$tag}@redacted.local')";
        }
        if (dd_column_exists($conexion, 'clientes', 'telefono')) {
            $setParts[] = "telefono = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'whatsapp')) {
            $setParts[] = "whatsapp = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'fecha_nacimiento')) {
            $setParts[] = "fecha_nacimiento = NULL";
        }
        if (dd_column_exists($conexion, 'clientes', 'pais')) {
            $setParts[] = "pais = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'estado')) {
            $setParts[] = "estado = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'ciudad')) {
            $setParts[] = "ciudad = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'direccion')) {
            $setParts[] = "direccion = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'codigo_postal')) {
            $setParts[] = "codigo_postal = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'numero_pasaporte')) {
            $setParts[] = "numero_pasaporte = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'condiciones_medicas')) {
            $setParts[] = "condiciones_medicas = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'clientes', 'alergias')) {
            $setParts[] = "alergias = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'clientes', 'medicamentos_actuales')) {
            $setParts[] = "medicamentos_actuales = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'clientes', 'contacto_emergencia_nombre')) {
            $setParts[] = "contacto_emergencia_nombre = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'contacto_emergencia_telefono')) {
            $setParts[] = "contacto_emergencia_telefono = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'contacto_emergencia_relacion')) {
            $setParts[] = "contacto_emergencia_relacion = ''";
        }
        if (dd_column_exists($conexion, 'clientes', 'notas')) {
            $setParts[] = "notas = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'clientes', 'status')) {
            $setParts[] = "status = 'inactivo'";
        }

        if (empty($setParts)) {
            return 0;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($clientIds, $types, $params, 'i');
        if ($inClause === '') {
            return 0;
        }

        $sql = "UPDATE clientes SET " . implode(', ', $setParts) . " WHERE id IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_anonymize_appointments')) {
    function dd_anonymize_appointments($conexion, $clientIds)
    {
        if (empty($clientIds) || !dd_table_exists($conexion, 'appointments') || !dd_column_exists($conexion, 'appointments', 'client_id')) {
            return 0;
        }

        $setParts = [];
        if (dd_column_exists($conexion, 'appointments', 'notes')) {
            $setParts[] = "notes = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'appointments', 'preparation_instructions')) {
            $setParts[] = "preparation_instructions = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'appointments', 'result_notes')) {
            $setParts[] = "result_notes = '[REDACTED]'";
        }
        if (dd_column_exists($conexion, 'appointments', 'cancellation_reason')) {
            $setParts[] = "cancellation_reason = '[REDACTED]'";
        }

        if (empty($setParts)) {
            return 0;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($clientIds, $types, $params, 'i');
        if ($inClause === '') {
            return 0;
        }

        $sql = "UPDATE appointments SET " . implode(', ', $setParts) . " WHERE client_id IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_anonymize_travel_packages')) {
    function dd_anonymize_travel_packages($conexion, $clientIds)
    {
        if (empty($clientIds) || !dd_table_exists($conexion, 'travel_packages') || !dd_column_exists($conexion, 'travel_packages', 'client_id')) {
            return 0;
        }

        $setParts = [];
        $optionalTextColumns = [
            'special_requests',
            'internal_notes',
            'flight_notes',
            'hotel_notes',
            'transport_notes',
            'meals_notes',
            'payment_notes',
        ];
        foreach ($optionalTextColumns as $col) {
            if (dd_column_exists($conexion, 'travel_packages', $col)) {
                $setParts[] = "{$col} = '[REDACTED]'";
            }
        }

        if (empty($setParts)) {
            return 0;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($clientIds, $types, $params, 'i');
        if ($inClause === '') {
            return 0;
        }

        $sql = "UPDATE travel_packages SET " . implode(', ', $setParts) . " WHERE client_id IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_delete_client_notifications')) {
    function dd_delete_client_notifications($conexion, $clientIds)
    {
        if (empty($clientIds) || !dd_table_exists($conexion, 'notifications')) {
            return 0;
        }

        $hasRecipientType = dd_column_exists($conexion, 'notifications', 'recipient_type');
        $hasRecipientId = dd_column_exists($conexion, 'notifications', 'recipient_id');
        if (!$hasRecipientType || !$hasRecipientId) {
            return 0;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($clientIds, $types, $params, 'i');
        if ($inClause === '') {
            return 0;
        }

        $sql = "DELETE FROM notifications
                WHERE recipient_type = 'client'
                  AND recipient_id IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_cleanup_active_sessions')) {
    function dd_cleanup_active_sessions($conexion, $usernames)
    {
        $usernames = array_values(array_filter(array_map(function ($v) {
            return trim((string)$v);
        }, $usernames), function ($v) {
            return $v !== '';
        }));

        if (empty($usernames) || !dd_table_exists($conexion, 'sessiones_activas') || !dd_column_exists($conexion, 'sessiones_activas', 'usuario')) {
            return 0;
        }

        $types = '';
        $params = [];
        $inClause = dd_build_in_clause($usernames, $types, $params, 's');
        if ($inClause === '') {
            return 0;
        }

        $sql = "DELETE FROM sessiones_activas WHERE usuario IN ({$inClause})";
        return dd_exec_stmt($conexion, $sql, $types, $params);
    }
}

if (!function_exists('dd_anonymize_users')) {
    function dd_anonymize_users($conexion, $userRows, $requestIdTag)
    {
        $result = [
            'affected' => 0,
            'old_usernames' => [],
            'new_usernames' => [],
        ];
        if (empty($userRows) || !dd_table_exists($conexion, 'usuarios')) {
            return $result;
        }

        $hasUsuario = dd_column_exists($conexion, 'usuarios', 'usuario');
        $hasUsrlogin = dd_column_exists($conexion, 'usuarios', 'usrlogin');
        $hasNombre = dd_column_exists($conexion, 'usuarios', 'nombre');
        $hasEmail = dd_column_exists($conexion, 'usuarios', 'email');
        $hasTelefono = dd_column_exists($conexion, 'usuarios', 'telefono');
        $hasCelular = dd_column_exists($conexion, 'usuarios', 'celular');
        $hasCiudad = dd_column_exists($conexion, 'usuarios', 'ciudad');
        $hasCargo = dd_column_exists($conexion, 'usuarios', 'cargo');
        $hasEmpresa = dd_column_exists($conexion, 'usuarios', 'empresa');
        $hasActivo = dd_column_exists($conexion, 'usuarios', 'activo');
        $hasAvatar = dd_column_exists($conexion, 'usuarios', 'avatar');
        $hasPassword = dd_column_exists($conexion, 'usuarios', 'password');
        $hasToken = dd_column_exists($conexion, 'usuarios', 'token');
        $hasResetToken = dd_column_exists($conexion, 'usuarios', 'password_reset_token');
        $hasResetExpiry = dd_column_exists($conexion, 'usuarios', 'password_reset_expires_at');
        $hasResetSent = dd_column_exists($conexion, 'usuarios', 'password_reset_sent_at');
        $hasCambioPassword = dd_column_exists($conexion, 'usuarios', 'cambio_password');
        $hasProviderId = dd_column_exists($conexion, 'usuarios', 'provider_id');
        $hasServiceProviderId = dd_column_exists($conexion, 'usuarios', 'service_provider_id');

        $requestShort = preg_replace('/[^A-Za-z0-9]/', '', (string)$requestIdTag);
        $requestShort = substr((string)$requestShort, -8);
        if ($requestShort === '') {
            $requestShort = 'REQ';
        }

        foreach ($userRows as $row) {
            $userId = (int)($row['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            if (!dd_is_client_candidate($row)) {
                continue;
            }

            $oldUser = trim((string)($row['usuario'] ?? ''));
            if ($oldUser !== '') {
                $result['old_usernames'][] = $oldUser;
            }

            $newUser = 'deleted_' . $userId . '_' . $requestShort;
            if (strlen($newUser) > 90) {
                $newUser = substr($newUser, 0, 90);
            }
            $newEmail = 'deleted_user_' . $userId . '_' . strtolower($requestShort) . '@redacted.local';
            $newPassword = hash('sha512', bin2hex(random_bytes(16)) . microtime(true));
            $newToken = bin2hex(random_bytes(16));

            $setParts = [];
            $types = '';
            $params = [];

            if ($hasUsuario) {
                $setParts[] = 'usuario = ?';
                $types .= 's';
                $params[] = $newUser;
            }
            if ($hasUsrlogin) {
                $setParts[] = 'usrlogin = ?';
                $types .= 's';
                $params[] = $newUser;
            }
            if ($hasNombre) {
                $setParts[] = 'nombre = ?';
                $types .= 's';
                $params[] = 'Deleted User';
            }
            if ($hasEmail) {
                $setParts[] = 'email = ?';
                $types .= 's';
                $params[] = $newEmail;
            }
            if ($hasTelefono) {
                $setParts[] = 'telefono = ?';
                $types .= 's';
                $params[] = '';
            }
            if ($hasCelular) {
                $setParts[] = 'celular = ?';
                $types .= 's';
                $params[] = '';
            }
            if ($hasCiudad) {
                $setParts[] = 'ciudad = ?';
                $types .= 's';
                $params[] = '';
            }
            if ($hasCargo) {
                $setParts[] = 'cargo = ?';
                $types .= 's';
                $params[] = '';
            }
            if ($hasEmpresa) {
                $setParts[] = 'empresa = ?';
                $types .= 's';
                $params[] = '';
            }
            if ($hasAvatar) {
                $setParts[] = 'avatar = ?';
                $types .= 's';
                $params[] = 'img/perfil/default.png';
            }
            if ($hasPassword) {
                $setParts[] = 'password = ?';
                $types .= 's';
                $params[] = $newPassword;
            }
            if ($hasToken) {
                $setParts[] = 'token = ?';
                $types .= 's';
                $params[] = $newToken;
            }
            if ($hasResetToken) {
                $setParts[] = 'password_reset_token = NULL';
            }
            if ($hasResetExpiry) {
                $setParts[] = 'password_reset_expires_at = NULL';
            }
            if ($hasResetSent) {
                $setParts[] = 'password_reset_sent_at = NULL';
            }
            if ($hasActivo) {
                $setParts[] = 'activo = 0';
            }
            if ($hasCambioPassword) {
                $setParts[] = 'cambio_password = 1';
            }
            if ($hasProviderId) {
                $setParts[] = 'provider_id = NULL';
            }
            if ($hasServiceProviderId) {
                $setParts[] = 'service_provider_id = NULL';
            }

            if (empty($setParts)) {
                continue;
            }

            $sql = "UPDATE usuarios SET " . implode(', ', $setParts) . " WHERE id = ? LIMIT 1";
            $types .= 'i';
            $params[] = $userId;
            $affected = dd_exec_stmt($conexion, $sql, $types, $params);
            if ($affected > 0) {
                $result['affected'] += $affected;
                $result['new_usernames'][] = $newUser;
            }
        }

        $result['old_usernames'] = array_values(array_unique($result['old_usernames']));
        $result['new_usernames'] = array_values(array_unique($result['new_usernames']));
        return $result;
    }
}

if (!function_exists('dd_build_result_summary')) {
    function dd_build_result_summary($counts)
    {
        $parts = [];
        foreach ($counts as $key => $value) {
            $parts[] = $key . '=' . (int)$value;
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('dd_process_request')) {
    function dd_process_request($conexion, $requestDbId, $adminUserId)
    {
        dd_ensure_requests_schema($conexion);

        $requestDbId = (int)$requestDbId;
        $adminUserId = (int)$adminUserId;
        if ($requestDbId <= 0) {
            throw new RuntimeException('invalid_request_id');
        }
        if ($adminUserId <= 0) {
            throw new RuntimeException('invalid_admin_user');
        }

        mysqli_begin_transaction($conexion);
        $requestRow = null;
        try {
            $selectSql = "SELECT id, request_id, request_email, request_phone, status
                          FROM data_deletion_requests
                          WHERE id = ?
                          LIMIT 1
                          FOR UPDATE";
            $selectRes = dd_exec_stmt($conexion, $selectSql, 'i', [$requestDbId], true);
            $requestRow = $selectRes ? mysqli_fetch_assoc($selectRes) : null;
            if (!$requestRow) {
                throw new RuntimeException('not_found');
            }

            $status = trim((string)($requestRow['status'] ?? 'pending'));
            if ($status === 'completed') {
                throw new RuntimeException('already_completed');
            }
            if ($status === 'processing') {
                throw new RuntimeException('already_processing');
            }

            dd_exec_stmt(
                $conexion,
                "UPDATE data_deletion_requests
                 SET status = 'processing', last_error = NULL, updated_at = NOW()
                 WHERE id = ? LIMIT 1",
                'i',
                [$requestDbId]
            );

            $target = dd_resolve_deletion_target(
                $conexion,
                (string)($requestRow['request_email'] ?? ''),
                (string)($requestRow['request_phone'] ?? '')
            );
            $crmClientIds = dd_unique_int_ids($target['crm_client_ids'] ?? []);
            $clientUserIds = dd_unique_int_ids($target['client_user_ids'] ?? []);
            $bookingIds = dd_unique_int_ids($target['booking_ids'] ?? []);
            $clientRows = is_array($target['client_rows'] ?? null) ? $target['client_rows'] : [];

            $counts = [
                'targets_users_matched' => count($clientUserIds),
                'targets_clients_matched' => count($crmClientIds),
                'targets_bookings_matched' => count($bookingIds),
                'targets_identifier_conflict' => !empty($target['identifier_conflict']) ? 1 : 0,
                'bookings_anonymized' => dd_anonymize_bookings($conexion, $bookingIds),
                'inbox_messages_deleted' => dd_delete_inbox_messages($conexion, $bookingIds, $clientUserIds),
                'inbox_reads_deleted' => dd_delete_inbox_reads($conexion, $clientUserIds),
                'calendar_events_deleted' => dd_delete_calendar_events($conexion, $bookingIds, $clientUserIds),
                'provider_links_deleted' => dd_delete_provider_users($conexion, $clientUserIds),
            ];

            $certCleanup = dd_cleanup_certificates($conexion, $clientUserIds);
            $counts['certificado_rows_deleted'] = (int)$certCleanup['rows_deleted'];
            $counts['certificado_files_deleted'] = (int)$certCleanup['files_deleted'];

            $clientDocsCleanup = dd_cleanup_client_documents($conexion, $crmClientIds);
            $counts['client_documents_deleted'] = (int)$clientDocsCleanup['rows_deleted'];
            $counts['client_document_files_deleted'] = (int)$clientDocsCleanup['files_deleted'];

            $anonymizedUsers = dd_anonymize_users($conexion, $clientRows, (string)($requestRow['request_id'] ?? ''));
            $counts['users_anonymized'] = (int)$anonymizedUsers['affected'];
            $counts['crm_clients_anonymized'] = dd_anonymize_crm_clients($conexion, $crmClientIds, (string)($requestRow['request_id'] ?? ''));
            $counts['appointments_anonymized'] = dd_anonymize_appointments($conexion, $crmClientIds);
            $counts['travel_packages_anonymized'] = dd_anonymize_travel_packages($conexion, $crmClientIds);
            $counts['notifications_deleted'] = dd_delete_client_notifications($conexion, $crmClientIds);

            $sessionUsernames = array_values(array_unique(array_merge(
                (array)$anonymizedUsers['old_usernames'],
                (array)$anonymizedUsers['new_usernames']
            )));
            $counts['active_sessions_deleted'] = dd_cleanup_active_sessions($conexion, $sessionUsernames);

            $resultSummary = dd_build_result_summary($counts);

            dd_exec_stmt(
                $conexion,
                "UPDATE data_deletion_requests
                 SET status = 'completed',
                     processed_at = NOW(),
                     processed_by_user_id = ?,
                     result_summary = ?,
                     last_error = NULL,
                     updated_at = NOW()
                 WHERE id = ?
                 LIMIT 1",
                'isi',
                [$adminUserId, $resultSummary, $requestDbId]
            );

            mysqli_commit($conexion);
            return [
                'request_id' => (string)($requestRow['request_id'] ?? ''),
                'summary' => $resultSummary,
                'counts' => $counts,
            ];
        } catch (Throwable $e) {
            mysqli_rollback($conexion);

            $safeError = dd_safe_error_message($e->getMessage());
            $skipMarkFailed = in_array($safeError, ['already_completed', 'already_processing', 'not_found', 'invalid_request_id', 'invalid_admin_user'], true);
            if (!$skipMarkFailed && $requestDbId > 0 && dd_table_exists($conexion, 'data_deletion_requests')) {
                $failSql = "UPDATE data_deletion_requests
                            SET status = 'failed',
                                processed_by_user_id = ?,
                                last_error = ?,
                                updated_at = NOW()
                            WHERE id = ?
                              AND status <> 'completed'
                            LIMIT 1";
                try {
                    dd_exec_stmt($conexion, $failSql, 'isi', [$adminUserId, $safeError, $requestDbId]);
                } catch (Throwable $ignored) {
                    // ignore secondary failure
                }
            }
            throw new RuntimeException($safeError);
        }
    }
}
