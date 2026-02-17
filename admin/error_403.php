<?php
if (!defined('RENDERING_FORBIDDEN_PAGE')) {
    define('RENDERING_FORBIDDEN_PAGE', true);
}
http_response_code(403);

if (!isset($title) || $title === '') {
    $title = 'MedTravel';
}
if (!isset($top_header)) {
    $top_header = '';
}
if (!isset($top_header_2)) {
    $top_header_2 = '';
}
if (!isset($footer)) {
    $footer = '';
}
if (!isset($sider_bar)) {
    $sider_bar = '';
}
if (!isset($global_first_style)) {
    $global_first_style = '';
}
if (!isset($theme_global_style)) {
    $theme_global_style = '';
}
if (!isset($theme_layout_style)) {
    $theme_layout_style = '';
}
if (!isset($theme_layout_script)) {
    $theme_layout_script = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> - 403</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <link href="../../assets/pages/css/error.min.css" rel="stylesheet" type="text/css" />
    <?php echo $theme_layout_style; ?>
    <style>
        .page-404-3 {
            position: relative;
            overflow: hidden;
            border-radius: 4px;
            min-height: 420px;
            margin: 20px 0;
        }
        .page-404-3 .page-inner {
            position: relative;
            min-height: 420px;
        }
        .page-404-3 .page-inner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.35;
        }
    </style>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
    <div class="wrapper">
        <header class="page-header">
            <nav class="navbar mega-menu" role="navigation">
                <div class="container-fluid">
                    <?php echo $top_header; ?>
                    <?php echo $top_header_2; ?>
                </div>
            </nav>
        </header>

        <div class="container-fluid">
            <div class="page-content">
                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-content-col">
                            <div class="page-404-3">
                                <div class="page-inner">
                                    <img src="../../assets/pages/media/pages/earth.jpg" class="img-responsive" alt="">
                                    <div class="error-404">
                                        <h1>403</h1>
                                        <h2>Acceso Denegado</h2>
                                        <p>No tienes permisos para acceder a este módulo.</p>
                                        <p>Si crees que esto es un error, contacta al administrador.</p>
                                        <p>
                                            <a href="index.php" class="btn green">Ir al Dashboard</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $footer; ?>
            </div>
        </div>
        <?php echo $sider_bar; ?>
    </div>

    <?php echo $theme_layout_script; ?>
</body>
</html>
