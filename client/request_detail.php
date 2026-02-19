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
                                    <span class="caption-subject font-green bold uppercase">Messages / Communication</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <div id="client-request-messages" style="max-height:360px;overflow:auto;margin-bottom:15px;">
                                    <p>Loading messages...</p>
                                </div>
                                <form id="client-send-message-form">
                                    <input type="hidden" id="client-booking-id" value="<?php echo (int)$requestId; ?>">
                                    <div class="form-group">
                                        <label for="client-message-text">Send a message</label>
                                        <textarea class="form-control" id="client-message-text" rows="3" maxlength="2000" placeholder="Write your message to the MedTravel coordination team"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Send message</button>
                                </form>
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

