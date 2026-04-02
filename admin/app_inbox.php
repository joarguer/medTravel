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
$is_linked_medical_staff_session = is_provider_linked_medical_staff_session($conexion ?? null);
$page_heading = $is_linked_medical_staff_session ? 'Inbox asignado' : 'Inbox';
$page_breadcrumb = $page_heading;
$page_intro_class = $is_linked_medical_staff_session ? 'info' : 'warning';
$page_intro_title = $is_linked_medical_staff_session ? 'Seguimiento operativo de tus casos asignados' : 'Seguimiento operativo del prestador';
$page_intro_body = $is_linked_medical_staff_session
    ? 'Este inbox concentra la comunicación de los items que ya quedaron bajo tu responsabilidad operativa. Si un caso sigue sin asignación clínica, la administración del prestador debe decidir quién lo toma.'
    : 'Cuando un item ya tiene staff asignado, el seguimiento operativo normal debe llevarlo esa persona. Desde aquí puedes supervisar el caso o intervenir de forma explícita cuando haga falta.';
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
    <title><?php echo $title;?> - <?php echo htmlspecialchars($page_heading, ENT_QUOTES); ?></title>
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
            font-size: 14px;
            line-height: 1.35;
            color: #2f353b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #admin-inbox-thread-list .mt-thread-item.unread .mt-thread-title {
            font-weight: 700;
        }
        #admin-inbox-thread-list .mt-thread-sub {
            margin-top: 4px;
            color: #5f6c7b;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #admin-inbox-thread-list .mt-thread-preview {
            margin-top: 4px;
            font-size: 12px;
            color: #8a94a6;
            line-height: 1.35;
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
        #admin-inbox-thread-list .mt-thread-status-badge {
            font-size: 10px;
            padding: 3px 6px;
            line-height: 1.2;
            max-width: 88px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #admin-inbox-thread-list .mt-time {
            font-size: 11px;
            color: #95a5a6;
            white-space: nowrap;
        }
        #admin-inbox-messages .admin-structured-card {
            border: 1px solid #dfe6ee;
            border-radius: 4px;
            background: #fff;
            padding: 10px;
        }
        #admin-inbox-messages .admin-structured-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        #admin-inbox-messages .admin-structured-icon {
            color: #5b9bd1;
            font-size: 15px;
        }
        #admin-inbox-messages .admin-structured-title {
            font-weight: 700;
            color: #2f353b;
            flex: 1 1 auto;
            min-width: 180px;
        }
        #admin-inbox-messages .admin-structured-badge {
            font-size: 10px;
            line-height: 1.2;
            padding: 3px 7px;
        }
        #admin-inbox-messages .admin-structured-list {
            margin: 6px 0 0 18px;
            padding: 0;
        }
        #admin-inbox-messages .admin-structured-note {
            margin-top: 8px;
        }
        /* ── Medical Documents section ── */
        #admin-inbox-messages .mt-docs-section,
        #admin-inbox-docs-content .mt-docs-section {
            border: 1px solid #d4e6f1;
            border-radius: 4px;
            background: #eaf4fb;
            padding: 10px 12px;
            margin-bottom: 14px;
        }
        #admin-inbox-messages .mt-docs-header,
        #admin-inbox-docs-content .mt-docs-header {
            font-size: 13px;
            color: #2471a3;
            margin-bottom: 8px;
        }
        #admin-inbox-messages .mt-docs-icon,
        #admin-inbox-docs-content .mt-docs-icon { margin-right: 3px; }
        #admin-inbox-messages .mt-docs-empty,
        #admin-inbox-docs-content .mt-docs-empty {
            margin: 4px 0 0;
            font-style: italic;
            font-size: 12px;
        }
        #admin-inbox-messages .mt-docs-list,
        #admin-inbox-docs-content .mt-docs-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        #admin-inbox-messages .mt-doc-row,
        #admin-inbox-docs-content .mt-doc-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            flex-wrap: wrap;
            background: #fff;
            border-radius: 3px;
            padding: 5px 8px;
        }
        #admin-inbox-messages .mt-doc-type,
        #admin-inbox-docs-content .mt-doc-type { flex: 0 0 auto; }
        #admin-inbox-messages .mt-doc-main,
        #admin-inbox-docs-content .mt-doc-main {
            flex: 1 1 220px;
            min-width: 160px;
        }
        #admin-inbox-messages .mt-doc-title,
        #admin-inbox-docs-content .mt-doc-title {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2f353b;
            text-decoration: none;
            word-break: break-word;
        }
        #admin-inbox-messages .mt-doc-title:hover,
        #admin-inbox-docs-content .mt-doc-title:hover {
            text-decoration: underline;
            color: #1a73e8;
        }
        #admin-inbox-messages .mt-doc-name,
        #admin-inbox-docs-content .mt-doc-name {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            word-break: break-all;
            color: #7f8c9d;
        }
        #admin-inbox-messages .mt-doc-note,
        #admin-inbox-docs-content .mt-doc-note {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #5f6c7b;
        }
        #admin-inbox-messages .mt-doc-date,
        #admin-inbox-docs-content .mt-doc-date {
            flex: 0 0 auto;
            font-size: 11px;
            white-space: nowrap;
        }
        #admin-inbox-messages .mt-doc-download,
        #admin-inbox-docs-content .mt-doc-download { flex: 0 0 auto; }
        /* ── Section divider ── */
        #admin-inbox-messages .mt-section-divider {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #95a5a6;
            border-bottom: 1px solid #eef1f5;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        /* ── Doc Viewer Modal ── */
        #adminDocViewerModal .mt-dv-type-badge {
            font-size: 12px;
            vertical-align: middle;
            margin-left: 6px;
        }
        #adminDocViewerModal .mt-dv-filename {
            word-break: break-all;
            font-weight: 600;
            color: #2f353b;
        }
        #adminDocViewerModal .mt-dv-meta {
            font-size: 11px;
            color: #7f8c9d;
            margin-top: 3px;
        }
        #adminDocViewerModal .mt-dv-preview-wrap {
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
        #adminDocViewerModal .mt-dv-preview-wrap iframe {
            width: 100%;
            height: 80vh;
            max-height: 80vh;
            border: none;
            display: block;
        }
        #adminDocViewerModal .mt-dv-preview-wrap img {
            max-width: 100%;
            max-height: 80vh;
            display: block;
            margin: auto;
        }
        #adminDocViewerModal .mt-dv-no-preview {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c9d;
        }
        #adminDocViewerModal .mt-dv-no-preview .fa {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
            color: #bdc3c7;
        }
        #adminAttachDocumentModal .help-block {
            margin-bottom: 0;
        }
        #adminAttachDocumentModal .mt-attach-context {
            margin-top: 8px;
            font-size: 12px;
            color: #7f8c8d;
        }
        #admin-chat-attach-status {
            margin-top: 8px;
            display: none;
        }
        /* Responsive: full-screen on mobile */
        @media (max-width: 767px) {
            #adminDocViewerModal .modal-dialog {
                margin: 0;
                width: 100%;
            }
            #adminDocViewerModal .modal-content {
                border-radius: 0;
                min-height: 100vh;
            }
            #adminDocViewerModal .mt-dv-preview-wrap iframe {
                height: 60vh;
                max-height: 60vh;
            }
            #adminDocViewerModal .mt-dv-preview-wrap img {
                max-height: 60vh;
            }
        }
        /* ── Chat bubble rows ── */
        #admin-inbox-messages {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        #admin-inbox-messages .mt-msg-row {
            display: flex;
            width: 100%;
            margin-bottom: 6px;
        }
        #admin-inbox-messages .mt-msg-row--own  { justify-content: flex-end; }
        #admin-inbox-messages .mt-msg-row--other { justify-content: flex-start; }
        #admin-inbox-messages .mt-msg {
            max-width: 70%;
            min-width: 80px;
            padding: 10px 12px;
            border-radius: 16px;
            word-break: break-word;
            overflow-wrap: anywhere;
            box-shadow: 0 1px 3px rgba(0,0,0,.07);
        }
        #admin-inbox-messages .mt-bubble-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }
        #admin-inbox-messages .mt-bubble-name {
            font-size: 11px;
            font-weight: 600;
            opacity: .8;
            white-space: nowrap;
        }
        #admin-inbox-messages .mt-bubble-time {
            font-size: 10px;
            opacity: .55;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #admin-inbox-messages .mt-bubble-body { line-height: 1.5; }
        #admin-inbox-messages .mt-shared-doc-card {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(26,115,232,.16);
        }
        #admin-inbox-messages .mt-shared-doc-label {
            font-size: 12px;
            font-weight: 600;
            opacity: .85;
        }
        #admin-inbox-messages .mt-shared-doc-name {
            margin-top: 4px;
            word-break: break-word;
            font-size: 15px;
            font-weight: 600;
        }
        #admin-inbox-messages .mt-shared-doc-meta,
        #admin-inbox-messages .mt-shared-doc-file,
        #admin-inbox-messages .mt-shared-doc-note {
            margin-top: 4px;
            font-size: 12px;
            opacity: .9;
        }
        #admin-inbox-messages .mt-shared-doc-actions {
            margin-top: 6px;
        }
        #admin-inbox-messages .mt-shared-doc-link {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
            position: relative;
            z-index: 1;
        }
        #admin-inbox-messages .mt-bubble-status {
            margin-top: 4px;
            font-size: 10px;
            opacity: .7;
        }
        /* Structured / system actions — neutral grey (full width) */
        #admin-inbox-messages .mt-msg-system {
            background: #f4f6f7;
            border: 1px solid #d5dce4;
            max-width: 100%;
            border-radius: 8px;
        }
        /* Own messages — right-aligned teal-blue */
        #admin-inbox-messages .mt-msg-row--own .mt-msg-human {
            background: #1a73e8;
            color: #fff;
            border-radius: 16px 16px 4px 16px;
        }
        #admin-inbox-messages .mt-msg-row--own .mt-shared-doc-card {
            border-top-color: rgba(255,255,255,.28);
        }
        #admin-inbox-messages .mt-msg-row--own .mt-shared-doc-label,
        #admin-inbox-messages .mt-msg-row--own .mt-shared-doc-name,
        #admin-inbox-messages .mt-msg-row--own .mt-shared-doc-link {
            color: #fff;
        }
        #admin-inbox-messages .mt-msg-row--own .mt-msg-human .mt-bubble-time { opacity: .7; }
        /* Other messages — left-aligned light grey */
        #admin-inbox-messages .mt-msg-row--other .mt-msg-human {
            background: #f0f2f5;
            color: #2c3e50;
            border-radius: 16px 16px 16px 4px;
            border: 1px solid #e0e4ea;
        }
        #admin-inbox-messages .mt-msg-row--other .mt-shared-doc-label {
            color: #51606f;
        }
        #admin-inbox-messages .mt-msg-row--other .mt-shared-doc-link {
            color: #1a73e8;
        }
        #admin-inbox-messages .mt-msg-row--grouped {
            margin-bottom: 2px;
        }
        #admin-inbox-messages .mt-msg-row--system {
            justify-content: center;
        }
        #admin-typing-indicator {
            font-size: 12px;
            color: #7f8c9d;
            margin-top: 6px;
            display: none;
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
                <h1><?php echo htmlspecialchars($page_heading, ENT_QUOTES); ?></h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Home</a></li>
                    <li class="active"><?php echo htmlspecialchars($page_breadcrumb, ENT_QUOTES); ?></li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="alert alert-<?php echo $page_intro_class; ?>" style="margin-bottom:15px;">
                    <strong><?php echo htmlspecialchars($page_intro_title, ENT_QUOTES); ?></strong><br>
                    <?php echo htmlspecialchars($page_intro_body, ENT_QUOTES); ?>
                </div>
                <div class="inbox">
                    <div class="row">
                        <div class="col-md-3 inbox-sidebar">
                            <button type="button" class="btn btn-sm btn-default compose-btn btn-block" id="admin-inbox-refresh">
                                <i class="fa fa-refresh"></i> Actualizar
                            </button>
                            <ul class="inbox-nav" id="admin-inbox-thread-list">
                                <li><a href="javascript:;">Cargando conversaciones...</a></li>
                            </ul>
                        </div>
                        <div class="col-md-9 inbox-body">
                            <div id="admin-inbox-help-wrap" style="margin-bottom:12px;">
                                <button type="button" class="btn btn-xs btn-default" id="admin-inbox-help-toggle" aria-expanded="false" aria-controls="admin-inbox-help-collapse">
                                    ¿Cómo usar la botonera?
                                </button>
                                <div id="admin-inbox-help-collapse" class="panel panel-default collapse" style="margin-top:8px; margin-bottom:0;">
                                    <div class="panel-heading" style="cursor:pointer;" id="admin-inbox-help-header">
                                        <strong>🛈 Guía rápida para usar la botonera</strong>
                                    </div>
                                    <div class="panel-body" style="padding-top:10px;">
                                        <p style="margin-bottom:8px;"><strong>Guía rápida: cómo usar este Inbox</strong></p>
                                        <ul style="margin:0 0 0 18px; padding:0;">
                                            <li>Puedes escribir mensajes libres desde el inicio de la conversación.</li>
                                            <li>Usa los botones de “Acciones formales” cuando necesites registrar decisiones o solicitudes con efecto operativo:</li>
                                            <li style="list-style:none; margin-left:8px;">• “FECHAS DISPONIBLES / FECHAS NO DISPONIBLES”: confirma disponibilidad de fechas.</li>
                                            <li style="list-style:none; margin-left:8px;">• “SOLICITAR HISTORIA CLÍNICA / LABORATORIOS / IMÁGENES / FOTOGRAFÍAS”: solicita información clínica específica.</li>
                                            <li style="list-style:none; margin-left:8px;">• “APROBACIÓN FINAL”: úsalo solo cuando el caso esté listo para aprobación final.</li>
                                            <li style="list-style:none; margin-left:8px;">• “NO ELEGIBLE”: si el paciente no aplica para el servicio.</li>
                                            <li>En “Acciones estructuradas”:</li>
                                            <li style="list-style:none; margin-left:8px;">• “SOLICITAR INFORMACIÓN ADICIONAL”: pide documentos o datos faltantes (queda registrado como tarjeta).</li>
                                            <li style="list-style:none; margin-left:8px;">• “PROPONER AJUSTE DE COTIZACIÓN”: propone ajuste de cotización con justificación.</li>
                                            <li style="list-style:none; margin-left:8px;">• “PROPONER REUNIÓN”: agenda una propuesta real con fecha y hora para que el paciente la acepte o pida cambio.</li>
                                            <li>El paciente responderá con botones (Aceptar / Pedir cambios / Rechazar) o subirá documentos.</li>
                                            <li>Los mensajes libres no cambian estados por sí solos.</li>
                                            <li>Importante: Mantén toda la comunicación aquí para trazabilidad. No uses mensajes externos.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="inbox-header">
                                <div id="admin-inbox-title">Selecciona un hilo</div>
                            </div>
                            <div class="inbox-content" id="admin-inbox-content" style="display:none;">
                                <div id="admin-inbox-docs-panel" class="panel panel-default" style="display:none;margin-bottom:12px;">
                                    <div class="panel-heading" style="padding:8px 12px;">
                                        <a href="#admin-inbox-docs-collapse" data-toggle="collapse" aria-expanded="false" aria-controls="admin-inbox-docs-collapse" style="display:flex;align-items:center;justify-content:space-between;text-decoration:none;">
                                            <span><i class="fa fa-paperclip" aria-hidden="true"></i> Ver documentos compartidos</span>
                                            <span class="badge" id="admin-inbox-docs-count">0</span>
                                        </a>
                                    </div>
                                    <div id="admin-inbox-docs-collapse" class="panel-collapse collapse">
                                        <div class="panel-body" id="admin-inbox-docs-content" style="padding:10px;"></div>
                                    </div>
                                </div>
                                <div id="admin-inbox-messages" style="max-height:420px;overflow:auto;border:1px solid #eef1f5;padding:12px;background:#fff;"></div>
                                <div id="admin-inbox-fee-alert" class="note note-warning" style="display:none;margin-top:12px;">
                                    <strong>Condición de coordinación pendiente.</strong>
                                    La mensajería libre queda bloqueada solo por esta condición comercial. Puedes seguir usando las acciones formales.
                                </div>
                                <div id="admin-inbox-commission-alert" class="note note-info" style="display:none;margin-top:12px;">
                                    <strong>Estado de comisión.</strong>
                                    Esta solicitud tiene una condición comercial pendiente.
                                </div>
                                <div id="admin-inbox-quick-replies" style="display:none;margin-top:12px;">
                                    <label>Acciones formales rápidas</label>
                                    <div class="btn-group btn-group-xs" role="group" style="display:flex;flex-wrap:wrap;gap:6px;">
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="DATES_AVAILABLE">FECHAS DISPONIBLES</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="DATES_NOT_AVAILABLE">FECHAS NO DISPONIBLES</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_MEDICAL_HISTORY">SOLICITAR HISTORIA CLÍNICA</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_LABS">SOLICITAR LABORATORIOS</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_IMAGING">SOLICITAR IMÁGENES</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="REQUEST_PHOTOS">SOLICITAR FOTOGRAFÍAS</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="FINAL_APPROVED">APROBACIÓN FINAL</button>
                                        <button type="button" class="btn btn-default btn-xs admin-quick-reply" data-reply="FINAL_NOT_ELIGIBLE">NO ELEGIBLE</button>
                                    </div>
                                    <div id="admin-inbox-structured-actions" style="display:none;margin-top:10px;">
                                        <label>Acciones estructuradas</label>
                                        <div class="btn-group btn-group-xs" role="group" style="display:flex;flex-wrap:wrap;gap:6px;">
                                            <button type="button" class="btn btn-default btn-xs" id="admin-open-request-info">SOLICITAR INFORMACIÓN ADICIONAL</button>
                                            <button type="button" class="btn btn-default btn-xs" id="admin-open-propose-quote">PROPONER AJUSTE DE COTIZACIÓN</button>
                                            <button type="button" class="btn btn-default btn-xs" id="admin-open-propose-meeting">PROPONER REUNIÓN</button>
                                        </div>
                                    </div>
                                </div>
                                <form id="admin-inbox-send-form" style="margin-top:12px;">
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <label for="admin-inbox-message">Escribir mensaje</label>
                                        <textarea class="form-control" id="admin-inbox-message" rows="3" maxlength="2000" placeholder="Escribe tu mensaje..."></textarea>
                                    </div>
                                    <div id="admin-typing-indicator" style="font-size:12px;color:#999;min-height:18px;margin-bottom:4px;">El paciente está escribiendo…</div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                        <button type="button" class="btn btn-default btn-sm" id="admin-chat-attach-btn"><i class="fa fa-paperclip"></i> Adjuntar documento</button>
                                        <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto;"><i class="fa fa-paper-plane"></i> Enviar</button>
                                    </div>
                                    <div id="admin-chat-attach-status" class="text-muted"></div>
                                    <div class="text-muted" style="margin-top:8px;">El chat queda libre desde el inicio. Usa las acciones formales de arriba cuando necesites registrar una decisión o solicitud con efecto operativo. Los mensajes libres no cambian el estado por sí solos.</div>
                                    <div id="admin-inbox-compose-note" class="text-muted" style="margin-top:8px;display:none;"></div>
                                </form>
                            </div>
                            <div class="inbox-content" id="admin-inbox-empty">
                                <div class="note note-info" style="margin:0;">Selecciona un hilo en el panel izquierdo.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer;?>
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

<div class="modal fade" id="adminAttachDocumentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="admin-attach-document-form">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Adjuntar documento</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="admin-attach-thread-id" value="">
                    <input type="hidden" id="admin-attach-thread-type" value="">
                    <input type="hidden" id="admin-attach-request-id" value="">
                    <input type="hidden" id="admin-attach-item-id" value="">
                    <div class="form-group">
                        <label for="admin-attach-file">Seleccionar archivo</label>
                        <input type="file" class="form-control" id="admin-attach-file" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" required>
                    </div>
                    <div class="form-group">
                        <label for="admin-attach-title">Título del documento</label>
                        <input type="text" class="form-control" id="admin-attach-title" maxlength="190" required>
                    </div>
                    <div class="form-group">
                        <label for="admin-attach-type">Tipo de documento</label>
                        <select class="form-control" id="admin-attach-type" required>
                            <option value="other">Otro</option>
                            <option value="medical_history">Historia clínica</option>
                            <option value="lab_results">Examen / laboratorio</option>
                            <option value="diagnostic_imaging">Imagen diagnóstica</option>
                            <option value="quote">Cotización</option>
                            <option value="consent_form">Consentimiento</option>
                            <option value="medical_order">Orden médica</option>
                            <option value="prescription">Fórmula / indicación</option>
                            <option value="administrative_document">Documento administrativo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="admin-attach-note">Observación (opcional)</label>
                        <textarea class="form-control" id="admin-attach-note" rows="3" maxlength="500" placeholder="Ejemplo: soporte enviado por el paciente para revisión inicial"></textarea>
                    </div>
                    <p class="help-block">El documento se adjuntará al hilo actual y quedará disponible en el chat y en la lista de documentos compartidos.</p>
                    <div class="mt-attach-context" id="admin-attach-context">Contexto sin definir.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="admin-attach-submit-btn">Adjuntar al chat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="adminRequestInfoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Solicitar información adicional</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Documentos requeridos</label>
                    <div class="checkbox-list" id="admin-request-info-types">
                        <label><input type="checkbox" value="labs"> Laboratorios</label>
                        <label><input type="checkbox" value="imaging"> Imágenes</label>
                        <label><input type="checkbox" value="photos"> Fotografías</label>
                        <label><input type="checkbox" value="medical_history"> Historia clínica</label>
                        <label><input type="checkbox" value="other"> Otro</label>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="admin-request-info-note">Nota breve</label>
                    <textarea class="form-control" id="admin-request-info-note" rows="3" maxlength="500" placeholder="¿Qué necesitas del cliente?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="admin-submit-request-info">Enviar solicitud</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminProposeQuoteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Proponer ajuste de cotización</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="admin-propose-amount">Monto</label>
                            <input type="number" class="form-control" id="admin-propose-amount" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="admin-propose-currency">Moneda</label>
                            <input type="text" class="form-control" id="admin-propose-currency" maxlength="10" value="USD">
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="admin-propose-notes">Justificación / notas</label>
                    <textarea class="form-control" id="admin-propose-notes" rows="3" maxlength="500" placeholder="Explica por qué se necesita este ajuste"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="admin-submit-propose-quote">Enviar propuesta</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminProposeMeetingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Proponer reunión en MedTravel</h4>
            </div>
            <div class="modal-body">
                <p class="help-block" style="margin-top:0;">La propuesta base siempre queda registrada dentro de MedTravel. Google Calendar y Google Meet son opcionales.</p>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="admin-meeting-start-at">Inicio</label>
                            <input type="datetime-local" class="form-control" id="admin-meeting-start-at">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="admin-meeting-end-at">Fin</label>
                            <input type="datetime-local" class="form-control" id="admin-meeting-end-at">
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="admin-meeting-note">Nota opcional</label>
                    <textarea class="form-control" id="admin-meeting-note" rows="3" maxlength="500" placeholder="Contexto adicional para la propuesta de reunión"></textarea>
                </div>
                <div class="form-group" style="margin-top:15px;margin-bottom:0;">
                    <label>Integraciones opcionales al aceptar</label>
                    <div class="checkbox-list">
                        <label><input type="checkbox" id="admin-meeting-enable-calendar"> Agregar evento a Google Calendar</label>
                        <label><input type="checkbox" id="admin-meeting-enable-meet"> Crear enlace de Google Meet</label>
                    </div>
                    <p class="help-block" id="admin-meeting-integration-help" style="margin-bottom:0;">Si no marcas ninguna opción, la propuesta quedará solo en MedTravel.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="admin-submit-propose-meeting">Enviar propuesta</button>
            </div>
        </div>
    </div>
</div>

<?php echo $theme_layout_script;?>
<script type="text/javascript">
window.AdminInboxHelpConfig = {
    userId: <?php echo (int)($_SESSION['id_usuario'] ?? 0); ?>,
    role: <?php echo json_encode((string)($_SESSION['rol'] ?? '')); ?>,
    isLinkedMedicalStaffSession: <?php echo $is_linked_medical_staff_session ? 'true' : 'false'; ?>,
    realtimeBaseUrl: <?php echo json_encode(MT_REALTIME_BASE_URL); ?>,
    realtimeSocketPath: <?php echo json_encode(MT_REALTIME_SOCKET_PATH); ?>,
    realtimeTokenUrl: "/admin/ajax/realtime_token.php"
};
</script>
<script src="<?php echo htmlspecialchars(rtrim((string)MT_REALTIME_BASE_URL, '/'), ENT_QUOTES, 'UTF-8'); ?>/realtime/socket.io/socket.io.js"></script>
<script src="js/app_inbox.js" type="text/javascript"></script>
</body>
</html>
