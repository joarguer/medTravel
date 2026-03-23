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
    $initialThreadType = strtoupper(trim((string)($_GET['thread_type'] ?? 'CARE')));
    if (!in_array($initialThreadType, ['CARE', 'ITEM'], true)) {
        $initialThreadType = 'CARE';
    }
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

    if ($bookingIdForFee > 0 && $initialThreadType !== 'CARE') {
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
        #client-inbox-messages .mt-shared-doc-card {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(26,115,232,.16);
        }
        #client-inbox-messages .mt-shared-doc-label {
            font-size: 12px;
            font-weight: 600;
            opacity: .85;
        }
        #client-inbox-messages .mt-shared-doc-name {
            margin-top: 4px;
            word-break: break-word;
        }
        #client-inbox-messages .mt-shared-doc-meta,
        #client-inbox-messages .mt-shared-doc-file,
        #client-inbox-messages .mt-shared-doc-note {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            opacity: .88;
        }
        #client-inbox-messages .mt-shared-doc-actions {
            margin-top: 6px;
        }
        #client-inbox-messages .mt-shared-doc-link {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
            position: relative;
            z-index: 1;
        }
        #client-inbox-messages .mt-bubble-status {
            margin-top: 4px;
            font-size: 10px;
            opacity: .7;
        }
        /* Own (client) messages — right, teal-blue */
        #client-inbox-messages .mt-msg-row--own .mt-msg-bubble {
            background: #1a73e8;
            color: #fff;
            border-radius: 16px 16px 4px 16px;
        }
        #client-inbox-messages .mt-msg-row--own .mt-shared-doc-card {
            border-top-color: rgba(255,255,255,.28);
        }
        #client-inbox-messages .mt-msg-row--own .mt-shared-doc-label,
        #client-inbox-messages .mt-msg-row--own .mt-shared-doc-name,
        #client-inbox-messages .mt-msg-row--own .mt-shared-doc-meta,
        #client-inbox-messages .mt-msg-row--own .mt-shared-doc-file,
        #client-inbox-messages .mt-msg-row--own .mt-shared-doc-note,
        #client-inbox-messages .mt-msg-row--own .mt-shared-doc-link {
            color: #fff;
        }
        #client-inbox-messages .mt-msg-row--own .mt-bubble-time { opacity: .7; }
        /* Other messages — left, light grey */
        #client-inbox-messages .mt-msg-row--other .mt-msg-bubble {
            background: #f0f2f5;
            color: #2c3e50;
            border-radius: 16px 16px 16px 4px;
            border: 1px solid #e0e4ea;
        }
        #client-inbox-messages .mt-msg-row--other .mt-shared-doc-label {
            color: #51606f;
        }
        #client-inbox-messages .mt-msg-row--other .mt-shared-doc-link {
            color: #1a73e8;
        }
        #client-inbox-messages .mt-msg-row--grouped {
            margin-bottom: 2px;
        }
        #client-inbox-messages .mt-msg-row--system {
            justify-content: center;
        }
        #client-inbox-messages .mt-msg-row--system .mt-msg-bubble {
            max-width: 100%;
            background: #f4f6f7;
            border: 1px solid #d5dce4;
            border-radius: 8px;
            color: #2c3e50;
        }
        #client-inbox-docs-content .mt-docs-section {
            border: 1px solid #d4e6f1;
            border-radius: 4px;
            background: #eaf4fb;
            padding: 10px 12px;
            margin-bottom: 0;
        }
        #client-inbox-docs-content .mt-docs-header {
            font-size: 13px;
            color: #2471a3;
            margin-bottom: 8px;
        }
        #client-inbox-docs-content .mt-docs-icon { margin-right: 3px; }
        #client-inbox-docs-content .mt-docs-empty {
            margin: 4px 0 0;
            font-style: italic;
            font-size: 12px;
        }
        #client-inbox-docs-content .mt-docs-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        #client-inbox-docs-content .mt-doc-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            background: #fff;
            border-radius: 3px;
            padding: 5px 8px;
        }
        #client-inbox-docs-content .mt-doc-type { flex: 0 0 auto; }
        #client-inbox-docs-content .mt-doc-main {
            flex: 1 1 220px;
            min-width: 160px;
        }
        #client-inbox-docs-content .mt-doc-title {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2f353b;
            text-decoration: none;
            word-break: break-word;
        }
        #client-inbox-docs-content .mt-doc-title:hover {
            text-decoration: underline;
            color: #1a73e8;
        }
        #client-inbox-docs-content .mt-doc-name {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            word-break: break-all;
            text-decoration: none;
            color: #7f8c9d;
        }
        #client-inbox-docs-content .mt-doc-name:hover {
            text-decoration: underline;
            color: #1a73e8;
        }
        #client-inbox-docs-content .mt-doc-note {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #5f6c7b;
        }
        #client-inbox-docs-content .mt-doc-date {
            flex: 0 0 auto;
            font-size: 11px;
            white-space: nowrap;
        }
        #client-inbox-docs-content .mt-doc-download { flex: 0 0 auto; }
        #clientAttachDocumentModal .help-block {
            margin-bottom: 0;
        }
        #clientAttachDocumentModal .mt-attach-context {
            margin-top: 8px;
            font-size: 12px;
            color: #7f8c8d;
        }
        #client-chat-attach-status {
            margin-top: 8px;
            display: none;
        }
        #clientDocViewerModal .mt-dv-type-badge {
            font-size: 12px;
            vertical-align: middle;
            margin-left: 6px;
        }
        #clientDocViewerModal .mt-dv-filename {
            word-break: break-all;
            font-weight: 600;
            color: #2f353b;
        }
        #clientDocViewerModal .mt-dv-meta {
            font-size: 11px;
            color: #7f8c9d;
            margin-top: 3px;
        }
        #clientDocViewerModal .mt-dv-preview-wrap {
            background: #f4f6f7;
            border: 1px solid #dfe6ee;
            border-radius: 4px;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 0;
        }
        #clientDocViewerModal .mt-dv-preview-wrap iframe {
            width: 100%;
            height: 80vh;
            max-height: 80vh;
            border: none;
            display: block;
        }
        #clientDocViewerModal .mt-dv-preview-wrap img {
            max-width: 100%;
            max-height: 80vh;
            display: block;
            margin: auto;
        }
        #clientDocViewerModal .mt-dv-no-preview {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c9d;
        }
        #clientDocViewerModal .mt-dv-no-preview .fa {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
            color: #bdc3c7;
        }
        #client-typing-indicator {
            font-size: 12px;
            color: #7f8c9d;
            margin-top: 6px;
            display: none;
        }
        @media (max-width: 767px) {
            #clientDocViewerModal .modal-dialog {
                margin: 0;
                width: 100%;
            }
            #clientDocViewerModal .modal-content {
                border-radius: 0;
                min-height: 100vh;
            }
            #clientDocViewerModal .mt-dv-preview-wrap iframe {
                height: 60vh;
                max-height: 60vh;
            }
            #clientDocViewerModal .mt-dv-preview-wrap img {
                max-height: 60vh;
            }
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
                                <div id="client-inbox-title">Select a MedTravel or Medical Provider thread</div>
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
                                <strong>There are formal actions pending in a service thread.</strong>
                                <a id="client-go-service-thread" class="btn btn-default btn-xs" style="margin-left:10px;" href="#">Go to Service Thread</a>
                            </div>
                            <div id="client-inbox-fee-actions" class="well" style="display:none;margin-bottom:12px;">
                                <h4 style="margin-top:0;">Formal quick actions</h4>
                                <p class="text-muted" style="margin-bottom:10px;">While the coordination fee is still pending, free-form chat remains blocked. You can still record formal updates below.</p>
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
                                <div id="client-inbox-docs-panel" class="panel panel-default" style="display:none;margin-bottom:12px;">
                                    <div class="panel-heading" style="padding:8px 12px;">
                                        <a href="#client-inbox-docs-collapse" data-toggle="collapse" aria-expanded="false" aria-controls="client-inbox-docs-collapse" style="display:flex;align-items:center;justify-content:space-between;text-decoration:none;">
                                            <span><i class="fa fa-paperclip" aria-hidden="true"></i> View shared documents</span>
                                            <span class="badge" id="client-inbox-docs-count">0</span>
                                        </a>
                                    </div>
                                    <div id="client-inbox-docs-collapse" class="panel-collapse collapse">
                                        <div class="panel-body" id="client-inbox-docs-content" style="padding:10px;"></div>
                                    </div>
                                </div>
                                <div id="client-inbox-messages" style="max-height:420px;overflow:auto;border:1px solid #eef1f5;padding:12px;background:#fff;"></div>
                                <form id="client-inbox-send-form" style="margin-top:12px;">
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <label for="client-inbox-message">Write a message</label>
                                        <textarea class="form-control" id="client-inbox-message" rows="3" maxlength="2000" placeholder="Write your message..." <?php echo $clientFeeGateActive ? 'disabled' : ''; ?>></textarea>
                                    </div>
                                    <div id="client-typing-indicator" style="font-size:12px;color:#999;min-height:18px;margin-bottom:4px;">Support is typing…</div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                        <button type="button" class="btn btn-default btn-sm" id="client-chat-attach-btn" <?php echo $clientFeeGateActive ? 'disabled' : ''; ?>><i class="fa fa-paperclip"></i> Attach document</button>
                                        <button type="submit" class="btn btn-primary btn-sm" id="client-inbox-send-btn" style="margin-left:auto;" <?php echo $clientFeeGateActive ? 'disabled' : ''; ?>><i class="fa fa-paper-plane"></i> Send</button>
                                    </div>
                                    <div id="client-chat-attach-status" class="text-muted"></div>
                                    <div class="text-muted" style="margin-top:8px;">Chat is open from the start. Use formal actions when you need to register a decision or a request that should affect the case workflow. Free-form messages do not change status by themselves.</div>
                                    <div id="client-inbox-compose-note" class="text-muted" style="margin-top:8px;display:none;"></div>
                                </form>
                            </div>
                            <div class="inbox-content" id="client-inbox-empty">
                                <div class="note note-info" style="margin:0;">Select a MedTravel Coordination or Medical Provider thread from the left panel.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>
</div>

<div class="modal fade" id="clientAttachDocumentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="client-attach-document-form">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Attach document</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="client-attach-thread-id" value="">
                    <input type="hidden" id="client-attach-thread-type" value="">
                    <input type="hidden" id="client-attach-request-id" value="">
                    <input type="hidden" id="client-attach-item-id" value="">
                    <div class="form-group">
                        <label for="client-attach-file">Select file</label>
                        <input type="file" class="form-control" id="client-attach-file" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" required>
                    </div>
                    <div class="form-group">
                        <label for="client-attach-title">Document title</label>
                        <input type="text" class="form-control" id="client-attach-title" maxlength="190" required>
                    </div>
                    <div class="form-group">
                        <label for="client-attach-type">Document type</label>
                        <select class="form-control" id="client-attach-type" required>
                            <option value="other">Other</option>
                            <option value="medical_history">Medical history</option>
                            <option value="lab_results">Exam / lab result</option>
                            <option value="diagnostic_imaging">Diagnostic image</option>
                            <option value="quote">Quote / estimate</option>
                            <option value="consent_form">Consent form</option>
                            <option value="medical_order">Medical order</option>
                            <option value="prescription">Prescription / indication</option>
                            <option value="administrative_document">Administrative document</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client-attach-note">Note (optional)</label>
                        <textarea class="form-control" id="client-attach-note" rows="3" maxlength="500" placeholder="Example: shared for the provider review"></textarea>
                    </div>
                    <p class="help-block">The document will be attached to the current thread and will remain visible in the chat and in shared documents.</p>
                    <div class="mt-attach-context" id="client-attach-context">Thread context not available.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="client-attach-submit-btn">Attach to chat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="clientDocViewerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span id="clientDocViewerName" class="mt-dv-filename">Document</span>
                    <span id="clientDocViewerType" class="label label-info mt-dv-type-badge"></span>
                </h4>
                <p id="clientDocViewerMeta" class="mt-dv-meta" style="margin:0;"></p>
            </div>
            <div class="modal-body" style="padding:12px;">
                <div class="mt-dv-preview-wrap" id="clientDocViewerPreview">
                    <div class="mt-dv-no-preview">
                        <i class="fa fa-file-o" aria-hidden="true"></i>
                        <span>Preview not available.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> Close</button>
                <a id="clientDocViewerOpen" href="#" target="_blank" rel="noopener" class="btn btn-default"><i class="fa fa-external-link" aria-hidden="true"></i> Open in new tab</a>
                <a id="clientDocViewerDownload" href="#" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa fa-download" aria-hidden="true"></i> Download</a>
            </div>
        </div>
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
    userId: <?php echo (int)($clientUserId ?? 0); ?>,
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
