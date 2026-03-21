<?php
/**
 * admin/ajax/provider_medical_staff.php
 * CRUD MVP para staff medico interno por prestador.
 *
 * Acciones:
 *   - list_staff
 *   - get_staff
 *   - save_staff
 *   - toggle_staff
 *   - reorder_staff
 *
 * Nota: expone active_only para futura asignacion provider -> medical staff
 * sin acoplar aun booking_request_items a esta tabla.
 */

require_once '../include/conexion.php';
require_once '../include/roles.php';
require_once '../include/provider_medical_staff_helpers.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (
    !user_can(PERM_PROVIDERS_MEDICAL_MANAGE) &&
    !user_can('providers.medical.edit') &&
    !user_can('providers.edit')
) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden']);
    exit;
}

function pms_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function pms_err($message, $status = 400, $extra = [])
{
    http_response_code((int)$status);
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra));
    exit;
}

function bind_stmt_params($stmt, $types, &$values)
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

function pms_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_medical_staff'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

function pms_staff_services_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_medical_staff_services'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

function pms_table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function pms_provider_exists($conexion, $providerId)
{
    $sql = 'SELECT id, name FROM providers WHERE id = ?';
    if (pms_table_has_column($conexion, 'providers', 'is_deleted')) {
        $sql .= ' AND is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function pms_clean_text($value, $max = 255)
{
    $text = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, (int)$max, 'UTF-8');
    }
    return substr($text, 0, (int)$max);
}

function pms_clean_email($value)
{
    $email = pms_clean_text($value, 120);
    if ($email === '') {
        return '';
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function pms_clean_long_text($value)
{
    return trim((string)$value);
}

function pms_requested_flag($key)
{
    return isset($_POST[$key]) ? 1 : 0;
}

function pms_status_select_expr($alias = 'pms')
{
    global $conexion;
    return provider_staff_status_select_expr($conexion, $alias);
}

function pms_sort_select_expr($alias = 'pms')
{
    global $conexion;
    return provider_staff_sort_select_expr($conexion, $alias);
}

function pms_sort_order_sql($alias = 'pms')
{
    global $conexion;
    $column = provider_staff_sort_column_name($conexion);
    if ($column === '') {
        return $alias . '.id';
    }
    return $alias . '.`' . $column . '`';
}

function pms_primary_order_sql($alias = 'pms')
{
    global $conexion;
    if (provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
        return $alias . '.is_primary_doctor DESC, ';
    }
    return '';
}

function pms_normalize_staff_row($row)
{
    $row = provider_staff_normalize_row($row);
    $row['bio_short_preview'] = $row['bio_short'] !== ''
        ? $row['bio_short']
        : trim((string)($row['notes'] ?? ''));
    return $row;
}

function pms_next_sort_order($conexion, $providerId)
{
    $sortExpr = pms_sort_select_expr('pms');
    $stmt = mysqli_prepare(
        $conexion,
        'SELECT COALESCE(MAX(' . $sortExpr . '), 0) AS max_sort
         FROM provider_medical_staff pms
         WHERE pms.provider_id = ?'
    );
    if (!$stmt) {
        return 10;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    $maxSort = isset($row['max_sort']) ? (int)$row['max_sort'] : 0;
    return $maxSort > 0 ? ($maxSort + 10) : 10;
}

function pms_service_label($row)
{
    $serviceName = trim((string)($row['service_name'] ?? ''));
    $categoryName = trim((string)($row['category_name'] ?? ''));
    if ($serviceName === '') {
        $serviceId = (int)($row['service_id'] ?? 0);
        return $serviceId > 0 ? ('Servicio #' . $serviceId) : 'Servicio';
    }
    if ($categoryName === '') {
        return $serviceName;
    }
    return $serviceName . ' · ' . $categoryName;
}

function pms_session_provider_id()
{
    return isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
}

function pms_session_user_id()
{
    foreach (['id_usuario', 'id', 'user_id', 'usuario_id'] as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            $id = (int)$_SESSION[$key];
            if ($id > 0) {
                return $id;
            }
        }
    }
    return 0;
}

function pms_is_medical_user_role($roleId)
{
    return in_array((int)$roleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true);
}

function pms_resolve_user_role_id($row)
{
    if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
        return (int)$row['role_id'];
    }
    return (int)normalize_role_value($row['rol'] ?? null);
}

function pms_assert_provider_scope($providerId)
{
    if ($providerId <= 0) {
        pms_err('provider_id required');
    }
    if (is_role_admin_session()) {
        return;
    }
    $sessionProviderId = pms_session_provider_id();
    if ($sessionProviderId <= 0 || $sessionProviderId !== (int)$providerId) {
        pms_err('forbidden', 403);
    }
    if (is_provider_linked_medical_staff_session()) {
        pms_err('forbidden', 403);
    }
}

function pms_has_access_columns($conexion)
{
    return pms_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')
        && pms_table_has_column($conexion, 'provider_medical_staff', 'can_access_admin');
}

function pms_provider_enabled_services($conexion, $providerId, $activeOnly = true)
{
    if (
        !pms_table_has_column($conexion, 'provider_catalog_services', 'provider_id') ||
        !pms_table_has_column($conexion, 'provider_catalog_services', 'service_id') ||
        !pms_table_has_column($conexion, 'service_catalog', 'id')
    ) {
        return [];
    }

    $hasServiceActive = pms_table_has_column($conexion, 'service_catalog', 'is_active');
    $hasServiceDeleted = pms_table_has_column($conexion, 'service_catalog', 'is_deleted');
    $hasCategoryTable = pms_table_has_column($conexion, 'service_categories', 'id');
    $hasServiceCategory = pms_table_has_column($conexion, 'service_catalog', 'category_id');
    $categoryOrderExpr = ($hasCategoryTable && $hasServiceCategory) ? "COALESCE(cat.name, '')" : "''";

    $select = [
        'sc.id AS service_id',
        'sc.name AS service_name',
        $hasServiceCategory ? 'sc.category_id' : 'NULL AS category_id',
        ($hasCategoryTable && $hasServiceCategory) ? 'cat.name AS category_name' : "'' AS category_name",
    ];

    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM provider_catalog_services pcs
            INNER JOIN service_catalog sc ON sc.id = pcs.service_id';
    if ($hasCategoryTable && $hasServiceCategory) {
        $sql .= ' LEFT JOIN service_categories cat ON cat.id = sc.category_id';
    }
    $sql .= ' WHERE pcs.provider_id = ?';
    if ($activeOnly && $hasServiceActive) {
        $sql .= ' AND sc.is_active = 1';
    }
    if ($hasServiceDeleted) {
        $sql .= ' AND sc.is_deleted = 0';
    }
    $sql .= ' ORDER BY ' . $categoryOrderExpr . ' ASC, sc.name ASC, sc.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row['service_id'] = (int)($row['service_id'] ?? 0);
        $row['category_id'] = isset($row['category_id']) && $row['category_id'] !== null ? (int)$row['category_id'] : null;
        $row['label'] = pms_service_label($row);
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function pms_requested_service_ids()
{
    $raw = $_POST['service_ids'] ?? $_POST['service_ids[]'] ?? [];
    if (!is_array($raw)) {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $raw = preg_split('/\s*,\s*/', $raw);
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function pms_validate_provider_service_ids($conexion, $providerId, $serviceIds)
{
    $serviceIds = array_values(array_unique(array_map('intval', (array)$serviceIds)));
    if (empty($serviceIds)) {
        return [];
    }

    $allowed = pms_provider_enabled_services($conexion, $providerId, true);
    $allowedById = [];
    foreach ($allowed as $row) {
        $allowedById[(int)$row['service_id']] = $row;
    }

    $resolved = [];
    foreach ($serviceIds as $serviceId) {
        if ($serviceId <= 0 || !isset($allowedById[$serviceId])) {
            return ['error' => 'invalid_provider_service', 'service_id' => $serviceId];
        }
        $resolved[] = $allowedById[$serviceId];
    }
    return $resolved;
}

function pms_fetch_staff_services_map($conexion, $providerId, $staffIds = [], $activeOnly = true)
{
    if (!pms_staff_services_table_ready($conexion)) {
        return [];
    }

    $hasRelActive = pms_table_has_column($conexion, 'provider_medical_staff_services', 'active');
    $hasServiceActive = pms_table_has_column($conexion, 'service_catalog', 'is_active');
    $hasServiceDeleted = pms_table_has_column($conexion, 'service_catalog', 'is_deleted');
    $hasCategoryTable = pms_table_has_column($conexion, 'service_categories', 'id');
    $hasServiceCategory = pms_table_has_column($conexion, 'service_catalog', 'category_id');
    $categoryOrderExpr = ($hasCategoryTable && $hasServiceCategory) ? "COALESCE(cat.name, '')" : "''";

    $select = [
        'rel.provider_medical_staff_id AS staff_id',
        'sc.id AS service_id',
        'sc.name AS service_name',
        ($hasServiceCategory ? 'sc.category_id' : 'NULL') . ' AS category_id',
        ($hasCategoryTable && $hasServiceCategory) ? 'cat.name AS category_name' : "'' AS category_name",
    ];

    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM provider_medical_staff_services rel
            INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id
            INNER JOIN service_catalog sc ON sc.id = rel.service_id';
    if ($hasCategoryTable && $hasServiceCategory) {
        $sql .= ' LEFT JOIN service_categories cat ON cat.id = sc.category_id';
    }
    $sql .= ' WHERE pms.provider_id = ?';
    if ($activeOnly && $hasRelActive) {
        $sql .= ' AND rel.active = 1';
    }
    if ($hasServiceActive) {
        $sql .= ' AND sc.is_active = 1';
    }
    if ($hasServiceDeleted) {
        $sql .= ' AND sc.is_deleted = 0';
    }

    $types = 'i';
    $params = [$providerId];
    $staffIds = array_values(array_filter(array_map('intval', (array)$staffIds)));
    if (!empty($staffIds)) {
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        $sql .= ' AND rel.provider_medical_staff_id IN (' . $placeholders . ')';
        $types .= str_repeat('i', count($staffIds));
        foreach ($staffIds as $staffId) {
            $params[] = $staffId;
        }
    }
    $sql .= ' ORDER BY ' . $categoryOrderExpr . ' ASC, sc.name ASC, sc.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    bind_stmt_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $map = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $staffId = (int)($row['staff_id'] ?? 0);
        if ($staffId <= 0) {
            continue;
        }
        $row['service_id'] = (int)($row['service_id'] ?? 0);
        $row['category_id'] = isset($row['category_id']) && $row['category_id'] !== null ? (int)$row['category_id'] : null;
        $row['label'] = pms_service_label($row);
        if (!isset($map[$staffId])) {
            $map[$staffId] = [];
        }
        $map[$staffId][] = $row;
    }
    mysqli_stmt_close($stmt);
    return $map;
}

function pms_attach_service_payload($rows, $serviceMap)
{
    $out = [];
    foreach ((array)$rows as $row) {
        $staffId = (int)($row['id'] ?? 0);
        $serviceItems = isset($serviceMap[$staffId]) ? $serviceMap[$staffId] : [];
        $serviceIds = [];
        $serviceLabels = [];
        foreach ($serviceItems as $serviceItem) {
            $serviceIds[] = (int)$serviceItem['service_id'];
            $serviceLabels[] = (string)($serviceItem['label'] ?? pms_service_label($serviceItem));
        }
        $summary = 'Sin servicios asignados';
        $count = count($serviceLabels);
        if ($count > 0) {
            $summaryParts = array_slice($serviceLabels, 0, 3);
            $summary = implode(', ', $summaryParts);
            if ($count > 3) {
                $summary .= ' +' . ($count - 3);
            }
        }
        $row['service_items'] = $serviceItems;
        $row['service_ids'] = $serviceIds;
        $row['service_count'] = $count;
        $row['service_summary'] = $summary;
        $row['primary_service_label'] = $count > 0 ? $serviceLabels[0] : '';
        $out[] = $row;
    }
    return $out;
}

function pms_replace_staff_services($conexion, $providerId, $staffId, $serviceIds)
{
    if (!pms_staff_services_table_ready($conexion)) {
        if (!empty($serviceIds)) {
            return ['error' => 'staff_services_table_missing'];
        }
        return ['ok' => true];
    }

    $deleteSql = 'DELETE rel
                  FROM provider_medical_staff_services rel
                  INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id
                  WHERE rel.provider_medical_staff_id = ? AND pms.provider_id = ?';
    $deleteStmt = mysqli_prepare($conexion, $deleteSql);
    if (!$deleteStmt) {
        return ['error' => 'db_prepare_failed'];
    }
    mysqli_stmt_bind_param($deleteStmt, 'ii', $staffId, $providerId);
    $deleted = mysqli_stmt_execute($deleteStmt);
    $deleteErr = mysqli_stmt_error($deleteStmt);
    mysqli_stmt_close($deleteStmt);
    if (!$deleted) {
        return ['error' => 'db_error', 'detail' => $deleteErr];
    }

    $serviceIds = array_values(array_unique(array_map('intval', (array)$serviceIds)));
    if (empty($serviceIds)) {
        return ['ok' => true];
    }

    $hasActive = pms_table_has_column($conexion, 'provider_medical_staff_services', 'active');
    if ($hasActive) {
        $insertSql = 'INSERT INTO provider_medical_staff_services
                        (provider_medical_staff_id, service_id, active)
                      VALUES (?, ?, 1)';
    } else {
        $insertSql = 'INSERT INTO provider_medical_staff_services
                        (provider_medical_staff_id, service_id)
                      VALUES (?, ?)';
    }
    $insertStmt = mysqli_prepare($conexion, $insertSql);
    if (!$insertStmt) {
        return ['error' => 'db_prepare_failed'];
    }
    foreach ($serviceIds as $serviceId) {
        mysqli_stmt_bind_param($insertStmt, 'ii', $staffId, $serviceId);
        $ok = mysqli_stmt_execute($insertStmt);
        if (!$ok) {
            $err = mysqli_stmt_error($insertStmt);
            mysqli_stmt_close($insertStmt);
            return ['error' => 'db_error', 'detail' => $err];
        }
    }
    mysqli_stmt_close($insertStmt);
    return ['ok' => true];
}

function pms_resolve_provider_service_id($conexion, $providerId, $serviceId = 0, $offerId = 0)
{
    $serviceId = (int)$serviceId;
    $offerId = (int)$offerId;
    if ($serviceId > 0) {
        $validated = pms_validate_provider_service_ids($conexion, $providerId, [$serviceId]);
        if (!empty($validated['error'])) {
            return ['error' => 'invalid_provider_service'];
        }
        return ['service_id' => $serviceId];
    }

    if ($offerId <= 0 || !pms_table_has_column($conexion, 'provider_service_offers', 'id')) {
        return ['error' => 'service_id_or_offer_id_required'];
    }

    $stmt = mysqli_prepare(
        $conexion,
        'SELECT service_id FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['error' => 'db_prepare_failed'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $offerId, $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row || empty($row['service_id'])) {
        return ['error' => 'offer_not_found'];
    }

    $resolvedServiceId = (int)$row['service_id'];
    $validated = pms_validate_provider_service_ids($conexion, $providerId, [$resolvedServiceId]);
    if (!empty($validated['error'])) {
        return ['error' => 'invalid_provider_service'];
    }
    return ['service_id' => $resolvedServiceId];
}

function pms_fetch_linked_user($conexion, $userId, $providerId, $currentStaffId = 0)
{
    if ($userId <= 0 || !pms_table_ready($conexion)) {
        return null;
    }
    if (!pms_table_has_column($conexion, 'usuarios', 'provider_id')) {
        return null;
    }

    $hasActive = pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasDeleted = pms_table_has_column($conexion, 'usuarios', 'is_deleted');
    $hasEmail = pms_table_has_column($conexion, 'usuarios', 'email');
    $hasRoleId = pms_table_has_column($conexion, 'usuarios', 'role_id');
    $hasRol = pms_table_has_column($conexion, 'usuarios', 'rol');

    $select = [
        'u.id',
        "COALESCE(NULLIF(u.nombre, ''), NULLIF(u.usuario, ''), CONCAT('Usuario #', u.id)) AS nombre",
        "COALESCE(NULLIF(u.usuario, ''), CONCAT('usuario_', u.id)) AS usuario",
        $hasEmail ? "COALESCE(NULLIF(u.email, ''), '') AS email" : "'' AS email",
        $hasActive ? 'u.activo' : '1 AS activo',
        $hasRoleId ? 'u.role_id' : 'NULL AS role_id',
        $hasRol ? 'u.rol' : 'NULL AS rol',
    ];

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM usuarios u WHERE u.id = ? AND u.provider_id = ?';
    if ($hasDeleted) {
        $sql .= ' AND u.is_deleted = 0';
    }
    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }

    $roleId = pms_resolve_user_role_id($row);
    if (!pms_is_medical_user_role($roleId)) {
        return ['error' => 'linked_user_must_be_medical_provider_role'];
    }

    if (pms_has_access_columns($conexion)) {
        $sqlLink = 'SELECT id, full_name FROM provider_medical_staff WHERE linked_user_id = ?';
        if ($currentStaffId > 0) {
            $sqlLink .= ' AND id <> ?';
        }
        $sqlLink .= ' LIMIT 1';
        $stmtLink = mysqli_prepare($conexion, $sqlLink);
        if ($stmtLink) {
            if ($currentStaffId > 0) {
                mysqli_stmt_bind_param($stmtLink, 'ii', $userId, $currentStaffId);
            } else {
                mysqli_stmt_bind_param($stmtLink, 'i', $userId);
            }
            mysqli_stmt_execute($stmtLink);
            $resLink = mysqli_stmt_get_result($stmtLink);
            $rowLink = $resLink ? mysqli_fetch_assoc($resLink) : null;
            mysqli_stmt_close($stmtLink);
            if ($rowLink) {
                return ['error' => 'linked_user_already_assigned', 'linked_staff' => $rowLink];
            }
        }
    }

    $row['role_id_resolved'] = $roleId;
    return $row;
}

function pms_access_status_payload($row)
{
    $linkedUserId = (int)($row['linked_user_id'] ?? 0);
    $canAccessAdmin = (int)($row['can_access_admin'] ?? 0) === 1;
    $linkedUserActive = isset($row['linked_user_active']) ? ((int)$row['linked_user_active'] === 1) : false;

    $payload = [
        'linked_user_label' => 'Sin usuario vinculado',
        'access_status' => 'no_user',
        'access_status_label' => 'Sin usuario vinculado',
    ];

    if ($linkedUserId <= 0) {
        return $payload;
    }

    $userName = trim((string)($row['linked_user_name'] ?? ''));
    $username = trim((string)($row['linked_username'] ?? ''));
    $email = trim((string)($row['linked_user_email'] ?? ''));
    $parts = [];
    if ($userName !== '') {
        $parts[] = $userName;
    }
    if ($username !== '') {
        $parts[] = '@' . $username;
    }
    if ($email !== '') {
        $parts[] = $email;
    }
    $payload['linked_user_label'] = !empty($parts) ? implode(' · ', $parts) : ('Usuario #' . $linkedUserId);

    if (!$canAccessAdmin) {
        $payload['access_status'] = 'linked_without_access';
        $payload['access_status_label'] = 'Usuario vinculado sin acceso';
        return $payload;
    }
    if (!$linkedUserActive) {
        $payload['access_status'] = 'linked_user_inactive';
        $payload['access_status_label'] = 'Usuario vinculado inactivo';
        return $payload;
    }

    $payload['access_status'] = 'enabled';
    $payload['access_status_label'] = 'Médico con acceso propio';
    return $payload;
}

function pms_staff_row($conexion, $staffId, $providerId = 0)
{
    $hasAccessColumns = pms_has_access_columns($conexion);
    $hasUsersTable = pms_table_has_column($conexion, 'usuarios', 'id');
    $hasUserActivo = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasUserEmail = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'email');

    $select = provider_staff_select_columns($conexion, 'pms');
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.nombre AS linked_user_name' : 'NULL AS linked_user_name';
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.usuario AS linked_username' : 'NULL AS linked_username';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserEmail) ? 'u.email AS linked_user_email' : 'NULL AS linked_user_email';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserActivo) ? 'u.activo AS linked_user_active' : 'NULL AS linked_user_active';

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM provider_medical_staff pms';
    if ($hasAccessColumns && $hasUsersTable) {
        $sql .= ' LEFT JOIN usuarios u ON u.id = pms.linked_user_id';
    }
    $sql .= ' WHERE pms.id = ?';
    if ($providerId > 0) {
        $sql .= ' AND pms.provider_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
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
    if (!$row) {
        return null;
    }
    $row = pms_normalize_staff_row($row);
    $row = array_merge($row, pms_access_status_payload($row));
    $effectiveProviderId = $providerId > 0 ? $providerId : (int)($row['provider_id'] ?? 0);
    $serviceMap = pms_fetch_staff_services_map($conexion, $effectiveProviderId, [$staffId]);
    $items = pms_attach_service_payload([$row], $serviceMap);
    return !empty($items) ? $items[0] : $row;
}

function pms_fetch_linkable_users($conexion, $providerId, $currentStaffId = 0)
{
    if (!pms_table_has_column($conexion, 'usuarios', 'provider_id')) {
        return [];
    }

    $hasDeleted = pms_table_has_column($conexion, 'usuarios', 'is_deleted');
    $hasActive = pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasEmail = pms_table_has_column($conexion, 'usuarios', 'email');
    $hasRoleId = pms_table_has_column($conexion, 'usuarios', 'role_id');
    $hasRol = pms_table_has_column($conexion, 'usuarios', 'rol');

    $select = [
        'u.id',
        "COALESCE(NULLIF(u.nombre, ''), NULLIF(u.usuario, ''), CONCAT('Usuario #', u.id)) AS nombre",
        "COALESCE(NULLIF(u.usuario, ''), CONCAT('usuario_', u.id)) AS usuario",
        $hasEmail ? "COALESCE(NULLIF(u.email, ''), '') AS email" : "'' AS email",
        $hasActive ? 'u.activo' : '1 AS activo',
        $hasRoleId ? 'u.role_id' : 'NULL AS role_id',
        $hasRol ? 'u.rol' : 'NULL AS rol',
    ];

    if (pms_has_access_columns($conexion)) {
        $select[] = 'pms2.id AS linked_staff_id';
        $select[] = 'pms2.full_name AS linked_staff_name';
    } else {
        $select[] = 'NULL AS linked_staff_id';
        $select[] = 'NULL AS linked_staff_name';
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM usuarios u';
    if (pms_has_access_columns($conexion)) {
        $sql .= ' LEFT JOIN provider_medical_staff pms2
                  ON pms2.linked_user_id = u.id';
        if ($currentStaffId > 0) {
            $sql .= ' AND pms2.id <> ' . (int)$currentStaffId;
        }
    }
    $sql .= ' WHERE u.provider_id = ?';
    if ($hasDeleted) {
        $sql .= ' AND u.is_deleted = 0';
    }
    $orderActiveExpr = $hasActive ? 'u.activo' : '1';
    $sql .= ' ORDER BY ' . $orderActiveExpr . ' DESC, u.nombre ASC, u.usuario ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $roleId = pms_resolve_user_role_id($row);
        $available = pms_is_medical_user_role($roleId) && empty($row['linked_staff_id']);
        $labelParts = [];
        if (!empty($row['nombre'])) {
            $labelParts[] = $row['nombre'];
        }
        if (!empty($row['usuario'])) {
            $labelParts[] = '@' . $row['usuario'];
        }
        if (!empty($row['email'])) {
            $labelParts[] = $row['email'];
        }
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'nombre' => (string)($row['nombre'] ?? ''),
            'usuario' => (string)($row['usuario'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'activo' => (int)($row['activo'] ?? 0),
            'role_id' => $roleId,
            'role_label' => isset(get_available_roles()[$roleId]) ? get_available_roles()[$roleId] : (string)$roleId,
            'linked_staff_id' => isset($row['linked_staff_id']) && $row['linked_staff_id'] !== null ? (int)$row['linked_staff_id'] : null,
            'linked_staff_name' => (string)($row['linked_staff_name'] ?? ''),
            'available' => $available,
            'label' => implode(' · ', array_filter($labelParts)),
        ];
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function pms_list_staff_rows($conexion, $providerId, $activeOnly = false)
{
    $hasAccessColumns = pms_has_access_columns($conexion);
    $hasUsersTable = pms_table_has_column($conexion, 'usuarios', 'id');
    $hasUserActivo = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasUserEmail = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'email');

    $select = provider_staff_select_columns($conexion, 'pms');
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.nombre AS linked_user_name' : 'NULL AS linked_user_name';
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.usuario AS linked_username' : 'NULL AS linked_username';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserEmail) ? 'u.email AS linked_user_email' : 'NULL AS linked_user_email';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserActivo) ? 'u.activo AS linked_user_active' : 'NULL AS linked_user_active';

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM provider_medical_staff pms';
    if ($hasAccessColumns && $hasUsersTable) {
        $sql .= ' LEFT JOIN usuarios u ON u.id = pms.linked_user_id';
    }
    $sql .= ' WHERE pms.provider_id = ?';
    if ($activeOnly) {
        $sql .= ' AND ' . pms_status_select_expr('pms') . ' = 1';
    }
    $sql .= ' ORDER BY ' . pms_status_select_expr('pms') . ' DESC, ' . pms_sort_order_sql('pms') . ' ASC, ' . pms_primary_order_sql('pms') . 'pms.full_name ASC, pms.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row = pms_normalize_staff_row($row);
        $rows[] = array_merge($row, pms_access_status_payload($row));
    }
    mysqli_stmt_close($stmt);
    return pms_attach_service_payload($rows, pms_fetch_staff_services_map($conexion, $providerId));
}

function pms_fetch_sorted_staff_ids($conexion, $providerId)
{
    $rows = pms_list_staff_rows($conexion, $providerId, false);
    return array_values(array_map('intval', array_column($rows, 'id')));
}

function pms_resequence_staff_sort_order($conexion, $providerId, $orderedIds)
{
    $orderedIds = array_values(array_filter(array_map('intval', (array)$orderedIds)));
    if (empty($orderedIds)) {
        return true;
    }

    $sortColumn = provider_staff_sort_column_name($conexion);
    if ($sortColumn === '') {
        return true;
    }

    $stmt = mysqli_prepare(
        $conexion,
        'UPDATE provider_medical_staff
            SET `' . $sortColumn . '` = ?, updated_at = NOW()
          WHERE id = ? AND provider_id = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $position = 10;
    foreach ($orderedIds as $id) {
        mysqli_stmt_bind_param($stmt, 'iii', $position, $id, $providerId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $position += 10;
    }

    mysqli_stmt_close($stmt);
    return true;
}

if (!pms_table_ready($conexion)) {
    pms_err('provider_medical_staff_table_missing — run sql/2026_03_12_provider_medical_staff.sql', 503);
}

$action = isset($_POST['action']) ? trim((string)$_POST['action'])
    : (isset($_GET['action']) ? trim((string)$_GET['action']) : '');

switch ($action) {
    case 'list_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $activeOnly = (int)($_GET['active_only'] ?? $_POST['active_only'] ?? 0) === 1;
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }
        $rows = pms_list_staff_rows($conexion, $providerId, $activeOnly);
        $activeCount = 0;
        foreach ($rows as $row) {
            if ((int)($row['is_active'] ?? $row['active'] ?? 0) === 1) {
                $activeCount++;
            }
        }

        pms_ok([
            'provider' => $provider,
            'items' => $rows,
            'total' => count($rows),
            'active_total' => $activeCount,
        ]);
    }

    case 'list_provider_services': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        pms_ok([
            'provider' => $provider,
            'items' => pms_provider_enabled_services($conexion, $providerId, true),
        ]);
    }

    // ── Catálogos base del sistema para el modal de staff ────────────────────
    // Fuente centralizada de roles y especialidades.
    // ESTADO: opciones servidas como catálogo del sistema (no persistidas en BD todavía).
    // SIGUIENTE PASO: crear tablas staff_role_catalog y staff_specialty_catalog
    // para que admin pueda ampliar/editar las opciones sin tocar código.
    case 'list_staff_catalogs': {
        pms_ok([
            'roles' => [
                'Lead Doctor', 'Specialist', 'Surgeon', 'Dentist', 'Orthodontist',
                'Oral Surgeon', 'Cosmetic Dentist', 'General Physician', 'Nurse',
                'Patient Coordinator', 'Medical Assistant', 'Anesthesiologist',
                'Therapist', 'Administrative Coordinator',
            ],
            'specialties' => [
                'Dentistry', 'Cosmetic Dentistry', 'Orthodontics', 'Oral Surgery',
                'Plastic Surgery', 'Bariatric Surgery', 'Dermatology', 'Ophthalmology',
                'Fertility', 'Orthopedics', 'General Medicine', 'Aesthetic Medicine',
                'Rehabilitation', 'Nutrition',
            ],
        ]);
    }

    // ── Sedes/clínicas del provider para el modal de staff ───────────────────
    // Fuente controlada: nombre del provider + sedes ya registradas en staff existente.
    // ESTADO: no existe tabla provider_branches todavía.
    // SIGUIENTE PASO: crear tabla provider_branches y reemplazar la query del historial.
    case 'list_provider_clinics': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        // Sede principal: nombre del provider (sede raíz)
        $clinics = [];
        if (!empty($provider['name'])) {
            $clinics[] = [
                'value'  => trim($provider['name']),
                'label'  => trim($provider['name']),
                'source' => 'provider',
            ];
        }

        // Sedes adicionales: valores distintos ya usados por staff del mismo provider
        $additionalQuery = mysqli_prepare(
            $conexion,
            "SELECT DISTINCT TRIM(clinic_name) AS cn
             FROM provider_medical_staff
             WHERE provider_id = ?
               AND TRIM(IFNULL(clinic_name, '')) != ''
             ORDER BY cn ASC
             LIMIT 50"
        );
        if ($additionalQuery) {
            mysqli_stmt_bind_param($additionalQuery, 'i', $providerId);
            mysqli_stmt_execute($additionalQuery);
            $additionalRes = mysqli_stmt_get_result($additionalQuery);
            while ($additionalRow = mysqli_fetch_assoc($additionalRes)) {
                $cn = $additionalRow['cn'];
                // No duplicar la sede principal
                $isDuplicate = false;
                foreach ($clinics as $existing) {
                    if (mb_strtolower($existing['value']) === mb_strtolower($cn)) {
                        $isDuplicate = true;
                        break;
                    }
                }
                if (!$isDuplicate) {
                    $clinics[] = ['value' => $cn, 'label' => $cn, 'source' => 'history'];
                }
            }
            mysqli_stmt_close($additionalQuery);
        }

        pms_ok(['clinics' => $clinics]);
    }

    case 'list_linkable_users': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $currentStaffId = (int)($_GET['staff_id'] ?? $_POST['staff_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        pms_ok([
            'provider' => $provider,
            'items' => pms_fetch_linkable_users($conexion, $providerId, $currentStaffId),
        ]);
    }

    case 'get_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $staffId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        pms_assert_provider_scope($providerId);
        if ($staffId <= 0) {
            pms_err('provider_id and id required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $row = pms_staff_row($conexion, $staffId, $providerId);
        if (!$row) {
            pms_err('staff_not_found', 404);
        }

        pms_ok([
            'item' => $row,
            'provider' => $provider,
            'provider_services' => pms_provider_enabled_services($conexion, $providerId, true),
        ]);
    }

    case 'save_staff': {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $currentRow = $staffId > 0 ? pms_staff_row($conexion, $staffId, $providerId) : null;
        if ($staffId > 0 && !$currentRow) {
            pms_err('staff_not_found', 404);
        }

        $fullName = pms_clean_text($_POST['full_name'] ?? '', 150);
        if ($fullName === '') {
            pms_err('El nombre completo es obligatorio', 422);
        }

        $roleTitle = pms_clean_text($_POST['role_title'] ?? '', 120);
        $specialty = pms_clean_text($_POST['specialty'] ?? '', 120);
        $bioShort = pms_clean_long_text($_POST['bio_short'] ?? '');
        $photo = pms_clean_text($_POST['photo'] ?? '', 255);
        $license = pms_clean_text($_POST['professional_license'] ?? '', 120);
        $email = pms_clean_email($_POST['email'] ?? '');
        if ($email === false) {
            pms_err('El correo no tiene un formato válido', 422);
        }
        $phone = pms_clean_text($_POST['phone'] ?? '', 60);
        $clinicName = pms_clean_text($_POST['clinic_name'] ?? '', 180);
        $notes = pms_clean_long_text($_POST['notes'] ?? '');
        $isPrimaryDoctor = pms_requested_flag('is_primary_doctor');
        $isActive = isset($_POST['is_active']) ? pms_requested_flag('is_active') : pms_requested_flag('active');
        $sortOrderRaw = trim((string)($_POST['sort_order'] ?? ''));
        $sortOrder = ($sortOrderRaw === '')
            ? ($currentRow ? (int)($currentRow['sort_order'] ?? 0) : pms_next_sort_order($conexion, $providerId))
            : max(0, (int)$sortOrderRaw);
        $linkedUserId = (int)($_POST['linked_user_id'] ?? 0);
        $canAccessAdmin = isset($_POST['can_access_admin']) ? 1 : 0;
        $requestedServiceIds = pms_requested_service_ids();

        if ($canAccessAdmin === 1 && $linkedUserId <= 0) {
            pms_err('Debes seleccionar un usuario vinculado para habilitar acceso al admin', 422);
        }

        if (($linkedUserId > 0 || $canAccessAdmin === 1) && !pms_has_access_columns($conexion)) {
            pms_err('provider_medical_staff_access_columns_missing — run sql/2026_03_12_provider_staff_access_and_item_assignment.sql', 503);
        }

        $linkedUser = null;
        if ($linkedUserId > 0) {
            $linkedUser = pms_fetch_linked_user($conexion, $linkedUserId, $providerId, $staffId);
            if (!$linkedUser) {
                pms_err('El usuario vinculado no pertenece a este prestador', 422);
            }
            if (!empty($linkedUser['error'])) {
                if ($linkedUser['error'] === 'linked_user_must_be_medical_provider_role') {
                    pms_err('El usuario vinculado debe tener rol médico del prestador', 422);
                }
                if ($linkedUser['error'] === 'linked_user_already_assigned') {
                    $staffName = trim((string)($linkedUser['linked_staff']['full_name'] ?? ''));
                    pms_err('Ese usuario ya está vinculado a otro staff' . ($staffName !== '' ? ': ' . $staffName : ''), 422);
                }
                pms_err('No fue posible validar el usuario vinculado', 422);
            }
        } else {
            $canAccessAdmin = 0;
        }

        $validatedServices = pms_validate_provider_service_ids($conexion, $providerId, $requestedServiceIds);
        if (!empty($validatedServices['error'])) {
            pms_err('Solo puedes asignar al médico servicios activos habilitados para este prestador', 422);
        }

        mysqli_begin_transaction($conexion);

        try {
            $statusColumn = provider_staff_status_column_name($conexion);
            $legacyActiveColumn = provider_staff_has_legacy_active_column($conexion);
            $linkedUserIdSql = $linkedUserId > 0 ? $linkedUserId : 0;

            if ($staffId > 0) {
                $fields = [];
                $types = '';
                $params = [];

                $fields[] = 'full_name = ?';
                $types .= 's';
                $params[] = $fullName;

                if (provider_staff_table_has_column($conexion, 'role_title')) {
                    $fields[] = 'role_title = ?';
                    $types .= 's';
                    $params[] = $roleTitle;
                }
                if (provider_staff_table_has_column($conexion, 'specialty')) {
                    $fields[] = 'specialty = ?';
                    $types .= 's';
                    $params[] = $specialty;
                }
                if (provider_staff_table_has_column($conexion, 'bio_short')) {
                    $fields[] = 'bio_short = ?';
                    $types .= 's';
                    $params[] = $bioShort;
                }
                if (provider_staff_table_has_column($conexion, 'photo')) {
                    $fields[] = 'photo = ?';
                    $types .= 's';
                    $params[] = $photo;
                }
                if (provider_staff_table_has_column($conexion, 'email')) {
                    $fields[] = 'email = ?';
                    $types .= 's';
                    $params[] = $email;
                }
                if (provider_staff_table_has_column($conexion, 'phone')) {
                    $fields[] = 'phone = ?';
                    $types .= 's';
                    $params[] = $phone;
                }
                if (provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
                    $fields[] = 'is_primary_doctor = ?';
                    $types .= 'i';
                    $params[] = $isPrimaryDoctor;
                }
                if (provider_staff_table_has_column($conexion, 'sort_order')) {
                    $fields[] = 'sort_order = ?';
                    $types .= 'i';
                    $params[] = $sortOrder;
                }
                if (provider_staff_table_has_column($conexion, 'professional_license')) {
                    $fields[] = 'professional_license = ?';
                    $types .= 's';
                    $params[] = $license;
                }
                if (provider_staff_table_has_column($conexion, 'clinic_name')) {
                    $fields[] = 'clinic_name = ?';
                    $types .= 's';
                    $params[] = $clinicName;
                }
                if (provider_staff_table_has_column($conexion, 'notes')) {
                    $fields[] = 'notes = ?';
                    $types .= 's';
                    $params[] = $notes;
                }
                if (pms_has_access_columns($conexion)) {
                    $fields[] = 'linked_user_id = NULLIF(?, 0)';
                    $types .= 'i';
                    $params[] = $linkedUserIdSql;
                    $fields[] = 'can_access_admin = ?';
                    $types .= 'i';
                    $params[] = $canAccessAdmin;
                }
                if ($statusColumn !== '') {
                    $fields[] = '`' . $statusColumn . '` = ?';
                    $types .= 'i';
                    $params[] = $isActive;
                }
                if ($legacyActiveColumn && $statusColumn !== 'active') {
                    $fields[] = 'active = ?';
                    $types .= 'i';
                    $params[] = $isActive;
                }
                if (provider_staff_table_has_column($conexion, 'updated_at')) {
                    $fields[] = 'updated_at = NOW()';
                }

                $sql = 'UPDATE provider_medical_staff SET ' . implode(', ', $fields) . ' WHERE id = ? AND provider_id = ? LIMIT 1';
                $stmt = mysqli_prepare($conexion, $sql);
                if (!$stmt) {
                    throw new Exception('db_prepare_failed');
                }
                $types .= 'ii';
                $params[] = $staffId;
                $params[] = $providerId;
                bind_stmt_params($stmt, $types, $params);
                $ok = mysqli_stmt_execute($stmt);
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                if (!$ok) {
                    throw new Exception('db_error: ' . $err);
                }
                $savedId = $staffId;
                $message = 'Staff médico actualizado correctamente';
            } else {
                $columns = ['provider_id', 'full_name'];
                $placeholders = ['?', '?'];
                $types = 'is';
                $params = [$providerId, $fullName];

                if (provider_staff_table_has_column($conexion, 'role_title')) {
                    $columns[] = 'role_title';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $roleTitle;
                }
                if (provider_staff_table_has_column($conexion, 'specialty')) {
                    $columns[] = 'specialty';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $specialty;
                }
                if (provider_staff_table_has_column($conexion, 'bio_short')) {
                    $columns[] = 'bio_short';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $bioShort;
                }
                if (provider_staff_table_has_column($conexion, 'photo')) {
                    $columns[] = 'photo';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $photo;
                }
                if (provider_staff_table_has_column($conexion, 'email')) {
                    $columns[] = 'email';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $email;
                }
                if (provider_staff_table_has_column($conexion, 'phone')) {
                    $columns[] = 'phone';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $phone;
                }
                if (provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
                    $columns[] = 'is_primary_doctor';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $isPrimaryDoctor;
                }
                if (provider_staff_table_has_column($conexion, 'sort_order')) {
                    $columns[] = 'sort_order';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $sortOrder;
                }
                if (provider_staff_table_has_column($conexion, 'professional_license')) {
                    $columns[] = 'professional_license';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $license;
                }
                if (provider_staff_table_has_column($conexion, 'clinic_name')) {
                    $columns[] = 'clinic_name';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $clinicName;
                }
                if (provider_staff_table_has_column($conexion, 'notes')) {
                    $columns[] = 'notes';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $notes;
                }
                if (pms_has_access_columns($conexion)) {
                    $columns[] = 'linked_user_id';
                    $placeholders[] = 'NULLIF(?, 0)';
                    $types .= 'i';
                    $params[] = $linkedUserIdSql;
                    $columns[] = 'can_access_admin';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $canAccessAdmin;
                }
                if ($statusColumn !== '') {
                    $columns[] = $statusColumn;
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $isActive;
                }
                if ($legacyActiveColumn && $statusColumn !== 'active') {
                    $columns[] = 'active';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $isActive;
                }

                $sql = 'INSERT INTO provider_medical_staff (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = mysqli_prepare($conexion, $sql);
                if (!$stmt) {
                    throw new Exception('db_prepare_failed');
                }
                bind_stmt_params($stmt, $types, $params);
                $ok = mysqli_stmt_execute($stmt);
                $err = mysqli_stmt_error($stmt);
                $savedId = (int)mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt);
                if (!$ok) {
                    throw new Exception('db_error: ' . $err);
                }
                $message = 'Staff médico creado correctamente';
            }

            if ($isPrimaryDoctor === 1 && provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
                $stmtPrimary = mysqli_prepare(
                    $conexion,
                    'UPDATE provider_medical_staff
                        SET is_primary_doctor = CASE WHEN id = ? THEN 1 ELSE 0 END,
                            updated_at = NOW()
                      WHERE provider_id = ?'
                );
                if (!$stmtPrimary) {
                    throw new Exception('db_prepare_failed');
                }
                mysqli_stmt_bind_param($stmtPrimary, 'ii', $savedId, $providerId);
                $okPrimary = mysqli_stmt_execute($stmtPrimary);
                $errPrimary = mysqli_stmt_error($stmtPrimary);
                mysqli_stmt_close($stmtPrimary);
                if (!$okPrimary) {
                    throw new Exception('db_error: ' . $errPrimary);
                }
            }

            $replaceResult = pms_replace_staff_services($conexion, $providerId, $savedId, $requestedServiceIds);
            if (!empty($replaceResult['error'])) {
                if ($replaceResult['error'] === 'staff_services_table_missing') {
                    throw new Exception('provider_medical_staff_services_table_missing — run sql/2026_03_12_provider_medical_staff_services.sql');
                }
                throw new Exception(!empty($replaceResult['detail']) ? $replaceResult['detail'] : $replaceResult['error']);
            }

            mysqli_commit($conexion);
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $status = (
                strpos($e->getMessage(), 'provider_medical_staff_services_table_missing') !== false ||
                strpos($e->getMessage(), 'provider_medical_staff_access_columns_missing') !== false
            ) ? 503 : 500;
            pms_err($e->getMessage(), $status);
        }

        $saved = pms_staff_row($conexion, $savedId, $providerId);
        pms_ok([
            'item' => $saved,
            'message' => $message,
            'provider' => $provider,
        ]);
    }

    case 'list_assignable_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $serviceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
        $offerId = (int)($_GET['offer_id'] ?? $_POST['offer_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $resolved = pms_resolve_provider_service_id($conexion, $providerId, $serviceId, $offerId);
        if (!empty($resolved['error'])) {
            pms_err($resolved['error'], 422);
        }

        $rows = pms_list_staff_rows($conexion, $providerId, true);
        $targetServiceId = (int)$resolved['service_id'];
        $items = [];
        foreach ($rows as $row) {
            $serviceIds = array_map('intval', (array)($row['service_ids'] ?? []));
            if (in_array($targetServiceId, $serviceIds, true)) {
                $items[] = $row;
            }
        }

        pms_ok([
            'provider' => $provider,
            'service_id' => $targetServiceId,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    case 'reorder_staff': {
        $providerId = (int)($_POST['provider_id'] ?? $_GET['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $direction = trim((string)($_POST['direction'] ?? $_GET['direction'] ?? ''));
        pms_assert_provider_scope($providerId);
        if ($staffId <= 0 || !in_array($direction, ['up', 'down'], true)) {
            pms_err('provider_id, id and direction are required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }
        if (!pms_staff_row($conexion, $staffId, $providerId)) {
            pms_err('staff_not_found', 404);
        }

        $orderedIds = pms_fetch_sorted_staff_ids($conexion, $providerId);
        $currentIndex = array_search($staffId, $orderedIds, true);
        if ($currentIndex === false) {
            pms_err('staff_not_found', 404);
        }

        $swapIndex = ($direction === 'up') ? ($currentIndex - 1) : ($currentIndex + 1);
        if ($swapIndex < 0 || $swapIndex >= count($orderedIds)) {
            $rows = pms_list_staff_rows($conexion, $providerId, false);
            pms_ok([
                'items' => $rows,
                'provider' => $provider,
                'message' => 'No hay más movimientos disponibles',
            ]);
        }

        $tmp = $orderedIds[$currentIndex];
        $orderedIds[$currentIndex] = $orderedIds[$swapIndex];
        $orderedIds[$swapIndex] = $tmp;

        mysqli_begin_transaction($conexion);
        try {
            if (!pms_resequence_staff_sort_order($conexion, $providerId, $orderedIds)) {
                throw new Exception('db_error: reorder_failed');
            }
            mysqli_commit($conexion);
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            pms_err($e->getMessage(), 500);
        }

        $rows = pms_list_staff_rows($conexion, $providerId, false);
        pms_ok([
            'items' => $rows,
            'provider' => $provider,
            'message' => 'Orden del staff actualizado',
        ]);
    }

    case 'toggle_staff': {
        $providerId = (int)($_POST['provider_id'] ?? $_GET['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $value = (int)($_POST['value'] ?? $_GET['value'] ?? -1);
        pms_assert_provider_scope($providerId);
        if ($staffId <= 0 || ($value !== 0 && $value !== 1)) {
            pms_err('provider_id, id and value are required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }
        if (!pms_staff_row($conexion, $staffId, $providerId)) {
            pms_err('staff_not_found', 404);
        }

        $statusColumn = provider_staff_status_column_name($conexion);
        $legacyActiveColumn = provider_staff_has_legacy_active_column($conexion);
        $fields = [];
        $types = '';
        $params = [];
        if ($statusColumn !== '') {
            $fields[] = '`' . $statusColumn . '` = ?';
            $types .= 'i';
            $params[] = $value;
        }
        if ($legacyActiveColumn && $statusColumn !== 'active') {
            $fields[] = 'active = ?';
            $types .= 'i';
            $params[] = $value;
        }
        if (empty($fields)) {
            pms_err('provider_medical_staff_status_column_missing — run sql/2026_03_12_provider_medical_staff.sql', 503);
        }
        if (provider_staff_table_has_column($conexion, 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        $stmt = mysqli_prepare(
            $conexion,
            'UPDATE provider_medical_staff
                SET ' . implode(', ', $fields) . '
              WHERE id = ? AND provider_id = ?
              LIMIT 1'
        );
        if (!$stmt) {
            pms_err('db_prepare_failed', 500);
        }
        $types .= 'ii';
        $params[] = $staffId;
        $params[] = $providerId;
        bind_stmt_params($stmt, $types, $params);
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            pms_err('db_error: ' . $err, 500);
        }

        $row = pms_staff_row($conexion, $staffId, $providerId);
        pms_ok([
            'item' => $row,
            'message' => ($value === 1) ? 'Registro activado' : 'Registro desactivado',
            'provider' => $provider,
        ]);
    }

    default:
        pms_err('action_required', 400);
}
