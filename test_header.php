<?php
include('inc/include.php');

echo "<h1>TEST: Verificando tabla offer_detail_header</h1>";

// Verificar si la tabla existe
$table_check = mysqli_query($conexion, "SHOW TABLES LIKE 'offer_detail_header'");
if (mysqli_num_rows($table_check) == 0) {
    echo "<p style='color:red;'>❌ La tabla offer_detail_header NO EXISTE</p>";
    echo "<h3>SQL para crear la tabla:</h3>";
    echo "<pre>";
    echo "CREATE TABLE IF NOT EXISTS offer_detail_header (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL DEFAULT 'Medical Service Details',
    subtitle_1 VARCHAR(255) NOT NULL DEFAULT 'OFFER DETAILS',
    subtitle_2 TEXT,
    bg_image VARCHAR(500),
    activo ENUM('0','1') DEFAULT '0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO offer_detail_header (title, subtitle_1, subtitle_2, activo) 
VALUES ('Medical Service Details', 'OFFER DETAILS', 'Complete information about your medical service', '0');";
    echo "</pre>";
} else {
    echo "<p style='color:green;'>✅ La tabla offer_detail_header EXISTE</p>";
    
    // Ver todos los registros
    $query = mysqli_query($conexion, "SELECT * FROM offer_detail_header");
    echo "<h3>Registros en la tabla:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Title</th><th>Subtitle 1</th><th>Subtitle 2</th><th>BG Image</th><th>Activo</th><th>Created</th><th>Updated</th></tr>";
    
    if (mysqli_num_rows($query) == 0) {
        echo "<tr><td colspan='8' style='color:red;'>❌ NO HAY REGISTROS</td></tr>";
    } else {
        while ($row = mysqli_fetch_assoc($query)) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>{$row['subtitle_1']}</td>";
            echo "<td>{$row['subtitle_2']}</td>";
            echo "<td>" . ($row['bg_image'] ? $row['bg_image'] : '<em>Sin imagen</em>') . "</td>";
            echo "<td style='color:" . ($row['activo'] == '0' ? 'green' : 'red') . ";font-weight:bold;'>{$row['activo']}</td>";
            echo "<td>" . (isset($row['created_at']) ? $row['created_at'] : 'N/A') . "</td>";
            echo "<td>" . (isset($row['updated_at']) ? $row['updated_at'] : 'N/A') . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // Ver el registro que se está usando (activo = '0')
    $query_active = mysqli_query($conexion, "SELECT * FROM offer_detail_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
    echo "<h3>Registro activo (activo='0'):</h3>";
    if (mysqli_num_rows($query_active) == 0) {
        echo "<p style='color:red;'>❌ NO HAY REGISTRO CON activo='0'</p>";
        echo "<p>Ejecute este SQL para activar un registro:</p>";
        echo "<pre>UPDATE offer_detail_header SET activo = '0' WHERE id = 1;</pre>";
    } else {
        $active = mysqli_fetch_assoc($query_active);
        echo "<pre>";
        print_r($active);
        echo "</pre>";
    }
}
?>
