<?php
include __DIR__ . '/include/include.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - Dashboard</title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
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
            <div class="breadcrumbs">
                <h1>Client Dashboard</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li class="active">Dashboard</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="row">
                    <div class="col-md-6">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-calendar font-blue"></i>
                                    <span class="caption-subject font-blue bold uppercase">My Requests</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <h3 id="client-dashboard-total" style="margin-top:0;">0</h3>
                                <p style="margin-bottom:0;">Total requests linked to your account.</p>
                                <a href="/client/my_requests.php" class="btn btn-sm btn-primary" style="margin-top:15px;">View all</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-bell font-green"></i>
                                    <span class="caption-subject font-green bold uppercase">Notifications</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <h3 id="client-dashboard-notifications" style="margin-top:0;">0</h3>
                                <p style="margin-bottom:0;">Updates about your requests and provider responses.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-list font-purple"></i>
                                    <span class="caption-subject font-purple bold uppercase">Recent Requests</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-hover" id="client-dashboard-requests-table">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Service</th>
                                            <th>Status</th>
                                            <th>Last update</th>
                                            <th>Action</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>
</div>

<?php echo $theme_layout_script; ?>
<script src="/client/js/notifications.js" type="text/javascript"></script>
<script src="/client/js/dashboard.js" type="text/javascript"></script>
</body>
</html>

