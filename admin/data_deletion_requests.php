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
    <title><?php echo $title;?> - Solicitudes de eliminación de datos</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <?php echo $theme_layout_script;?>
    <style>
        .caption-helper {
            display: block;
            margin-top: 4px;
            color: #7b8a97;
            font-size: 13px;
            font-weight: 400;
        }
        .privacy-context-note {
            margin: 0;
            color: #6c7a89;
            max-width: 960px;
        }
        .dd-table th,
        .dd-table td {
            vertical-align: top;
        }
        .dd-table .label {
            display: inline-block;
            min-width: 96px;
            text-align: center;
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
                    <h1>Solicitudes de eliminación de datos
                        <small>Consola administrativa de privacidad y cumplimiento operativo</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="#">Administración</a></li>
                        <li><a href="#">Privacidad y Cumplimiento</a></li>
                        <li class="active">Solicitudes de eliminación</li>
                    </ol>
                </div>
                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-content-col">
                            <div class="portlet light">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-trash theme-font"></i>
                                        <span class="caption-subject font-dark bold">Privacidad y cumplimiento operativo</span>
                                        <span class="caption-helper">Administra solicitudes de eliminación de datos personales y ejecuta su procesamiento controlado desde administración central</span>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div class="alert alert-info">
                                        <strong>Alcance del módulo:</strong> esta pantalla administra <strong>solicitudes de eliminación de datos personales</strong> recibidas por MedTravel y su <strong>procesamiento operativo</strong>.
                                        <br>
                                        <span class="small">No es una consola de usuarios o clientes como entidad primaria. El recurso central aquí es la <strong>solicitud de privacidad</strong> y su trazabilidad administrativa.</span>
                                    </div>
                                    <div class="alert alert-warning">
                                        <strong>Actor responsable:</strong> este módulo debe ser utilizado por <strong>administración central</strong> para revisar y ejecutar solicitudes sensibles.
                                        <br>
                                        <span class="small">Antes de procesar una solicitud, debe verificarse que corresponda al titular correcto y que la ejecución sea procedente en el contexto operativo.</span>
                                    </div>
                                    <div class="alert alert-danger">
                                        <strong>Impacto transversal:</strong> procesar una solicitud activa el flujo real de <strong>eliminación y anonimización</strong> sobre múltiples dominios del sistema.
                                        <br>
                                        <span class="small">Puede afectar datos relacionados con cuentas, clientes, bookings, documentos, mensajes, calendario, paquetes y otras trazas operativas. Es una acción sensible y no debe ejecutarse como rutina administrativa genérica.</span>
                                    </div>
                                    <div class="row" style="margin-bottom:15px;">
                                        <div class="col-md-12">
                                            <p class="privacy-context-note">
                                                Usa esta consola como capa de <strong>privacidad/compliance operativa</strong>. El objetivo es gestionar la ejecución de solicitudes de eliminación ya registradas, no administrar manualmente entidades de negocio ni operar mantenimiento ordinario de usuarios o clientes.
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($loadError !== ''): ?>
                                        <div class="alert alert-danger"><?php echo htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php elseif (empty($requests)): ?>
                                        <div class="alert alert-info">No hay solicitudes de eliminación registradas.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered dd-table" id="dd-requests-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Solicitud</th>
                                                        <th>Fecha de registro</th>
                                                        <th>Teléfono</th>
                                                        <th>Email</th>
                                                        <th>Nombre</th>
                                                        <th>Mensaje</th>
                                                        <th>Estado</th>
                                                        <th>Procesada el</th>
                                                        <th>Resultado / trazabilidad</th>
                                                        <th>Acción</th>
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
                                                        $statusText = 'Pendiente';
                                                        if ($status === 'pending') {
                                                            $statusClass = 'label-warning';
                                                            $statusText = 'Pendiente';
                                                        } elseif ($status === 'processing') {
                                                            $statusClass = 'label-info';
                                                            $statusText = 'En proceso';
                                                        } elseif ($status === 'completed') {
                                                            $statusClass = 'label-success';
                                                            $statusText = 'Completada';
                                                        } elseif ($status === 'failed') {
                                                            $statusClass = 'label-danger';
                                                            $statusText = 'Fallida';
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
                                                            <td><span class="label <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></span></td>
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
                                                                        Procesar
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn btn-xs btn-default" disabled>Sin acción</button>
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
                    <h4 class="modal-title">Confirmar procesamiento de solicitud</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" style="margin-bottom:15px;">
                        <strong>Acción sensible:</strong> esta operación ejecuta el flujo real de <strong>eliminación y anonimización</strong> asociado a la solicitud.
                        <br>
                        <span class="small">Revísala antes de continuar. Su impacto puede extenderse a múltiples dominios operativos y no corresponde a una acción rutinaria.</span>
                    </div>
                    <p><strong>Solicitud:</strong> <span id="dd-modal-request-ref"></span></p>
                    <p>Escribe <strong>DELETE</strong> para confirmar que deseas procesarla.</p>
                    <input type="text" class="form-control" id="dd-modal-confirm-text" maxlength="20" autocomplete="off">
                    <input type="hidden" id="dd-modal-request-id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="dd-modal-confirm-btn">Procesar solicitud</button>
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
                showError('Identificador de solicitud no válido');
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
                showError('Identificador de solicitud no válido');
                return;
            }
            if (confirmText !== 'DELETE') {
                showError('Escribe DELETE para confirmar');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Procesando...');

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
                    showError((res && (res.message || res.error)) ? (res.message || res.error) : 'No fue posible procesar la solicitud');
                    return;
                }
                showSuccess('Solicitud procesada');
                window.location.reload();
            }).fail(function (xhr) {
                var msg = 'No fue posible procesar la solicitud';
                if (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) {
                    msg = xhr.responseJSON.message || xhr.responseJSON.error;
                }
                showError(msg);
            }).always(function () {
                $btn.prop('disabled', false).text('Procesar solicitud');
                $('#dd-process-modal').modal('hide');
            });
        });
    })();
    </script>
</body>
</html>
