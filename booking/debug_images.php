<!DOCTYPE html>
<html>
<head>
    <title>Debug - Provider Logos</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #667eea; color: white; }
        .error { color: red; }
        .success { color: green; }
        img { border: 2px solid #ccc; }
    </style>
</head>
<body>
    <h1>🔍 Debug de Logos de Proveedores</h1>
    
    <?php
    include(__DIR__ . '/../inc/include.php');
    
    echo "<h2>1. Consulta SQL y Resultados</h2>";
    
    $offers_sql = "SELECT 
                    o.id, o.title, o.provider_id,
                    p.name AS provider_name, p.logo AS provider_logo
                   FROM provider_service_offers o
                   INNER JOIN providers p ON o.provider_id = p.id
                   WHERE o.is_active = 1
                   LIMIT 10";
    
    $offers_res = mysqli_query($conexion, $offers_sql);
    
    echo "<table>";
    echo "<tr><th>Offer ID</th><th>Provider ID</th><th>Provider Name</th><th>Logo Value (DB)</th><th>Generated Path</th><th>Full Path</th><th>File Exists?</th><th>Image Test</th></tr>";
    
    if ($offers_res && mysqli_num_rows($offers_res) > 0) {
        while ($offer = mysqli_fetch_assoc($offers_res)) {
            $logo_db = $offer['provider_logo'];
            $provider_id = $offer['provider_id'];
            
            // Ruta que se genera en el código
            $generated_path = "../admin/img/providers/{$provider_id}/{$logo_db}";
            
            // Ruta física completa
            $full_physical_path = __DIR__ . "/../admin/img/providers/{$provider_id}/{$logo_db}";
            
            // Verificar si existe
            $exists = !empty($logo_db) && file_exists($full_physical_path);
            
            echo "<tr>";
            echo "<td>{$offer['id']}</td>";
            echo "<td>{$provider_id}</td>";
            echo "<td>" . htmlspecialchars($offer['provider_name']) . "</td>";
            echo "<td>" . ($logo_db ? htmlspecialchars($logo_db) : '<span class="error">EMPTY/NULL</span>') . "</td>";
            echo "<td><code>{$generated_path}</code></td>";
            echo "<td style='font-size:10px;'><code>{$full_physical_path}</code></td>";
            echo "<td>" . ($exists ? '<span class="success">✅ YES</span>' : '<span class="error">❌ NO</span>') . "</td>";
            echo "<td>";
            if ($exists) {
                echo "<img src='{$generated_path}' style='max-width:60px; max-height:60px;' onerror='this.parentElement.innerHTML=\"<span class=error>Load Failed</span>\"'>";
            } else {
                echo "<span class='error'>N/A</span>";
            }
            echo "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='8'>No offers found</td></tr>";
    }
    echo "</table>";
    
    echo "<h2>2. Verificar Directorio de Proveedores</h2>";
    $providers_dir = __DIR__ . "/../admin/img/providers/";
    echo "<p><strong>Base Directory:</strong> <code>{$providers_dir}</code></p>";
    
    if (is_dir($providers_dir)) {
        echo "<p class='success'>✅ Directory exists</p>";
        
        $providers = array_diff(scandir($providers_dir), array('.', '..'));
        echo "<p>Found " . count($providers) . " provider folders:</p>";
        echo "<ul>";
        foreach ($providers as $provider_folder) {
            $provider_path = $providers_dir . $provider_folder;
            if (is_dir($provider_path)) {
                $files = array_diff(scandir($provider_path), array('.', '..'));
                echo "<li><strong>{$provider_folder}</strong> - " . count($files) . " files: " . implode(', ', $files) . "</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ Directory does NOT exist!</p>";
        echo "<p>Attempting to create it...</p>";
        if (mkdir($providers_dir, 0755, true)) {
            echo "<p class='success'>✅ Directory created successfully</p>";
        } else {
            echo "<p class='error'>❌ Failed to create directory</p>";
        }
    }
    
    echo "<h2>3. Verificar Permisos</h2>";
    if (is_dir($providers_dir)) {
        $perms = fileperms($providers_dir);
        echo "<p>Permissions: " . decoct($perms & 0777) . "</p>";
        echo "<p>Readable: " . (is_readable($providers_dir) ? '✅ Yes' : '❌ No') . "</p>";
        echo "<p>Writable: " . (is_writable($providers_dir) ? '✅ Yes' : '❌ No') . "</p>";
    }
    
    echo "<h2>4. Test Paths</h2>";
    echo "<p><strong>Current directory (__DIR__):</strong> " . __DIR__ . "</p>";
    echo "<p><strong>Document root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
    ?>
    
    <hr>
    <p><a href="wizard.php">← Back to Wizard</a></p>
</body>
</html>
