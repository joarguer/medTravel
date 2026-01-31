<?php
// admin/ajax/service_providers.php
// API para gestionar proveedores de servicios de MedTravel
include("../include/conexion.php");
session_start();

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch($action) {
    case 'list':
        listProviders();
        break;
    case 'get':
        getProvider();
        break;
    case 'create':
        createProvider();
        break;
    case 'update':
        updateProvider();
        break;
    case 'delete':
        deleteProvider();
        break;
    case 'toggle_status':
        toggleStatus();
        break;
    default:
        echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
}

function listProviders() {
    global $conexion;
    
    $activeOnly = isset($_GET['active_only']) ? intval($_GET['active_only']) : 0;
    $type = $_GET['type'] ?? '';
    
    $sql = "SELECT 
                id, provider_name, provider_type, country, city,
                contact_name, contact_email, contact_phone,
                rating, is_active, is_preferred,
                created_at
            FROM service_providers 
            WHERE 1=1";
    
    if($activeOnly) {
        $sql .= " AND is_active = 1";
    }
    
    if($type) {
        $type = mysqli_real_escape_string($conexion, $type);
        $sql .= " AND provider_type = '$type'";
    }
    
    $sql .= " ORDER BY is_preferred DESC, provider_name ASC";
    
    $result = mysqli_query($conexion, $sql);
    $providers = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $providers[] = $row;
    }
    
    echo json_encode(['ok' => true, 'data' => $providers]);
}

function getProvider() {
    global $conexion;
    
    $id = intval($_GET['id'] ?? 0);
    
    if($id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        return;
    }
    
    $sql = "SELECT * FROM service_providers WHERE id = $id";
    $result = mysqli_query($conexion, $sql);
    
    if($result && mysqli_num_rows($result) > 0) {
        $provider = mysqli_fetch_assoc($result);
        echo json_encode(['ok' => true, 'data' => $provider]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Proveedor no encontrado']);
    }
}

function createProvider() {
    global $conexion;
    
    $data = buildProviderData();
    
    if(!$data['provider_name']) {
        echo json_encode(['ok' => false, 'message' => 'El nombre del proveedor es obligatorio']);
        return;
    }
    
    $userId = $_SESSION['id_usuario'];
    
    $sql = "INSERT INTO service_providers (
                provider_name, provider_type, tax_id, country, city,
                contact_name, contact_position, contact_email, contact_phone, contact_mobile,
                website, payment_terms, bank_account, preferred_payment_method,
                rating, is_active, is_preferred, notes, contract_details, created_by
            ) VALUES (
                '{$data['provider_name']}', " . ($data['provider_type'] ? "'{$data['provider_type']}'" : "NULL") . ", 
                " . ($data['tax_id'] ? "'{$data['tax_id']}'" : "NULL") . ", '{$data['country']}', 
                " . ($data['city'] ? "'{$data['city']}'" : "NULL") . ",
                " . ($data['contact_name'] ? "'{$data['contact_name']}'" : "NULL") . ", 
                " . ($data['contact_position'] ? "'{$data['contact_position']}'" : "NULL") . ", 
                " . ($data['contact_email'] ? "'{$data['contact_email']}'" : "NULL") . ", 
                " . ($data['contact_phone'] ? "'{$data['contact_phone']}'" : "NULL") . ", 
                " . ($data['contact_mobile'] ? "'{$data['contact_mobile']}'" : "NULL") . ",
                " . ($data['website'] ? "'{$data['website']}'" : "NULL") . ", 
                " . ($data['payment_terms'] ? "'{$data['payment_terms']}'" : "NULL") . ", 
                " . ($data['bank_account'] ? "'{$data['bank_account']}'" : "NULL") . ", 
                '{$data['preferred_payment_method']}',
                {$data['rating']}, {$data['is_active']}, {$data['is_preferred']}, 
                " . ($data['notes'] ? "'{$data['notes']}'" : "NULL") . ", 
                " . ($data['contract_details'] ? "'{$data['contract_details']}'" : "NULL") . ", 
                $userId
            )";
    
    if(mysqli_query($conexion, $sql)) {
        $newId = mysqli_insert_id($conexion);
        echo json_encode([
            'ok' => true, 
            'message' => 'Proveedor creado exitosamente',
            'id' => $newId
        ]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Error al crear proveedor: ' . mysqli_error($conexion)]);
    }
}

function updateProvider() {
    global $conexion;
    
    $id = intval($_POST['id'] ?? 0);
    
    if($id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        return;
    }
    
    $data = buildProviderData();
    
    $sql = "UPDATE service_providers SET
                provider_name = '{$data['provider_name']}',
                provider_type = " . ($data['provider_type'] ? "'{$data['provider_type']}'" : "NULL") . ",
                tax_id = " . ($data['tax_id'] ? "'{$data['tax_id']}'" : "NULL") . ",
                country = '{$data['country']}',
                city = " . ($data['city'] ? "'{$data['city']}'" : "NULL") . ",
                contact_name = " . ($data['contact_name'] ? "'{$data['contact_name']}'" : "NULL") . ",
                contact_position = " . ($data['contact_position'] ? "'{$data['contact_position']}'" : "NULL") . ",
                contact_email = " . ($data['contact_email'] ? "'{$data['contact_email']}'" : "NULL") . ",
                contact_phone = " . ($data['contact_phone'] ? "'{$data['contact_phone']}'" : "NULL") . ",
                contact_mobile = " . ($data['contact_mobile'] ? "'{$data['contact_mobile']}'" : "NULL") . ",
                website = " . ($data['website'] ? "'{$data['website']}'" : "NULL") . ",
                payment_terms = " . ($data['payment_terms'] ? "'{$data['payment_terms']}'" : "NULL") . ",
                bank_account = " . ($data['bank_account'] ? "'{$data['bank_account']}'" : "NULL") . ",
                preferred_payment_method = '{$data['preferred_payment_method']}',
                rating = {$data['rating']},
                is_active = {$data['is_active']},
                is_preferred = {$data['is_preferred']},
                notes = " . ($data['notes'] ? "'{$data['notes']}'" : "NULL") . ",
                contract_details = " . ($data['contract_details'] ? "'{$data['contract_details']}'" : "NULL") . "
            WHERE id = $id";
    
    if(mysqli_query($conexion, $sql)) {
        echo json_encode(['ok' => true, 'message' => 'Proveedor actualizado exitosamente']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Error al actualizar proveedor: ' . mysqli_error($conexion)]);
    }
}

function deleteProvider() {
    global $conexion;
    
    $id = intval($_POST['id'] ?? 0);
    
    if($id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        return;
    }
    
    // Verificar si tiene servicios asociados
    $checkSql = "SELECT COUNT(*) as count FROM medtravel_services_catalog WHERE provider_id = $id";
    $checkResult = mysqli_query($conexion, $checkSql);
    $count = mysqli_fetch_assoc($checkResult)['count'];
    
    if($count > 0) {
        echo json_encode([
            'ok' => false, 
            'message' => "No se puede eliminar. El proveedor tiene $count servicio(s) asociado(s)"
        ]);
        return;
    }
    
    $sql = "DELETE FROM service_providers WHERE id = $id";
    
    if(mysqli_query($conexion, $sql)) {
        echo json_encode(['ok' => true, 'message' => 'Proveedor eliminado exitosamente']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Error al eliminar proveedor']);
    }
}

function toggleStatus() {
    global $conexion;
    
    $id = intval($_POST['id'] ?? 0);
    
    if($id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        return;
    }
    
    $sql = "UPDATE service_providers SET is_active = NOT is_active WHERE id = $id";
    
    if(mysqli_query($conexion, $sql)) {
        $statusSql = "SELECT is_active FROM service_providers WHERE id = $id";
        $result = mysqli_query($conexion, $statusSql);
        $isActive = mysqli_fetch_assoc($result)['is_active'];
        
        echo json_encode([
            'ok' => true, 
            'message' => 'Estado actualizado',
            'is_active' => $isActive
        ]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Error al actualizar estado']);
    }
}

function buildProviderData() {
    global $conexion;
    
    return [
        'provider_name' => mysqli_real_escape_string($conexion, $_POST['provider_name'] ?? ''),
        'provider_type' => mysqli_real_escape_string($conexion, $_POST['provider_type'] ?? ''),
        'tax_id' => mysqli_real_escape_string($conexion, $_POST['tax_id'] ?? ''),
        'country' => mysqli_real_escape_string($conexion, $_POST['country'] ?? 'Colombia'),
        'city' => mysqli_real_escape_string($conexion, $_POST['city'] ?? ''),
        'contact_name' => mysqli_real_escape_string($conexion, $_POST['contact_name'] ?? ''),
        'contact_position' => mysqli_real_escape_string($conexion, $_POST['contact_position'] ?? ''),
        'contact_email' => mysqli_real_escape_string($conexion, $_POST['contact_email'] ?? ''),
        'contact_phone' => mysqli_real_escape_string($conexion, $_POST['contact_phone'] ?? ''),
        'contact_mobile' => mysqli_real_escape_string($conexion, $_POST['contact_mobile'] ?? ''),
        'website' => mysqli_real_escape_string($conexion, $_POST['website'] ?? ''),
        'payment_terms' => mysqli_real_escape_string($conexion, $_POST['payment_terms'] ?? ''),
        'bank_account' => mysqli_real_escape_string($conexion, $_POST['bank_account'] ?? ''),
        'preferred_payment_method' => mysqli_real_escape_string($conexion, $_POST['preferred_payment_method'] ?? 'transfer'),
        'rating' => floatval($_POST['rating'] ?? 0),
        'is_active' => intval($_POST['is_active'] ?? 1),
        'is_preferred' => intval($_POST['is_preferred'] ?? 0),
        'notes' => mysqli_real_escape_string($conexion, $_POST['notes'] ?? ''),
        'contract_details' => mysqli_real_escape_string($conexion, $_POST['contract_details'] ?? '')
    ];
}
?>
