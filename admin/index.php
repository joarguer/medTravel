<?php
include("include/include.php");

// Helpers para métricas desde BD
function fetch_count($conexion, $sql, $types = '', $params = []) {
    if ($stmt = mysqli_prepare($conexion, $sql)) {
        if (!empty($types) && !empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $count);
            if (mysqli_stmt_fetch($stmt)) {
                mysqli_stmt_close($stmt);
                return (int)$count;
            }
        }
        error_log('fetch_count error: '.mysqli_error($conexion));
        mysqli_stmt_close($stmt);
    }
    return 0;
}

function month_labels() {
    $labels = [];
    $map = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    for ($i = 11; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i month");
        $key = date('Y-m', $ts);
        $labels[] = ['key' => $key, 'label' => $map[(int)date('n', $ts) - 1]];
    }
    return $labels;
}

function monthly_counts($conexion, $table, $where = '1', $types = '', $params = []) {
    $sql = "SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, COUNT(*) as total FROM $table WHERE $where GROUP BY ym ORDER BY ym";
    $data = [];
    if ($stmt = mysqli_prepare($conexion, $sql)) {
        if (!empty($types) && !empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $data[$row['ym']] = (int)$row['total'];
            }
        } else {
            error_log('monthly_counts exec error: '.mysqli_error($conexion));
        }
        mysqli_stmt_close($stmt);
    }
    return $data;
}

// Datos base
$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$series_data = [];
$pie_data = [];
$metric_cards = [];
$provider_onboarding_steps = [];
$provider_operation_links = [];

if ($es_admin) {
    $metric_cards = [
        ['label' => 'Prestadores activos', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM providers WHERE is_active = 1"), 'icon' => 'icon-heart', 'class' => 'font-green-sharp'],
        ['label' => 'Servicios publicados', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM medtravel_services_catalog WHERE is_active = 1"), 'icon' => 'icon-grid', 'class' => 'font-red-haze'],
        ['label' => 'Bookings pendientes', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM booking_requests WHERE status = 'pending'"), 'icon' => 'icon-calendar', 'class' => 'font-blue-sharp'],
        ['label' => 'Proveedores complementarios', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM service_providers WHERE is_active = 1"), 'icon' => 'icon-plane', 'class' => 'font-purple-soft'],
    ];
    $chart1_title = 'Servicios y ofertas';
    $chart1_subtitle = 'últimos 12 meses';
    $chart2_title = 'Mix de catálogo';
    $chart2_subtitle = 'participación por tipo';

    $services_month = monthly_counts($conexion, 'medtravel_services_catalog', '1');
    $offers_month = monthly_counts($conexion, 'provider_service_offers', '1');
} else {
    $metric_cards = [
        ['label' => 'Mis servicios publicados', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM medtravel_services_catalog WHERE is_active = 1 AND provider_id = ?", 'i', [$provider_id]), 'icon' => 'icon-grid', 'class' => 'font-green-sharp'],
        ['label' => 'Ofertas activas', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM provider_service_offers WHERE is_active = 1 AND provider_id = ?", 'i', [$provider_id]), 'icon' => 'icon-tag', 'class' => 'font-red-haze'],
        ['label' => 'Bookings pendientes', 'value' => 0, 'icon' => 'icon-calendar', 'class' => 'font-blue-sharp'],
        ['label' => 'Solicitudes totales', 'value' => 0, 'icon' => 'icon-users', 'class' => 'font-purple-soft'],
    ];
    $chart1_title = 'Mis servicios y ofertas';
    $chart1_subtitle = 'últimos 12 meses';
    $chart2_title = 'Mix de mi catálogo';
    $chart2_subtitle = 'participación por tipo';

    $services_month = monthly_counts($conexion, 'medtravel_services_catalog', 'provider_id = ?', 'i', [$provider_id]);
    $offers_month = monthly_counts($conexion, 'provider_service_offers', 'provider_id = ?', 'i', [$provider_id]);

    // Calcular bookings asociados a las ofertas del proveedor (búsqueda en JSON selected_offers)
    $offer_ids = [];
    $offer_res = mysqli_query($conexion, "SELECT id FROM provider_service_offers WHERE provider_id = " . $provider_id);
    if ($offer_res) {
        while ($row = mysqli_fetch_assoc($offer_res)) {
            $offer_ids[] = (int)$row['id'];
        }
    }
    if (!empty($offer_ids)) {
        $like_parts = [];
        foreach ($offer_ids as $oid) {
            $oid = (int)$oid;
            $like_parts[] = "selected_offers LIKE '%\"$oid\"%'";
        }
        $like_sql = implode(' OR ', $like_parts);
        $metric_cards[2]['value'] = fetch_count($conexion, "SELECT COUNT(*) FROM booking_requests WHERE status = 'pending' AND ($like_sql)");
        $metric_cards[3]['value'] = fetch_count($conexion, "SELECT COUNT(*) FROM booking_requests WHERE ($like_sql)");
    }

    if (!empty($can_view_my_bookings)) {
        $provider_operation_links[] = ['label' => 'Mis Solicitudes', 'href' => 'my_booking_requests.php'];
        $provider_operation_links[] = ['label' => 'Inbox', 'href' => 'app_inbox.php'];
        $provider_operation_links[] = ['label' => 'Agenda', 'href' => 'app_calendar.php'];
    }

    $provider_onboarding_steps = [
        [
            'eyebrow' => 'Paso 0',
            'title' => 'Completa toda la información de tu empresa',
            'summary' => 'Empieza por Mi Empresa. Este es el paso más importante para presentarte bien dentro de MedTravel.',
            'items' => [
                'Completa la información institucional lo más completa posible.',
                'Incluye nombre, ciudad, teléfono, email, descripción y logo de tu empresa.',
                'Esta información se usa en la presentación comercial y pública del provider dentro de MedTravel, por lo que impacta confianza y visibilidad.',
            ],
            'links' => [
                ['label' => 'Ir a Mi Empresa', 'href' => 'mi_empresa.php'],
            ],
        ],
        [
            'eyebrow' => 'Paso 1',
            'title' => 'Revisa o configura tus servicios',
            'summary' => 'Mis Servicios representa los servicios habilitados reales de tu empresa dentro del sistema.',
            'items' => [
                'Aquí defines la base operativa de lo que tu provider realmente puede atender.',
                'Estos servicios serán la base para crear ofertas y para asignar capacidad al staff médico.',
            ],
            'links' => [
                ['label' => 'Ir a Mis Servicios', 'href' => 'service_catalog.php'],
            ],
        ],
        [
            'eyebrow' => 'Paso 2',
            'title' => 'Crea tus ofertas',
            'summary' => 'Mis Ofertas es la capa comercial que publicas al paciente.',
            'items' => [
                'Un servicio habilitado no es lo mismo que una oferta publicada.',
                'La oferta toma como base un servicio de tu catálogo y le agrega enfoque comercial, precio y contenido visible.',
            ],
            'links' => [
                ['label' => 'Ir a Mis Ofertas', 'href' => 'provider_offers.php'],
            ],
        ],
        [
            'eyebrow' => 'Paso 3',
            'title' => 'Crea tu staff médico',
            'summary' => 'El staff se registra aparte del provider para mantener clara la estructura operativa.',
            'items' => [
                'Registra cada profesional desde el módulo de Staff médico.',
                'Luego podrás definir qué servicios puede atender cada integrante y, si aplica, su acceso al panel.',
            ],
            'links' => [
                ['label' => 'Ir a Staff médico', 'href' => 'staff_medico.php'],
            ],
        ],
        [
            'eyebrow' => 'Paso 4',
            'title' => 'Asigna servicios al staff',
            'summary' => 'Cada miembro del staff debe quedar asociado a los servicios que realmente puede atender.',
            'items' => [
                'Esta asignación se hace dentro del formulario de Staff médico.',
                'En cada perfil encontrarás el bloque “Servicios que puede atender” para relacionarlo con tus servicios habilitados.',
            ],
            'links' => [
                ['label' => 'Gestionar staff y asignaciones', 'href' => 'staff_medico.php'],
                ['label' => 'Ver Catálogos del staff', 'href' => 'staff_catalogs.php'],
            ],
        ],
        [
            'eyebrow' => 'Paso 5',
            'title' => 'Revisa tu operación',
            'summary' => 'Cuando ya tengas empresa, servicios, ofertas y staff, revisa tu operación diaria desde los módulos de seguimiento.',
            'items' => [
                'Consulta solicitudes, bookings pendientes y seguimiento operativo según los módulos habilitados en tu cuenta.',
                'Usa esta revisión para mantener trazabilidad y responder a tiempo desde el panel.',
            ],
            'links' => $provider_operation_links,
        ],
    ];
}

// Preparar series combinadas
$series_data = [];
$labels = month_labels();
foreach ($labels as $lb) {
    $key = $lb['key'];
    $series_data[] = [
        'month' => $lb['label'],
        'servicios' => isset($services_month[$key]) ? $services_month[$key] : 0,
        'ofertas' => isset($offers_month[$key]) ? $offers_month[$key] : 0,
    ];
}

// Pie actual
if ($es_admin) {
    $pie_data = [
        ['segment' => 'Servicios médicos', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM medtravel_services_catalog WHERE is_active = 1")],
        ['segment' => 'Proveedores complementarios', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM service_providers WHERE is_active = 1")],
        ['segment' => 'Bookings pendientes', 'value' => fetch_count($conexion, "SELECT COUNT(*) FROM booking_requests WHERE status = 'pending'")],
    ];
} else {
    $pie_data = [
        ['segment' => 'Mis servicios', 'value' => $metric_cards[0]['value']],
        ['segment' => 'Mis ofertas', 'value' => $metric_cards[1]['value']],
        ['segment' => 'Mis bookings pendientes', 'value' => $metric_cards[2]['value']],
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
    <!-- BEGIN HEAD -->

    <head>
        <meta charset="utf-8" />
        <title>GRO | Panel</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <meta content="" name="description" />
        <meta content="" name="author" />
        <?php echo $global_first_style;?>
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <link href="../../assets/global/plugins/bootstrap-daterangepicker/daterangepicker.min.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/morris/morris.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/fullcalendar/fullcalendar.min.css" rel="stylesheet" type="text/css" />
        <!-- END PAGE LEVEL PLUGINS -->
        <?php echo $theme_global_style;?>
        <?php echo $theme_layout_style;?>
        <style>
            .provider-onboarding-panel {
                border: 1px solid #dfe6ee;
                margin-bottom: 25px;
            }
            .provider-onboarding-toggle {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                color: #2c3e50;
                text-decoration: none;
            }
            .provider-onboarding-toggle:hover,
            .provider-onboarding-toggle:focus {
                text-decoration: none;
                color: #1f2d3d;
            }
            .provider-onboarding-toggle .caption-subject {
                display: block;
            }
            .provider-onboarding-toggle .caption-helper {
                display: block;
                margin-top: 4px;
            }
            .provider-onboarding-badge {
                background: #eaf4fb;
                color: #2f6f9f;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                padding: 6px 10px;
                white-space: nowrap;
            }
            .provider-onboarding-intro {
                margin-bottom: 18px;
                color: #5f6b7a;
                max-width: 920px;
            }
            .provider-onboarding-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
            }
            .provider-onboarding-step {
                background: #fcfdff;
                border: 1px solid #e7ecf1;
                border-radius: 6px;
                padding: 16px;
                min-height: 100%;
            }
            .provider-onboarding-step-label {
                color: #6c7a89;
                display: inline-block;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .08em;
                margin-bottom: 10px;
                text-transform: uppercase;
            }
            .provider-onboarding-step h4 {
                margin: 0 0 8px;
                font-size: 18px;
            }
            .provider-onboarding-step p {
                color: #5f6b7a;
                margin-bottom: 10px;
            }
            .provider-onboarding-step ul {
                margin: 0 0 14px 18px;
                padding: 0;
            }
            .provider-onboarding-step li {
                color: #5f6b7a;
                margin-bottom: 6px;
            }
            .provider-onboarding-links {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            @media (max-width: 767px) {
                .provider-onboarding-toggle {
                    align-items: flex-start;
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <!-- END HEAD -->

    <body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
        <!-- BEGIN CONTAINER -->
        <div class="wrapper">
            <!-- BEGIN HEADER -->
            <header class="page-header">
                <nav class="navbar mega-menu" role="navigation">
                    <div class="container-fluid">
                        <?php echo $top_header;?>
                        <!-- BEGIN HEADER MENU -->
                        <?php echo $top_header_2;?>
                        <!-- END HEADER MENU -->
                    </div>
                    <!--/container-->
                </nav>
            </header>
            <!-- END HEADER -->
            <div class="container-fluid">
                <div class="page-content">
                    <!-- BEGIN BREADCRUMBS -->
                    <div class="breadcrumbs">
                        <h1>Panel Administrativo</h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="#">Home</a>
                            </li>
                            <li class="active">Panel Administrativo</li>
                        </ol>
                    </div>
                    <!-- END BREADCRUMBS -->
                    <!-- BEGIN PAGE BASE CONTENT -->
                    <?php if (!$es_admin): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="portlet light provider-onboarding-panel">
                                <div class="portlet-title" style="border-bottom:0; margin-bottom:0;">
                                    <a href="#provider-dashboard-onboarding-collapse" class="provider-onboarding-toggle" data-toggle="collapse" aria-expanded="false" aria-controls="provider-dashboard-onboarding-collapse">
                                        <span>
                                            <span class="caption-subject font-dark bold uppercase">Guía rápida para arrancar</span>
                                            <span class="caption-helper">Abre esta ayuda si es tu primera vez configurando tu cuenta como provider médico.</span>
                                        </span>
                                        <span>
                                            <span class="provider-onboarding-badge" id="provider-onboarding-toggle-label">Ver pasos</span>
                                            <i class="fa fa-chevron-down" id="provider-onboarding-toggle-icon" style="margin-left:10px;"></i>
                                        </span>
                                    </a>
                                </div>
                                <div id="provider-dashboard-onboarding-collapse" class="collapse">
                                    <div class="portlet-body" style="padding-top:0;">
                                        <p class="provider-onboarding-intro">
                                            Sigue este orden para dejar tu cuenta lista desde el inicio. La idea es que primero completes tu presentación institucional, luego configures tu capacidad médica y después pases a staff, ofertas y operación.
                                        </p>
                                        <div class="provider-onboarding-grid">
                                            <?php foreach ($provider_onboarding_steps as $step): ?>
                                            <div class="provider-onboarding-step">
                                                <span class="provider-onboarding-step-label"><?php echo htmlspecialchars($step['eyebrow'], ENT_QUOTES); ?></span>
                                                <h4><?php echo htmlspecialchars($step['title'], ENT_QUOTES); ?></h4>
                                                <p><?php echo htmlspecialchars($step['summary'], ENT_QUOTES); ?></p>
                                                <ul>
                                                    <?php foreach ($step['items'] as $item): ?>
                                                    <li><?php echo htmlspecialchars($item, ENT_QUOTES); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <?php if (!empty($step['links'])): ?>
                                                <div class="provider-onboarding-links">
                                                    <?php foreach ($step['links'] as $link): ?>
                                                    <a class="btn btn-xs btn-outline blue" href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($link['label'], ENT_QUOTES); ?></a>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <?php foreach ($metric_cards as $card): ?>
                        <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                            <div class="dashboard-stat2 bordered">
                                <div class="display">
                                    <div class="number">
                                        <h3 class="<?php echo $card['class']; ?>">
                                            <span data-counter="counterup" data-value="<?php echo $card['value']; ?>">0</span>
                                        </h3>
                                        <small><?php echo $card['label']; ?></small>
                                    </div>
                                    <div class="icon">
                                        <i class="<?php echo $card['icon']; ?>"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 col-sm-6">
                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <span class="caption-subject bold uppercase font-dark"><?php echo $chart1_title; ?></span>
                                        <span class="caption-helper"><?php echo $chart1_subtitle; ?></span>
                                    </div>
                                    <div class="actions">
                                        <a class="btn btn-circle btn-icon-only btn-default" href="#">
                                            <i class="icon-cloud-upload"></i>
                                        </a>
                                        <a class="btn btn-circle btn-icon-only btn-default" href="#">
                                            <i class="icon-wrench"></i>
                                        </a>
                                        <a class="btn btn-circle btn-icon-only btn-default" href="#">
                                            <i class="icon-trash"></i>
                                        </a>
                                        <a class="btn btn-circle btn-icon-only btn-default fullscreen" href="#"> </a>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div id="dashboard_amchart_1" class="CSSAnimationChart"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption ">
                                        <span class="caption-subject font-dark bold uppercase"><?php echo $chart2_title; ?></span>
                                        <span class="caption-helper"><?php echo $chart2_subtitle; ?></span>
                                    </div>
                                    <div class="actions">
                                        <a href="#" class="btn btn-circle green btn-outline btn-sm">
                                            <i class="fa fa-pencil"></i> Export </a>
                                        <a href="#" class="btn btn-circle green btn-outline btn-sm">
                                            <i class="fa fa-print"></i> Print </a>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div id="dashboard_amchart_3" class="CSSAnimationChart"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END PAGE BASE CONTENT -->
                </div>
                <!-- BEGIN FOOTER -->
                <?php echo $footer;?>
                <!-- END FOOTER -->
            </div>
        </div>
        <!-- END CONTAINER -->
        <!-- BEGIN QUICK SIDEBAR -->
        <?php echo $sider_bar;?>
        <!-- END QUICK SIDEBAR -->
        <!--[if lt IE 9]>
<script src="../../assets/global/plugins/respond.min.js"></script>
<script src="../../assets/global/plugins/excanvas.min.js"></script> 
<![endif]-->
        <!-- BEGIN CORE PLUGINS -->
        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
        <!-- THEME (loads jQuery) -->
        <?php echo $theme_layout_script;?>
        <!-- CORE PLUGINS (after jQuery) -->
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <!-- PAGE LEVEL PLUGINS -->
        <script src="../../assets/global/plugins/moment.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-daterangepicker/daterangepicker.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/morris/morris.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/morris/raphael-min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/counterup/jquery.waypoints.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/counterup/jquery.counterup.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amcharts/amcharts.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amcharts/serial.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amcharts/pie.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amcharts/radar.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amcharts/themes/light.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amcharts/themes/patterns.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amcharts/themes/chalk.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/ammap/ammap.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/ammap/maps/js/worldLow.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/amcharts/amstockcharts/amstock.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/fullcalendar/fullcalendar.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/horizontal-timeline/horozontal-timeline.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/flot/jquery.flot.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/flot/jquery.flot.resize.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/flot/jquery.flot.categories.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-easypiechart/jquery.easypiechart.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.sparkline.min.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL PLUGINS -->
        <script type="text/javascript">
        jQuery(function() {
            var seriesData = <?php echo json_encode($series_data); ?>;
            var pieData = <?php echo json_encode($pie_data); ?>;
            var $onboardingCollapse = $('#provider-dashboard-onboarding-collapse');

            function syncOnboardingToggle(isOpen) {
                $('#provider-onboarding-toggle-label').text(isOpen ? 'Ocultar pasos' : 'Ver pasos');
                $('#provider-onboarding-toggle-icon')
                    .toggleClass('fa-chevron-down', !isOpen)
                    .toggleClass('fa-chevron-up', isOpen);
            }

            if ($onboardingCollapse.length) {
                syncOnboardingToggle($onboardingCollapse.hasClass('in'));
                $onboardingCollapse.on('show.bs.collapse', function() {
                    syncOnboardingToggle(true);
                });
                $onboardingCollapse.on('hide.bs.collapse', function() {
                    syncOnboardingToggle(false);
                });
            }

            AmCharts.makeChart('dashboard_amchart_1', {
                type: 'serial',
                theme: 'light',
                dataProvider: seriesData,
                categoryField: 'month',
                startDuration: 0.4,
                graphs: [
                    {
                        balloonText: 'Servicios [[category]]: [[value]]',
                        fillAlphas: 0.7,
                        lineAlpha: 0.2,
                        title: 'Servicios',
                        type: 'column',
                        valueField: 'servicios',
                        lineColor: '#36c6d3'
                    },
                    {
                        balloonText: 'Ofertas [[category]]: [[value]]',
                        bullet: 'round',
                        lineThickness: 2,
                        title: 'Ofertas',
                        valueField: 'ofertas',
                        lineColor: '#8E44AD'
                    }
                ],
                chartCursor: {
                    categoryBalloonEnabled: true,
                    cursorAlpha: 0.1,
                    zoomable: false
                },
                categoryAxis: {
                    gridPosition: 'start',
                    axisAlpha: 0
                },
                legend: {
                    useGraphSettings: true
                }
            });

            AmCharts.makeChart('dashboard_amchart_3', {
                type: 'pie',
                theme: 'light',
                dataProvider: pieData,
                titleField: 'segment',
                valueField: 'value',
                innerRadius: '50%',
                balloonText: '[[title]]: [[value]]',
                colors: ['#36c6d3', '#E7505A', '#4B77BE']
            });
        });
        </script>
    </body>

</html>
