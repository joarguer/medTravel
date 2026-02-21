<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

function mis_datos_err($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function mis_datos_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function mis_datos_bind_params($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [];
    $bind[] = $stmt;
    $bind[] = &$types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function mis_datos_enum_options($typeDef)
{
    $typeDef = (string)$typeDef;
    if (!preg_match('/^enum\((.*)\)$/i', $typeDef, $m)) {
        return [];
    }
    if (!preg_match_all("/'([^']*)'/", $m[1], $matches)) {
        return [];
    }
    return array_values(array_filter(array_map('trim', $matches[1]), static function ($v) {
        return $v !== '';
    }));
}

function mis_datos_split_name($displayName)
{
    $displayName = trim((string)$displayName);
    if ($displayName === '') {
        return ['Client', ''];
    }
    $parts = preg_split('/\s+/', $displayName);
    $first = trim((string)($parts[0] ?? 'Client'));
    $last = '';
    if (count($parts) > 1) {
        $last = trim(implode(' ', array_slice($parts, 1)));
    }
    return [$first !== '' ? $first : 'Client', $last];
}

function mis_datos_schema($conexion)
{
    $schema = [];
    $res = mysqli_query($conexion, 'SHOW FULL COLUMNS FROM `clientes`');
    if (!$res) {
        return $schema;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $field = (string)($row['Field'] ?? '');
        if ($field === '') {
            continue;
        }
        $schema[$field] = $row;
    }
    return $schema;
}

function mis_datos_required_no_default($schema, $field)
{
    if (!isset($schema[$field])) {
        return false;
    }
    $meta = $schema[$field];
    $isNullable = strtoupper((string)($meta['Null'] ?? 'YES')) === 'YES';
    $default = $meta['Default'];
    $extra = strtolower((string)($meta['Extra'] ?? ''));
    if ($isNullable) {
        return false;
    }
    if ($default !== null) {
        return false;
    }
    if (strpos($extra, 'auto_increment') !== false) {
        return false;
    }
    return true;
}

function mis_datos_build_editable_fields($schema)
{
    $preferred = [
        'nombre',
        'apellido',
        'telefono',
        'whatsapp',
        'fecha_nacimiento',
        'pais',
        'estado',
        'ciudad',
        'direccion',
        'codigo_postal',
        'tipo_documento',
        'numero_pasaporte',
        'idioma_preferido',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'contacto_emergencia_relacion',
        'condiciones_medicas',
        'alergias',
        'medicamentos_actuales',
    ];

    $out = [];
    foreach ($preferred as $f) {
        if (isset($schema[$f])) {
            $out[] = $f;
        }
    }
    return $out;
}

function mis_datos_resolve_client_row($conexion, $schema, $clientUserId, $clientEmail)
{
    $clientUserId = (int)$clientUserId;
    $clientEmail = strtolower(trim((string)$clientEmail));
    if ($clientEmail === '') {
        return ['ok' => false, 'message' => 'session_email_missing'];
    }

    $hasClientUserIdCol = isset($schema['client_user_id']);
    $row = null;

    if ($hasClientUserIdCol && $clientUserId > 0) {
        $sqlByUser = 'SELECT * FROM clientes WHERE client_user_id = ? ORDER BY id DESC LIMIT 1';
        $stmtUser = mysqli_prepare($conexion, $sqlByUser);
        if ($stmtUser) {
            mysqli_stmt_bind_param($stmtUser, 'i', $clientUserId);
            if (mysqli_stmt_execute($stmtUser)) {
                $res = mysqli_stmt_get_result($stmtUser);
                $row = $res ? mysqli_fetch_assoc($res) : null;
            }
            mysqli_stmt_close($stmtUser);
        }
    }

    if (!$row) {
        $sqlByEmail = 'SELECT * FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) ORDER BY id DESC LIMIT 1';
        $stmtEmail = mysqli_prepare($conexion, $sqlByEmail);
        if ($stmtEmail) {
            mysqli_stmt_bind_param($stmtEmail, 's', $clientEmail);
            if (mysqli_stmt_execute($stmtEmail)) {
                $res = mysqli_stmt_get_result($stmtEmail);
                $row = $res ? mysqli_fetch_assoc($res) : null;
            }
            mysqli_stmt_close($stmtEmail);
        }
    }

    if ($row) {
        if ($hasClientUserIdCol && $clientUserId > 0 && (int)($row['client_user_id'] ?? 0) <= 0) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $stmtLink = mysqli_prepare($conexion, 'UPDATE clientes SET client_user_id = ? WHERE id = ? LIMIT 1');
                if ($stmtLink) {
                    mysqli_stmt_bind_param($stmtLink, 'ii', $clientUserId, $id);
                    mysqli_stmt_execute($stmtLink);
                    mysqli_stmt_close($stmtLink);
                    $row['client_user_id'] = $clientUserId;
                }
            }
        }
        return ['ok' => true, 'row' => $row, 'created' => false];
    }

    $displayName = (string)($_SESSION['nombre_usuario'] ?? 'Client');
    [$firstName, $lastName] = mis_datos_split_name($displayName);

    $insertData = [];
    if (isset($schema['nombre'])) {
        $insertData['nombre'] = $firstName;
    }
    if (isset($schema['apellido'])) {
        $insertData['apellido'] = $lastName;
    }
    if (isset($schema['email'])) {
        $insertData['email'] = $clientEmail;
    }
    if (isset($schema['telefono'])) {
        $insertData['telefono'] = trim((string)($_SESSION['telefono'] ?? ''));
    }
    if (isset($schema['pais'])) {
        $insertData['pais'] = 'USA';
    }
    if (isset($schema['status'])) {
        $statusOpts = mis_datos_enum_options((string)($schema['status']['Type'] ?? ''));
        $insertData['status'] = in_array('lead', $statusOpts, true) ? 'lead' : (string)($statusOpts[0] ?? '');
    }
    if (isset($schema['origen_contacto'])) {
        $originOpts = mis_datos_enum_options((string)($schema['origen_contacto']['Type'] ?? ''));
        $insertData['origen_contacto'] = in_array('web', $originOpts, true) ? 'web' : (string)($originOpts[0] ?? '');
    }
    if (isset($schema['idioma_preferido'])) {
        $langOpts = mis_datos_enum_options((string)($schema['idioma_preferido']['Type'] ?? ''));
        $insertData['idioma_preferido'] = in_array('en', $langOpts, true) ? 'en' : (string)($langOpts[0] ?? '');
    }
    if ($hasClientUserIdCol && $clientUserId > 0) {
        $insertData['client_user_id'] = $clientUserId;
    }

    foreach ($schema as $field => $meta) {
        if (array_key_exists($field, $insertData)) {
            continue;
        }
        if (!mis_datos_required_no_default($schema, $field)) {
            continue;
        }

        $type = strtolower((string)($meta['Type'] ?? ''));
        if (strpos($type, 'enum(') === 0) {
            $opts = mis_datos_enum_options($type);
            $insertData[$field] = (string)($opts[0] ?? '');
            continue;
        }
        if (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
            $insertData[$field] = 0;
            continue;
        }
        if (strpos($type, 'date') !== false || strpos($type, 'time') !== false || strpos($type, 'year') !== false) {
            $insertData[$field] = date('Y-m-d H:i:s');
            continue;
        }
        $insertData[$field] = '';
    }

    if (empty($insertData['email']) || empty($insertData['nombre']) || !array_key_exists('apellido', $insertData)) {
        return ['ok' => false, 'message' => 'required_cliente_columns_missing'];
    }

    $columns = array_keys($insertData);
    $values = array_values($insertData);
    $types = '';
    foreach ($values as $v) {
        $types .= is_int($v) ? 'i' : 's';
    }
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sqlInsert = 'INSERT INTO clientes (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')';

    $stmtInsert = mysqli_prepare($conexion, $sqlInsert);
    if (!$stmtInsert) {
        return ['ok' => false, 'message' => 'insert_prepare_failed'];
    }
    if (!mis_datos_bind_params($stmtInsert, $types, $values) || !mysqli_stmt_execute($stmtInsert)) {
        $err = mysqli_stmt_error($stmtInsert);
        mysqli_stmt_close($stmtInsert);
        return ['ok' => false, 'message' => 'insert_execute_failed: ' . $err];
    }
    $newId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtInsert);

    $stmtGet = mysqli_prepare($conexion, 'SELECT * FROM clientes WHERE id = ? LIMIT 1');
    if (!$stmtGet) {
        return ['ok' => false, 'message' => 'fetch_after_insert_failed'];
    }
    mysqli_stmt_bind_param($stmtGet, 'i', $newId);
    if (!mysqli_stmt_execute($stmtGet)) {
        mysqli_stmt_close($stmtGet);
        return ['ok' => false, 'message' => 'fetch_after_insert_execute_failed'];
    }
    $resNew = mysqli_stmt_get_result($stmtGet);
    $newRow = $resNew ? mysqli_fetch_assoc($resNew) : null;
    mysqli_stmt_close($stmtGet);

    if (!$newRow) {
        return ['ok' => false, 'message' => 'fetch_after_insert_empty'];
    }

    return ['ok' => true, 'row' => $newRow, 'created' => true];
}

function mis_datos_public_payload($row, $editableFields)
{
    $out = [];
    $out['id'] = (int)($row['id'] ?? 0);
    $out['email'] = (string)($row['email'] ?? '');
    foreach ($editableFields as $field) {
        $out[$field] = isset($row[$field]) ? (string)$row[$field] : '';
    }
    return $out;
}

if (!isset($conexion) || !$conexion) {
    mis_datos_err('db_unavailable', 500);
}
if (!client_table_exists($conexion, 'clientes')) {
    mis_datos_err('clientes_table_not_found', 409);
}

$schema = mis_datos_schema($conexion);
if (empty($schema)) {
    mis_datos_err('clientes_schema_unavailable', 500);
}

$tipo = trim((string)($_POST['tipo'] ?? 'get_profile'));
$clientUserId = get_client_user_id();
$clientEmail = client_get_session_email();
$resolved = mis_datos_resolve_client_row($conexion, $schema, $clientUserId, $clientEmail);
if (empty($resolved['ok'])) {
    mis_datos_err((string)($resolved['message'] ?? 'client_resolve_failed'), 422);
}
$row = $resolved['row'];
$editableFields = mis_datos_build_editable_fields($schema);

$requiredEditable = [];
foreach ($editableFields as $field) {
    if (mis_datos_required_no_default($schema, $field)) {
        $requiredEditable[] = $field;
    }
}

if ($tipo === 'get_profile') {
    $fieldMeta = [];
    foreach ($editableFields as $field) {
        $meta = $schema[$field] ?? null;
        if (!$meta) {
            continue;
        }
        $fieldMeta[$field] = [
            'type' => (string)($meta['Type'] ?? ''),
            'nullable' => strtoupper((string)($meta['Null'] ?? 'YES')) === 'YES',
            'required' => in_array($field, $requiredEditable, true),
            'enum_options' => mis_datos_enum_options((string)($meta['Type'] ?? '')),
        ];
    }

    mis_datos_ok([
        'created_profile' => !empty($resolved['created']),
        'client_map' => [
            'by_client_user_id' => isset($schema['client_user_id']) && $clientUserId > 0,
            'by_email_normalized' => true,
            'client_user_id' => (int)$clientUserId,
            'session_email' => (string)$clientEmail,
            'clientes_id' => (int)($row['id'] ?? 0),
        ],
        'read_only_fields' => ['email'],
        'required_fields' => $requiredEditable,
        'editable_fields' => $editableFields,
        'field_meta' => $fieldMeta,
        'data' => mis_datos_public_payload($row, $editableFields),
    ]);
}

if ($tipo !== 'save_profile') {
    mis_datos_err('invalid_tipo', 422);
}

$updates = [];
$updateTypes = '';
$updateValues = [];
$errors = [];

foreach ($editableFields as $field) {
    if (!array_key_exists($field, $_POST)) {
        continue;
    }
    $meta = $schema[$field] ?? null;
    if (!$meta) {
        continue;
    }

    $raw = trim((string)$_POST[$field]);
    $isRequired = in_array($field, $requiredEditable, true);
    $isNullable = strtoupper((string)($meta['Null'] ?? 'YES')) === 'YES';
    $type = strtolower((string)($meta['Type'] ?? ''));

    if ($isRequired && $raw === '') {
        $errors[$field] = 'required';
        continue;
    }

    if ($raw === '') {
        if ($isNullable) {
            $updates[] = "`{$field}` = NULL";
        } else {
            $updates[] = "`{$field}` = ?";
            $updateTypes .= 's';
            $updateValues[] = '';
        }
        continue;
    }

    if ($field === 'fecha_nacimiento') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $errors[$field] = 'invalid_date';
            continue;
        }
    }

    if (strpos($type, 'enum(') === 0) {
        $allowed = mis_datos_enum_options($type);
        if (!empty($allowed) && !in_array($raw, $allowed, true)) {
            $errors[$field] = 'invalid_option';
            continue;
        }
    }

    $updates[] = "`{$field}` = ?";
    $updateTypes .= 's';
    $updateValues[] = $raw;
}

if (!empty($errors)) {
    mis_datos_err('validation_failed:' . json_encode($errors), 422);
}

if (empty($updates)) {
    mis_datos_ok([
        'message' => 'no_changes',
        'data' => mis_datos_public_payload($row, $editableFields),
    ]);
}

$clientId = (int)($row['id'] ?? 0);
if ($clientId <= 0) {
    mis_datos_err('invalid_client_id', 500);
}

$updateSql = 'UPDATE clientes SET ' . implode(', ', $updates) . ' WHERE id = ? LIMIT 1';
$updateTypes .= 'i';
$updateValues[] = $clientId;

$stmtUpdate = mysqli_prepare($conexion, $updateSql);
if (!$stmtUpdate) {
    mis_datos_err('update_prepare_failed: ' . mysqli_error($conexion), 500);
}
if (!mis_datos_bind_params($stmtUpdate, $updateTypes, $updateValues) || !mysqli_stmt_execute($stmtUpdate)) {
    $err = mysqli_stmt_error($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
    mis_datos_err('update_execute_failed: ' . $err, 500);
}
mysqli_stmt_close($stmtUpdate);

$stmtRefresh = mysqli_prepare($conexion, 'SELECT * FROM clientes WHERE id = ? LIMIT 1');
if (!$stmtRefresh) {
    mis_datos_err('refresh_prepare_failed', 500);
}
mysqli_stmt_bind_param($stmtRefresh, 'i', $clientId);
if (!mysqli_stmt_execute($stmtRefresh)) {
    mysqli_stmt_close($stmtRefresh);
    mis_datos_err('refresh_execute_failed', 500);
}
$resRefresh = mysqli_stmt_get_result($stmtRefresh);
$updatedRow = $resRefresh ? mysqli_fetch_assoc($resRefresh) : null;
mysqli_stmt_close($stmtRefresh);

if (!$updatedRow) {
    mis_datos_err('refresh_not_found', 500);
}

mis_datos_ok([
    'message' => 'saved',
    'data' => mis_datos_public_payload($updatedRow, $editableFields),
]);
