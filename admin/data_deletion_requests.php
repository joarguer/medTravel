<?php
include("include/include.php");
// RBAC explícito para solicitudes sensibles de datos.
if (!user_can(PERM_SETTINGS_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
$requests = [];
$log = __DIR__ . "/logs/data_deletion.log";
if (file_exists($log)) {
    $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_reverse($lines) as $line) {
        $entry = json_decode($line, true);
        if ($entry) $requests[] = $entry;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Data Deletion Requests</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <?php echo $theme_layout_script;?>
    <?php echo $theme_global_js;?>
    <?php echo $theme_layout_js;?>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
    <div class="wrapper">
        <header class="page-header">
            <nav class="navbar mega-menu" role="navigation">
                <div class="container-fluid">
                    <?php echo $top_header;?>
                    <?php echo $top_header_2;?>
                </div>
            </nav>
        </header>
        <div class="container-fluid">
            <div class="page-content">
                <div class="breadcrumbs">
                    <h1>Data Deletion Requests</h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Dashboard</a></li>
                        <li class="active">Data Deletion</li>
                    </ol>
                </div>
                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-content-col">
                            <div class="portlet light">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-trash theme-font"></i>
                                        <span class="caption-subject font-dark bold uppercase">Requests</span>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <?php if (empty($requests)): ?>
                                        <div class="alert alert-info">No deletion requests logged.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Request ID</th>
                                                        <th>Timestamp</th>
                                                        <th>Phone</th>
                                                        <th>Email</th>
                                                        <th>Name</th>
                                                        <th>Message</th>
                                                        <th>IP</th>
                                                        <th>User Agent</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($requests as $r): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($r['request_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($r['timestamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($r['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($r['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($r['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($r['ip'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td style="max-width:240px;"><?php echo htmlspecialchars($r['user_agent'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo $footer;?>
        </div>
    </div>
    <?php echo $sider_bar;?>
</body>
</html>
