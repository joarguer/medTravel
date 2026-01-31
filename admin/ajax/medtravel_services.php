<?php
// admin/ajax/medtravel_services.php - API Backend para gestión de servicios MedTravel
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/medtravel_services.log');

header('Content-Type: application/json; charset=utf-8');
session_start();

// Validación de sesión
if(!isset($_SESSION['id_usuario'])){
    echo json_encode(['ok' => false, 'message' => 'Invalid session']);
    exit;
}

require_once('../include/conexion.php');

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$id_usuario = $_SESSION['id_usuario'];

try {
    switch($action) {
        case 'list':
            listServices($conexion);
            break;
        
        case 'get':
            getService($conexion);
            break;
        
        case 'create':
            createService($conexion, $id_usuario);
            break;
        
        case 'update':
            updateService($conexion);
            break;
        
        case 'delete':
            deleteService($conexion);
            break;
        
        case 'toggle_status':
            toggleStatus($conexion);
            break;

        case 'upload_image':
            uploadServiceImage();
            break;
        
        default:
            echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    }
} catch(Exception $e) {
    error_log("MedTravel Services Error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// ===================================================================
// LISTAR TODOS LOS SERVICIOS
// ===================================================================
function listServices($conexion) {
    $query = "SELECT 
        s.id,
        s.service_type,
        s.service_name,
        s.service_code,
        s.short_description,
        p.provider_name,
        s.cost_price,
        s.sale_price,
        s.currency,
        s.commission_amount,
        s.commission_percentage,
        s.is_active,
        s.availability_status,
        s.stock_quantity,
        s.featured,
        s.display_order,
        s.created_at
    FROM medtravel_services_catalog s
    LEFT JOIN service_providers p ON s.provider_id = p.id
    ORDER BY s.service_type ASC, s.display_order ASC, s.service_name ASC";
    
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        throw new Exception("Query error: " . mysqli_error($conexion));
    }
    
    $services = [];
    while($row = mysqli_fetch_assoc($result)) {
        $services[] = $row;
    }
    
    echo json_encode([
        'ok' => true,
        'data' => $services
    ]);
}

// ===================================================================
// OBTENER SERVICIO POR ID
// ===================================================================
function getService($conexion) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }
    
    $query = "SELECT * FROM medtravel_services_catalog WHERE id = $id";
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        throw new Exception("Query error: " . mysqli_error($conexion));
    }
    
    $service = mysqli_fetch_assoc($result);
    
    if(!$service) {
        echo json_encode(['ok' => false, 'message' => 'Service not found']);
        return;
    }
    
    echo json_encode([
        'ok' => true,
        'data' => $service
    ]);
}

// ===================================================================
// CREAR SERVICIO
// ===================================================================
function createService($conexion, $id_usuario) {
    // Validaciones obligatorias
    $service_type = isset($_POST['service_type']) ? mysqli_real_escape_string($conexion, $_POST['service_type']) : '';
    $service_name = isset($_POST['service_name']) ? mysqli_real_escape_string($conexion, $_POST['service_name']) : '';
    
    if(empty($service_type) || empty($service_name)) {
        echo json_encode(['ok' => false, 'message' => 'Service type and name are required']);
        return;
    }
    
    // Construir datos
    $data = buildServiceData($conexion, $_POST);
    $data['created_by'] = $id_usuario;
    
    // Construir query INSERT
    $fields = [];
    $values = [];
    
    foreach($data as $field => $value) {
        $fields[] = "`$field`";
        
        if(is_null($value)) {
            $values[] = "NULL";
        } elseif(is_numeric($value)) {
            $values[] = $value;
        } else {
            $values[] = "'" . mysqli_real_escape_string($conexion, $value) . "'";
        }
    }
    
    $query = "INSERT INTO medtravel_services_catalog (" . implode(', ', $fields) . ") 
              VALUES (" . implode(', ', $values) . ")";
    
    if(mysqli_query($conexion, $query)) {
        $new_id = mysqli_insert_id($conexion);
        
        // Obtener el servicio recién creado con comisiones calculadas
        $result = mysqli_query($conexion, "SELECT * FROM medtravel_services_catalog WHERE id = $new_id");
        $service = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'ok' => true,
            'message' => 'Service created successfully',
            'data' => $service
        ]);
    } else {
        throw new Exception("Error creating service: " . mysqli_error($conexion));
    }
}

// ===================================================================
// ACTUALIZAR SERVICIO
// ===================================================================
function updateService($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }
    
    // Construir datos
    $data = buildServiceData($conexion, $_POST);
    
    // Construir query UPDATE
    $sets = [];
    foreach($data as $field => $value) {
        if(is_null($value)) {
            $sets[] = "`$field` = NULL";
        } elseif(is_numeric($value)) {
            $sets[] = "`$field` = $value";
        } else {
            $sets[] = "`$field` = '" . mysqli_real_escape_string($conexion, $value) . "'";
        }
    }
    
    $query = "UPDATE medtravel_services_catalog SET " . implode(', ', $sets) . " WHERE id = $id";
    
    if(mysqli_query($conexion, $query)) {
        // Obtener el servicio actualizado
        $result = mysqli_query($conexion, "SELECT * FROM medtravel_services_catalog WHERE id = $id");
        $service = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'ok' => true,
            'message' => 'Service updated successfully',
            'data' => $service
        ]);
    } else {
        throw new Exception("Error updating service: " . mysqli_error($conexion));
    }
}

// ===================================================================
// ELIMINAR SERVICIO
// ===================================================================
function deleteService($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }
    
    $query = "DELETE FROM medtravel_services_catalog WHERE id = $id";
    
    if(mysqli_query($conexion, $query)) {
        echo json_encode([
            'ok' => true,
            'message' => 'Service deleted successfully'
        ]);
    } else {
        throw new Exception("Error deleting service: " . mysqli_error($conexion));
    }
}

// ===================================================================
// TOGGLE ESTADO ACTIVO/INACTIVO
// ===================================================================
function toggleStatus($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }
    
    $query = "UPDATE medtravel_services_catalog 
              SET is_active = IF(is_active = 1, 0, 1) 
              WHERE id = $id";
    
    if(mysqli_query($conexion, $query)) {
        $result = mysqli_query($conexion, "SELECT is_active FROM medtravel_services_catalog WHERE id = $id");
        $row = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'ok' => true,
            'message' => 'Status updated',
            'is_active' => $row['is_active']
        ]);
    } else {
        throw new Exception("Error updating status: " . mysqli_error($conexion));
    }
}

// ===================================================================
// SUBIR IMAGEN DEL SERVICIO
// ===================================================================
function uploadServiceImage() {
    if(!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'message' => 'Image file is required']);
        return;
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    if(!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid file type. Use JPG, PNG, GIF or WEBP.']);
        return;
    }

    $uploadDir = '../../img/services/';
    if(!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
    if(empty($extension)) {
        $extension = 'jpg';
    }

    $filename = 'service_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($extension);
    $filepath = $uploadDir . $filename;
    $dbPath = 'img/services/' . $filename;

    if(move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['ok' => true, 'path' => $dbPath]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Error saving uploaded image']);
    }
}

// ===================================================================
// HELPER: CONSTRUIR ARRAY DE DATOS DESDE POST
// ===================================================================
function buildServiceData($conexion, $post) {
    $data = [
        // Clasificación
        'service_type' => isset($post['service_type']) ? $post['service_type'] : null,
        'service_name' => isset($post['service_name']) ? $post['service_name'] : null,
        'service_code' => isset($post['service_code']) && !empty($post['service_code']) ? $post['service_code'] : null,
        'description' => isset($post['description']) && !empty($post['description']) ? $post['description'] : null,
        'short_description' => isset($post['short_description']) && !empty($post['short_description']) ? $post['short_description'] : null,
        
        // Proveedor - ahora usa provider_id FK
        'provider_id' => isset($post['provider_id']) && !empty($post['provider_id']) ? intval($post['provider_id']) : null,
        'provider_notes' => isset($post['provider_notes']) && !empty($post['provider_notes']) ? $post['provider_notes'] : null,
        
        // Costos - Ahora en COP con exchange_rate
        'cost_price_cop' => isset($post['cost_price_cop']) ? floatval($post['cost_price_cop']) : 0.00,
        'exchange_rate' => isset($post['exchange_rate']) ? floatval($post['exchange_rate']) : null,
        'sale_price' => isset($post['sale_price']) ? floatval($post['sale_price']) : 0.00,
        'currency' => isset($post['currency']) ? $post['currency'] : 'USD',
        
        // Detalles
        'service_details' => isset($post['service_details']) && !empty($post['service_details']) ? $post['service_details'] : null,
        
        // Disponibilidad
        'is_active' => isset($post['is_active']) ? 1 : 0,
        'availability_status' => isset($post['availability_status']) ? $post['availability_status'] : 'available',
        'stock_quantity' => isset($post['stock_quantity']) && !empty($post['stock_quantity']) ? intval($post['stock_quantity']) : null,
        'booking_lead_time' => isset($post['booking_lead_time']) ? intval($post['booking_lead_time']) : 0,
        
        // Visualización
        'icon_class' => isset($post['icon_class']) && !empty($post['icon_class']) ? $post['icon_class'] : null,
        'image_url' => isset($post['image_url']) && !empty($post['image_url']) ? $post['image_url'] : null,
        'display_order' => isset($post['display_order']) ? intval($post['display_order']) : 0,
        'featured' => isset($post['featured']) ? 1 : 0,
        
        // Metadata
        'tags' => isset($post['tags']) && !empty($post['tags']) ? $post['tags'] : null,
        'internal_notes' => isset($post['internal_notes']) && !empty($post['internal_notes']) ? $post['internal_notes'] : null,
    ];
    
    return $data;
}
