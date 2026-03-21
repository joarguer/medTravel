<?php
include('include/include.php');

if (!is_role_admin_session()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acceso denegado';
    exit;
}

$cleanupRootDir = realpath(__DIR__ . '/..');
$cleanupAction = isset($_POST['cleanup_action']) ? trim((string)$_POST['cleanup_action']) : '';
$cleanupMessages = [];
$cleanupErrors = [];
$cleanupPreview = null;
$cleanupExecution = null;

function cleanup_bool_post($key)
{
    return isset($_POST[$key]) && in_array((string)$_POST[$key], ['1', 'on', 'true'], true);
}

function cleanup_table_exists($conexion, $table)
{
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    return ($res && mysqli_num_rows($res) > 0);
}

function cleanup_table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function cleanup_table_count($conexion, $table)
{
    $sql = "SELECT COUNT(*) AS total FROM `{$table}`";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['total'] ?? 0);
}

function cleanup_collect_ints_from_query($conexion, $sql)
{
    $ids = [];
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return $ids;
    }
    while ($row = mysqli_fetch_row($res)) {
        $ids[] = (int)($row[0] ?? 0);
    }
    return array_values(array_unique(array_filter($ids, function ($id) {
        return (int)$id > 0;
    })));
}

function cleanup_provider_users_ready($conexion)
{
    return cleanup_table_exists($conexion, 'provider_users')
        && cleanup_table_has_column($conexion, 'provider_users', 'provider_id')
        && cleanup_table_has_column($conexion, 'provider_users', 'user_id');
}

function cleanup_provider_users_has_role($conexion)
{
    return cleanup_provider_users_ready($conexion)
        && cleanup_table_has_column($conexion, 'provider_users', 'role_in_provider');
}

function cleanup_provider_owner_role_priority_sql($puAlias = 'pu')
{
    return "CASE LOWER(COALESCE(NULLIF(TRIM({$puAlias}.role_in_provider), ''), 'owner'))
                WHEN 'owner' THEN 0
                WHEN 'primary' THEN 1
                WHEN 'principal' THEN 2
                WHEN 'admin' THEN 3
                WHEN 'administrator' THEN 4
                ELSE 10
            END";
}

function cleanup_user_owner_candidate_priority_sql($conexion, $alias = 'u')
{
    $parts = [];
    if (cleanup_table_has_column($conexion, 'usuarios', 'ppal')) {
        $parts[] = "CASE WHEN COALESCE({$alias}.ppal, 0) = 1 THEN 0 ELSE 1 END";
    }
    if (cleanup_table_has_column($conexion, 'usuarios', 'role_id')) {
        $parts[] = "CASE
            WHEN {$alias}.role_id = " . (int)ROLE_PROVIDER_ADMIN . " THEN 0
            WHEN {$alias}.role_id = " . (int)ROLE_PROVIDER . " THEN 1
            ELSE 5
        END";
    } elseif (cleanup_table_has_column($conexion, 'usuarios', 'rol')) {
        $parts[] = "CASE LOWER(TRIM(COALESCE({$alias}.rol, '')))
            WHEN '" . mysqli_real_escape_string($conexion, (string)ROLE_PROVIDER_ADMIN) . "' THEN 0
            WHEN 'provider_admin' THEN 0
            WHEN 'prestador_admin' THEN 0
            WHEN 'admin prestador' THEN 0
            WHEN '" . mysqli_real_escape_string($conexion, (string)ROLE_PROVIDER) . "' THEN 1
            WHEN 'provider' THEN 1
            WHEN 'prestador' THEN 1
            WHEN 'proveedor' THEN 1
            ELSE 5
        END";
    }
    $parts[] = "{$alias}.id ASC";
    return implode(', ', $parts);
}

function cleanup_preview_superuser_guard_state($conexion)
{
    $summary = [
        'superuser_scope_fix_needed' => 0,
        'superuser_provider_links' => 0,
        'superuser_staff_links' => 0,
        'superuser_restore_needed' => 0,
    ];

    if (cleanup_table_exists($conexion, 'usuarios')) {
        $select = [];
        if (cleanup_table_has_column($conexion, 'usuarios', 'provider_id')) {
            $select[] = 'provider_id';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
            $select[] = 'service_provider_id';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'is_deleted')) {
            $select[] = 'is_deleted';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'activo')) {
            $select[] = 'activo';
        }
        if (!empty($select)) {
            $sql = "SELECT " . implode(', ', $select) . " FROM usuarios WHERE id = 1 LIMIT 1";
            $res = mysqli_query($conexion, $sql);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                if (array_key_exists('provider_id', $row) && !empty($row['provider_id'])) {
                    $summary['superuser_scope_fix_needed']++;
                }
                if (array_key_exists('service_provider_id', $row) && !empty($row['service_provider_id'])) {
                    $summary['superuser_scope_fix_needed']++;
                }
                if (array_key_exists('is_deleted', $row) && (int)$row['is_deleted'] === 1) {
                    $summary['superuser_restore_needed'] = 1;
                }
                if (array_key_exists('activo', $row) && (int)$row['activo'] !== 1) {
                    $summary['superuser_restore_needed'] = 1;
                }
            }
        }
    }

    if (cleanup_provider_users_ready($conexion)) {
        $res = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM provider_users WHERE user_id = 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $summary['superuser_provider_links'] = (int)($row['total'] ?? 0);
        }
    }

    if (cleanup_table_exists($conexion, 'provider_medical_staff') && cleanup_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')) {
        $res = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM provider_medical_staff WHERE linked_user_id = 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $summary['superuser_staff_links'] = (int)($row['total'] ?? 0);
        }
    }

    return $summary;
}

function cleanup_apply_superuser_guard($conexion)
{
    $summary = [
        'superuser_rows_repaired' => 0,
        'superuser_provider_links_removed' => 0,
        'superuser_staff_links_detached' => 0,
    ];

    if (cleanup_table_exists($conexion, 'usuarios')) {
        $set = [];
        if (cleanup_table_has_column($conexion, 'usuarios', 'provider_id')) {
            $set[] = 'provider_id = NULL';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
            $set[] = 'service_provider_id = NULL';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'is_deleted')) {
            $set[] = 'is_deleted = 0';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'deleted_at')) {
            $set[] = 'deleted_at = NULL';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'deleted_by')) {
            $set[] = 'deleted_by = NULL';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'activo')) {
            $set[] = 'activo = 1';
        }
        if (!empty($set)) {
            $sql = "UPDATE usuarios SET " . implode(', ', $set) . " WHERE id = 1 LIMIT 1";
            if (!mysqli_query($conexion, $sql)) {
                throw new Exception('Failed to sanitize superuser row: ' . mysqli_error($conexion));
            }
            $summary['superuser_rows_repaired'] = mysqli_affected_rows($conexion);
        }
    }

    if (cleanup_provider_users_ready($conexion)) {
        if (!mysqli_query($conexion, "DELETE FROM provider_users WHERE user_id = 1")) {
            throw new Exception('Failed to remove superuser provider links: ' . mysqli_error($conexion));
        }
        $summary['superuser_provider_links_removed'] = mysqli_affected_rows($conexion);
    }

    if (cleanup_table_exists($conexion, 'provider_medical_staff') && cleanup_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')) {
        $set = ['linked_user_id = NULL'];
        if (cleanup_table_has_column($conexion, 'provider_medical_staff', 'can_access_admin')) {
            $set[] = 'can_access_admin = 0';
        }
        $sql = "UPDATE provider_medical_staff SET " . implode(', ', $set) . " WHERE linked_user_id = 1";
        if (!mysqli_query($conexion, $sql)) {
            throw new Exception('Failed to detach superuser from staff links: ' . mysqli_error($conexion));
        }
        $summary['superuser_staff_links_detached'] = mysqli_affected_rows($conexion);
    }

    return $summary;
}

function cleanup_collect_provider_domain_user_ids($conexion)
{
    $userIds = [];

    if (cleanup_table_exists($conexion, 'usuarios')) {
        $conditions = [];
        if (cleanup_table_has_column($conexion, 'usuarios', 'provider_id')) {
            $conditions[] = 'COALESCE(u.provider_id, 0) > 0';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
            $conditions[] = 'COALESCE(u.service_provider_id, 0) > 0';
        }
        if (cleanup_table_has_column($conexion, 'usuarios', 'role_id')) {
            $conditions[] = 'u.role_id IN (' . (int)ROLE_PROVIDER . ', ' . (int)ROLE_PROVIDER_ADMIN . ', ' . (int)ROLE_COMPLEMENTARY_ADMIN . ')';
        } elseif (cleanup_table_has_column($conexion, 'usuarios', 'rol')) {
            $conditions[] = "LOWER(TRIM(COALESCE(u.rol, ''))) IN ('" . mysqli_real_escape_string($conexion, (string)ROLE_PROVIDER) . "', '" . mysqli_real_escape_string($conexion, (string)ROLE_PROVIDER_ADMIN) . "', '" . mysqli_real_escape_string($conexion, (string)ROLE_COMPLEMENTARY_ADMIN) . "', 'provider', 'prestador', 'proveedor', 'provider_admin', 'prestador_admin', 'admin prestador', 'complementary_admin')";
        }
        if (!empty($conditions)) {
            $sql = "SELECT u.id FROM usuarios u WHERE u.id <> 1 AND (" . implode(' OR ', $conditions) . ")";
            $userIds = array_merge($userIds, cleanup_collect_ints_from_query($conexion, $sql));
        }
    }

    if (cleanup_provider_users_ready($conexion)) {
        $userIds = array_merge($userIds, cleanup_collect_ints_from_query($conexion, "SELECT user_id FROM provider_users WHERE user_id <> 1"));
    }

    if (cleanup_table_exists($conexion, 'provider_medical_staff') && cleanup_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')) {
        $userIds = array_merge($userIds, cleanup_collect_ints_from_query($conexion, "SELECT linked_user_id FROM provider_medical_staff WHERE linked_user_id IS NOT NULL AND linked_user_id > 0 AND linked_user_id <> 1"));
    }

    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($id) {
        return $id > 1;
    })));
    sort($userIds, SORT_NUMERIC);
    return $userIds;
}

function cleanup_delete_provider_domain_users($conexion)
{
    $userIds = cleanup_collect_provider_domain_user_ids($conexion);
    $summary = [
        'provider_domain_user_ids' => $userIds,
        'provider_domain_users_deleted' => 0,
        'provider_domain_user_links_removed' => 0,
    ];

    if (empty($userIds) || !cleanup_table_exists($conexion, 'usuarios')) {
        return $summary;
    }

    $in = implode(',', array_map('intval', $userIds));
    if (cleanup_provider_users_ready($conexion)) {
        if (!mysqli_query($conexion, "DELETE FROM provider_users WHERE user_id IN ({$in})")) {
            throw new Exception('Failed to delete provider ownership mappings for scoped users: ' . mysqli_error($conexion));
        }
        $summary['provider_domain_user_links_removed'] = mysqli_affected_rows($conexion);
    }

    if (!mysqli_query($conexion, "DELETE FROM usuarios WHERE id IN ({$in}) AND id <> 1")) {
        throw new Exception('Failed to delete provider/service-provider/staff users: ' . mysqli_error($conexion));
    }
    $summary['provider_domain_users_deleted'] = mysqli_affected_rows($conexion);

    return $summary;
}

function cleanup_provider_has_explicit_owner($conexion, $providerId)
{
    if ($providerId <= 0 || !cleanup_provider_users_ready($conexion) || !cleanup_table_exists($conexion, 'usuarios')) {
        return false;
    }

    $sql = "SELECT u.id
              FROM provider_users pu
              INNER JOIN usuarios u ON u.id = pu.user_id
             WHERE pu.provider_id = ?
               AND u.id <> 1";
    if (cleanup_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $sql .= " AND COALESCE(u.service_provider_id, 0) = 0";
    }
    if (cleanup_table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND COALESCE(u.is_deleted, 0) = 0";
    }
    if (cleanup_provider_users_has_role($conexion)) {
        $sql .= " ORDER BY " . cleanup_provider_owner_role_priority_sql('pu') . ", u.id ASC";
    } else {
        $sql .= " ORDER BY u.id ASC";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return !empty($row['id']);
}

function cleanup_find_provider_owner_candidate_user_id($conexion, $providerId)
{
    if ($providerId <= 0 || !cleanup_table_exists($conexion, 'usuarios') || !cleanup_table_has_column($conexion, 'usuarios', 'provider_id')) {
        return 0;
    }

    $sql = "SELECT u.id
              FROM usuarios u
             WHERE u.provider_id = ?
               AND u.id <> 1";
    if (cleanup_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $sql .= " AND COALESCE(u.service_provider_id, 0) = 0";
    }
    if (cleanup_table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND COALESCE(u.is_deleted, 0) = 0";
    }
    $sql .= " ORDER BY " . cleanup_user_owner_candidate_priority_sql($conexion, 'u') . " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int)($row['id'] ?? 0);
}

function cleanup_reconcile_provider_ownership($conexion, $dryRun = false)
{
    $summary = [
        'backfilled_providers' => 0,
        'providers_without_owner_candidate' => [],
        'provider_users_missing' => !cleanup_provider_users_ready($conexion),
    ];

    if (!cleanup_table_exists($conexion, 'providers')) {
        return $summary;
    }

    if (!cleanup_provider_users_ready($conexion)) {
        return $summary;
    }

    $sql = "SELECT id FROM providers WHERE 1=1";
    if (cleanup_table_has_column($conexion, 'providers', 'kind')) {
        $sql .= " AND kind = 'medical'";
    }
    if (cleanup_table_has_column($conexion, 'providers', 'is_deleted')) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " ORDER BY id ASC";
    $providerIds = cleanup_collect_ints_from_query($conexion, $sql);

    foreach ($providerIds as $providerId) {
        if (cleanup_provider_has_explicit_owner($conexion, $providerId)) {
            continue;
        }
        $candidateUserId = cleanup_find_provider_owner_candidate_user_id($conexion, $providerId);
        if ($candidateUserId <= 0) {
            $summary['providers_without_owner_candidate'][] = (int)$providerId;
            continue;
        }
        $summary['backfilled_providers']++;
        if (!$dryRun) {
            $stmt = mysqli_prepare(
                $conexion,
                "INSERT INTO provider_users (provider_id, user_id, role_in_provider)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE role_in_provider = VALUES(role_in_provider)"
            );
            if (!$stmt) {
                throw new Exception('Failed to prepare provider ownership backfill: ' . mysqli_error($conexion));
            }
            $role = 'owner';
            mysqli_stmt_bind_param($stmt, 'iis', $providerId, $candidateUserId, $role);
            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new Exception('Failed to backfill provider ownership: ' . $err);
            }
            mysqli_stmt_close($stmt);
        }
    }

    return $summary;
}

function cleanup_collect_external_child_fk_edges($conexion, $tables)
{
    if (empty($tables)) {
        return [];
    }
    $quoted = [];
    foreach ($tables as $table) {
        $quoted[] = "'" . mysqli_real_escape_string($conexion, $table) . "'";
    }
    $in = implode(',', $quoted);

    $sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND REFERENCED_TABLE_NAME IN ({$in})
              AND TABLE_NAME NOT IN ({$in})
            ORDER BY REFERENCED_TABLE_NAME, TABLE_NAME, CONSTRAINT_NAME";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return [];
    }
    $edges = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $edges[] = [
            'child' => (string)($row['TABLE_NAME'] ?? ''),
            'parent' => (string)($row['REFERENCED_TABLE_NAME'] ?? ''),
            'constraint' => (string)($row['CONSTRAINT_NAME'] ?? ''),
            'column' => (string)($row['COLUMN_NAME'] ?? ''),
            'ref_column' => (string)($row['REFERENCED_COLUMN_NAME'] ?? ''),
        ];
    }
    return $edges;
}

function cleanup_collect_fk_edges($conexion, $tables)
{
    if (empty($tables)) {
        return [];
    }
    $quoted = [];
    foreach ($tables as $table) {
        $quoted[] = "'" . mysqli_real_escape_string($conexion, $table) . "'";
    }
    $in = implode(',', $quoted);

    $sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND TABLE_NAME IN ({$in})
              AND REFERENCED_TABLE_NAME IN ({$in})
            ORDER BY TABLE_NAME, CONSTRAINT_NAME";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return [];
    }
    $edges = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $edges[] = [
            'child' => (string)($row['TABLE_NAME'] ?? ''),
            'parent' => (string)($row['REFERENCED_TABLE_NAME'] ?? ''),
            'constraint' => (string)($row['CONSTRAINT_NAME'] ?? ''),
            'column' => (string)($row['COLUMN_NAME'] ?? ''),
            'ref_column' => (string)($row['REFERENCED_COLUMN_NAME'] ?? ''),
        ];
    }
    return $edges;
}

function cleanup_delete_order_from_fk($tables, $edges)
{
    $priorityMap = [
        // Current operational case flow: thread metadata/messages/docs/events before items/request.
        'inbox_email_throttle' => 10,
        'inbox_thread_reads' => 20,
        'inbox_messages' => 30,
        'client_documents' => 40,
        'calendar_events' => 50,
        'commission_payments' => 60,
        'booking_request_items' => 70,
        'booking_requests' => 80,
        // Canonical medical provider reset: ownership/staff/docs/settings before provider roots.
        'provider_medical_staff_services' => 100,
        'offer_media' => 105,
        'provider_documents' => 110,
        'provider_verification_items' => 115,
        'provider_medical_staff' => 120,
        'provider_staff_roles' => 125,
        'provider_staff_specialties' => 126,
        'provider_users' => 130,
        'provider_commission_settings' => 135,
        'provider_verification' => 140,
        'provider_service_offers' => 145,
        'provider_catalog_services' => 150,
        'provider_categories' => 155,
        'medtravel_services_catalog' => 160,
        'service_providers' => 170,
        'providers' => 180,
    ];
    $priorityFor = function ($table) use ($priorityMap) {
        return isset($priorityMap[$table]) ? (int)$priorityMap[$table] : 999;
    };

    $inDegree = [];
    $adj = [];
    foreach ($tables as $table) {
        $inDegree[$table] = 0;
        $adj[$table] = [];
    }
    foreach ($edges as $edge) {
        $child = $edge['child'];
        $parent = $edge['parent'];
        if (!isset($inDegree[$child]) || !isset($inDegree[$parent])) {
            continue;
        }
        $adj[$child][] = $parent;
        $inDegree[$parent]++;
    }

    $queue = [];
    foreach ($tables as $table) {
        if ($inDegree[$table] === 0) {
            $queue[] = $table;
        }
    }
    usort($queue, function ($a, $b) use ($priorityFor) {
        $cmp = $priorityFor($a) <=> $priorityFor($b);
        return $cmp !== 0 ? $cmp : strcmp($a, $b);
    });

    $order = [];
    while (!empty($queue)) {
        $node = array_shift($queue);
        $order[] = $node;
        foreach ($adj[$node] as $parent) {
            $inDegree[$parent]--;
            if ($inDegree[$parent] === 0) {
                $queue[] = $parent;
            }
        }
        usort($queue, function ($a, $b) use ($priorityFor) {
            $cmp = $priorityFor($a) <=> $priorityFor($b);
            return $cmp !== 0 ? $cmp : strcmp($a, $b);
        });
    }

    if (count($order) !== count($tables)) {
        $remaining = [];
        foreach ($tables as $table) {
            if (!in_array($table, $order, true)) {
                $remaining[] = $table;
            }
        }
        usort($remaining, function ($a, $b) use ($priorityFor) {
            $cmp = $priorityFor($a) <=> $priorityFor($b);
            return $cmp !== 0 ? $cmp : strcmp($a, $b);
        });
        $order = array_merge($order, $remaining);
    }
    return $order;
}

function cleanup_detect_attachment_dirs($rootDir)
{
    $candidates = [
        ['relative' => 'uploads/bookings', 'group' => 'bookings', 'label' => 'Booking attachments'],
        ['relative' => 'upload/bookings', 'group' => 'bookings', 'label' => 'Booking attachments'],
        ['relative' => 'booking/uploads', 'group' => 'bookings', 'label' => 'Booking attachments'],
        ['relative' => 'booking/attachments', 'group' => 'bookings', 'label' => 'Booking attachments'],
        ['relative' => 'booking/files', 'group' => 'bookings', 'label' => 'Booking attachments'],
        // Dedicated storage used by client_documents in the current inbox/document flow.
        ['relative' => 'uploads/medical_docs', 'group' => 'bookings', 'label' => 'Shared medical documents'],
        ['relative' => 'uploads/provider_documents', 'group' => 'full_catalog', 'label' => 'Provider verification documents'],
        ['relative' => 'uploads/staff_photos', 'group' => 'full_catalog', 'label' => 'Staff photos'],
    ];
    $found = [];
    foreach ($candidates as $meta) {
        $relative = (string)($meta['relative'] ?? '');
        $path = $rootDir . DIRECTORY_SEPARATOR . $relative;
        if (is_dir($path)) {
            $found[] = [
                'relative' => $relative,
                'path' => $path,
                'group' => (string)($meta['group'] ?? 'bookings'),
                'label' => (string)($meta['label'] ?? $relative),
            ];
        }
    }
    return $found;
}

function cleanup_filter_attachment_dirs($dirs, $include)
{
    $selected = [];
    foreach ((array)$dirs as $dirMeta) {
        $group = (string)($dirMeta['group'] ?? 'bookings');
        if ($group === 'bookings' && empty($include['bookings'])) {
            continue;
        }
        if ($group === 'calendar' && empty($include['calendar'])) {
            continue;
        }
        if ($group === 'inbox' && empty($include['inbox'])) {
            continue;
        }
        if ($group === 'full_catalog' && empty($include['full_catalog'])) {
            continue;
        }
        $selected[] = $dirMeta;
    }
    return $selected;
}

function cleanup_count_files_recursive($path)
{
    if (!is_dir($path)) {
        return 0;
    }
    $count = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $entry) {
        if ($entry->isFile()) {
            $count++;
        }
    }
    return $count;
}

function cleanup_delete_files_recursive($path)
{
    if (!is_dir($path)) {
        return 0;
    }
    $deleted = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $entry) {
        if ($entry->isFile()) {
            if (@unlink($entry->getPathname())) {
                $deleted++;
            }
        } elseif ($entry->isDir()) {
            @rmdir($entry->getPathname());
        }
    }
    return $deleted;
}

function cleanup_log_message($message)
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    error_log('[CLEANUP] ' . $message);
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/cleanup.log', $line, FILE_APPEND | LOCK_EX);
}

function cleanup_build_reset_plan($conexion, $include)
{
    $groups = [
        // Current case lifecycle: request -> items -> documents/commission and related case records.
        'bookings' => ['client_documents', 'commission_payments', 'booking_request_items', 'booking_requests'],
        // Operational conversation metadata derived from thread_id / request_id / item_id.
        'inbox' => ['inbox_email_throttle', 'inbox_thread_reads', 'inbox_messages'],
        'calendar' => ['calendar_events'],
        'full_catalog' => [
            'provider_medical_staff_services',
            'offer_media',
            'provider_documents',
            'provider_verification_items',
            'provider_medical_staff',
            'provider_staff_roles',
            'provider_staff_specialties',
            'provider_users',
            'provider_commission_settings',
            'provider_verification',
            'provider_catalog_services',
            'provider_categories',
            'provider_service_offers',
            'medtravel_services_catalog',
            'service_providers',
            'providers',
        ],
    ];

    $selected = [];
    if (!empty($include['bookings'])) {
        $selected = array_merge($selected, $groups['bookings']);
    }
    if (!empty($include['inbox'])) {
        $selected = array_merge($selected, $groups['inbox']);
    }
    if (!empty($include['calendar'])) {
        $selected = array_merge($selected, $groups['calendar']);
    }
    if (!empty($include['full_catalog'])) {
        $selected = array_merge($selected, $groups['full_catalog']);
    }

    $selected = array_values(array_unique($selected));
    $existing = [];
    $counts = [];
    foreach ($selected as $table) {
        if (!cleanup_table_exists($conexion, $table)) {
            continue;
        }
        $existing[] = $table;
        $counts[$table] = cleanup_table_count($conexion, $table);
    }

    $edges = cleanup_collect_fk_edges($conexion, $existing);
    $externalEdges = cleanup_collect_external_child_fk_edges($conexion, $existing);
    $order = cleanup_delete_order_from_fk($existing, $edges);
    $warnings = [];
    $customCounts = [];
    $customSteps = [];
    if (!empty($externalEdges)) {
        $warnings[] = 'External FK dependencies detected. Delete order is safe only inside the selected tables.';
    }
    if (!empty($include['bookings']) && cleanup_table_exists($conexion, 'client_documents')) {
        $warnings[] = 'Shared documents are part of the booking reset because client_documents is scoped by booking_request_id / item_id in the active inbox flow.';
    }
    if (!empty($include['inbox']) && cleanup_table_exists($conexion, 'inbox_email_throttle')) {
        $warnings[] = 'Inbox reset includes inbox_email_throttle to remove stale notification metadata tied to thread_id.';
    }
    $guardPreview = cleanup_preview_superuser_guard_state($conexion);
    if ($guardPreview['superuser_scope_fix_needed'] > 0 || $guardPreview['superuser_restore_needed'] > 0) {
        $customCounts['superuser_guard_repairs'] = $guardPreview['superuser_scope_fix_needed'] + $guardPreview['superuser_restore_needed'];
    }
    if ($guardPreview['superuser_provider_links'] > 0) {
        $customCounts['superuser_provider_links_to_remove'] = $guardPreview['superuser_provider_links'];
    }
    if ($guardPreview['superuser_staff_links'] > 0) {
        $customCounts['superuser_staff_links_to_detach'] = $guardPreview['superuser_staff_links'];
    }
    if (!empty($customCounts)) {
        $customSteps[] = 'Always sanitize the protected superuser (`usuarios.id = 1`) and remove contaminated provider/staff links.';
    }

    if (!empty($include['full_catalog'])) {
        $providerDomainUserIds = cleanup_collect_provider_domain_user_ids($conexion);
        if (!empty($providerDomainUserIds)) {
            $customCounts['provider_domain_users_to_delete'] = count($providerDomainUserIds);
            $customSteps[] = 'Delete provider/service-provider/staff scoped users except the global superuser.';
        }
    } else {
        $ownershipPreview = cleanup_reconcile_provider_ownership($conexion, true);
        if (!empty($ownershipPreview['provider_users_missing'])) {
            $warnings[] = 'provider_users is missing; explicit provider ownership cannot be reconciled during cleanup.';
        } else {
            if ((int)$ownershipPreview['backfilled_providers'] > 0) {
                $customCounts['providers_owner_mapping_to_backfill'] = (int)$ownershipPreview['backfilled_providers'];
                $customSteps[] = 'Backfill explicit owner/admin mapping in provider_users for remaining medical providers.';
            }
            if (!empty($ownershipPreview['providers_without_owner_candidate'])) {
                $warnings[] = 'Some remaining medical providers still have no resolvable owner/admin candidate: '
                    . implode(', ', array_map('intval', $ownershipPreview['providers_without_owner_candidate']));
            }
        }
    }

    return [
        'selected' => $selected,
        'tables' => $existing,
        'counts' => $counts,
        'fk_edges' => $edges,
        'external_fk_edges' => $externalEdges,
        'delete_order' => $order,
        'warnings' => $warnings,
        'custom_counts' => $customCounts,
        'custom_steps' => $customSteps,
    ];
}

$envName = defined('APP_ENV') ? (string)APP_ENV : 'prod';
$allowResetRaw = strtolower(trim((string)getenv('ALLOW_DEV_RESET')));
$allowResetFlag = in_array($allowResetRaw, ['1', 'true', 'yes', 'on'], true);
$resetEnabled = ($envName === 'dev' && $allowResetFlag);

$includeOptions = [
    'bookings' => cleanup_bool_post('include_bookings'),
    'inbox' => cleanup_bool_post('include_inbox'),
    'calendar' => cleanup_bool_post('include_calendar'),
    'full_catalog' => cleanup_bool_post('include_full_catalog'),
    'include_files' => cleanup_bool_post('include_files'),
    'reset_autoincrement' => cleanup_bool_post('reset_autoincrement'),
];
if ($cleanupAction === '') {
    $includeOptions['bookings'] = true;
    $includeOptions['inbox'] = true;
    $includeOptions['calendar'] = true;
    $includeOptions['include_files'] = false;
    $includeOptions['reset_autoincrement'] = true;
}

$attachmentDirs = cleanup_filter_attachment_dirs(
    cleanup_detect_attachment_dirs($cleanupRootDir ?: dirname(__DIR__)),
    $includeOptions
);
foreach ($attachmentDirs as $k => $dirMeta) {
    $attachmentDirs[$k]['files'] = cleanup_count_files_recursive($dirMeta['path']);
}

if ($cleanupAction === 'preview' || $cleanupAction === 'execute') {
    $cleanupPreview = cleanup_build_reset_plan($conexion, $includeOptions);
    $hasFileTargets = ($includeOptions['include_files'] && !empty($attachmentDirs));
    if (empty($cleanupPreview['tables']) && !$hasFileTargets) {
        $cleanupErrors[] = 'Nothing to reset with the selected options.';
    }
}

if ($cleanupAction === 'execute' && empty($cleanupErrors)) {
    if (!$resetEnabled) {
        $cleanupErrors[] = 'Reset is blocked. Enable ALLOW_DEV_RESET=true and APP_ENV=dev.';
    }

    $confirmWord = trim((string)($_POST['confirm_word'] ?? ''));
    $confirmIrreversible = cleanup_bool_post('confirm_irreversible');
    $confirmFullReset = cleanup_bool_post('confirm_full_reset');

    if ($confirmWord !== 'RESET') {
        $cleanupErrors[] = 'Type RESET to execute.';
    }
    if (!$confirmIrreversible) {
        $cleanupErrors[] = 'You must confirm irreversible execution.';
    }
    if ($includeOptions['full_catalog'] && !$confirmFullReset) {
        $cleanupErrors[] = 'You must explicitly confirm full reset.';
    }

    if (empty($cleanupErrors)) {
        $startedAt = microtime(true);
        $deletedRows = [];
        $deletedFiles = [];
        $customActions = [];
        $postExecutionWarnings = [];
        $executedTables = $cleanupPreview['delete_order'];

        mysqli_begin_transaction($conexion);
        try {
            $superuserGuard = cleanup_apply_superuser_guard($conexion);
            foreach ($superuserGuard as $key => $value) {
                if ((int)$value > 0) {
                    $customActions[$key] = (int)$value;
                }
            }

            if (!empty($includeOptions['full_catalog'])) {
                $providerDomainUsers = cleanup_delete_provider_domain_users($conexion);
                if (!empty($providerDomainUsers['provider_domain_users_deleted'])) {
                    $customActions['provider_domain_users_deleted'] = (int)$providerDomainUsers['provider_domain_users_deleted'];
                }
                if (!empty($providerDomainUsers['provider_domain_user_links_removed'])) {
                    $customActions['provider_domain_user_links_removed'] = (int)$providerDomainUsers['provider_domain_user_links_removed'];
                }
            }

            foreach ($executedTables as $table) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    throw new Exception('Unsafe table name in delete plan: ' . $table);
                }
                $sql = "DELETE FROM `{$table}`";
                if (!mysqli_query($conexion, $sql)) {
                    throw new Exception('Delete failed for ' . $table . ': ' . mysqli_error($conexion));
                }
                $deletedRows[$table] = mysqli_affected_rows($conexion);
                if ($includeOptions['reset_autoincrement']) {
                    $aiSql = "ALTER TABLE `{$table}` AUTO_INCREMENT = 1";
                    if (!mysqli_query($conexion, $aiSql)) {
                        throw new Exception('Auto-increment reset failed for ' . $table . ': ' . mysqli_error($conexion));
                    }
                }
            }

            if (empty($includeOptions['full_catalog'])) {
                $ownershipRepair = cleanup_reconcile_provider_ownership($conexion, false);
                if ((int)$ownershipRepair['backfilled_providers'] > 0) {
                    $customActions['providers_owner_mapping_backfilled'] = (int)$ownershipRepair['backfilled_providers'];
                }
                if (!empty($ownershipRepair['providers_without_owner_candidate'])) {
                    $customActions['providers_without_owner_candidate'] = count($ownershipRepair['providers_without_owner_candidate']);
                    $postExecutionWarnings[] = 'Warning: providers without resolvable owner/admin candidate remain: '
                        . implode(', ', array_map('intval', $ownershipRepair['providers_without_owner_candidate']));
                }
            }

            mysqli_commit($conexion);
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            $cleanupErrors[] = 'Execute failed: ' . $e->getMessage();
        }

        if (empty($cleanupErrors) && $includeOptions['include_files']) {
            foreach ($attachmentDirs as $dirMeta) {
                $deletedFiles[$dirMeta['relative']] = cleanup_delete_files_recursive($dirMeta['path']);
            }
        }

        if (empty($cleanupErrors)) {
            $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);
            $cleanupExecution = [
                'deleted_rows' => $deletedRows,
                'deleted_files' => $deletedFiles,
                'custom_actions' => $customActions,
                'elapsed_ms' => $elapsedMs,
            ];
            cleanup_log_message(
                'user_id=' . (int)($_SESSION['id_usuario'] ?? 0)
                . ' action=execute'
                . ' options=' . json_encode($includeOptions)
                . ' deleted_rows=' . json_encode($deletedRows)
                . ' deleted_files=' . json_encode($deletedFiles)
                . ' elapsed_ms=' . $elapsedMs
            );
            $cleanupMessages[] = 'Reset executed successfully.';
            foreach ($postExecutionWarnings as $warningMessage) {
                $cleanupMessages[] = $warningMessage;
            }
            $cleanupPreview = cleanup_build_reset_plan($conexion, $includeOptions);
        } else {
            cleanup_log_message(
                'user_id=' . (int)($_SESSION['id_usuario'] ?? 0)
                . ' action=execute_failed'
                . ' options=' . json_encode($includeOptions)
                . ' errors=' . json_encode($cleanupErrors)
            );
        }
    }
} elseif ($cleanupAction === 'preview' && empty($cleanupErrors)) {
    cleanup_log_message(
        'user_id=' . (int)($_SESSION['id_usuario'] ?? 0)
        . ' action=preview'
        . ' options=' . json_encode($includeOptions)
        . ' tables=' . json_encode($cleanupPreview['tables'])
    );
    $cleanupMessages[] = 'Preview generated. Review counts and delete order before executing.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> - Cleanup / Reset Seguro</title>
    <?php echo $global_first_style; ?>
    <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
<div class="wrapper">
    <header class="page-header">
        <nav class="navbar mega-menu" role="navigation">
            <div class="container-fluid">
                <?php echo $top_header; ?>
                <?php echo $top_header_2; ?>
            </div>
        </nav>
    </header>

    <div class="container-fluid">
        <div class="page-content">
            <div class="breadcrumbs">
                <h1>Cleanup / Reset Seguro <small>Reset dev + guardas del dominio medico</small></h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Inicio</a></li>
                    <li class="active">Cleanup / Reset Seguro</li>
                </ol>
            </div>

            <div class="portlet light bordered">
                <div class="portlet-title">
                    <div class="caption">
                        <i class="icon-refresh font-yellow-gold"></i>
                        <span class="caption-subject font-yellow-gold bold uppercase">Environment Reset (Development Safe Mode)</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <?php if (!empty($cleanupMessages)): ?>
                        <?php foreach ($cleanupMessages as $msg): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($cleanupErrors)): ?>
                        <?php foreach ($cleanupErrors as $err): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="alert <?php echo $resetEnabled ? 'alert-success' : 'alert-warning'; ?>">
                        <strong>Guard:</strong>
                        APP_ENV=<?php echo htmlspecialchars($envName, ENT_QUOTES, 'UTF-8'); ?> ·
                        ALLOW_DEV_RESET=<?php echo htmlspecialchars($allowResetRaw !== '' ? $allowResetRaw : '(not set)', ENT_QUOTES, 'UTF-8'); ?> ·
                        Execute enabled: <strong><?php echo $resetEnabled ? 'YES' : 'NO'; ?></strong>
                    </div>

                    <form method="post" class="form-horizontal">
                        <div class="form-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Operational reset (recommended)</h4>
                                    <div class="mt-checkbox-list">
                                        <label class="mt-checkbox mt-checkbox-outline"> Include cases, items, documents and commissions
                                            <input type="checkbox" name="include_bookings" value="1" <?php echo $includeOptions['bookings'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> Include inbox messages and thread metadata
                                            <input type="checkbox" name="include_inbox" value="1" <?php echo $includeOptions['inbox'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> Include calendar events / appointments
                                            <input type="checkbox" name="include_calendar" value="1" <?php echo $includeOptions['calendar'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> Reset AUTO_INCREMENT (dev only)
                                            <input type="checkbox" name="reset_autoincrement" value="1" <?php echo $includeOptions['reset_autoincrement'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h4>Full reset (dangerous)</h4>
                                    <div class="mt-checkbox-list">
                                        <label class="mt-checkbox mt-checkbox-outline"> Also delete medical provider/staff demo domain data
                                            <input type="checkbox" name="include_full_catalog" value="1" <?php echo $includeOptions['full_catalog'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                    </div>
                                    <p class="help-block">This removes canonical medical provider data, explicit ownership links, staff records, staff-linked users and demo service-provider scope while preserving the protected global superuser (`usuarios.id = 1`).</p>
                                    <?php if (!empty($attachmentDirs)): ?>
                                        <div class="mt-checkbox-list">
                                            <label class="mt-checkbox mt-checkbox-outline"> Also delete dedicated case/provider/staff upload folders
                                                <input type="checkbox" name="include_files" value="1" <?php echo $includeOptions['include_files'] ? 'checked' : ''; ?>>
                                                <span></span>
                                            </label>
                                        </div>
                                        <p class="help-block">
                                            Detected folders:
                                            <?php
                                            $folderLabels = [];
                                            foreach ($attachmentDirs as $dirMeta) {
                                                $folderLabels[] = htmlspecialchars($dirMeta['label'], ENT_QUOTES, 'UTF-8')
                                                    . ': '
                                                    . htmlspecialchars($dirMeta['relative'], ENT_QUOTES, 'UTF-8')
                                                    . ' (' . (int)$dirMeta['files'] . ' files)';
                                            }
                                            echo implode(' · ', $folderLabels);
                                            ?>
                                        </p>
                                        <p class="help-block">Only dedicated project folders are included here. No generic upload roots are deleted.</p>
                                    <?php else: ?>
                                        <p class="help-block">No dedicated case/provider/staff upload folders detected in this environment.</p>
                                    <?php endif; ?>

                                    <hr>
                                    <h4>Execute confirmation</h4>
                                    <div class="form-group">
                                        <label class="col-md-4 control-label">Type RESET</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" name="confirm_word" value="" placeholder="RESET">
                                        </div>
                                    </div>
                                    <div class="mt-checkbox-list">
                                        <label class="mt-checkbox mt-checkbox-outline"> I understand this cannot be undone
                                            <input type="checkbox" name="confirm_irreversible" value="1">
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> I also confirm full reset of the medical provider/staff demo domain
                                            <input type="checkbox" name="confirm_full_reset" value="1">
                                            <span></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="cleanup_action" value="preview" class="btn default">
                                <i class="fa fa-search"></i> Preview
                            </button>
                            <button type="submit" name="cleanup_action" value="execute" class="btn red" <?php echo $resetEnabled ? '' : 'disabled'; ?>>
                                <i class="fa fa-warning"></i> Execute
                            </button>
                        </div>
                    </form>

                    <?php if (is_array($cleanupPreview)): ?>
                        <hr>
                        <h4>Preview result</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Counts by table</h5>
                                <table class="table table-bordered table-condensed">
                                    <thead>
                                    <tr>
                                        <th>Table</th>
                                        <th>Rows</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($cleanupPreview['tables'])): ?>
                                        <tr><td colspan="2">No matching tables selected.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($cleanupPreview['tables'] as $table): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string)($cleanupPreview['counts'][$table] ?? 'n/a'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                                <?php if (!empty($cleanupPreview['custom_counts'])): ?>
                                    <h5>Canonical guard counts</h5>
                                    <table class="table table-bordered table-condensed">
                                        <thead>
                                        <tr>
                                            <th>Operation</th>
                                            <th>Rows</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($cleanupPreview['custom_counts'] as $label => $count): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int)$count; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5>Delete order (within selected tables)</h5>
                                <?php if (!empty($cleanupPreview['warnings'])): ?>
                                    <?php foreach ($cleanupPreview['warnings'] as $warning): ?>
                                        <div class="alert alert-warning" style="margin-bottom:10px;">
                                            <?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($cleanupPreview['custom_steps'])): ?>
                                    <div class="alert alert-info" style="margin-bottom:10px;">
                                        <strong>Canonical guard steps:</strong>
                                        <ul style="margin:8px 0 0 18px;">
                                            <?php foreach ($cleanupPreview['custom_steps'] as $step): ?>
                                                <li><?php echo htmlspecialchars($step, ENT_QUOTES, 'UTF-8'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($cleanupPreview['external_fk_edges'])): ?>
                                    <div class="alert alert-warning" style="margin-bottom:10px;">
                                        <strong>External child tables not included in this reset:</strong>
                                        <ul style="margin:8px 0 0 18px;">
                                            <?php foreach ($cleanupPreview['external_fk_edges'] as $edge): ?>
                                                <li><?php echo htmlspecialchars($edge['child'] . ' -> ' . $edge['parent'] . ' (' . $edge['constraint'] . ')', ENT_QUOTES, 'UTF-8'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <ol>
                                    <?php if (empty($cleanupPreview['delete_order'])): ?>
                                        <li>No tables selected.</li>
                                    <?php else: ?>
                                        <?php foreach ($cleanupPreview['delete_order'] as $table): ?>
                                            <li><?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ol>
                            </div>
                        </div>

                        <h5>Detected FK relations inside selected tables</h5>
                        <table class="table table-striped table-bordered table-condensed">
                            <thead>
                            <tr>
                                <th>Child table</th>
                                <th>Parent table</th>
                                <th>Constraint</th>
                                <th>Column</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($cleanupPreview['fk_edges'])): ?>
                                <tr><td colspan="4">No FK relations detected among selected tables.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cleanupPreview['fk_edges'] as $edge): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($edge['child'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($edge['parent'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($edge['constraint'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($edge['column'] . ' -> ' . $edge['ref_column'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (is_array($cleanupExecution)): ?>
                        <hr>
                        <h4>Execute report</h4>
                        <p><strong>Execution time:</strong> <?php echo (int)$cleanupExecution['elapsed_ms']; ?> ms</p>
                        <table class="table table-bordered table-condensed">
                            <thead>
                            <tr>
                                <th>Table</th>
                                <th>Deleted rows</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cleanupExecution['deleted_rows'] as $table => $deleted): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$deleted; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!empty($cleanupExecution['custom_actions'])): ?>
                            <h5>Canonical guard actions</h5>
                            <table class="table table-bordered table-condensed">
                                <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Affected rows</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cleanupExecution['custom_actions'] as $label => $count): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)$count; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <?php if (!empty($cleanupExecution['deleted_files'])): ?>
                            <h5>Deleted files</h5>
                            <ul>
                                <?php foreach ($cleanupExecution['deleted_files'] as $path => $deleted): ?>
                                    <li><?php echo htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int)$deleted; ?> file(s)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="portlet light bordered">
                <div class="portlet-title">
                    <div class="caption">
                        <i class="icon-trash font-red"></i>
                        <span class="caption-subject font-red bold uppercase">Cleanup administrativo seguro</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="active"><a href="#tab-users" data-toggle="tab">Usuarios</a></li>
                        <li><a href="#tab-providers" data-toggle="tab">Providers médicos</a></li>
                        <li><a href="#tab-service-providers" data-toggle="tab">Service Providers</a></li>
                        <li><a href="#tab-medtravel-services" data-toggle="tab">MedTravel Services</a></li>
                    </ul>

                    <div class="tab-content" style="padding-top:15px;">
                        <div class="tab-pane active" id="tab-users">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="users-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-users-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-providers">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="providers-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-providers-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Ciudad</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-service-providers">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="service-providers-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-service-providers-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Proveedor</th>
                                    <th>Tipo</th>
                                    <th>Email</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-medtravel-services">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="medtravel-services-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-medtravel-services-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Servicio</th>
                                    <th>Tipo</th>
                                    <th>Disponibilidad</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>

    <?php echo $sider_bar; ?>
</div>

<?php echo $theme_layout_script; ?>
<script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
<script>
(function(){
    var usersShowDeleted = false;
    var providersShowDeleted = false;
    var serviceProvidersShowDeleted = false;
    var medtravelServicesShowDeleted = false;

    function notifyError(msg){
        if (window.toastr) { toastr.error(msg); return; }
        alert(msg);
    }

    function notifySuccess(msg){
        if (window.toastr) { toastr.success(msg); return; }
        alert(msg);
    }

    function actionButton(showDeleted, actionClass, dataId, options){
        options = options || {};
        var text = showDeleted ? 'Restaurar' : 'Eliminar (Soft)';
        var toneClass = showDeleted ? 'btn-info' : 'btn-danger';
        var disabled = options.disabled ? ' disabled="disabled"' : '';
        var title = options.title ? ' title="' + String(options.title).replace(/"/g, '&quot;') + '"' : '';
        return '<button class="btn btn-xs ' + toneClass + ' ' + actionClass + '" data-id="' + dataId + '"' + disabled + title + '>' + text + '</button>';
    }

    var usersTable = $('#cleanup-users-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'usuario' },
            { data: 'nombre' },
            { data: 'email' },
            { data: 'activo', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                var protectionReason = row.cleanup_protection_reason || '';
                var isRestore = usersShowDeleted;
                var canAct = isRestore ? parseInt(row.can_restore || 0, 10) === 1 : parseInt(row.can_soft_delete || 0, 10) === 1;
                if (!canAct && protectionReason) {
                    return actionButton(isRestore, 'btn-soft-delete-user', row.id, {
                        disabled: true,
                        title: protectionReason
                    }) + '<div class="text-muted small" style="margin-top:4px;">' + protectionReason + '</div>';
                }
                return actionButton(isRestore, 'btn-soft-delete-user', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var providersTable = $('#cleanup-providers-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'type' },
            { data: 'city' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(providersShowDeleted, 'btn-soft-delete-provider', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var serviceProvidersTable = $('#cleanup-service-providers-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'provider_name' },
            { data: 'provider_type' },
            { data: 'contact_email' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(serviceProvidersShowDeleted, 'btn-soft-delete-service-provider', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var medtravelServicesTable = $('#cleanup-medtravel-services-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'service_name' },
            { data: 'service_type' },
            { data: 'availability_status' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(medtravelServicesShowDeleted, 'btn-soft-delete-medtravel-service', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    function loadUsers(){
        $.getJSON('ajax/cleanup_users.php', { action: 'list_users', show_deleted: usersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            usersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar usuarios';
            notifyError(msg);
        });
    }

    function loadProviders(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_providers', show_deleted: providersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            providersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar providers';
            notifyError(msg);
        });
    }

    function loadServiceProviders(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_service_providers', show_deleted: serviceProvidersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            serviceProvidersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar service providers';
            notifyError(msg);
        });
    }

    function loadMedtravelServices(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_medtravel_services', show_deleted: medtravelServicesShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            medtravelServicesTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar MedTravel Services';
            notifyError(msg);
        });
    }

    function confirmAction(isRestore){
        return window.confirm(isRestore ? '¿Deseas restaurar?' : '¿Deseas eliminar (soft)?');
    }

    $('#users-show-deleted').on('change', function(){
        usersShowDeleted = $(this).is(':checked');
        loadUsers();
    });

    $('#providers-show-deleted').on('change', function(){
        providersShowDeleted = $(this).is(':checked');
        loadProviders();
    });

    $('#service-providers-show-deleted').on('change', function(){
        serviceProvidersShowDeleted = $(this).is(':checked');
        loadServiceProviders();
    });

    $('#medtravel-services-show-deleted').on('change', function(){
        medtravelServicesShowDeleted = $(this).is(':checked');
        loadMedtravelServices();
    });

    $('#cleanup-users-table').on('click', '.btn-soft-delete-user', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = usersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_users.php', {
            action: isRestore ? 'restore_user' : 'soft_delete_user',
            user_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Usuario restaurado' : 'Usuario eliminado (soft)'));
                loadUsers();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-providers-table').on('click', '.btn-soft-delete-provider', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = providersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_provider' : 'soft_delete_provider',
            provider_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Provider restaurado' : 'Provider eliminado (soft)'));
                loadProviders();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-service-providers-table').on('click', '.btn-soft-delete-service-provider', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = serviceProvidersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_service_provider' : 'soft_delete_service_provider',
            service_provider_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Service provider restaurado' : 'Service provider eliminado (soft)'));
                loadServiceProviders();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-medtravel-services-table').on('click', '.btn-soft-delete-medtravel-service', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = medtravelServicesShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_medtravel_service' : 'soft_delete_medtravel_service',
            medtravel_service_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Servicio restaurado' : 'Servicio eliminado (soft)'));
                loadMedtravelServices();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    loadUsers();
    loadProviders();
    loadServiceProviders();
    loadMedtravelServices();
})();
</script>
</body>
</html>
