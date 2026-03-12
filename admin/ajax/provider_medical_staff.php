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
 *
 * Nota: expone active_only para futura asignacion provider -> medical staff
 * sin acoplar aun booking_request_items a esta tabla.
 */

require_once '../include/conexion.php';
require_once '../include/roles.php';

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
    $email = pms_clean_text($value, 190);
    if ($email === '') {
        return '';
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
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
    $hasRoleId = pms_table_has_column($conexion, 'usuarios', 'role_id');
    $hasRol = pms_table_has_column($conexion, 'usuarios', 'rol');

    $select = [
        'u.id',
        "COALESCE(NULLIF(u.nombre, ''), NULLIF(u.usuario, ''), CONCAT('Usuario #', u.id)) AS nombre",
        "COALESCE(NULLIF(u.usuario, ''), CONCAT('usuario_', u.id)) AS usuario",
        "COALESCE(NULLIF(u.email, ''), '') AS email",
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

    $select = [
        'pms.id',
        'pms.provider_id',
        'pms.full_name',
        'pms.specialty',
        'pms.professional_license',
        'pms.email',
        'pms.phone',
        'pms.clinic_name',
        'pms.notes',
        'pms.active',
        'pms.created_at',
        'pms.updated_at',
        $hasAccessColumns ? 'pms.linked_user_id' : 'NULL AS linked_user_id',
        $hasAccessColumns ? 'pms.can_access_admin' : '0 AS can_access_admin',
        ($hasAccessColumns && $hasUsersTable) ? 'u.nombre AS linked_user_name' : 'NULL AS linked_user_name',
        ($hasAccessColumns && $hasUsersTable) ? 'u.usuario AS linked_username' : 'NULL AS linked_username',
        ($hasAccessColumns && $hasUsersTable) ? 'u.email AS linked_user_email' : 'NULL AS linked_user_email',
        ($hasAccessColumns && $hasUsersTable && $hasUserActivo) ? 'u.activo AS linked_user_active' : 'NULL AS linked_user_active',
    ];

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
    $row['active_label'] = ((int)($row['active'] ?? 0) === 1) ? 'Activo' : 'Inactivo';
    return array_merge($row, pms_access_status_payload($row));
}

function pms_fetch_linkable_users($conexion, $providerId, $currentStaffId = 0)
{
    if (!pms_table_has_column($conexion, 'usuarios', 'provider_id')) {
        return [];
    }

    $hasDeleted = pms_table_has_column($conexion, 'usuarios', 'is_deleted');
    $hasActive = pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasRoleId = pms_table_has_column($conexion, 'usuarios', 'role_id');
    $hasRol = pms_table_has_column($conexion, 'usuarios', 'rol');

    $select = [
        'u.id',
        "COALESCE(NULLIF(u.nombre, ''), NULLIF(u.usuario, ''), CONCAT('Usuario #', u.id)) AS nombre",
        "COALESCE(NULLIF(u.usuario, ''), CONCAT('usuario_', u.id)) AS usuario",
        "COALESCE(NULLIF(u.email, ''), '') AS email",
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

    $select = [
        'pms.id',
        'pms.provider_id',
        'pms.full_name',
        'pms.specialty',
        'pms.professional_license',
        'pms.email',
        'pms.phone',
        'pms.clinic_name',
        'pms.notes',
        'pms.active',
        'pms.created_at',
        'pms.updated_at',
        $hasAccessColumns ? 'pms.linked_user_id' : 'NULL AS linked_user_id',
        $hasAccessColumns ? 'pms.can_access_admin' : '0 AS can_access_admin',
        ($hasAccessColumns && $hasUsersTable) ? 'u.nombre AS linked_user_name' : 'NULL AS linked_user_name',
        ($hasAccessColumns && $hasUsersTable) ? 'u.usuario AS linked_username' : 'NULL AS linked_username',
        ($hasAccessColumns && $hasUsersTable) ? 'u.email AS linked_user_email' : 'NULL AS linked_user_email',
        ($hasAccessColumns && $hasUsersTable && $hasUserActivo) ? 'u.activo AS linked_user_active' : 'NULL AS linked_user_active',
    ];

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM provider_medical_staff pms';
    if ($hasAccessColumns && $hasUsersTable) {
        $sql .= ' LEFT JOIN usuarios u ON u.id = pms.linked_user_id';
    }
    $sql .= ' WHERE pms.provider_id = ?';
    if ($activeOnly) {
        $sql .= ' AND pms.active = 1';
    }
    $sql .= ' ORDER BY pms.active DESC, pms.full_name ASC, pms.id DESC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row['active_label'] = ((int)($row['active'] ?? 0) === 1) ? 'Activo' : 'Inactivo';
        $rows[] = array_merge($row, pms_access_status_payload($row));
    }
    mysqli_stmt_close($stmt);
    return $rows;
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
            if ((int)($row['active'] ?? 0) === 1) {
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

        pms_ok(['item' => $row, 'provider' => $provider]);
    }

    case 'save_staff': {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $fullName = pms_clean_text($_POST['full_name'] ?? '', 180);
        if ($fullName === '') {
            pms_err('El nombre completo es obligatorio', 422);
        }

        $specialty = pms_clean_text($_POST['specialty'] ?? '', 180);
        $license = pms_clean_text($_POST['professional_license'] ?? '', 120);
        $email = pms_clean_email($_POST['email'] ?? '');
        if ($email === false) {
            pms_err('El correo no tiene un formato válido', 422);
        }
        $phone = pms_clean_text($_POST['phone'] ?? '', 80);
        $clinicName = pms_clean_text($_POST['clinic_name'] ?? '', 180);
        $notes = trim((string)($_POST['notes'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;
        $linkedUserId = (int)($_POST['linked_user_id'] ?? 0);
        $canAccessAdmin = isset($_POST['can_access_admin']) ? 1 : 0;

        if ($canAccessAdmin === 1 && $linkedUserId <= 0) {
            pms_err('Debes seleccionar un usuario vinculado para habilitar acceso al admin', 422);
        }

        if (($linkedUserId > 0 || $canAccessAdmin === 1) && !pms_has_access_columns($conexion)) {
            pms_err('provider_medical_staff_access_columns_missing — run sql/2026_03_12_provider_staff_access_and_item_assignment.sql', 503);
        }

        if ($staffId > 0 && !pms_staff_row($conexion, $staffId, $providerId)) {
            pms_err('staff_not_found', 404);
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

        if ($staffId > 0) {
            if (pms_has_access_columns($conexion)) {
                $stmt = mysqli_prepare(
                    $conexion,
                    'UPDATE provider_medical_staff
                        SET full_name = ?, specialty = ?, professional_license = ?, email = ?, phone = ?, clinic_name = ?, linked_user_id = NULLIF(?, 0), can_access_admin = ?, notes = ?, active = ?, updated_at = NOW()
                      WHERE id = ? AND provider_id = ?
                      LIMIT 1'
                );
            } else {
                $stmt = mysqli_prepare(
                    $conexion,
                    'UPDATE provider_medical_staff
                        SET full_name = ?, specialty = ?, professional_license = ?, email = ?, phone = ?, clinic_name = ?, notes = ?, active = ?, updated_at = NOW()
                      WHERE id = ? AND provider_id = ?
                      LIMIT 1'
                );
            }
            if (!$stmt) {
                pms_err('db_prepare_failed', 500);
            }
            if (pms_has_access_columns($conexion)) {
                $linkedUserIdSql = $linkedUserId > 0 ? $linkedUserId : 0;
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssiisiii',
                    $fullName,
                    $specialty,
                    $license,
                    $email,
                    $phone,
                    $clinicName,
                    $linkedUserIdSql,
                    $canAccessAdmin,
                    $notes,
                    $active,
                    $staffId,
                    $providerId
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    'sssssssiii',
                    $fullName,
                    $specialty,
                    $license,
                    $email,
                    $phone,
                    $clinicName,
                    $notes,
                    $active,
                    $staffId,
                    $providerId
                );
            }
            $ok = mysqli_stmt_execute($stmt);
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if (!$ok) {
                pms_err('db_error: ' . $err, 500);
            }
            $savedId = $staffId;
            $message = 'Staff médico actualizado correctamente';
        } else {
            if (pms_has_access_columns($conexion)) {
                $stmt = mysqli_prepare(
                    $conexion,
                    'INSERT INTO provider_medical_staff
                        (provider_id, full_name, specialty, professional_license, email, phone, clinic_name, linked_user_id, can_access_admin, notes, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?)'
                );
            } else {
                $stmt = mysqli_prepare(
                    $conexion,
                    'INSERT INTO provider_medical_staff
                        (provider_id, full_name, specialty, professional_license, email, phone, clinic_name, notes, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
            }
            if (!$stmt) {
                pms_err('db_prepare_failed', 500);
            }
            if (pms_has_access_columns($conexion)) {
                $linkedUserIdSql = $linkedUserId > 0 ? $linkedUserId : 0;
                mysqli_stmt_bind_param(
                    $stmt,
                    'issssssiisi',
                    $providerId,
                    $fullName,
                    $specialty,
                    $license,
                    $email,
                    $phone,
                    $clinicName,
                    $linkedUserIdSql,
                    $canAccessAdmin,
                    $notes,
                    $active
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    'isssssssi',
                    $providerId,
                    $fullName,
                    $specialty,
                    $license,
                    $email,
                    $phone,
                    $clinicName,
                    $notes,
                    $active
                );
            }
            $ok = mysqli_stmt_execute($stmt);
            $err = mysqli_stmt_error($stmt);
            $savedId = (int)mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
            if (!$ok) {
                pms_err('db_error: ' . $err, 500);
            }
            $message = 'Staff médico creado correctamente';
        }

        $saved = pms_staff_row($conexion, $savedId, $providerId);
        pms_ok([
            'item' => $saved,
            'message' => $message,
            'provider' => $provider,
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

        $stmt = mysqli_prepare(
            $conexion,
            'UPDATE provider_medical_staff
                SET active = ?, updated_at = NOW()
              WHERE id = ? AND provider_id = ?
              LIMIT 1'
        );
        if (!$stmt) {
            pms_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'iii', $value, $staffId, $providerId);
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
