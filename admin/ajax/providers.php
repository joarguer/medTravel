<?php
session_start();
include('../include/include.php');
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');
$tipo = isset($_REQUEST['tipo']) ? $_REQUEST['tipo'] : '';

function slugify($text){
    $text = preg_replace('~[^\pL0-9]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) return 'n-a';
    return $text;
}

function table_has_column($conexion, $table, $column){
    static $cache = array();
    $key = $table.'.'.$column;
    if(array_key_exists($key, $cache)) return $cache[$key];
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function table_exists($conexion, $table){
    static $cache = array();
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$table];
}

function bind_dynamic_stmt_params($stmt, $values){
    if (empty($values)) {
        return;
    }
    $types = '';
    $bind = array();
    foreach ($values as $idx => $value) {
        $types .= is_int($value) ? 'i' : 's';
        $bindName = 'b' . $idx;
        $$bindName = $value;
        $bind[] = &$$bindName;
    }
    array_unshift($bind, $types);
    call_user_func_array(array($stmt, 'bind_param'), $bind);
}

function provider_users_schema_ready($conexion){
    return table_exists($conexion, 'provider_users')
        && table_has_column($conexion, 'provider_users', 'provider_id')
        && table_has_column($conexion, 'provider_users', 'user_id')
        && table_has_column($conexion, 'provider_users', 'role_in_provider');
}

function resolve_provider_owner_role_priority_sql(){
    return "CASE LOWER(COALESCE(NULLIF(TRIM(pu.role_in_provider), ''), 'owner'))
                WHEN 'owner' THEN 0
                WHEN 'primary' THEN 1
                WHEN 'principal' THEN 2
                WHEN 'admin' THEN 3
                WHEN 'administrator' THEN 4
                ELSE 10
            END";
}

function fetch_provider_owner_user_from_mapping($conexion, $provider_id){
    if ($provider_id <= 0 || !provider_users_schema_ready($conexion) || !table_exists($conexion, 'usuarios')) {
        return null;
    }

    $select = array(
        'u.id',
        table_has_column($conexion, 'usuarios', 'usuario') ? 'u.usuario' : "'' AS usuario",
        table_has_column($conexion, 'usuarios', 'nombre') ? 'u.nombre' : "'' AS nombre",
        table_has_column($conexion, 'usuarios', 'provider_id') ? 'u.provider_id' : 'NULL AS provider_id',
        table_has_column($conexion, 'usuarios', 'service_provider_id') ? 'u.service_provider_id' : 'NULL AS service_provider_id',
        table_has_column($conexion, 'usuarios', 'role_id') ? 'u.role_id' : 'NULL AS role_id',
        table_has_column($conexion, 'usuarios', 'rol') ? 'u.rol' : 'NULL AS rol',
        'pu.role_in_provider'
    );

    $sql = "SELECT " . implode(', ', $select) . "
              FROM provider_users pu
              INNER JOIN usuarios u ON u.id = pu.user_id
             WHERE pu.provider_id = ?
               AND u.id <> 1";
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $sql .= " AND COALESCE(u.service_provider_id, 0) = 0";
    }
    if (table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND COALESCE(u.is_deleted, 0) = 0";
    }
    $sql .= " ORDER BY " . resolve_provider_owner_role_priority_sql() . ", u.id ASC LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    $row['owner_source'] = 'provider_users';
    return $row;
}

function fetch_provider_owner_user_legacy($conexion, $provider_id){
    if ($provider_id <= 0 || !table_exists($conexion, 'usuarios') || !table_has_column($conexion, 'usuarios', 'provider_id')) {
        return null;
    }

    $select = array(
        'u.id',
        table_has_column($conexion, 'usuarios', 'usuario') ? 'u.usuario' : "'' AS usuario",
        table_has_column($conexion, 'usuarios', 'nombre') ? 'u.nombre' : "'' AS nombre",
        table_has_column($conexion, 'usuarios', 'provider_id') ? 'u.provider_id' : 'NULL AS provider_id',
        table_has_column($conexion, 'usuarios', 'service_provider_id') ? 'u.service_provider_id' : 'NULL AS service_provider_id',
        table_has_column($conexion, 'usuarios', 'role_id') ? 'u.role_id' : 'NULL AS role_id',
        table_has_column($conexion, 'usuarios', 'rol') ? 'u.rol' : 'NULL AS rol',
        table_has_column($conexion, 'usuarios', 'ppal') ? 'u.ppal' : '0 AS ppal'
    );

    $sql = "SELECT " . implode(', ', $select) . "
              FROM usuarios u
             WHERE u.provider_id = ?
               AND u.id <> 1";
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $sql .= " AND COALESCE(u.service_provider_id, 0) = 0";
    }
    if (table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND COALESCE(u.is_deleted, 0) = 0";
    }

    $rolePriority = '5';
    if (table_has_column($conexion, 'usuarios', 'role_id')) {
        $rolePriority = "CASE
            WHEN u.role_id = " . (int)ROLE_PROVIDER_ADMIN . " THEN 0
            WHEN u.role_id = " . (int)ROLE_PROVIDER . " THEN 1
            ELSE 5
        END";
    } elseif (table_has_column($conexion, 'usuarios', 'rol')) {
        $rolePriority = "CASE LOWER(TRIM(COALESCE(u.rol, '')))
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

    $ppalPriority = table_has_column($conexion, 'usuarios', 'ppal')
        ? 'CASE WHEN COALESCE(u.ppal, 0) = 1 THEN 0 ELSE 1 END'
        : '1';

    $sql .= " ORDER BY {$ppalPriority}, {$rolePriority}, u.id ASC LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    $row['owner_source'] = 'legacy_fallback';
    return $row;
}

function fetch_provider_owner_user($conexion, $provider_id, $allowLegacyFallback = true){
    $owner = fetch_provider_owner_user_from_mapping($conexion, $provider_id);
    if ($owner) {
        return $owner;
    }
    if (!$allowLegacyFallback) {
        return null;
    }
    return fetch_provider_owner_user_legacy($conexion, $provider_id);
}

function create_provider_owner_user($conexion, $provider_id, $username, $password_plain, $display_name){
    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
    $fields = array('usuario', 'password', 'nombre', 'rol', 'provider_id');
    $values = array($username, $password_hash, $display_name, (string)ROLE_PROVIDER_ADMIN, (int)$provider_id);

    if (table_has_column($conexion, 'usuarios', 'role_id')) {
        $fields[] = 'role_id';
        $values[] = (int)ROLE_PROVIDER_ADMIN;
    }
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $fields[] = 'service_provider_id';
        $values[] = null;
    }
    if (table_has_column($conexion, 'usuarios', 'ppal')) {
        $fields[] = 'ppal';
        $values[] = 0;
    }

    $sql = "INSERT INTO usuarios (" . implode(', ', $fields) . ") VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando INSERT usuario owner/admin');
    }
    bind_dynamic_stmt_params($stmt, $values);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error ejecutando INSERT usuario owner/admin: ' . $err);
    }
    $user_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $user_id;
}

function update_provider_owner_user($conexion, $user_id, $provider_id, $username, $display_name, $password_plain = ''){
    $fields = array(
        'usuario = ?',
        'nombre = ?'
    );
    $values = array($username, $display_name);

    if ($password_plain !== '') {
        $fields[] = 'password = ?';
        $values[] = password_hash($password_plain, PASSWORD_DEFAULT);
    }
    if (table_has_column($conexion, 'usuarios', 'rol')) {
        $fields[] = 'rol = ?';
        $values[] = (string)ROLE_PROVIDER_ADMIN;
    }
    if (table_has_column($conexion, 'usuarios', 'role_id')) {
        $fields[] = 'role_id = ?';
        $values[] = (int)ROLE_PROVIDER_ADMIN;
    }
    if (table_has_column($conexion, 'usuarios', 'provider_id')) {
        $fields[] = 'provider_id = ?';
        $values[] = (int)$provider_id;
    }
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $fields[] = 'service_provider_id = ?';
        $values[] = null;
    }
    if (table_has_column($conexion, 'usuarios', 'ppal')) {
        $fields[] = 'ppal = ?';
        $values[] = 0;
    }

    $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ? LIMIT 1";
    $values[] = (int)$user_id;
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando UPDATE usuario owner/admin');
    }
    bind_dynamic_stmt_params($stmt, $values);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error ejecutando UPDATE usuario owner/admin: ' . $err);
    }
    mysqli_stmt_close($stmt);
}

function ensure_provider_owner_mapping($conexion, $provider_id, $user_id){
    if ($provider_id <= 0 || $user_id <= 0) {
        throw new Exception('Provider owner mapping inválido');
    }
    if ((int)$user_id === 1) {
        throw new Exception('El superusuario global no puede mapearse como owner de provider');
    }
    if (!provider_users_schema_ready($conexion)) {
        throw new Exception('provider_users no está listo para ownership explícito');
    }

    $sql = "INSERT INTO provider_users (provider_id, user_id, role_in_provider)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE role_in_provider = VALUES(role_in_provider)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando INSERT provider_users');
    }
    $role = 'owner';
    mysqli_stmt_bind_param($stmt, 'iis', $provider_id, $user_id, $role);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error ejecutando INSERT provider_users: ' . $err);
    }
    mysqli_stmt_close($stmt);
}

try{
    if($tipo == 'list'){
        $kind_filter = isset($_REQUEST['kind']) ? $_REQUEST['kind'] : '';
        $kinds = array('medical','partner');
        if($kind_filter && !in_array($kind_filter, $kinds)) $kind_filter = '';
        // permiso: vista general si no hay filtro, o específica por tipo
        $can_view_any = user_can('providers.view');
        $can_view_med = user_can('providers.medical.view');
        $can_view_partner = user_can('providers.partner.view');
        if(!$can_view_any && !$can_view_med && !$can_view_partner){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        $rows = [];
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $sql = "SELECT 
                    p.id, p.type, p.kind, p.name, p.slug, p.city, p.is_verified, p.is_active, p.created_at,
                    COALESCE(pv.status,'pending') AS verification_status,
                    COALESCE(pv.verification_level,'basic') AS verification_level,
                    COALESCE(pv.trust_score,0) AS trust_score,
                    COALESCE(items.total_items,0) AS total_items,
                    COALESCE(items.checked_items,0) AS checked_items,
                    CASE WHEN COALESCE(items.total_items,0) > 0 
                        THEN ROUND((items.checked_items / items.total_items) * 100, 0)
                        ELSE 0 END AS completion_percent
                FROM providers p
                LEFT JOIN provider_verification pv ON pv.provider_id = p.id
                LEFT JOIN (
                    SELECT provider_id, COUNT(*) AS total_items,
                           SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) AS checked_items
                    FROM provider_verification_items
                    GROUP BY provider_id
                ) items ON items.provider_id = p.id
                WHERE 1=1";
        if($hasSoftDelete){ $sql .= " AND p.is_deleted = 0"; }
        if($kind_filter){ $sql .= " AND p.kind = '".mysqli_real_escape_string($conexion,$kind_filter)."'"; }
        $sql .= " ORDER BY p.created_at DESC";
        $res = mysqli_query($conexion, $sql);
        if(mysqli_errno($conexion)){ error_log('providers list error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db']); exit; }
        while($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        // filtrar según permisos específicos si no tiene permiso general
        if(!$can_view_any){
            $rows = array_filter($rows, function($r) use ($can_view_med, $can_view_partner){
                if($r['kind']==='partner') return $can_view_partner;
                return $can_view_med; // default medical
            });
            $rows = array_values($rows);
        }
        echo json_encode(['ok'=>true,'data'=>$rows]); exit;
    }

    if($tipo == 'get'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id <= 0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $sql = "SELECT * FROM providers WHERE id = ?";
        if($hasSoftDelete){ $sql .= " AND is_deleted = 0"; }
        $sql .= " LIMIT 1";
        if($st = mysqli_prepare($conexion, $sql)){
            mysqli_stmt_bind_param($st, 'i', $id);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($st);
            if(!$row){ echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
            // permiso según tipo
            $kind = isset($row['kind']) ? $row['kind'] : 'medical';
            if(!user_can('providers.view') && !user_can('providers.'.$kind.'.view')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
            
            // categories
            $cats = [];
            $s = mysqli_prepare($conexion, "SELECT category_id FROM provider_categories WHERE provider_id = ?");
            mysqli_stmt_bind_param($s, 'i', $id); mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s);
            while($cc = mysqli_fetch_assoc($r)) $cats[] = (int)$cc['category_id']; mysqli_stmt_close($s);
            
            // services
            $sv = [];
            $s2 = mysqli_prepare($conexion, "SELECT service_id FROM provider_catalog_services WHERE provider_id = ?");
            mysqli_stmt_bind_param($s2, 'i', $id); mysqli_stmt_execute($s2); $r2 = mysqli_stmt_get_result($s2);
            while($ss = mysqli_fetch_assoc($r2)) $sv[] = (int)$ss['service_id']; mysqli_stmt_close($s2);
            
            // owner/admin inicial canónico del provider
            $user_data = fetch_provider_owner_user($conexion, $id, true);

            echo json_encode(['ok'=>true,'data'=>['provider'=>$row,'category_ids'=>$cats,'service_ids'=>$sv,'user'=>$user_data]]); exit;
        } else { error_log('providers get prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'create'){
        $type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        $username = isset($_REQUEST['username']) ? trim($_REQUEST['username']) : '';
        $password = isset($_REQUEST['password']) ? trim($_REQUEST['password']) : '';
        $kind = isset($_REQUEST['kind']) ? trim($_REQUEST['kind']) : 'medical';
        if(!in_array($kind, ['medical','partner'])) $kind = 'medical';

        // Legacy freeze: no permitir nuevas altas de kind=partner.
        if($kind === 'partner'){
            http_response_code(422);
            echo json_encode([
                'ok'=>false,
                'error'=>'legacy_partner_frozen',
                'message'=>'Legacy complementario — usar service_providers'
            ]);
            exit;
        }

        // permisos por tipo
        if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        
        if($type === '' || ($type != 'medico' && $type != 'clinica') || $name === ''){ 
            echo json_encode(['ok'=>false,'error'=>'invalid_input','message'=>'Datos incompletos']); exit; 
        }
        if($username === '' || $password === ''){ 
            echo json_encode(['ok'=>false,'error'=>'invalid_credentials','message'=>'Usuario y contraseña son requeridos']); exit; 
        }
        
        // Verificar que el usuario no exista
        $check_user = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
        mysqli_stmt_bind_param($check_user, 's', $username);
        mysqli_stmt_execute($check_user);
        $result_check = mysqli_stmt_get_result($check_user);
        if(mysqli_num_rows($result_check) > 0){
            mysqli_stmt_close($check_user);
            echo json_encode(['ok'=>false,'error'=>'username_exists','message'=>'El usuario ya existe']); exit;
        }
        mysqli_stmt_close($check_user);
        
        $legal_name = isset($_REQUEST['legal_name']) ? trim($_REQUEST['legal_name']) : null;
        $description = isset($_REQUEST['description']) ? trim($_REQUEST['description']) : null;
        $city = isset($_REQUEST['city']) ? trim($_REQUEST['city']) : null;
        $address = isset($_REQUEST['address']) ? trim($_REQUEST['address']) : null;
        $phone = isset($_REQUEST['phone']) ? trim($_REQUEST['phone']) : null;
        $email = isset($_REQUEST['email']) ? trim($_REQUEST['email']) : null;
        $website = isset($_REQUEST['website']) ? trim($_REQUEST['website']) : null;
        $is_verified = isset($_REQUEST['is_verified']) ? (int)$_REQUEST['is_verified'] : 0;
        $is_active = isset($_REQUEST['is_active']) ? (int)$_REQUEST['is_active'] : 0;

        $base_slug = slugify($name);
        $slug = $base_slug; $i = 1;
        while(true){ $s = mysqli_prepare($conexion, "SELECT id FROM providers WHERE slug = ? LIMIT 1"); mysqli_stmt_bind_param($s, 's', $slug); mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s); $exists = ($r && mysqli_num_rows($r)>0); mysqli_stmt_close($s); if(!$exists) break; $slug = $base_slug . '-' . $i; $i++; }

        // Iniciar transacción
        mysqli_begin_transaction($conexion);
        
        try {
            // 1. Insertar proveedor
            $ins = mysqli_prepare($conexion, "INSERT INTO providers (type,kind,name,legal_name,slug,description,city,address,phone,email,website,is_verified,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if(!$ins){ throw new Exception('Error preparando INSERT provider'); }
            mysqli_stmt_bind_param($ins,'sssssssssssii', $type, $kind, $name, $legal_name, $slug, $description, $city, $address, $phone, $email, $website, $is_verified, $is_active);
            $exec = mysqli_stmt_execute($ins);
            if(!$exec){ throw new Exception('Error ejecutando INSERT provider: '.mysqli_stmt_error($ins)); }
            $provider_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($ins);
            
            // 1b. Crear registro de verificación base si no existe
            $ver_status = $is_verified ? 'verified' : 'pending';
            $vs = mysqli_prepare($conexion, "INSERT INTO provider_verification (provider_id, status, verification_level, trust_score) VALUES (?,?, 'basic', 0)");
            if($vs){ mysqli_stmt_bind_param($vs, 'is', $provider_id, $ver_status); mysqli_stmt_execute($vs); mysqli_stmt_close($vs); }
            
            // 2. Crear usuario owner/admin inicial
            $owner_user_id = create_provider_owner_user($conexion, $provider_id, $username, $password, $name);

            // 3. Persistir ownership explícito del provider
            ensure_provider_owner_mapping($conexion, $provider_id, $owner_user_id);
            
            // 4. Relaciones con categorías y servicios
            $category_ids = isset($_REQUEST['category_ids']) && is_array($_REQUEST['category_ids']) ? $_REQUEST['category_ids'] : [];
            $service_ids = isset($_REQUEST['service_ids']) && is_array($_REQUEST['service_ids']) ? $_REQUEST['service_ids'] : [];
            
            if(!empty($category_ids)){
                $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_categories (provider_id, category_id) VALUES (?,?)");
                foreach($category_ids as $cid){ $cid = (int)$cid; mysqli_stmt_bind_param($stmt,'ii',$provider_id,$cid); mysqli_stmt_execute($stmt); }
                mysqli_stmt_close($stmt);
            }
            if(!empty($service_ids)){
                $stmt2 = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_catalog_services (provider_id, service_id) VALUES (?,?)");
                foreach($service_ids as $sid){ $sid = (int)$sid; mysqli_stmt_bind_param($stmt2,'ii',$provider_id,$sid); mysqli_stmt_execute($stmt2); }
                mysqli_stmt_close($stmt2);
            }
            
            // Commit
            mysqli_commit($conexion);
            echo json_encode(['ok'=>true,'id'=>$provider_id,'message'=>'Proveedor y owner/admin inicial creados exitosamente']); exit;
            
        } catch(Exception $e) {
            mysqli_rollback($conexion);
            error_log('providers create error: '.$e->getMessage());
            echo json_encode(['ok'=>false,'error'=>'db_transaction','message'=>$e->getMessage()]); exit;
        }
    }

    if($tipo == 'update'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id','message'=>'ID inválido']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        
        $username = isset($_REQUEST['username']) ? trim($_REQUEST['username']) : '';
        $password = isset($_REQUEST['password']) ? trim($_REQUEST['password']) : '';
        // obtener kind actual si no viene en request
        $kind = isset($_REQUEST['kind']) ? trim($_REQUEST['kind']) : '';
        $kind_db = 'medical';
        $providerFound = false;
        $kindSql = "SELECT kind FROM providers WHERE id = ?";
        if($hasSoftDelete){ $kindSql .= " AND is_deleted = 0"; }
        $kindSql .= " LIMIT 1";
        $kq = mysqli_prepare($conexion, $kindSql);
        mysqli_stmt_bind_param($kq,'i',$id);
        mysqli_stmt_execute($kq);
        $kr = mysqli_stmt_get_result($kq);
        if($kr && $rowk = mysqli_fetch_assoc($kr)){ $kind_db = $rowk['kind'] ?: 'medical'; $providerFound = true; }
        mysqli_stmt_close($kq);
        if($hasSoftDelete && !$providerFound){
            echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); exit;
        }
        if($kind === '' || !in_array($kind, ['medical','partner'])) $kind = $kind_db;

        // Legacy freeze: no permitir convertir de medical -> partner.
        if($kind === 'partner' && $kind_db !== 'partner'){
            http_response_code(422);
            echo json_encode([
                'ok'=>false,
                'error'=>'legacy_partner_frozen',
                'message'=>'Legacy complementario — usar service_providers'
            ]);
            exit;
        }

        // permisos por tipo
        if($kind === 'partner'){
            if(!user_can('providers.partner.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        } else {
            if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        }
        
        if($username === ''){ 
            echo json_encode(['ok'=>false,'error'=>'invalid_username','message'=>'Usuario es requerido']); exit; 
        }
        
        $owner_user = fetch_provider_owner_user($conexion, $id, true);
        $owner_user_id = $owner_user && !empty($owner_user['id']) ? (int)$owner_user['id'] : 0;

        // Verificar unicidad del login del owner/admin inicial
        $check_user = mysqli_prepare($conexion, "SELECT u.id FROM usuarios u WHERE u.usuario = ? LIMIT 1");
        mysqli_stmt_bind_param($check_user, 's', $username);
        mysqli_stmt_execute($check_user);
        $result_check = mysqli_stmt_get_result($check_user);
        if($row_check = mysqli_fetch_assoc($result_check)){
            if((int)$row_check['id'] !== $owner_user_id){
                mysqli_stmt_close($check_user);
                echo json_encode(['ok'=>false,'error'=>'username_exists','message'=>'El usuario ya está en uso']); exit;
            }
        }
        mysqli_stmt_close($check_user);
        
        $allowed = ['type','kind','name','legal_name','description','city','address','phone','email','website','is_verified','is_active'];
        $fields=[]; $values=[];
        foreach($allowed as $k){ 
            if(isset($_REQUEST[$k])){ 
                if(in_array($k,['is_verified','is_active'])) $values[] = (int)$_REQUEST[$k]; 
                else $values[] = trim($_REQUEST[$k]); 
                $fields[] = "$k = ?"; 
            } 
        }
        if(empty($fields)){ echo json_encode(['ok'=>false,'error'=>'nothing_to_update','message'=>'No hay datos para actualizar']); exit; }
        
        $regenerate_slug = isset($_REQUEST['name']);
        if($regenerate_slug){ 
            $base_slug = slugify(trim($_REQUEST['name'])); 
            $slug = $base_slug; $i=1; 
            while(true){ 
                $s = mysqli_prepare($conexion, "SELECT id FROM providers WHERE slug = ? AND id != ? LIMIT 1"); 
                mysqli_stmt_bind_param($s,'si',$slug,$id); 
                mysqli_stmt_execute($s); 
                $r = mysqli_stmt_get_result($s); 
                $exists = ($r && mysqli_num_rows($r)>0); 
                mysqli_stmt_close($s); 
                if(!$exists) break; 
                $slug = $base_slug . '-' . $i; 
                $i++; 
            } 
            array_unshift($fields,'slug = ?'); 
            array_unshift($values,$slug); 
        }
        
        // Iniciar transacción
        mysqli_begin_transaction($conexion);
        
        try {
            // 1. Actualizar proveedor
            $sql = 'UPDATE providers SET '.implode(', ', $fields).' WHERE id = ?';
            if($hasSoftDelete){ $sql .= ' AND is_deleted = 0'; }
            $sql .= ' LIMIT 1';
            $values[] = $id; 
            $types=''; 
            foreach($values as $v){ $types .= is_int($v)?'i':'s'; }
            
            if($stmt = mysqli_prepare($conexion, $sql)){
                $bind_names = array(); 
                $bind_names[] = $types; 
                for($i=0;$i<count($values);$i++){ 
                    $bind_name = 'b'.$i; 
                    $$bind_name = $values[$i]; 
                    $bind_names[] = &$$bind_name; 
                }
	                call_user_func_array(array($stmt,'bind_param'), $bind_names);
	                $exec = mysqli_stmt_execute($stmt);
	                if(!$exec){ throw new Exception('Error actualizando provider: '.mysqli_stmt_error($stmt)); }
	                mysqli_stmt_close($stmt);
	            } else { 
	                throw new Exception('Error preparando UPDATE provider: '.mysqli_error($conexion)); 
            }
            
            // 2. Actualizar o crear owner/admin inicial
            $provider_name = isset($_REQUEST['name'])
                ? trim($_REQUEST['name'])
                : (($owner_user && isset($owner_user['nombre'])) ? trim((string)$owner_user['nombre']) : '');
            
            if($owner_user_id > 0){
                update_provider_owner_user($conexion, $owner_user_id, $id, $username, $provider_name, $password);
            } else {
                if($password === ''){
                    throw new Exception('Se requiere contraseña para crear el owner/admin inicial');
                }
                $owner_user_id = create_provider_owner_user($conexion, $id, $username, $password, $provider_name);
            }

            ensure_provider_owner_mapping($conexion, $id, $owner_user_id);
            
            // 3. Actualizar relaciones
            $category_ids = isset($_REQUEST['category_ids']) && is_array($_REQUEST['category_ids']) ? $_REQUEST['category_ids'] : [];
            $service_ids = isset($_REQUEST['service_ids']) && is_array($_REQUEST['service_ids']) ? $_REQUEST['service_ids'] : [];
            
            // Eliminar relaciones existentes
            $d1 = mysqli_prepare($conexion, "DELETE FROM provider_categories WHERE provider_id = ?"); 
            mysqli_stmt_bind_param($d1,'i',$id); 
            mysqli_stmt_execute($d1); 
            mysqli_stmt_close($d1);
            
            $d2 = mysqli_prepare($conexion, "DELETE FROM provider_catalog_services WHERE provider_id = ?"); 
            mysqli_stmt_bind_param($d2,'i',$id); 
            mysqli_stmt_execute($d2); 
            mysqli_stmt_close($d2);
            
            // Reinsertar
            if(!empty($category_ids)){
                $ins = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_categories (provider_id, category_id) VALUES (?,?)");
                foreach($category_ids as $cid){ 
                    $cid = (int)$cid; 
                    mysqli_stmt_bind_param($ins,'ii',$id,$cid); 
                    mysqli_stmt_execute($ins); 
                }
                mysqli_stmt_close($ins);
            }
            if(!empty($service_ids)){
                $ins2 = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_catalog_services (provider_id, service_id) VALUES (?,?)");
                foreach($service_ids as $sid){ 
                    $sid = (int)$sid; 
                    mysqli_stmt_bind_param($ins2,'ii',$id,$sid); 
                    mysqli_stmt_execute($ins2); 
                }
                mysqli_stmt_close($ins2);
            }
            
            // Commit
            mysqli_commit($conexion);
            echo json_encode(['ok'=>true,'message'=>'Proveedor y owner/admin inicial actualizados exitosamente']); exit;
            
        } catch(Exception $e) {
            mysqli_rollback($conexion);
            error_log('providers update error: '.$e->getMessage());
            echo json_encode(['ok'=>false,'error'=>'db_transaction','message'=>$e->getMessage()]); exit;
        }
    }

    if($tipo == 'toggle'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0; $val = isset($_REQUEST['val']) ? (int)$_REQUEST['val'] : 0; if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        if(!in_array($val, [0,1], true)){ echo json_encode(['ok'=>false,'error'=>'invalid_val']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $kind = 'medical';
        $kindSql = "SELECT kind FROM providers WHERE id = ?";
        if($hasSoftDelete){ $kindSql .= " AND is_deleted = 0"; }
        $kindSql .= " LIMIT 1";
        $kq = mysqli_prepare($conexion, $kindSql);
        mysqli_stmt_bind_param($kq,'i',$id);
        mysqli_stmt_execute($kq);
        $kr = mysqli_stmt_get_result($kq);
        if($kr && $rowk = mysqli_fetch_assoc($kr)) $kind = $rowk['kind'] ?: 'medical';
        mysqli_stmt_close($kq);
        if($hasSoftDelete && (!$kr || mysqli_num_rows($kr) === 0)){ echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); exit; }
        if($kind === 'partner'){
            if(!user_can('providers.partner.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        } else {
            if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        }
        $toggleSql = "UPDATE providers SET is_active = ? WHERE id = ?";
        if($hasSoftDelete){ $toggleSql .= " AND is_deleted = 0"; }
        $toggleSql .= " LIMIT 1";
        $st = mysqli_prepare($conexion, $toggleSql); mysqli_stmt_bind_param($st,'ii',$val,$id); $exec = mysqli_stmt_execute($st); if(!$exec){ error_log('providers toggle error: '.mysqli_stmt_error($st)); echo json_encode(['ok'=>false,'error'=>'db_toggle']); mysqli_stmt_close($st); exit; } mysqli_stmt_close($st); echo json_encode(['ok'=>true]); exit;
    }

    if($tipo == 'soft_delete'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $hasDeletedAt = table_has_column($conexion, 'providers', 'deleted_at');
        $hasDeletedBy = table_has_column($conexion, 'providers', 'deleted_by');
        if(!$hasSoftDelete || !$hasDeletedAt || !$hasDeletedBy){ echo json_encode(['ok'=>false,'error'=>'soft_delete_columns_missing']); exit; }

        $kind = 'medical';
        $kindSql = "SELECT kind FROM providers WHERE id = ? AND is_deleted = 0 LIMIT 1";
        $kq = mysqli_prepare($conexion, $kindSql);
        mysqli_stmt_bind_param($kq,'i',$id);
        mysqli_stmt_execute($kq);
        $kr = mysqli_stmt_get_result($kq);
        if($kr && $rowk = mysqli_fetch_assoc($kr)) $kind = $rowk['kind'] ?: 'medical';
        mysqli_stmt_close($kq);
        if(!$kr || mysqli_num_rows($kr) === 0){ echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); exit; }

        if($kind === 'partner'){
            if(!user_can('providers.partner.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        } else {
            if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        }

        $sessionUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
        $sql = "UPDATE providers SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, is_active = 0 WHERE id = ? AND is_deleted = 0 LIMIT 1";
        $st = mysqli_prepare($conexion, $sql);
        if(!$st){ echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
        mysqli_stmt_bind_param($st, 'ii', $sessionUserId, $id);
        $exec = mysqli_stmt_execute($st);
        if(!$exec){ error_log('providers soft delete error: '.mysqli_stmt_error($st)); echo json_encode(['ok'=>false,'error'=>'db_soft_delete']); mysqli_stmt_close($st); exit; }
        if(mysqli_stmt_affected_rows($st) < 1){ echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); mysqli_stmt_close($st); exit; }
        mysqli_stmt_close($st);
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown_tipo']); exit;
} catch(Exception $e){ error_log('providers exception: '.$e->getMessage()); echo json_encode(['ok'=>false,'error'=>'exception']); exit; }
