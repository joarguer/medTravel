<?php
include __DIR__ . '/include/include.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - Calendar</title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <link href="/assets/global/plugins/fullcalendar/fullcalendar.min.css" rel="stylesheet" type="text/css" />
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
                <h1>Calendar</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li class="active">Calendar</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption">
                            <i class="icon-calendar font-blue"></i>
                            <span class="caption-subject font-blue bold uppercase">My Appointments</span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div id="client-calendar"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>
</div>

<div class="modal fade" id="client-calendar-detail-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                <h4 class="modal-title" id="client-calendar-detail-title">Event detail</h4>
            </div>
            <div class="modal-body">
                <p><strong>Status:</strong> <span id="client-calendar-detail-status"></span></p>
                <p><strong>Start:</strong> <span id="client-calendar-detail-start"></span></p>
                <p><strong>End:</strong> <span id="client-calendar-detail-end"></span></p>
                <p><strong>Type:</strong> <span id="client-calendar-detail-type"></span></p>
                <p id="client-calendar-detail-description-wrap"><strong>Description:</strong><br><span id="client-calendar-detail-description"></span></p>
                <hr>
                <p style="margin-bottom:0;">
                    <a href="#" id="client-calendar-open-request" target="_blank">Open Request</a> |
                    <a href="#" id="client-calendar-open-inbox" target="_blank">Open Inbox thread</a>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn green-jungle" id="client-calendar-accept-btn" style="display:none;">Accept</button>
                <a href="#" id="client-calendar-request-change-btn" class="btn blue" style="display:none;" target="_blank">Request change</a>
                <button type="button" class="btn default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php echo $theme_layout_script; ?>
<script src="/assets/global/plugins/fullcalendar/lib/moment.min.js" type="text/javascript"></script>
<script src="/assets/global/plugins/fullcalendar/fullcalendar.min.js" type="text/javascript"></script>
<script src="/client/js/notifications.js" type="text/javascript"></script>
<script type="text/javascript">
window.ClientCalendarConfig = {
    listUrl: '/client/ajax/calendar.php',
    acceptUrl: '/client/ajax/calendar.php',
    requestBase: '/client/request_detail.php',
    inboxBase: '/client/app_inbox.php'
};
</script>
<script src="/client/js/app_calendar.js" type="text/javascript"></script>
</body>
</html>
