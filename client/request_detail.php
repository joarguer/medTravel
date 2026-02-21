<?php
include __DIR__ . '/include/include.php';
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($requestId <= 0) {
    header('Location: /client/my_requests.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - Request Detail</title>
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
                <h1>Request Detail</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li><a href="/client/my_requests.php">My Requests</a></li>
                    <li class="active">Detail #<?php echo (int)$requestId; ?></li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-doc font-blue"></i>
                                    <span class="caption-subject font-blue bold uppercase">Request #<?php echo (int)$requestId; ?></span>
                                </div>
                            </div>
                            <div class="portlet-body" id="client-request-detail-body">
                                <p>Loading detail...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-speech font-green"></i>
                                    <span class="caption-subject font-green bold uppercase">Communication</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <p>Messages and updates are managed in your Inbox for privacy and clarity.</p>
                                <p>
                                    <a class="btn btn-primary" id="client-open-inbox-care" href="/client/app_inbox.php?request_id=<?php echo (int)$requestId; ?>&thread_type=CARE">
                                        <i class="icon-envelope-open"></i> Open Inbox
                                    </a>
                                </p>
                                <div id="client-inbox-item-links" style="margin-top:10px;">
                                    <a class="btn btn-default" id="client-open-inbox-medical" href="/client/app_inbox.php?request_id=<?php echo (int)$requestId; ?>&thread_type=CARE">
                                        <i class="icon-bubble"></i> Message Medical Provider
                                    </a>
                                    <a class="btn btn-default" id="client-open-inbox-complementary" href="/client/app_inbox.php?request_id=<?php echo (int)$requestId; ?>&thread_type=CARE" style="margin-left:8px;">
                                        <i class="icon-bubble"></i> Message Complementary Provider
                                    </a>
                                    <p class="text-muted" style="margin:8px 0 0 0;">Buttons route to the first available item by category.</p>
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
<script src="/client/js/request_detail.js" type="text/javascript"></script>
</body>
</html>
