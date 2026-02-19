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

$can_create = $can_admin_view || $provider_id > 0 || $service_provider_id > 0;
$can_update = $can_create;
$can_delete = $can_admin_view;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Calendar</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <link href="/assets/global/plugins/fullcalendar/fullcalendar.min.css" rel="stylesheet" type="text/css" />
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
                <h1>Calendar</h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Home</a></li>
                    <li class="active">Calendar</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption">
                            <i class="icon-calendar font-blue"></i>
                            <span class="caption-subject font-blue bold uppercase">Booking Calendar</span>
                        </div>
                        <div class="actions">
                            <select id="admin-calendar-filter" class="form-control input-sm" style="min-width:180px;">
                                <?php if ($can_admin_view): ?>
                                <option value="ALL">All events</option>
                                <option value="CARE">CARE threads</option>
                                <?php endif; ?>
                                <option value="ITEM" selected>ITEM threads</option>
                            </select>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div id="admin-calendar-provider-guide" class="alert alert-info" style="display:none; margin-bottom:15px;">
                            Select an ITEM thread, then click a date/time to propose a schedule.
                        </div>
                        <div id="admin-calendar-item-selector-wrap" style="display:none; margin-bottom:15px;">
                            <label for="admin-calendar-item-select" style="font-weight:600; margin-right:8px;">ITEM thread</label>
                            <select id="admin-calendar-item-select" class="form-control input-sm" style="display:inline-block; min-width:260px; max-width:420px;">
                                <option value="">Select an ITEM...</option>
                            </select>
                        </div>
                        <div id="admin-calendar-empty-state" class="alert alert-warning" style="display:none; margin-bottom:15px;"></div>
                        <div id="admin-calendar"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer;?>
    </div>
</div>

<div class="modal fade" id="admin-calendar-create-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="admin-calendar-create-form">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                    <h4 class="modal-title">Create event</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" name="title" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label>Event Type</label>
                        <select class="form-control" name="event_type" id="admin-calendar-create-type">
                            <?php if ($can_admin_view): ?>
                            <option value="CARE">CARE</option>
                            <?php endif; ?>
                            <option value="ITEM" selected>ITEM</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Request ID</label>
                        <input type="number" min="1" class="form-control" name="request_id">
                    </div>
                    <div class="form-group">
                        <label>Item ID (required for ITEM)</label>
                        <input type="number" min="1" class="form-control" name="item_id">
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Start</label>
                                <input type="datetime-local" class="form-control" name="start_at" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>End</label>
                                <input type="datetime-local" class="form-control" name="end_at">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="scheduled">scheduled</option>
                            <option value="proposed">proposed</option>
                            <option value="confirmed">confirmed</option>
                            <option value="cancelled">cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="all_day" value="1"> All day</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn blue">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="admin-calendar-detail-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="admin-calendar-detail-form">
                <input type="hidden" name="id" id="admin-calendar-detail-id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                    <h4 class="modal-title">Event detail</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" name="title" id="admin-calendar-detail-title" required maxlength="255">
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Start</label>
                                <input type="datetime-local" class="form-control" name="start_at" id="admin-calendar-detail-start" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>End</label>
                                <input type="datetime-local" class="form-control" name="end_at" id="admin-calendar-detail-end">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Type</label>
                                <input type="text" class="form-control" id="admin-calendar-detail-type" readonly>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Request ID</label>
                                <input type="number" class="form-control" name="request_id" id="admin-calendar-detail-request">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Item ID</label>
                                <input type="number" class="form-control" name="item_id" id="admin-calendar-detail-item">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status" id="admin-calendar-detail-status">
                            <option value="scheduled">scheduled</option>
                            <option value="proposed">proposed</option>
                            <option value="confirmed">confirmed</option>
                            <option value="cancelled">cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" id="admin-calendar-detail-description" rows="3"></textarea>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="all_day" id="admin-calendar-detail-allday" value="1"> All day</label>
                    </div>
                    <hr>
                    <p style="margin-bottom:0;">
                        <a href="#" id="admin-calendar-open-request" target="_blank">Open Request</a> |
                        <a href="#" id="admin-calendar-open-inbox" target="_blank">Open Inbox thread</a>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn red" id="admin-calendar-delete-btn" <?php echo $can_delete ? '' : 'style="display:none;"'; ?>>Delete</button>
                    <button type="submit" class="btn blue" <?php echo $can_update ? '' : 'style="display:none;"'; ?>>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo $theme_layout_script;?>
<script src="/assets/global/plugins/fullcalendar/lib/moment.min.js" type="text/javascript"></script>
<script src="/assets/global/plugins/fullcalendar/fullcalendar.min.js" type="text/javascript"></script>
<script type="text/javascript">
window.AdminCalendarConfig = {
    canAdmin: <?php echo $can_admin_view ? 'true' : 'false'; ?>,
    isProvider: <?php echo (!$can_admin_view && ($provider_id > 0 || $service_provider_id > 0)) ? 'true' : 'false'; ?>,
    canCreate: <?php echo $can_create ? 'true' : 'false'; ?>,
    canUpdate: <?php echo $can_update ? 'true' : 'false'; ?>,
    canDelete: <?php echo $can_delete ? 'true' : 'false'; ?>,
    listUrl: 'ajax/calendar.php',
    inboxBase: 'app_inbox.php',
    requestBase: '<?php echo $can_admin_view ? 'booking_requests.php' : 'my_booking_requests.php'; ?>'
};
</script>
<script src="js/app_calendar.js" type="text/javascript"></script>
</body>
</html>
