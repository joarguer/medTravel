<?php
/**
 * Helper de Timezones para MedTravel
 * Funciones para manejar dual display de zonas horarias (Cliente/Proveedor)
 * 
 * IMPORTANTE: Todas las fechas en BD están en UTC
 */

/**
 * Convierte fecha/hora UTC a timezone específico
 * 
 * @param string $utc_datetime Fecha en formato Y-m-d H:i:s (UTC)
 * @param string $target_timezone Timezone destino (ej: 'America/New_York')
 * @return array ['datetime' => '2024-01-29 14:30:00', 'formatted' => '29 Ene 2024, 2:30 PM', 'timezone' => 'EST']
 */
function convertFromUTC($utc_datetime, $target_timezone = 'UTC') {
    if (empty($utc_datetime)) {
        return null;
    }
    
    try {
        // Crear DateTime en UTC
        $dt = new DateTime($utc_datetime, new DateTimeZone('UTC'));
        
        // Convertir a timezone destino
        $dt->setTimezone(new DateTimeZone($target_timezone));
        
        return [
            'datetime' => $dt->format('Y-m-d H:i:s'),
            'date' => $dt->format('Y-m-d'),
            'time' => $dt->format('H:i:s'),
            'formatted' => $dt->format('d M Y, g:i A'),
            'formatted_short' => $dt->format('d/m/Y H:i'),
            'timezone' => $dt->format('T'), // Abreviatura (EST, PST, etc.)
            'timezone_full' => $target_timezone,
            'offset' => $dt->format('P') // +05:00
        ];
    } catch (Exception $e) {
        error_log("Error en convertFromUTC: " . $e->getMessage());
        return null;
    }
}

/**
 * Convierte fecha/hora de timezone específico a UTC para guardar en BD
 * 
 * @param string $local_datetime Fecha en formato Y-m-d H:i:s
 * @param string $source_timezone Timezone origen (ej: 'America/Bogota')
 * @return string Fecha en UTC (Y-m-d H:i:s) para guardar en BD
 */
function convertToUTC($local_datetime, $source_timezone = 'UTC') {
    if (empty($local_datetime)) {
        return null;
    }
    
    try {
        // Crear DateTime en timezone origen
        $dt = new DateTime($local_datetime, new DateTimeZone($source_timezone));
        
        // Convertir a UTC
        $dt->setTimezone(new DateTimeZone('UTC'));
        
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Error en convertToUTC: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener timezone del cliente desde BD
 * 
 * @param mysqli $conexion Conexión a BD
 * @param int $client_id ID del cliente
 * @return string Timezone del cliente (default: America/New_York)
 */
function getClientTimezone($conexion, $client_id) {
    $query = "SELECT client_timezone FROM clientes WHERE id = " . intval($client_id);
    $result = mysqli_query($conexion, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['client_timezone'] ?: 'America/New_York';
    }
    
    return 'America/New_York'; // Default
}

/**
 * Obtener timezone del proveedor desde BD
 * 
 * @param mysqli $conexion Conexión a BD
 * @param int $provider_id ID del proveedor
 * @return string Timezone del proveedor (default: America/Bogota)
 */
function getProviderTimezone($conexion, $provider_id) {
    $query = "SELECT provider_timezone FROM providers WHERE id = " . intval($provider_id);
    $result = mysqli_query($conexion, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['provider_timezone'] ?: 'America/Bogota';
    }
    
    return 'America/Bogota'; // Default
}

/**
 * Mostrar dual timezone (Cliente y Proveedor) para una cita
 * Retorna HTML listo para mostrar en UI
 * 
 * @param string $utc_datetime Fecha/hora en UTC desde BD
 * @param string $client_timezone Timezone del cliente
 * @param string $provider_timezone Timezone del proveedor
 * @return string HTML formateado
 */
function displayDualTimezone($utc_datetime, $client_timezone, $provider_timezone) {
    if (empty($utc_datetime)) {
        return '<span class="text-muted">No asignado</span>';
    }
    
    $client_time = convertFromUTC($utc_datetime, $client_timezone);
    $provider_time = convertFromUTC($utc_datetime, $provider_timezone);
    
    if (!$client_time || !$provider_time) {
        return '<span class="text-danger">Error en conversión</span>';
    }
    
    $html = '<div class="timezone-display">';
    $html .= '<div class="client-time">';
    $html .= '<i class="fa fa-user"></i> <strong>Cliente:</strong> ';
    $html .= '<span class="text-primary">' . $client_time['formatted'] . '</span> ';
    $html .= '<small class="text-muted">(' . $client_time['timezone'] . ')</small>';
    $html .= '</div>';
    $html .= '<div class="provider-time mt-5">';
    $html .= '<i class="fa fa-hospital-o"></i> <strong>Proveedor:</strong> ';
    $html .= '<span class="text-success">' . $provider_time['formatted'] . '</span> ';
    $html .= '<small class="text-muted">(' . $provider_time['timezone'] . ')</small>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Ejemplo de uso en formulario: Guardar cita
 * 
 * EJEMPLO AL GUARDAR:
 * 
 * $appointment_date = $_POST['appointment_date']; // 2024-02-15
 * $appointment_time = $_POST['appointment_time']; // 10:30:00
 * $client_timezone = getClientTimezone($conexion, $client_id);
 * $provider_timezone = getProviderTimezone($conexion, $provider_id);
 * 
 * // El cliente ingresa la fecha en SU zona horaria
 * $local_datetime = $appointment_date . ' ' . $appointment_time;
 * $utc_datetime = convertToUTC($local_datetime, $client_timezone);
 * 
 * // Guardar en BD
 * $sql = "INSERT INTO appointments (
 *     client_id,
 *     provider_id,
 *     appointment_datetime_utc,
 *     client_timezone,
 *     provider_timezone
 * ) VALUES (
 *     $client_id,
 *     $provider_id,
 *     '$utc_datetime',
 *     '$client_timezone',
 *     '$provider_timezone'
 * )";
 */

/**
 * Ejemplo de uso en listado: Mostrar citas
 * 
 * EJEMPLO AL LISTAR:
 * 
 * $sql = "SELECT 
 *     a.id,
 *     a.appointment_datetime_utc,
 *     a.client_timezone,
 *     a.provider_timezone,
 *     c.nombre AS client_name,
 *     p.name AS provider_name
 * FROM appointments a
 * JOIN clientes c ON a.client_id = c.id
 * JOIN providers p ON a.provider_id = p.id";
 * 
 * $result = mysqli_query($conexion, $sql);
 * while ($row = mysqli_fetch_assoc($result)) {
 *     echo '<tr>';
 *     echo '<td>' . $row['id'] . '</td>';
 *     echo '<td>' . $row['client_name'] . '</td>';
 *     echo '<td>' . $row['provider_name'] . '</td>';
 *     echo '<td>' . displayDualTimezone(
 *         $row['appointment_datetime_utc'],
 *         $row['client_timezone'],
 *         $row['provider_timezone']
 *     ) . '</td>';
 *     echo '</tr>';
 * }
 */

/**
 * Lista de timezones comunes para selectores
 */
function getCommonTimezones() {
    return [
        'America/New_York' => 'New York (EST/EDT)',
        'America/Chicago' => 'Chicago (CST/CDT)',
        'America/Denver' => 'Denver (MST/MDT)',
        'America/Los_Angeles' => 'Los Angeles (PST/PDT)',
        'America/Phoenix' => 'Phoenix (MST)',
        'America/Anchorage' => 'Alaska (AKST/AKDT)',
        'Pacific/Honolulu' => 'Hawaii (HST)',
        'America/Bogota' => 'Bogotá (COT)',
        'America/Mexico_City' => 'Ciudad de México (CST)',
        'America/Sao_Paulo' => 'São Paulo (BRT)',
        'America/Buenos_Aires' => 'Buenos Aires (ART)',
        'Europe/London' => 'London (GMT/BST)',
        'Europe/Paris' => 'Paris (CET/CEST)',
        'Europe/Madrid' => 'Madrid (CET/CEST)',
        'Asia/Tokyo' => 'Tokyo (JST)',
        'Australia/Sydney' => 'Sydney (AEDT/AEST)'
    ];
}

/**
 * Validar que un timezone es válido
 * 
 * @param string $timezone
 * @return bool
 */
function isValidTimezone($timezone) {
    return in_array($timezone, timezone_identifiers_list());
}
?>
