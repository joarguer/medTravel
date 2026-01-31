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
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <!-- END CORE PLUGINS -->
        <!-- BEGIN PAGE LEVEL PLUGINS -->
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
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <!-- app.min.js and theme scripts loaded from include.php -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <script type="text/javascript">
        jQuery(function() {
            var seriesData = <?php echo json_encode($series_data); ?>;
            var pieData = <?php echo json_encode($pie_data); ?>;

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