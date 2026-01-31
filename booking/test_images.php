<?php
// Test para verificar qué logos están llegando
include(__DIR__ . '/../inc/include.php');

$offers_sql = "SELECT 
                o.id, o.title, o.provider_id,
                p.name AS provider_name, p.logo AS provider_logo
               FROM provider_service_offers o
               INNER JOIN providers p ON o.provider_id = p.id
               WHERE o.is_active = 1
               LIMIT 5";
$offers_res = mysqli_query($conexion, $offers_sql);

echo "<h2>Test de Logos de Proveedores</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID Oferta</th><th>Proveedor</th><th>Logo Path</th><th>Imagen</th><th>Archivo Existe?</th></tr>";

if ($offers_res) {
    while ($row = mysqli_fetch_assoc($offers_res)) {
        $logo_path = $row['provider_logo'];
        $full_path = __DIR__ . '/../' . $logo_path;
        $file_exists = file_exists($full_path) ? '✅ SI' : '❌ NO';
        
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['provider_name']}</td>";
        echo "<td>" . htmlspecialchars($logo_path) . "</td>";
        echo "<td>";
        if (!empty($logo_path)) {
            echo "<img src='../{$logo_path}' style='max-width:100px; border:2px solid #ccc;' onerror='this.style.border=\"2px solid red\"'>";
        } else {
            echo "Sin logo";
        }
        echo "</td>";
        echo "<td>{$file_exists}</td>";
        echo "</tr>";
    }
}
echo "</table>";

echo "<br><br><h3>Verificación de rutas:</h3>";
echo "<p><strong>__DIR__:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Ruta base del proyecto:</strong> " . __DIR__ . "/../</p>";
?>
