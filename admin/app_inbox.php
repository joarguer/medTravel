<?php
include('include/include.php');

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$service_provider_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
$can_admin_view = is_role_admin_session();
if (!$can_admin_view && $provider_id <= 0 && $service_provider_id <= 0) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Inbox</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <link href="../../assets/apps/css/inbox.css" rel="stylesheet" type="text/css" />
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
                <h1>Inbox</h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Home</a></li>
                    <li class="active">Inbox</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="inbox">
                    <div class="row">
                        <div class="col-md-3 inbox-sidebar">
                            <button type="button" class="btn btn-sm btn-default compose-btn btn-block" id="admin-inbox-refresh">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <ul class="inbox-nav" id="admin-inbox-thread-list">
                                <li><a href="javascript:;">Loading threads...</a></li>
                            </ul>
                        </div>
                        <div class="col-md-9 inbox-body">
                            <div class="inbox-header">
                                <h1 id="admin-inbox-title">Select a thread</h1>
                            </div>
                            <div class="inbox-content" id="admin-inbox-content" style="display:none;">
                                <div id="admin-inbox-messages" style="max-height:420px;overflow:auto;border:1px solid #eef1f5;padding:12px;background:#fff;"></div>
                                <div id="admin-inbox-fee-alert" class="note note-warning" style="display:none;margin-top:12px;">
                                    <strong>Coordination Fee required.</strong>
                                    Use quick replies while the fee is pending.
                                </div>
                                <div id="admin-inbox-quick-replies" style="display:none;margin-top:12px;">
                                    <label>Quick replies</label>
                                    <div class="btn-group btn-group-xs" role="group" style="display:flex;flex-wrap:wrap;gap:6px;">
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="DATES_AVAILABLE">Dates available</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="DATES_NOT_AVAILABLE">Dates not available</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_MEDICAL_HISTORY">REQUEST HISTORY</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_LABS">REQUEST LABS</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_IMAGING">REQUEST IMAGING</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_PHOTOS">REQUEST PHOTOS</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="FINAL_APPROVED">FINAL APPROVED</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="FINAL_NOT_ELIGIBLE">NOT ELIGIBLE</button>
                                    </div>
                                </div>
                                <form id="admin-inbox-send-form" style="margin-top:12px;">
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <label for="admin-inbox-message">Write a message</label>
                                        <textarea class="form-control" id="admin-inbox-message" rows="3" maxlength="2000" placeholder="Write your message..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Send</button>
                                </form>
                            </div>
                            <div class="inbox-content" id="admin-inbox-empty">
                                <div class="note note-info" style="margin:0;">Select a thread from the left panel.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer;?>
    </div>
</div>

<?php echo $theme_layout_script;?>
<script src="js/app_inbox.js" type="text/javascript"></script>
</body>
</html>
