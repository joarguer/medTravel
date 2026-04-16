<?php
include('include/include.php');

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$service_provider_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
$is_linked_medical_staff_session = is_provider_linked_medical_staff_session($conexion ?? null);
$is_admin_session = is_role_admin_session();

$page_heading = $is_linked_medical_staff_session ? 'Mis solicitudes asignadas' : 'Mis Solicitudes';
$page_breadcrumb = $page_heading;
$page_caption = $is_linked_medical_staff_session ? 'Mis solicitudes asignadas' : 'Solicitudes del prestador';
$page_intro_title = $is_linked_medical_staff_session ? 'Tu bandeja operativa' : 'Vista operativa del prestador';
$page_intro_body = $is_linked_medical_staff_session
    ? 'Aquí verás los casos que ya quedaron bajo tu responsabilidad operativa. Si algún caso nuevo sigue sin staff asignado, la administración del prestador debe asignarlo o podrás asumirlo solo cuando siga realmente pendiente.'
    : 'Cuando un item ya tiene médico o staff asignado, esa persona debe llevar el seguimiento operativo. Desde esta vista puedes supervisar, asignar o intervenir de forma explícita cuando haga falta.';
$page_intro_class = $is_linked_medical_staff_session ? 'info' : 'warning';

if ($provider_id <= 0 && $service_provider_id <= 0) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Mis Solicitudes</title>
    <?php echo $global_first_style;?>
    <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/bootstrap-summernote/summernote.css" rel="stylesheet" type="text/css" />
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <style>
        #my_booking_detail_modal .modal-dialog {
            width: 92%;
            max-width: 1180px;
        }
        #my_booking_detail_modal .modal-body {
            max-height: calc(100vh - 180px);
            overflow-y: auto;
            padding: 18px;
            background: #f7f9fc;
        }
        .mt-request-detail {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .mt-request-detail .mt-detail-sticky {
            position: sticky;
            top: -18px;
            z-index: 20;
            background: #f7f9fc;
            padding: 0 0 12px;
        }
        .mt-request-detail .mt-detail-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin: 0;
            padding: 18px;
            border: 1px solid #dfe6ee;
            border-radius: 10px;
            background: linear-gradient(135deg, #ffffff 0%, #f3f7fb 100%);
            box-shadow: 0 8px 24px rgba(31, 45, 61, 0.06);
        }
        .mt-request-detail .mt-eyebrow {
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 11px;
            color: #5f6f82;
            margin-bottom: 4px;
        }
        .mt-request-detail .mt-detail-title {
            margin: 0 0 6px;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-request-detail .mt-inline-meta,
        .mt-request-detail .mt-header-actions,
        .mt-request-detail .mt-inline-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .mt-request-detail .mt-inline-label {
            font-weight: 600;
            color: #555;
            margin-right: 4px;
        }
        .mt-request-detail .mt-header-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .mt-request-detail .mt-header-summary-card {
            border: 1px solid #e4ebf2;
            border-radius: 8px;
            background: #fff;
            padding: 10px 12px;
            min-height: 76px;
            overflow: hidden;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-request-detail .mt-header-summary-card .mt-summary-label,
        .mt-request-detail .mt-header-summary-card .mt-summary-value {
            margin: 0;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-request-detail .mt-header-summary-card .label {
            white-space: normal;
            display: inline-block;
            max-width: 100%;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 0;
        }
        .mt-summary-card {
            border: 1px solid #e7ecf1;
            border-radius: 6px;
            background: #fff;
            padding: 12px;
        }
        .mt-summary-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7d90;
            margin-bottom: 6px;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-summary-value {
            font-weight: 600;
            color: #2c3e50;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-summary-value .label {
            white-space: normal;
            display: inline-block;
            max-width: 100%;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-request-detail .mt-workflow-guide {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .mt-request-detail .mt-guide-card {
            border: 1px solid #dfe6ee;
            border-radius: 10px;
            background: #fff;
            padding: 14px 16px;
        }
        .mt-request-detail .mt-guide-card h6 {
            margin: 0 0 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #214d72;
        }
        .mt-request-detail .mt-guide-card p {
            margin: 0;
            color: #4f5f6f;
            line-height: 1.5;
        }
        .mt-request-detail .mt-detail-tabs {
            border: 1px solid #dfe6ee;
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
        }
        .mt-request-detail .mt-detail-tabs .nav-tabs {
            border-bottom: 1px solid #dfe6ee;
            padding: 0 14px;
            background: #f8fafc;
        }
        .mt-request-detail .mt-detail-tabs .nav-tabs > li > a {
            color: #516070;
            font-weight: 600;
            border: 0;
            border-bottom: 3px solid transparent;
            margin-right: 6px;
            padding: 14px 10px;
            background: transparent;
        }
        .mt-request-detail .mt-detail-tabs .nav-tabs > li.active > a,
        .mt-request-detail .mt-detail-tabs .nav-tabs > li.active > a:focus,
        .mt-request-detail .mt-detail-tabs .nav-tabs > li.active > a:hover {
            color: #1d5f8c;
            border: 0;
            border-bottom: 3px solid #1d84c6;
            background: transparent;
        }
        .mt-request-detail .mt-tab-pane {
            padding: 18px;
        }
        .mt-request-detail .mt-panel {
            border: 1px solid #e7ecf1;
            border-radius: 8px;
            background: #fbfcfe;
            padding: 14px 16px;
        }
        .mt-request-detail .mt-panel + .mt-panel {
            margin-top: 12px;
        }
        .mt-request-detail .mt-panel-title {
            margin: 0 0 12px;
            font-size: 15px;
            font-weight: 700;
            color: #2c3e50;
        }
        .mt-request-detail .mt-panel-subtitle {
            margin: 0 0 12px;
            color: #6b7d90;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .mt-request-detail .mt-quick-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .mt-request-detail .mt-actions-note {
            margin-top: 10px;
            color: #6b7d90;
        }
        .mt-section {
            border-top: 1px solid #eef1f5;
            padding-top: 16px;
            margin-top: 16px;
        }
        .mt-section:first-child {
            border-top: 0;
            padding-top: 0;
            margin-top: 0;
        }
        .mt-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .mt-section-head h5 {
            margin: 0;
            font-weight: 700;
        }
        .mt-conversation-log {
            max-height: 360px;
            overflow: auto;
            border: 1px solid #e5e5e5;
            padding: 12px;
            background: #fff;
            border-radius: 6px;
        }
        .mt-request-detail .mt-conversation-cta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 14px;
            padding: 14px 16px;
            border: 1px solid #dfe6ee;
            border-radius: 8px;
            background: #f8fbfd;
        }
        .mt-request-detail .mt-conversation-cta p {
            margin: 6px 0 0;
            color: #5d6b78;
        }
        .mt-message-row {
            border-bottom: 1px solid #ececec;
            padding: 10px 0;
        }
        .mt-message-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .mt-message-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 6px;
        }
        .mt-message-time,
        .mt-message-actor {
            font-size: 12px;
            color: #7f8c8d;
        }
        .mt-role-chip {
            min-width: 84px;
            text-align: center;
        }
        .btn[aria-disabled="true"] {
            pointer-events: auto;
            opacity: .65;
        }
        .mt-page-intro {
            margin-bottom: 18px;
        }
        .mt-page-intro .alert {
            margin-bottom: 0;
        }
        @media (max-width: 767px) {
            #my_booking_detail_modal .modal-dialog {
                width: auto;
                margin: 10px;
            }
            .mt-request-detail .mt-detail-header {
                flex-direction: column;
            }
            .mt-request-detail .mt-detail-sticky {
                top: -12px;
            }
            .mt-request-detail .mt-conversation-cta {
                flex-direction: column;
            }
        }
    /* ── Doc Viewer Modal ── */
    #adminDocViewerModal .mt-dv-type-badge { font-size: 12px; vertical-align: middle; margin-left: 6px; }
    #adminDocViewerModal .mt-dv-filename { word-break: break-all; font-weight: 600; color: #2f353b; }
    #adminDocViewerModal .mt-dv-meta { font-size: 11px; color: #7f8c9d; margin-top: 3px; }
    #adminDocViewerModal .mt-dv-preview-wrap {
        background: #f4f6f7; border: 1px solid #dfe6ee; border-radius: 4px;
        min-height: 200px; display: flex; align-items: center; justify-content: center;
        overflow: hidden; padding: 0;
    }
    #adminDocViewerModal .mt-dv-preview-wrap iframe { width: 100%; height: 80vh; max-height: 80vh; border: none; display: block; }
    #adminDocViewerModal .mt-dv-preview-wrap img { max-width: 100%; max-height: 80vh; display: block; margin: auto; }
    #adminDocViewerModal .mt-dv-no-preview { text-align: center; padding: 40px 20px; color: #7f8c9d; }
    #adminDocViewerModal .mt-dv-no-preview .fa { font-size: 48px; display: block; margin-bottom: 10px; color: #bdc3c7; }
    @media (max-width: 767px) {
        #adminDocViewerModal .modal-dialog { margin: 0; width: 100%; }
        #adminDocViewerModal .modal-content { border-radius: 0; min-height: 100vh; }
        #adminDocViewerModal .mt-dv-preview-wrap iframe { height: 60vh; max-height: 60vh; }
        #adminDocViewerModal .mt-dv-preview-wrap img { max-height: 60vh; }
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
                <h1><?php echo htmlspecialchars($page_heading, ENT_QUOTES, 'UTF-8'); ?></h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Inicio</a></li>
                    <li class="active"><?php echo htmlspecialchars($page_breadcrumb, ENT_QUOTES, 'UTF-8'); ?></li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mt-page-intro">
                            <div class="alert alert-<?php echo htmlspecialchars($page_intro_class, ENT_QUOTES, 'UTF-8'); ?>">
                                <strong><?php echo htmlspecialchars($page_intro_title, ENT_QUOTES, 'UTF-8'); ?>.</strong>
                                <?php echo htmlspecialchars($page_intro_body, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-list font-blue"></i>
                                    <span class="caption-subject font-blue bold uppercase"><?php echo htmlspecialchars($page_caption, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="actions">
                                    <button class="btn btn-circle btn-icon-only btn-default" id="btn-reload-my-bookings">
                                        <i class="icon-refresh"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <table class="table table-striped table-bordered table-hover" id="my_booking_requests_table">
                                    <thead>
                                    <tr>
                                        <th>Caso</th>
                                        <th>Fecha</th>
                                        <th>Destino / Línea de tiempo</th>
                                        <th>Tipo</th>
                                        <th>Servicio</th>
                                        <th>Responsable operativo</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
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

<div class="modal fade" id="my_booking_detail_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Detalle de solicitud</h4>
            </div>
            <div class="modal-body" id="my_booking_detail_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="provider_reject_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Rechazar caso</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="provider_reject_item_id" value="">
                <div class="form-group">
                    <label for="provider_reject_reason">Motivo del rechazo (obligatorio)</label>
                    <input type="text" class="form-control" id="provider_reject_reason" maxlength="255" placeholder="Explica brevemente por qué no tomarás este caso">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-provider-reject-save">Rechazar caso</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="provider_propose_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Proponer cita</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="provider_propose_item_id" value="">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_start_at">Inicio de la reunión (obligatorio)</label>
                            <input type="datetime-local" class="form-control" id="provider_proposed_start_at">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_end_at">Fin de la reunión (obligatorio)</label>
                            <input type="datetime-local" class="form-control" id="provider_proposed_end_at">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_price">Valor propuesto (opcional)</label>
                            <input type="number" class="form-control" id="provider_proposed_price" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_currency">Moneda</label>
                            <select class="form-control" id="provider_proposed_currency">
                                <option value="USD">USD</option>
                                <option value="COP">COP</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="provider_proposed_notes">Notas para la propuesta (obligatorio)</label>
                    <textarea class="form-control" id="provider_proposed_notes" rows="3" placeholder="Explica disponibilidad, agenda sugerida o condiciones relevantes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-provider-propose-save">Enviar propuesta de cita</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Marcar valoración virtual realizada -->
<div class="modal fade" id="modal-assessment-done" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Marcar valoración virtual realizada</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assessment_done_item_id" value="">
                <div class="form-group">
                    <label for="assessment_notes">Notas de la valoración (opcional)</label>
                    <textarea class="form-control" id="assessment_notes" rows="4" placeholder="Observaciones clínicas, hallazgos relevantes de la valoración virtual..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-assessment-done-save">Confirmar valoración realizada</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar plan clínico acordado -->
<div class="modal fade" id="modal-plan-agreed" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Registrar plan clínico acordado</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="plan_agreed_item_id" value="">
                <div class="alert alert-info" style="margin-bottom:14px;">
                    <strong>Incluir en el plan:</strong> resumen terapéutico &mdash; procedimiento propuesto &mdash; etapas &mdash; condiciones médicas relevantes &mdash; notas importantes para coordinación futura.
                </div>
                <div class="form-group">
                    <label for="plan_description">Descripción del plan acordado <span class="text-danger">*</span></label>
                    <textarea id="plan_description" class="form-control summernote-plan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-plan-agreed-save"><i class="fa fa-check"></i> Registrar plan acordado</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Agendar procedimiento presencial -->
<div class="modal fade" id="modal-procedure-schedule" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Agendar procedimiento presencial</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="procedure_schedule_item_id" value="">
                <div id="procedure_timeline_hint" class="alert alert-info" style="display:none;"></div>
                <div class="form-group">
                    <label for="procedure_date">Fecha del procedimiento <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="procedure_date" placeholder="YYYY-MM-DD">
                    <p class="help-block">La fecha debe estar dentro de la ventana de viaje del paciente.</p>
                </div>
                <div class="form-group">
                    <label for="procedure_notes">Notas del procedimiento (opcional)</label>
                    <textarea class="form-control" id="procedure_notes" rows="3" placeholder="Indicaciones preoperatorias, instrucciones para el paciente, información logística..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-procedure-schedule-save">Confirmar agenda</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cerrar caso -->
<div class="modal fade" id="modal-case-close" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Cerrar caso clínico</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="case_close_item_id" value="">
                <div class="alert alert-warning">Esta acción cierra el caso clínico de forma definitiva. Confirma que el tratamiento fue completado y el paciente está dado de alta.</div>
                <div class="form-group">
                    <label for="case_close_reason">Resumen de cierre <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="case_close_reason" rows="3" placeholder="Alta clínica satisfactoria, procedimiento completado el..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-case-close-save">Cerrar caso</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reversión de estado (admin) -->
<div class="modal fade" id="modal-reversal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Revertir estado del caso</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reversal_item_id" value="">
                <input type="hidden" id="reversal_target_status" value="">
                <div class="alert alert-danger">Estás a punto de revertir el estado del caso. Esta acción quedará registrada en el historial.</div>
                <div class="form-group">
                    <label>Revertir a</label>
                    <p class="form-control-static" id="reversal_target_label"></p>
                </div>
                <div class="form-group">
                    <label for="reversal_reason">Motivo de la reversión <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="reversal_reason" rows="3" placeholder="Explica por qué se revierte este estado..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-reversal-save">Confirmar reversión</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Actualizar ventana de fechas del paciente -->
<div class="modal fade" id="modal-update-timeline" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Actualizar ventana de viaje del paciente</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="update_timeline_booking_id" value="">
                <p class="text-muted">Actualiza las fechas de viaje del paciente según lo acordado post valoración. Esto permite agendar procedimientos en la ventana correcta.</p>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="update_timeline_from">Fecha de llegada <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="update_timeline_from">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="update_timeline_to">Fecha de salida <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="update_timeline_to">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="update_timeline_reason">Motivo del cambio <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="update_timeline_reason" maxlength="255" placeholder="Ej: Paciente confirmó disponibilidad en nueva ventana acordada post valoración">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-update-timeline-save">Actualizar fechas</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assign_staff_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Asignar médico / staff</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assign_staff_item_id" value="">
                <div class="form-group">
                    <label>Asignación actual</label>
                    <p class="form-control-static" id="assign_staff_current_label">Sin asignar</p>
                </div>
                <div class="form-group">
                    <label for="assign_staff_select">Staff elegible</label>
                    <select class="form-control" id="assign_staff_select">
                        <option value="">Selecciona un médico o staff</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-assign-staff-save">Guardar asignación</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminDocViewerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span id="adminDocViewerName" class="mt-dv-filename">Documento</span>
                    <span id="adminDocViewerType" class="label label-info mt-dv-type-badge"></span>
                </h4>
                <p id="adminDocViewerMeta" class="mt-dv-meta" style="margin:0;"></p>
            </div>
            <div class="modal-body" style="padding:12px;">
                <div class="mt-dv-preview-wrap" id="adminDocViewerPreview">
                    <div class="mt-dv-no-preview">
                        <i class="fa fa-file-o" aria-hidden="true"></i>
                        <span>Vista previa no disponible.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> Cerrar</button>
                <a id="adminDocViewerDownload" href="#" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa fa-download" aria-hidden="true"></i> Descargar</a>
            </div>
        </div>
    </div>
</div>

<?php echo $theme_layout_script;?>
<script>
window.MY_BOOKING_REQUESTS_CONTEXT = {
    isLinkedMedicalStaffSession: <?php echo $is_linked_medical_staff_session ? 'true' : 'false'; ?>,
    isAdminSession: <?php echo $is_admin_session ? 'true' : 'false'; ?>
};
</script>
<script src="../../assets/global/scripts/datatable.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/bootstrap-summernote/summernote.min.js" type="text/javascript"></script>
<script src="js/my_booking_requests.js" type="text/javascript"></script>
</body>
</html>
