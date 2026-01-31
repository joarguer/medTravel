<?php
// admin/ajax/exchange_rate.php
// API para obtener la tasa de cambio vigente
include("../include/conexion.php");
session_start();

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_current';

switch($action) {
    case 'get_current':
        getCurrentRate();
        break;
    case 'list':
        listRates();
        break;
    case 'update':
        updateRate();
        break;
    default:
        echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
}

function getCurrentRate() {
    global $conexion;
    
    $sql = "SELECT rate, effective_date, source 
            FROM exchange_rates 
            WHERE from_currency = 'USD' 
              AND to_currency = 'COP' 
              AND is_active = 1 
            ORDER BY effective_date DESC 
            LIMIT 1";
    
    $result = mysqli_query($conexion, $sql);
    
    if($result && mysqli_num_rows($result) > 0) {
        $rate = mysqli_fetch_assoc($result);
        echo json_encode([
            'ok' => true,
            'rate' => floatval($rate['rate']),
            'effective_date' => $rate['effective_date'],
            'source' => $rate['source']
        ]);
    } else {
        // Tasa por defecto si no hay en BD
        echo json_encode([
            'ok' => true,
            'rate' => 4150.00,
            'effective_date' => date('Y-m-d'),
            'source' => 'default'
        ]);
    }
}

function listRates() {
    global $conexion;
    
    $sql = "SELECT id, from_currency, to_currency, rate, effective_date, source, is_active, created_at
            FROM exchange_rates 
            WHERE from_currency = 'USD' AND to_currency = 'COP'
            ORDER BY effective_date DESC, created_at DESC 
            LIMIT 100";
    
    $result = mysqli_query($conexion, $sql);
    $rates = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $rates[] = $row;
    }
    
    echo json_encode(['ok' => true, 'data' => $rates]);
}

function updateRate() {
    global $conexion;
    
    $rate = floatval($_POST['rate'] ?? 0);
    $effectiveDate = $_POST['effective_date'] ?? date('Y-m-d');
    $source = mysqli_real_escape_string($conexion, $_POST['source'] ?? 'manual');
    $notes = mysqli_real_escape_string($conexion, $_POST['notes'] ?? '');
    $userId = $_SESSION['id_usuario'];
    
    if($rate <= 0) {
        echo json_encode(['ok' => false, 'message' => 'La tasa debe ser mayor a 0']);
        return;
    }
    
    // Desactivar tasas anteriores
    $sqlDeactivate = "UPDATE exchange_rates 
                      SET is_active = 0 
                      WHERE from_currency = 'USD' 
                        AND to_currency = 'COP' 
                        AND is_active = 1";
    mysqli_query($conexion, $sqlDeactivate);
    
    // Insertar nueva tasa
    $sqlInsert = "INSERT INTO exchange_rates 
                  (from_currency, to_currency, rate, effective_date, source, is_active, created_by, notes) 
                  VALUES ('USD', 'COP', $rate, '$effectiveDate', '$source', 1, $userId, '$notes')";
    
    if(mysqli_query($conexion, $sqlInsert)) {
        echo json_encode([
            'ok' => true, 
            'message' => 'Tasa de cambio actualizada correctamente',
            'rate' => $rate
        ]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Error al actualizar la tasa: ' . mysqli_error($conexion)]);
    }
}
?>
