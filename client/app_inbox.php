<?php
include __DIR__ . '/include/include.php';
require_once __DIR__ . '/../inc/fee_gate.php';
require_once __DIR__ . '/../inc/commission_gate.php';

$clientFeeGateActive = false;
$clientCommissionGateActive = false;
$clientCommissionPaid = false;
$clientCommissionMessage = '';
if (isset($conexion) && $conexion) {
    $ownerScope = client_build_booking_owner_scope($conexion, 'br', (int)$clientUserId, client_get_session_email());
    $requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : (isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0);
    $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    $bookingIdForFee = $requestId > 0 ? $requestId : 0;

    if ($bookingIdForFee <= 0 && $itemId > 0 && ($ownerScope['sql'] ?? '1=0') !== '1=0') {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasRequestsSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $sql = "SELECT bri.booking_request_id
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
        if ($hasItemsSoftDelete) {
            $sql .= " AND bri.is_deleted = 0";
        }
        if ($hasRequestsSoftDelete) {
            $sql .= " AND br.is_deleted = 0";
        }
        $sql .= " LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            $types = 'i' . (string)($ownerScope['types'] ?? '');
            $params = array_merge([$itemId], is_array($ownerScope['params'] ?? null) ? $ownerScope['params'] : []);
            if (mt_fee_bind_stmt_params($stmt, $types, $params) && mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                if ($row) {
                    $bookingIdForFee = (int)($row['booking_request_id'] ?? 0);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($bookingIdForFee > 0) {
        $clientFeeGateActive = is_booking_fee_required($conexion, $bookingIdForFee);
    }

    if ($requestId > 0 && $itemId > 0) {
        $commissionGate = commission_gate_status($conexion, $requestId, $itemId);
        $clientCommissionGateActive = !empty($commissionGate['enabled']) && empty($commissionGate['paid']);
        $clientCommissionPaid = !empty($commissionGate['paid']);
        if ($clientCommissionGateActive) {
            $clientCommissionMessage = 'Provider details and free messaging unlock after the commission payment is completed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - Inbox</title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <link href="/assets/apps/css/inbox.css" rel="stylesheet" type="text/css" />
    <style type="text/css">
        #client-inbox-thread-list {
            margin-top: 10px;
        }
        #client-inbox-thread-list .mt-thread-item {
            margin: 0;
            border-bottom: 1px solid #eef1f5;
            background: #fff;
        }
        #client-inbox-thread-list .mt-thread-link {
            display: block;
            padding: 10px 12px;
            text-decoration: none;
            color: inherit;
            border-left: 3px solid transparent;
        }
        #client-inbox-thread-list .mt-thread-item:hover .mt-thread-link {
            background: #f7f9fb;
        }
        #client-inbox-thread-list .mt-thread-item.active .mt-thread-link {
            background: #eef4ff;
            border-left-color: #5b9bd1;
        }
        #client-inbox-thread-list .mt-thread-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        #client-inbox-thread-list .mt-thread-main {
            min-width: 0;
            flex: 1 1 auto;
        }
        #client-inbox-thread-list .mt-thread-title {
            font-weight: 600;
            color: #2f353b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #client-inbox-thread-list .mt-thread-item.unread .mt-thread-title {
            font-weight: 700;
        }
        #client-inbox-thread-list .mt-thread-sub {
            margin-top: 3px;
            color: #7f8c9d;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #client-inbox-thread-list .mt-thread-preview {
            margin-top: 2px;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #client-inbox-thread-list .mt-dot {
            margin: 0 4px;
        }
        #client-inbox-thread-list .mt-thread-meta {
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            flex-direction: column;
            gap: 4px;
            flex: 0 0 auto;
            text-align: right;
        }
        #client-inbox-thread-list .mt-badge,
        #client-inbox-thread-list .mt-unread {
            font-size: 10px;
            padding: 3px 6px;
            line-height: 1.2;
        }
        #client-inbox-thread-list .mt-time {
            font-size: 11px;
            color: #95a5a6;
            white-space: nowrap;
        }
        @media (max-width: 767px) {
            #client-inbox-thread-list .mt-thread-row {
                flex-direction: column;
                gap: 6px;
            }
            #client-inbox-thread-list .mt-thread-meta {
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                text-align: left;
                flex-wrap: wrap;
            }
        }
        /* ── Chat bubble rows ── */
        #client-inbox-messages {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        #client-inbox-messages .mt-msg-row {
            display: flex;
            width: 100%;
            margin-bottom: 6px;
        }
        #client-inbox-messages .mt-msg-row--own  { justify-content: flex-end; }
        #client-inbox-messages .mt-msg-row--other { justify-content: flex-start; }
        #client-inbox-messages .mt-msg-bubble {
            max-width: 70%;
            min-width: 80px;
            padding: 10px 12px;
            border-radius: 16px;
            word-break: break-word;
            overflow-wrap: anywhere;
            box-shadow: 0 1px 3px rgba(0,0,0,.07);
        }
        #client-inbox-messages .mt-bubble-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }
        #client-inbox-messages .mt-bubble-name {
            font-size: 11px;
            font-weight: 600;
            opacity: .8;
            white-space: nowrap;
        }
        #client-inbox-messages .mt-bubble-time {
            font-size: 10px;
            opacity: .55;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #client-inbox-messages .mt-bubble-body { line-height: 1.5; }
        /* Own (client) messages — right, teal-blue */
        #client-inbox-messages .mt-msg-row--own .mt-msg-bubble {
            background: #1a73e8;
            color: #fff;
            border-radius: 16px 16px 4px 16px;
        }
        #client-inbox-messages .mt-msg-row--own .mt-bubble-time { opacity: .7; }
        /* Other messages — left, light grey */
        #client-inbox-messages .mt-msg-row--other .mt-msg-bubble {
            background: #f0f2f5;
            color: #2c3e50;
            border-radius: 16px 16px 16px 4px;
            border: 1px solid #e0e4ea;
        }
    </style>
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
                <h1>Inbox</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li class="active">Inbox</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="inbox">
                    <div class="row">
                        <div class="col-md-3 inbox-sidebar">
                            <button type="button" class="btn btn-sm btn-default compose-btn btn-block" id="client-inbox-refresh">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <ul class="inbox-nav" id="client-inbox-thread-list">
                                <li><a href="javascript:;">Loading threads...</a></li>
                            </ul>
                        </div>
                        <div class="col-md-9 inbox-body">
                            <div class="inbox-header">
                                <div id="client-inbox-title">Select a thread</div>
                            </div>
                            <div id="client-inbox-fee-alert" class="note note-warning" style="<?php echo $clientFeeGateActive ? '' : 'display:none;'; ?>">
                                <strong>Coordination Fee required.</strong>
                                Unlock after Coordination Fee.
                            </div>
                            <div id="client-inbox-commission-alert" class="note note-info" style="<?php echo $clientCommissionGateActive ? '' : 'display:none;'; ?>">
                                <strong>Commission payment required.</strong>
                                <?php echo htmlspecialchars($clientCommissionMessage !== '' ? $clientCommissionMessage : 'Provider details and free messaging unlock after the commission payment is completed.', ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div id="client-inbox-structured-alert" class="note note-info" style="display:none; margin-bottom:12px;">
                                <strong>There are pending structured actions in a service thread.</strong>
                                <a id="client-go-service-thread" class="btn btn-default btn-xs" style="margin-left:10px;" href="#">Go to Service Thread</a>
                            </div>
                            <div id="client-inbox-fee-actions" class="well" style="display:none;margin-bottom:12px;">
                                <h4 style="margin-top:0;">Quick actions</h4>
                                <p class="text-muted" style="margin-bottom:10px;">Messaging is limited until the coordination fee is paid.</p>
                                <div class="btn-group btn-group-sm" id="client-inbox-quick-actions" role="group" style="margin-bottom:12px;">
                                    <button type="button" class="btn btn-default client-quick-action" data-action="REQUEST_AVAILABILITY">Ask about availability</button>
                                    <button type="button" class="btn btn-default client-quick-action" data-action="DATES_FLEXIBLE">My dates are flexible</button>
                                    <button type="button" class="btn btn-default client-quick-action" data-action="DOCS_UPLOADED">I uploaded documents</button>
                                </div>
                                <hr style="margin:12px 0;">
                                <h4 style="margin-top:0;">Upload medical documents</h4>
                                <form id="client-inbox-doc-form" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="client-doc-type">Document type</label>
                                                <select class="form-control" id="client-doc-type">
                                                    <option value="medical_history">Medical history</option>
                                                    <option value="lab_results">Lab results</option>
                                                    <option value="prescription">Prescription</option>
                                                    <option value="insurance">Insurance</option>
                                                    <option value="photos">Photos</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="client-doc-file">File</label>
                                                <input type="file" class="form-control" id="client-doc-file" name="client_doc_files[]" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" multiple>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="client-doc-batch" style="margin-top:10px;"></div>
                                    <div class="form-group">
                                        <label for="client-doc-title">Title (optional)</label>
                                        <input type="text" class="form-control" id="client-doc-title" maxlength="255" placeholder="Document title">
                                    </div>
                                    <div class="form-group">
                                        <label for="client-doc-description">Description (optional)</label>
                                        <textarea class="form-control" id="client-doc-description" rows="2" maxlength="500" placeholder="Short description"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm" id="client-doc-upload-btn"><i class="fa fa-upload"></i> Upload document</button>
                                    <div id="client-doc-upload-status" style="margin-top:10px;"></div>
                                </form>
                            </div>
                            <div class="inbox-content" id="client-inbox-content" style="display:none;">
                                <div id="client-inbox-messages" style="max-height:420px;overflow:auto;border:1px solid #eef1f5;padding:12px;background:#fff;"></div>
                                <form id="client-inbox-send-form" style="margin-top:12px;">
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <label for="client-inbox-message">Write a message</label>
                                        <textarea class="form-control" id="client-inbox-message" rows="3" maxlength="2000" placeholder="Write your message..." <?php echo $clientFeeGateActive ? 'disabled' : ''; ?>></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary" id="client-inbox-send-btn" <?php echo $clientFeeGateActive ? 'disabled' : ''; ?>><i class="fa fa-paper-plane"></i> Send</button>
                                    <div id="client-inbox-compose-note" class="text-muted" style="margin-top:8px;display:none;">Free-form messaging is locked right now. Please use the structured actions above.</div>
                                </form>
                            </div>
                            <div class="inbox-content" id="client-inbox-empty">
                                <div class="note note-info" style="margin:0;">Select a thread from the left panel.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>
</div>

<div class="modal fade" id="clientProposeDatesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Propose new dates</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="client-proposed-date-from">Date from (optional)</label>
                    <input type="date" class="form-control" id="client-proposed-date-from">
                </div>
                <div class="form-group">
                    <label for="client-proposed-date-to">Date to (optional)</label>
                    <input type="date" class="form-control" id="client-proposed-date-to">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="client-proposed-notes">Notes (optional)</label>
                    <textarea class="form-control" id="client-proposed-notes" rows="3" maxlength="500" placeholder="Any extra details about your availability"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="client-submit-propose-dates">Send proposal</button>
            </div>
        </div>
    </div>
</div>

<?php echo $theme_layout_script; ?>
<script src="/client/js/notifications.js" type="text/javascript"></script>
<script type="text/javascript">
window.ClientInboxConfig = {
    feeGateActive: <?php echo $clientFeeGateActive ? 'true' : 'false'; ?>,
    commissionGateActive: <?php echo $clientCommissionGateActive ? 'true' : 'false'; ?>,
    commissionPaid: <?php echo $clientCommissionPaid ? 'true' : 'false'; ?>,
    commissionMessage: <?php echo json_encode($clientCommissionMessage); ?>,
    realtimeBaseUrl: <?php echo json_encode(MT_REALTIME_BASE_URL); ?>,
    realtimeSocketPath: <?php echo json_encode(MT_REALTIME_SOCKET_PATH); ?>,
    realtimeTokenUrl: "/client/ajax/realtime_token.php"
};
</script>
<script src="<?php echo htmlspecialchars(rtrim((string)MT_REALTIME_BASE_URL, '/'), ENT_QUOTES, 'UTF-8'); ?>/realtime/socket.io/socket.io.js"></script>
<script src="/client/js/app_inbox.js" type="text/javascript"></script>
</body>
</html>
