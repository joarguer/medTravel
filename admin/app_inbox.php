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
    <style type="text/css">
        #admin-inbox-thread-list {
            margin-top: 10px;
        }
        #admin-inbox-thread-list .mt-thread-item {
            margin: 0;
            border-bottom: 1px solid #eef1f5;
            background: #fff;
        }
        #admin-inbox-thread-list .mt-thread-link {
            display: block;
            padding: 10px 12px;
            text-decoration: none;
            color: inherit;
            border-left: 3px solid transparent;
        }
        #admin-inbox-thread-list .mt-thread-item:hover .mt-thread-link {
            background: #f7f9fb;
        }
        #admin-inbox-thread-list .mt-thread-item.active .mt-thread-link {
            background: #eef4ff;
            border-left-color: #5b9bd1;
        }
        #admin-inbox-thread-list .mt-thread-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        #admin-inbox-thread-list .mt-thread-main {
            min-width: 0;
            flex: 1 1 auto;
        }
        #admin-inbox-thread-list .mt-thread-title {
            font-weight: 600;
            color: #2f353b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #admin-inbox-thread-list .mt-thread-item.unread .mt-thread-title {
            font-weight: 700;
        }
        #admin-inbox-thread-list .mt-thread-sub {
            margin-top: 3px;
            color: #7f8c9d;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #admin-inbox-thread-list .mt-thread-preview {
            margin-top: 2px;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #admin-inbox-thread-list .mt-dot {
            margin: 0 4px;
        }
        #admin-inbox-thread-list .mt-thread-meta {
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            flex-direction: column;
            gap: 4px;
            flex: 0 0 auto;
            text-align: right;
        }
        #admin-inbox-thread-list .mt-badge,
        #admin-inbox-thread-list .mt-unread {
            font-size: 10px;
            padding: 3px 6px;
            line-height: 1.2;
        }
        #admin-inbox-thread-list .mt-time {
            font-size: 11px;
            color: #95a5a6;
            white-space: nowrap;
        }
        @media (max-width: 767px) {
            #admin-inbox-thread-list .mt-thread-row {
                flex-direction: column;
                gap: 6px;
            }
            #admin-inbox-thread-list .mt-thread-meta {
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                text-align: left;
                flex-wrap: wrap;
            }
        }
    </style>
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
                                    <div id="admin-inbox-structured-actions" style="display:none;margin-top:10px;">
                                        <label>Structured proposals</label>
                                        <div class="btn-group btn-group-xs" role="group" style="display:flex;flex-wrap:wrap;gap:6px;">
                                            <button type="button" class="btn btn-default btn-xs" id="admin-open-request-info">Request additional info</button>
                                            <button type="button" class="btn btn-default btn-xs" id="admin-open-propose-quote">Propose quote adjustment</button>
                                        </div>
                                    </div>
                                </div>
                                <form id="admin-inbox-send-form" style="margin-top:12px;">
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <label for="admin-inbox-message">Write a message</label>
                                        <textarea class="form-control" id="admin-inbox-message" rows="3" maxlength="2000" placeholder="Write your message..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Send</button>
                                    <div id="admin-inbox-compose-note" class="text-muted" style="margin-top:8px;display:none;">Messaging will be available after the initial review. Please use the options above.</div>
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

<div class="modal fade" id="adminRequestInfoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Request additional info</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Required documents</label>
                    <div class="checkbox-list" id="admin-request-info-types">
                        <label><input type="checkbox" value="labs"> Labs</label>
                        <label><input type="checkbox" value="imaging"> Imaging</label>
                        <label><input type="checkbox" value="photos"> Photos</label>
                        <label><input type="checkbox" value="medical_history"> Medical history</label>
                        <label><input type="checkbox" value="other"> Other</label>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="admin-request-info-note">Short note</label>
                    <textarea class="form-control" id="admin-request-info-note" rows="3" maxlength="500" placeholder="What do you need from the client?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="admin-submit-request-info">Send request</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminProposeQuoteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Propose quote adjustment</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="admin-propose-amount">Amount</label>
                            <input type="number" class="form-control" id="admin-propose-amount" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="admin-propose-currency">Currency</label>
                            <input type="text" class="form-control" id="admin-propose-currency" maxlength="10" value="USD">
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="admin-propose-notes">Justification / notes</label>
                    <textarea class="form-control" id="admin-propose-notes" rows="3" maxlength="500" placeholder="Explain why this adjustment is needed"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="admin-submit-propose-quote">Send proposal</button>
            </div>
        </div>
    </div>
</div>

<?php echo $theme_layout_script;?>
<script src="js/app_inbox.js" type="text/javascript"></script>
</body>
</html>
