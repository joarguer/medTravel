<?php
include("include/include.php");
if (!user_can(PERM_REPORTS_VIEW)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> - Módulo Legacy</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
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
                    <h1>Módulo legacy en transición
                        <small>Informes ya no forma parte de la operación activa del panel</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="#">Administración</a></li>
                        <li class="active">Legacy / transición</li>
                    </ol>
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".page-sidebar">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="toggle-icon">
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                        </span>
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light">
                            <div class="portlet-title">
                                <div class="caption">
                                    <span class="caption-subject font-dark bold">Informes retirado de navegación</span>
                                    <span class="caption-helper" style="display:block; margin-top:4px; color:#7b8a97; font-size:13px; font-weight:400;">Esta ruta se conserva temporalmente solo por compatibilidad y transición controlada.</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <div class="alert alert-warning">
                                    <strong>Estado del módulo:</strong> esta pantalla ya no forma parte del flujo operativo activo de MedTravel y fue retirada de la navegación visible del panel.
                                </div>
                                <div class="alert alert-info">
                                    <strong>Motivo:</strong> el recurso se mantiene temporalmente para evitar cortes bruscos durante el retiro controlado del módulo legacy.
                                    <br>
                                    <span class="small">No debe utilizarse como consola activa de reportes, edición o gestión operativa.</span>
                                </div>
                                <div class="alert alert-danger">
                                    <strong>Compatibilidad transitoria:</strong> el acceso directo por URL sigue disponible solo mientras se completa la retirada segura del módulo y su limpieza posterior.
                                </div>

                                <div class="row" style="margin-top:20px;">
                                    <div class="col-md-8">
                                        <h4 style="margin-top:0;">Qué significa esta pantalla</h4>
                                        <p class="text-muted" style="max-width:880px;">
                                            Informes no se rescatará como módulo activo en esta fase. Si necesitas operar cuentas, contenidos, prestadores, servicios, paquetes o flujos de booking, debes hacerlo desde sus módulos vigentes dentro del panel.
                                        </p>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="well" style="margin-bottom:0;">
                                            <strong>Siguiente referencia operativa</strong>
                                            <p class="small" style="margin:10px 0 0;">
                                                Usa los módulos activos del menú para operar dominios vigentes. Esta página no procesa reportes ni acciones funcionales.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo $footer;?>
        </div>
        <?php echo $sider_bar;?>
    </div>

    <?php echo $theme_layout_script;?>
</body>
</html>
