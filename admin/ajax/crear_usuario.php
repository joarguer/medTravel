<?php
session_start();
include("../include/include.php");
require_once __DIR__ . '/../include/password_utils.php';
$resultados = array();
$tipo = isset($_REQUEST["tipo"]) ? $_REQUEST["tipo"] : '';
$empresa = isset($_REQUEST['empresa']) ? $_REQUEST['empresa'] : '';

function usuarios_has_column($conexion, $column) {
    $column = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE '{$column}'");
    return $q && mysqli_num_rows($q) > 0;
}

function table_has_column($conexion, $table, $column) {
    $table = mysqli_real_escape_string($conexion, $table);
    $column = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    return $q && mysqli_num_rows($q) > 0;
}

function fetch_provider_name($conexion, $provider_id) {
    $hasDeleted = table_has_column($conexion, 'providers', 'is_deleted');
    $sql = "SELECT name FROM providers WHERE id = ?";
    if ($hasDeleted) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return '';
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $name = '';
    if ($res && $row = mysqli_fetch_assoc($res)) $name = $row['name'];
    mysqli_stmt_close($stmt);
    return $name;
}

function validate_medical_provider($conexion, $provider_id) {
    $hasDeleted = table_has_column($conexion, 'providers', 'is_deleted');
    $sql = "SELECT id, name FROM providers WHERE id = ? AND is_active = 1";
    if ($hasDeleted) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_service_provider_name($conexion, $service_provider_id) {
    $hasDeleted = table_has_column($conexion, 'service_providers', 'is_deleted');
    $sql = "SELECT provider_name FROM service_providers WHERE id = ? AND is_active = 1";
    if ($hasDeleted) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $service_provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $name = null;
    if ($res && $row = mysqli_fetch_assoc($res)) $name = $row['provider_name'];
    mysqli_stmt_close($stmt);
    return $name;
}

function validate_active_service_provider($conexion, $service_provider_id) {
    $hasDeleted = table_has_column($conexion, 'service_providers', 'is_deleted');
    $sql = "SELECT id, provider_name FROM service_providers WHERE id = ? AND is_active = 1";
    if ($hasDeleted) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $service_provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    return $row;
}

if($tipo == 'crear_usuario'){
    if (!is_role_admin_session() && !user_can('users.create')) {
        http_response_code(403);
        $resultados['error'] = 'forbidden';
        echo json_encode($resultados);
        return;
    }
    // validar unicidad de usuario (email usado como usuario)
    $email = isset($_REQUEST['email']) ? trim($_REQUEST['email']) : '';
    $rasocial = isset($_REQUEST['rasocial']) ? trim($_REQUEST['rasocial']) : '';
    $nombre = isset($_REQUEST['nombre']) ? trim($_REQUEST['nombre']) : '';
    $apellido = isset($_REQUEST['apellido']) ? trim($_REQUEST['apellido']) : '';
    $fullnombre = trim($nombre . ' ' . $apellido);
    $celular = isset($_REQUEST['celular']) ? trim($_REQUEST['celular']) : '';
    $rol = isset($_REQUEST['role']) ? trim($_REQUEST['role']) : (isset($_REQUEST['rol']) ? trim($_REQUEST['rol']) : '');
    $ppal = isset($_REQUEST['ppal']) ? (int)$_REQUEST['ppal'] : 0;
    $provider_id = isset($_REQUEST['provider_id']) && $_REQUEST['provider_id'] !== '' ? (int)$_REQUEST['provider_id'] : null;
    $service_provider_id = isset($_REQUEST['service_provider_id']) && $_REQUEST['service_provider_id'] !== '' ? (int)$_REQUEST['service_provider_id'] : null;
    $provider_session_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : null;
    $service_provider_session_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : null;

    $role_id_val = is_numeric($rol) ? intval($rol) : normalize_role_value($rol);
    $is_global_admin_role = in_array($role_id_val, [ROLE_ADMIN, ROLE_ADMINISTRATIVE], true);
    $is_complementary_role = ($role_id_val === ROLE_COMPLEMENTARY_ADMIN);
    $is_medical_provider_role = in_array($role_id_val, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true);

    // Contexto no-admin: fijar ownership por sesión
    if (!is_role_admin_session()) {
        if ($service_provider_session_id) {
            $service_provider_id = $service_provider_session_id;
            $provider_id = null;
            $is_complementary_role = true;
            if ($role_id_val !== ROLE_COMPLEMENTARY_ADMIN) {
                $role_id_val = ROLE_COMPLEMENTARY_ADMIN;
                $rol = (string)ROLE_COMPLEMENTARY_ADMIN;
            }
            $is_medical_provider_role = false;
            $is_global_admin_role = false;
        } elseif ($provider_session_id) {
            $provider_id = $provider_session_id;
            $service_provider_id = null;
            if (!in_array($role_id_val, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true)) {
                $role_id_val = ROLE_PROVIDER;
                $rol = (string)ROLE_PROVIDER;
            }
            $is_medical_provider_role = true;
            $is_complementary_role = false;
            $is_global_admin_role = false;
        }
    }

    // Validar ownership según rol
    if ($is_complementary_role) {
        if ($service_provider_id === null || $service_provider_id <= 0) {
            $resultados['error'] = 'service_provider_required';
            $resultados['status'] = null;
            echo json_encode($resultados);
            return;
        }
        $active_service_provider = validate_active_service_provider($conexion, (int)$service_provider_id);
        if (!$active_service_provider) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'status' => null,
                'error' => 'Invalid or inactive complementary provider'
            ]);
            return;
        }
        $provider_id = null;
        if ($rasocial === '') {
            $rasocial = $active_service_provider['provider_name'];
        }
        if ($rasocial === null || trim((string)$rasocial) === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'status' => null,
                'error' => 'Invalid or inactive complementary provider'
            ]);
            return;
        }
    } elseif ($is_medical_provider_role) {
        if ($provider_id === null || $provider_id <= 0) {
            $resultados['error'] = 'provider_required';
            $resultados['status'] = null;
            echo json_encode($resultados);
            return;
        }
        $active_provider = validate_medical_provider($conexion, (int)$provider_id);
        if (!$active_provider) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'status' => null,
                'error' => 'Invalid or inactive medical provider'
            ]);
            return;
        }
        $service_provider_id = null;
        if ($rasocial === '') {
            $rasocial = $active_provider['name'];
        }
    } elseif ($is_global_admin_role) {
        $provider_id = null;
        $service_provider_id = null;
    } else {
        $provider_id = null;
        $service_provider_id = null;
    }

    // comprobar usuario único
    $sql_check = "SELECT id FROM usuarios WHERE usuario = ? LIMIT 1";
    if ($stmtc = mysqli_prepare($conexion, $sql_check)) {
        mysqli_stmt_bind_param($stmtc, 's', $email);
        mysqli_stmt_execute($stmtc);
        $resc = mysqli_stmt_get_result($stmtc);
        if ($resc && mysqli_num_rows($resc) > 0) {
            $resultados['error'] = 'usuario_existente';
            $resultados['status'] = null;
            mysqli_stmt_close($stmtc);
            echo json_encode($resultados);
            return;
        }
        mysqli_stmt_close($stmtc);
    }

    $has_role_id = usuarios_has_column($conexion, 'role_id');
    $has_service_provider_id = usuarios_has_column($conexion, 'service_provider_id');
    if ($is_complementary_role && !$has_service_provider_id) {
        $resultados['error'] = 'service_provider_column_missing';
        $resultados['status'] = null;
        echo json_encode($resultados);
        return;
    }

    $avatar_default = 'img/perfil/default.png';
    $cambio_password = 1;
    $activo = 1;
    $initial_plain_password = $email !== '' ? $email : substr(md5(uniqid(rand(), true)), 0, 12);
    $token = generate_user_token();
    $password = hash_password($initial_plain_password, $token);
    $usuario_val = $email;
    $usrlogin_val = $email;
    $cargo_val = isset($_REQUEST['cargo']) ? trim($_REQUEST['cargo']) : '';
    $ciudad_val = isset($_REQUEST['ciudad']) ? trim($_REQUEST['ciudad']) : '';
    $telefono_val = isset($_REQUEST['telefono']) ? trim($_REQUEST['telefono']) : '';

    if ($has_role_id && $has_service_provider_id) {
        $sql_ins = "INSERT INTO usuarios (usuario, password, avatar, nombre, activo, token, empresa, ppal, usrlogin, rol, role_id, cargo, email, ciudad, telefono, celular, cambio_password, provider_id, service_provider_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    } elseif ($has_role_id) {
        $sql_ins = "INSERT INTO usuarios (usuario, password, avatar, nombre, activo, token, empresa, ppal, usrlogin, rol, role_id, cargo, email, ciudad, telefono, celular, cambio_password, provider_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    } elseif ($has_service_provider_id) {
        $sql_ins = "INSERT INTO usuarios (usuario, password, avatar, nombre, activo, token, empresa, ppal, usrlogin, rol, cargo, email, ciudad, telefono, celular, cambio_password, provider_id, service_provider_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    } else {
        $sql_ins = "INSERT INTO usuarios (usuario, password, avatar, nombre, activo, token, empresa, ppal, usrlogin, rol, cargo, email, ciudad, telefono, celular, cambio_password, provider_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    }

    $stmin = mysqli_prepare($conexion, $sql_ins);
    if (!$stmin) {
        $resultados['status'] = null;
        $resultados['error'] = 'db_prepare_error';
        echo json_encode($resultados);
        return;
    }

    if ($has_role_id && $has_service_provider_id) {
        $types = 'ssssissississsssiii';
        mysqli_stmt_bind_param($stmin, $types, $usuario_val, $password, $avatar_default, $fullnombre, $activo, $token, $rasocial, $ppal, $usrlogin_val, $rol, $role_id_val, $cargo_val, $email, $ciudad_val, $telefono_val, $celular, $cambio_password, $provider_id, $service_provider_id);
    } elseif ($has_role_id) {
        $types = 'ssssissississsssii';
        mysqli_stmt_bind_param($stmin, $types, $usuario_val, $password, $avatar_default, $fullnombre, $activo, $token, $rasocial, $ppal, $usrlogin_val, $rol, $role_id_val, $cargo_val, $email, $ciudad_val, $telefono_val, $celular, $cambio_password, $provider_id);
    } elseif ($has_service_provider_id) {
        $types = 'ssssississsssssiii';
        mysqli_stmt_bind_param($stmin, $types, $usuario_val, $password, $avatar_default, $fullnombre, $activo, $token, $rasocial, $ppal, $usrlogin_val, $rol, $cargo_val, $email, $ciudad_val, $telefono_val, $celular, $cambio_password, $provider_id, $service_provider_id);
    } else {
        $types = 'ssssississsssssii';
        mysqli_stmt_bind_param($stmin, $types, $usuario_val, $password, $avatar_default, $fullnombre, $activo, $token, $rasocial, $ppal, $usrlogin_val, $rol, $cargo_val, $email, $ciudad_val, $telefono_val, $celular, $cambio_password, $provider_id);
    }

    if (!mysqli_stmt_execute($stmin)) {
        $resultados['status'] = null;
        $resultados['error'] = mysqli_stmt_error($stmin);
        mysqli_stmt_close($stmin);
        echo json_encode($resultados);
        return;
    }
    $resultados['status'] = true;
    $resultados['id'] = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmin);
}

if($tipo == 'rol'){
    $id_usuario = $_REQUEST['id_usuario'];
    $rol        = $_REQUEST['rol'];
    // update both rol and role_id if available
    $role_id_val = is_numeric($rol) ? intval($rol) : null;
    $colcheck = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE 'role_id'");
    if ($colcheck && mysqli_num_rows($colcheck) > 0 && $role_id_val !== null) {
        $busca = mysqli_query($conexion, "UPDATE usuarios SET rol = '".mysqli_real_escape_string($conexion,$rol)."', role_id = {$role_id_val} WHERE id = '".intval($id_usuario)."'");
    } else {
        $busca = mysqli_query($conexion, "UPDATE usuarios SET rol = '".mysqli_real_escape_string($conexion,$rol)."' WHERE id = '".intval($id_usuario)."'");
    }
    if (mysqli_error($conexion)) {
        $resultados["status"]   = null;
        $resultados['error']    = mysqli_error($conexion);
    } else {
        $resultados["status"]   = true;
    }
}

if($tipo == 'crear_avatar'){
    $id = $_REQUEST['id_usuario'];
    $ruta = "../img/perfil/".$id."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT avatar FROM usuarios WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            //buscamos la ruta del archivo existente para eliminar
            $archivo_ = mysqli_fetch_array($busco);
            $archivo = '../'.$archivo_['imagen'];
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/perfil/".$id."_".$_FILES['file']['name']."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE usuarios SET avatar = '$ruta' WHERE id='".$id."'");
            if($_SESSION['id_usuario'] == $id){
                $_SESSION['foto_perfil']    = $ruta;
            }
            $resultados["status"]       = true;
            $resultados["ruta"]         = $ruta;
        } else {
            $resultados["status"]       = null;
        }
    } else {
        $resultados["status"]       = false;
    }
}

if($_REQUEST['tipo'] == 'crear_password'){
    $id = $_REQUEST['id_usuario'];
    $id = intval($id);
    $pass1 = isset($_REQUEST["pass1"]) ? (string)$_REQUEST["pass1"] : '';
    if ($id <= 0 || $pass1 === '') {
        $resultados["status"] = null;
        $resultados['error'] = 'invalid_input';
        echo json_encode($resultados);
        return;
    }

    $current_user = array();
    $has_users_soft_delete = usuarios_has_column($conexion, 'is_deleted');
    $sql_user = "SELECT id, token, password FROM usuarios WHERE id = ?";
    if ($has_users_soft_delete) {
        $sql_user .= " AND is_deleted = 0";
    }
    $sql_user .= " LIMIT 1";
    if ($stmtUser = mysqli_prepare($conexion, $sql_user)) {
        mysqli_stmt_bind_param($stmtUser, 'i', $id);
        mysqli_stmt_execute($stmtUser);
        $resUser = mysqli_stmt_get_result($stmtUser);
        if ($resUser && ($rowUser = mysqli_fetch_assoc($resUser))) {
            $current_user = $rowUser;
        }
        mysqli_stmt_close($stmtUser);
    }

    if (empty($current_user)) {
        $resultados["status"] = null;
        $resultados['error'] = 'user_not_found';
        echo json_encode($resultados);
        return;
    }

    $new_token = ensure_password_token($current_user);
    $new_hash = hash_password($pass1, $new_token);
    $has_cambio_password = usuarios_has_column($conexion, 'cambio_password');

    if ($has_cambio_password) {
        $stmtUpdate = mysqli_prepare($conexion, "UPDATE usuarios SET password = ?, token = ?, cambio_password = 1 WHERE id = ? LIMIT 1");
    } else {
        $stmtUpdate = mysqli_prepare($conexion, "UPDATE usuarios SET password = ?, token = ? WHERE id = ? LIMIT 1");
    }

    if (!$stmtUpdate) {
        $resultados["status"] = null;
        $resultados['error'] = 'db_prepare_error';
        echo json_encode($resultados);
        return;
    }

    mysqli_stmt_bind_param($stmtUpdate, 'ssi', $new_hash, $new_token, $id);
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $resultados["status"]   = null;
        $resultados['error']    = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
    } else {
        $resultados["status"]   = true;
        mysqli_stmt_close($stmtUpdate);
    }
}

if($_REQUEST["tipo"] == 'eliminar'){
    $busca = mysqli_query($conexion, "DELETE FROM certificado WHERE id_usuario = '".$_REQUEST["id"]."'");
    if (mysqli_error($conexion)) {
        $resultados["status"]   = null;
    } else {
        $resultados["status"]   = true;
    }
}

if($_REQUEST["tipo"] == 'listar_empresas'){
    $busca = mysqli_query($conexion, "SELECT * FROM empresas WHERE estado = 1");
    if (mysqli_error($conexion)) {
        $resultados["status"]   = null;
    } else {
        $resultados["status"]   = true;
        while($row = mysqli_fetch_array($busca)){
            $resultados["empresas"][] = $row;
        }
    }
}

echo json_encode($resultados);
?> 
