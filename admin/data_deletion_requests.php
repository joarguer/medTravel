<?php
include("include/include.php");
require_once __DIR__ . "/include/data_deletion_service.php";

if (!user_can(PERM_SETTINGS_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
if (!is_role_admin_session()) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$requests = [];
$loadError = '';
try {
    $requests = dd_fetch_requests($conexion, 500);
} catch (Throwable $e) {
    $loadError = 'No se pudieron cargar las solicitudes.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Data Deletion Requests</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <?php echo $theme_layout_script;?>
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
                    <h1>Data Deletion Requests</h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Dashboard</a></li>
                        <li class="active">Data Deletion</li>
                    </ol>
                </div>
                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-content-col">
                            <div class="portlet light">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-trash theme-font"></i>
                                        <span class="caption-subject font-dark bold uppercase">Requests</span>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <?php if ($loadError !== ''): ?>
                                        <div class="alert alert-danger"><?php echo htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php elseif (empty($requests)): ?>
                                        <div class="alert alert-info">No deletion requests logged.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered" id="dd-requests-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Request ID</th>
                                                        <th>Created At</th>
                                                        <th>Phone</th>
                                                        <th>Email</th>
                                                        <th>Name</th>
                                                        <th>Message</th>
                                                        <th>Status</th>
                                                        <th>Processed At</th>
                                                        <th>Summary</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($requests as $r): ?>
                                                        <?php
                                                        $status = trim((string)($r['status'] ?? 'pending'));
                                                        if ($status === '') {
                                                            $status = 'pending';
                                                        }
                                                        $isProcessable = ($status === 'pending' || $status === 'failed');
                                                        $statusClass = 'label-default';
                                                        if ($status === 'pending') {
                                                            $statusClass = 'label-warning';
                                                        } elseif ($status === 'processing') {
                                                            $statusClass = 'label-info';
                                                        } elseif ($status === 'completed') {
                                                            $statusClass = 'label-success';
                                                        } elseif ($status === 'failed') {
                                                            $statusClass = 'label-danger';
                                                        }
                                                        ?>
                                                        <tr data-request-id="<?php echo (int)($r['id'] ?? 0); ?>">
                                                            <td><?php echo (int)($r['id'] ?? 0); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($r['request_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars(dd_mask_phone((string)($r['request_phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars(dd_mask_email((string)($r['request_email'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($r['request_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td style="max-width: 260px;"><?php echo htmlspecialchars((string)($r['request_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><span class="label <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                            <td><?php echo htmlspecialchars((string)($r['processed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td style="max-width: 300px;">
                                                                <?php
                                                                $summary = trim((string)($r['result_summary'] ?? ''));
                                                                if ($summary === '') {
                                                                    $summary = trim((string)($r['last_error'] ?? ''));
                                                                }
                                                                echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($isProcessable): ?>
                                                                    <button type="button"
                                                                            class="btn btn-xs btn-danger btn-dd-process"
                                                                            data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                                                                            data-ref="<?php echo htmlspecialchars((string)($r['request_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                                        Process
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn btn-xs btn-default" disabled>Processed</button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo $footer;?>
        </div>
    </div>
    <?php echo $sider_bar;?>

    <div class="modal fade" id="dd-process-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title">Confirm Data Deletion Process</h4>
                </div>
                <div class="modal-body">
                    <p>This action will run the real deletion/anonymization workflow.</p>
                    <p><strong>Request:</strong> <span id="dd-modal-request-ref"></span></p>
                    <p>Type <strong>DELETE</strong> to continue.</p>
                    <input type="text" class="form-control" id="dd-modal-confirm-text" maxlength="20" autocomplete="off">
                    <input type="hidden" id="dd-modal-request-id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="dd-modal-confirm-btn">Process</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        function showError(msg) {
            if (window.toastr) {
                toastr.error(msg);
            } else {
                alert(msg);
            }
        }
        function showSuccess(msg) {
            if (window.toastr) {
                toastr.success(msg);
            } else {
                alert(msg);
            }
        }

        $(document).on('click', '.btn-dd-process', function () {
            var requestId = parseInt($(this).data('id'), 10) || 0;
            var requestRef = String($(this).data('ref') || '');
            if (requestId <= 0) {
                showError('Invalid request id');
                return;
            }
            $('#dd-modal-request-id').val(String(requestId));
            $('#dd-modal-request-ref').text(requestRef);
            $('#dd-modal-confirm-text').val('');
            $('#dd-process-modal').modal('show');
        });

        $('#dd-modal-confirm-btn').on('click', function () {
            var requestId = parseInt($('#dd-modal-request-id').val(), 10) || 0;
            var confirmText = String($('#dd-modal-confirm-text').val() || '').trim();
            if (requestId <= 0) {
                showError('Invalid request id');
                return;
            }
            if (confirmText !== 'DELETE') {
                showError('Type DELETE to confirm');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: 'ajax/data_deletion_requests.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'process',
                    request_id: requestId
                }
            }).done(function (res) {
                if (!res || !res.ok) {
                    showError((res && (res.message || res.error)) ? (res.message || res.error) : 'Process failed');
                    return;
                }
                showSuccess('Request processed');
                window.location.reload();
            }).fail(function (xhr) {
                var msg = 'Process failed';
                if (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) {
                    msg = xhr.responseJSON.message || xhr.responseJSON.error;
                }
                showError(msg);
            }).always(function () {
                $btn.prop('disabled', false).text('Process');
                $('#dd-process-modal').modal('hide');
            });
        });
    })();
    </script>
</body>
</html>
