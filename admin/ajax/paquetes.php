<?php
// admin/ajax/paquetes.php - API Backend para gestión de paquetes
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/paquetes_email.log');

header('Content-Type: application/json; charset=utf-8');
session_start();

// Validación de sesión
if(!isset($_SESSION['id_usuario'])){
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}

require_once('../include/conexion.php');

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$id_usuario = $_SESSION['id_usuario'];

try {
    switch($action) {
        case 'list':
            listPaquetes($conexion);
            break;
        
        case 'get':
            getPaquete($conexion);
            break;
        
        case 'create':
            createPaquete($conexion, $id_usuario);
            break;
        
        case 'update':
            updatePaquete($conexion, $id_usuario);
            break;
        
        case 'delete':
            deletePaquete($conexion);
            break;
        
        case 'get_clientes':
            getClientes($conexion);
            break;
        
        case 'get_client_info':
            getClientInfo($conexion);
            break;
        
        case 'send_quote':
            sendQuoteEmail($conexion);
            break;
        
        case 'get_catalog_services':
            getCatalogServices($conexion);
            break;
        
        case 'add_service_to_package':
            addServiceToPackage($conexion);
            break;
        
        case 'remove_service_from_package':
            removeServiceFromPackage($conexion);
            break;
        
        case 'get_package_services':
            getPackageServices($conexion);
            break;
        
        default:
            echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    error_log("Error en paquetes.php: " . $e->getMessage());
    echo json_encode([
        'ok' => false, 
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

// ===================================================================
// LISTAR PAQUETES
// ===================================================================
function listPaquetes($conexion) {
    $query = "SELECT 
        p.id,
        p.package_name,
        p.start_date,
        p.end_date,
        p.total_days,
        p.total_package_cost,
        p.net_margin,
        p.status,
        p.payment_status,
        p.currency,
        c.nombre AS client_nombre,
        c.apellido AS client_apellido,
        c.email AS client_email,
        p.created_at
    FROM travel_packages p
    INNER JOIN clientes c ON p.client_id = c.id
    ORDER BY p.created_at DESC";
    
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        throw new Exception("Error en consulta: " . mysqli_error($conexion));
    }
    
    $paquetes = [];
    while($row = mysqli_fetch_assoc($result)) {
        $paquetes[] = $row;
    }
    
    echo json_encode([
        'ok' => true,
        'data' => $paquetes
    ]);
}

// ===================================================================
// OBTENER PAQUETE POR ID
// ===================================================================
function getPaquete($conexion) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    $query = "SELECT * FROM travel_packages WHERE id = $id";
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        throw new Exception("Error en consulta: " . mysqli_error($conexion));
    }
    
    $paquete = mysqli_fetch_assoc($result);
    
    if(!$paquete) {
        echo json_encode(['ok' => false, 'message' => 'Paquete no encontrado']);
        return;
    }
    
    echo json_encode([
        'ok' => true,
        'data' => $paquete
    ]);
}

// ===================================================================
// CREAR PAQUETE
// ===================================================================
function createPaquete($conexion, $id_usuario) {
    // Validaciones obligatorias
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $start_date = isset($_POST['start_date']) ? mysqli_real_escape_string($conexion, $_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? mysqli_real_escape_string($conexion, $_POST['end_date']) : '';
    $total_package_cost = isset($_POST['total_package_cost']) ? floatval($_POST['total_package_cost']) : 0;
    
    if($client_id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Debe seleccionar un cliente']);
        return;
    }
    
    if(empty($start_date) || empty($end_date)) {
        echo json_encode(['ok' => false, 'message' => 'Las fechas son obligatorias']);
        return;
    }
    
    if($total_package_cost <= 0) {
        echo json_encode(['ok' => false, 'message' => 'El costo total debe ser mayor a 0']);
        return;
    }
    
    // Recopilar datos del formulario
    $data = buildPaqueteData($conexion, $_POST);
    $data['client_id'] = $client_id;
    $data['start_date'] = $start_date;
    $data['end_date'] = $end_date;
    $data['total_package_cost'] = $total_package_cost;
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
    
    $query = "INSERT INTO travel_packages (" . implode(', ', $fields) . ") 
              VALUES (" . implode(', ', $values) . ")";
    
    if(mysqli_query($conexion, $query)) {
        $new_id = mysqli_insert_id($conexion);
        
        // Obtener el paquete recién creado con márgenes calculados por trigger
        $result = mysqli_query($conexion, "SELECT * FROM travel_packages WHERE id = $new_id");
        $paquete = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'ok' => true,
            'message' => 'Paquete creado exitosamente',
            'data' => $paquete
        ]);
    } else {
        throw new Exception("Error al crear paquete: " . mysqli_error($conexion));
    }
}

// ===================================================================
// ACTUALIZAR PAQUETE
// ===================================================================
function updatePaquete($conexion, $id_usuario) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    // Validaciones obligatorias
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $start_date = isset($_POST['start_date']) ? mysqli_real_escape_string($conexion, $_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? mysqli_real_escape_string($conexion, $_POST['end_date']) : '';
    $total_package_cost = isset($_POST['total_package_cost']) ? floatval($_POST['total_package_cost']) : 0;
    
    if($client_id === 0 || empty($start_date) || empty($end_date) || $total_package_cost <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Datos obligatorios incompletos']);
        return;
    }
    
    // Recopilar datos del formulario
    $data = buildPaqueteData($conexion, $_POST);
    $data['client_id'] = $client_id;
    $data['start_date'] = $start_date;
    $data['end_date'] = $end_date;
    $data['total_package_cost'] = $total_package_cost;
    
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
    
    $query = "UPDATE travel_packages SET " . implode(', ', $sets) . " WHERE id = $id";
    
    if(mysqli_query($conexion, $query)) {
        // Obtener el paquete actualizado con márgenes recalculados por trigger
        $result = mysqli_query($conexion, "SELECT * FROM travel_packages WHERE id = $id");
        $paquete = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'ok' => true,
            'message' => 'Paquete actualizado exitosamente',
            'data' => $paquete
        ]);
    } else {
        throw new Exception("Error al actualizar paquete: " . mysqli_error($conexion));
    }
}

// ===================================================================
// ELIMINAR PAQUETE
// ===================================================================
function deletePaquete($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    $query = "DELETE FROM travel_packages WHERE id = $id";
    
    if(mysqli_query($conexion, $query)) {
        echo json_encode([
            'ok' => true,
            'message' => 'Paquete eliminado exitosamente'
        ]);
    } else {
        throw new Exception("Error al eliminar paquete: " . mysqli_error($conexion));
    }
}

// ===================================================================
// OBTENER LISTA DE CLIENTES PARA SELECT
// ===================================================================
function getClientes($conexion) {
    $query = "SELECT id, nombre, apellido, email, telefono 
              FROM clientes 
              WHERE status NOT IN ('descartado', 'cancelado')
              ORDER BY nombre, apellido";
    
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        throw new Exception("Error en consulta: " . mysqli_error($conexion));
    }
    
    $clientes = [];
    while($row = mysqli_fetch_assoc($result)) {
        $clientes[] = $row;
    }
    
    echo json_encode([
        'ok' => true,
        'data' => $clientes
    ]);
}

// ===================================================================
// HELPER: CONSTRUIR ARRAY DE DATOS DESDE POST
// ===================================================================
function buildPaqueteData($conexion, $post) {
    // Campos opcionales con valores por defecto
    $data = [
        // General
        'package_name' => isset($post['package_name']) ? $post['package_name'] : null,
        'status' => isset($post['status']) ? $post['status'] : 'quoted',
        'currency' => isset($post['currency']) ? $post['currency'] : 'USD',
        'internal_notes' => isset($post['internal_notes']) ? $post['internal_notes'] : null,
        
        // Vuelo
        'flight_included' => isset($post['flight_included']) ? 1 : 0,
        'flight_airline' => isset($post['flight_airline']) ? $post['flight_airline'] : null,
        'flight_departure_airport' => isset($post['flight_departure_airport']) ? $post['flight_departure_airport'] : null,
        'flight_arrival_airport' => isset($post['flight_arrival_airport']) ? $post['flight_arrival_airport'] : null,
        'flight_departure_date' => isset($post['flight_departure_date']) && !empty($post['flight_departure_date']) ? $post['flight_departure_date'] : null,
        'flight_return_date' => isset($post['flight_return_date']) && !empty($post['flight_return_date']) ? $post['flight_return_date'] : null,
        'flight_cost' => isset($post['flight_cost']) ? floatval($post['flight_cost']) : 0.00,
        'flight_notes' => isset($post['flight_notes']) ? $post['flight_notes'] : null,
        
        // Hotel
        'hotel_included' => isset($post['hotel_included']) ? 1 : 0,
        'hotel_name' => isset($post['hotel_name']) ? $post['hotel_name'] : null,
        'hotel_city' => isset($post['hotel_city']) ? $post['hotel_city'] : null,
        'hotel_nights' => isset($post['hotel_nights']) ? intval($post['hotel_nights']) : null,
        'hotel_cost_per_night' => isset($post['hotel_cost_per_night']) ? floatval($post['hotel_cost_per_night']) : 0.00,
        'hotel_total_cost' => isset($post['hotel_total_cost']) ? floatval($post['hotel_total_cost']) : 0.00,
        'hotel_notes' => isset($post['hotel_notes']) ? $post['hotel_notes'] : null,
        
        // Transporte
        'transport_included' => isset($post['transport_included']) ? 1 : 0,
        'transport_type' => isset($post['transport_type']) ? $post['transport_type'] : null,
        'transport_routes' => isset($post['transport_routes']) ? $post['transport_routes'] : null,
        'transport_cost' => isset($post['transport_cost']) ? floatval($post['transport_cost']) : 0.00,
        
        // Costos
        'medical_service_cost' => isset($post['medical_service_cost']) ? floatval($post['medical_service_cost']) : 0.00,
        'meals_cost' => isset($post['meals_cost']) ? floatval($post['meals_cost']) : 0.00,
        'additional_services_cost' => isset($post['additional_services_cost']) ? floatval($post['additional_services_cost']) : 0.00,
        
        // Monetización (triggers calcularán medtravel_fee_amount, gross_margin, net_margin)
        'medtravel_fee_type' => isset($post['medtravel_fee_type']) ? $post['medtravel_fee_type'] : 'percent',
        'medtravel_fee_value' => isset($post['medtravel_fee_value']) ? floatval($post['medtravel_fee_value']) : 0.00,
        'provider_commission_value' => isset($post['provider_commission_value']) ? floatval($post['provider_commission_value']) : 0.00,
        
        // Pagos
        'payment_status' => isset($post['payment_status']) ? $post['payment_status'] : 'pending',
        'payment_method' => isset($post['payment_method']) ? $post['payment_method'] : null,
        'deposit_amount' => isset($post['deposit_amount']) ? floatval($post['deposit_amount']) : 0.00,
        'amount_paid' => isset($post['amount_paid']) ? floatval($post['amount_paid']) : 0.00,
        'payment_reference' => isset($post['payment_reference']) ? $post['payment_reference'] : null,
        'payment_notes' => isset($post['payment_notes']) ? $post['payment_notes'] : null,
    ];
    
    return $data;
}

// ===================================================================
// OBTENER INFO DEL CLIENTE
// ===================================================================
function getClientInfo($conexion) {
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    
    if($client_id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID de cliente inválido']);
        return;
    }
    
    $query = "SELECT id, nombre, apellido, email, telefono FROM clientes WHERE id = ?";
    $stmt = mysqli_prepare($conexion, $query);
    mysqli_stmt_bind_param($stmt, 'i', $client_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['ok' => true, 'data' => $row]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Cliente no encontrado']);
    }
}

// ===================================================================
// ENVIAR COTIZACIÓN POR EMAIL
// ===================================================================
function sendQuoteEmail($conexion) {
    $package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : 'Cotización MedTravel';
    $custom_message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $include_details = isset($_POST['include_details']) ? intval($_POST['include_details']) : 1;
    
    if($package_id === 0 || empty($email)) {
        echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
        return;
    }
    
    // Obtener datos del paquete
    $query = "SELECT 
        p.*,
        c.nombre as client_nombre,
        c.apellido as client_apellido,
        c.email as client_email
    FROM travel_packages p
    INNER JOIN clientes c ON p.client_id = c.id
    WHERE p.id = ?";
    
    $stmt = mysqli_prepare($conexion, $query);
    mysqli_stmt_bind_param($stmt, 'i', $package_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(!$package = mysqli_fetch_assoc($result)) {
        echo json_encode(['ok' => false, 'message' => 'Paquete no encontrado']);
        return;
    }
    
    // Construir email HTML
    $email_body = buildQuoteEmailHTML($package, $custom_message, $include_details);
    
    // Usar configuración SMTP profesional
    require_once('../include/email_config.php');
    
    try {
        $options = array(
            'cc' => array(),
            'bcc' => array(),
            'alt_body' => strip_tags($email_body)
        );
        
        $debug_info = array(
            'to' => $email,
            'subject' => $subject,
            'account' => 'patientcare',
            'package_id' => $package_id
        );
        
        $sent = sendEmail($email, $subject, $email_body, 'patientcare', $options, $conexion);
        
        if($sent) {
            error_log("Cotización enviada - Paquete #$package_id - Cliente: " . $package['client_nombre'] . " - Email: $email");
            echo json_encode([
                'ok' => true, 
                'message' => 'Cotización enviada exitosamente a ' . $email,
                'debug' => $debug_info
            ]);
        } else {
            error_log("sendEmail retornó false para paquete #$package_id");
            echo json_encode([
                'ok' => false, 
                'message' => 'El servidor de correo no pudo procesar el envío. Revise la configuración SMTP.',
                'debug' => $debug_info
            ]);
        }
        
    } catch(Exception $e) {
        error_log("ERROR enviando cotización paquete #$package_id: " . $e->getMessage());
        echo json_encode([
            'ok' => false, 
            'message' => 'Error: ' . $e->getMessage(),
            'error_details' => array(
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            )
        ]);
    }
}

// ===================================================================
// CONSTRUIR HTML DEL EMAIL DE COTIZACIÓN
// ===================================================================
function buildQuoteEmailHTML($package, $custom_message = '', $include_details = 1) {
    $currency_symbol = $package['currency'] == 'USD' ? '$' : ($package['currency'] == 'EUR' ? '€' : 'COP ');
    
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; }
        .package-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .price-box { background: #0f766e; color: white; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
        .price-box h2 { margin: 0; font-size: 36px; }
        .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .details-table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .details-table td:first-child { font-weight: bold; width: 40%; }
        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px; }
        .btn { display: inline-block; background: #0f766e; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 MedTravel</h1>
            <p>Medical Tourism Excellence</p>
        </div>
        
        <div class="content">
            <h2>Estimado/a ' . htmlspecialchars($package['client_nombre'] . ' ' . $package['client_apellido']) . ',</h2>
            
            <p>Nos complace presentarle la cotización de su paquete de turismo médico:</p>
            
            <div class="package-info">
                <h3>' . htmlspecialchars($package['package_name'] ?: 'Paquete Médico') . '</h3>
                <table class="details-table">
                    <tr>
                        <td>Fechas:</td>
                        <td>' . date('d/m/Y', strtotime($package['start_date'])) . ' - ' . date('d/m/Y', strtotime($package['end_date'])) . '</td>
                    </tr>
                    <tr>
                        <td>Duración:</td>
                        <td>' . $package['total_days'] . ' días</td>
                    </tr>
                </table>
            </div>';

    if($include_details) {
        $html .= '
            <h3>Detalles del Paquete:</h3>
            <table class="details-table">';
        
        if($package['flight_included']) {
            $html .= '<tr><td>✈️ Vuelo:</td><td>' . $currency_symbol . number_format($package['flight_cost'], 2) . '</td></tr>';
        }
        if($package['hotel_included']) {
            $html .= '<tr><td>🏨 Hotel (' . $package['hotel_nights'] . ' noches):</td><td>' . $currency_symbol . number_format($package['hotel_total_cost'], 2) . '</td></tr>';
        }
        if($package['transport_included']) {
            $html .= '<tr><td>🚗 Transporte:</td><td>' . $currency_symbol . number_format($package['transport_cost'], 2) . '</td></tr>';
        }
        if($package['medical_service_cost'] > 0) {
            $html .= '<tr><td>💉 Servicio Médico:</td><td>' . $currency_symbol . number_format($package['medical_service_cost'], 2) . '</td></tr>';
        }
        if($package['meals_cost'] > 0) {
            $html .= '<tr><td>🍽️ Alimentación:</td><td>' . $currency_symbol . number_format($package['meals_cost'], 2) . '</td></tr>';
        }
        if($package['additional_services_cost'] > 0) {
            $html .= '<tr><td>➕ Servicios Adicionales:</td><td>' . $currency_symbol . number_format($package['additional_services_cost'], 2) . '</td></tr>';
        }
        
        $html .= '</table>';
    }
    
    $html .= '
            <div class="price-box">
                <p style="margin: 0; font-size: 18px;">Precio Total</p>
                <h2>' . $currency_symbol . number_format($package['total_package_cost'], 2) . ' ' . $package['currency'] . '</h2>
            </div>';
    
    if(!empty($custom_message)) {
        $html .= '
            <div class="package-info">
                <p>' . nl2br(htmlspecialchars($custom_message)) . '</p>
            </div>';
    }
    
    $html .= '
            <p>Para confirmar su paquete o solicitar más información, no dude en contactarnos:</p>
            <ul>
                <li>📧 Email: info@medtravel.com.co</li>
                <li>📱 Teléfono: +1 (561) 698-8069</li>
                <li>💬 WhatsApp: +1 (561) 698-8069</li>
            </ul>
            
            <p style="text-align: center;">
                <a href="https://medtravel.com.co/contact.php" class="btn">Contactar Ahora</a>
            </p>
        </div>
        
        <div class="footer">
            <p>MedTravel - Medical Tourism Excellence</p>
            <p>Esta cotización es válida por 30 días</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

// ===================================================================
// OBTENER SERVICIOS DEL CATÁLOGO
// ===================================================================
function getCatalogServices($conexion) {
    $type = isset($_GET['type']) ? mysqli_real_escape_string($conexion, $_GET['type']) : '';
    
    $where = "is_active = 1";
    if(!empty($type)) {
        $where .= " AND service_type = '$type'";
    }
    
    $query = "SELECT 
        id,
        service_type,
        service_name,
        service_code,
        short_description,
        provider_name,
        cost_price,
        sale_price,
        currency,
        commission_amount,
        availability_status,
        stock_quantity
    FROM medtravel_services_catalog
    WHERE $where
    ORDER BY service_type ASC, display_order ASC, service_name ASC";
    
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        throw new Exception("Error querying catalog: " . mysqli_error($conexion));
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
// AGREGAR SERVICIO A PAQUETE
// ===================================================================
function addServiceToPackage($conexion) {
    $package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conexion, $_POST['notes']) : '';
    
    if($package_id === 0 || $service_id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid package or service ID']);
        return;
    }
    
    // Obtener precio del servicio
    $service_query = "SELECT sale_price, currency FROM medtravel_services_catalog WHERE id = $service_id";
    $service_result = mysqli_query($conexion, $service_query);
    $service = mysqli_fetch_assoc($service_result);
    
    if(!$service) {
        echo json_encode(['ok' => false, 'message' => 'Service not found']);
        return;
    }
    
    $unit_price = floatval($service['sale_price']);
    $total_price = $unit_price * $quantity;
    
    // Verificar si ya existe
    $check = mysqli_query($conexion, "SELECT id FROM package_services WHERE package_id = $package_id AND service_id = $service_id");
    if(mysqli_num_rows($check) > 0) {
        echo json_encode(['ok' => false, 'message' => 'Service already added to package']);
        return;
    }
    
    // Insertar
    $insert = "INSERT INTO package_services (package_id, service_id, quantity, unit_price, total_price, notes)
               VALUES ($package_id, $service_id, $quantity, $unit_price, $total_price, '$notes')";
    
    if(mysqli_query($conexion, $insert)) {
        echo json_encode([
            'ok' => true,
            'message' => 'Service added successfully',
            'total_price' => $total_price
        ]);
    } else {
        throw new Exception("Error adding service: " . mysqli_error($conexion));
    }
}

// ===================================================================
// ELIMINAR SERVICIO DE PAQUETE
// ===================================================================
function removeServiceFromPackage($conexion) {
    $package_service_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($package_service_id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }
    
    $delete = "DELETE FROM package_services WHERE id = $package_service_id";
    
    if(mysqli_query($conexion, $delete)) {
        echo json_encode([
            'ok' => true,
            'message' => 'Service removed successfully'
        ]);
    } else {
        throw new Exception("Error removing service: " . mysqli_error($conexion));
    }
}

// ===================================================================
// OBTENER SERVICIOS DE UN PAQUETE
// ===================================================================
function getPackageServices($conexion) {
    $package_id = isset($_GET['package_id']) ? intval($_GET['package_id']) : 0;
    
    if($package_id === 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid package ID']);
        return;
    }
    
    $query = "SELECT 
        ps.id,
        ps.package_id,
        ps.service_id,
        ps.quantity,
        ps.unit_price,
        ps.total_price,
        ps.notes,
        msc.service_type,
        msc.service_name,
        msc.service_code,
        msc.provider_name,
        msc.currency
    FROM package_services ps
    INNER JOIN medtravel_services_catalog msc ON ps.service_id = msc.id
    WHERE ps.package_id = $package_id
    ORDER BY msc.service_type ASC, msc.service_name ASC";
    
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        throw new Exception("Error querying package services: " . mysqli_error($conexion));
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
?>
