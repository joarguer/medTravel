<?php
include('include/include.php');

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$service_provider_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
$can_admin_view = is_coordination_admin_session();
$is_full_admin = is_role_admin_session();
$is_administrative_coordination = is_administrative_session();
$is_care_only_coordination = $is_administrative_coordination && !$is_full_admin;
$is_linked_medical_staff_session = is_provider_linked_medical_staff_session($conexion ?? null);
$page_heading = $is_linked_medical_staff_session ? 'Agenda asignada' : ($is_care_only_coordination ? 'Agenda de Coordinación' : 'Agenda');
$page_breadcrumb = $page_heading;
$page_caption = $is_linked_medical_staff_session ? 'Agenda operativa del staff asignado' : ($is_care_only_coordination ? 'Agenda CARE de MedTravel Coordination' : 'Agenda de coordinación');
$page_intro_class = $is_linked_medical_staff_session ? 'info' : ($is_care_only_coordination ? 'info' : 'warning');
$page_intro_title = $is_linked_medical_staff_session ? 'Coordinación sobre solicitudes asignadas' : ($is_care_only_coordination ? 'Coordinación MedTravel sobre solicitudes CARE' : 'Coordinación y supervisión del prestador');
$page_intro_body = $is_linked_medical_staff_session
    ? 'Esta agenda muestra únicamente citas y propuestas de los items que ya quedaron bajo tu responsabilidad operativa.'
    : ($is_care_only_coordination
        ? 'Esta agenda muestra únicamente eventos CARE del scope de coordinación MedTravel. No expone agendas ITEM ni superficies administrativas globales.'
        : 'Cuando un item ya tiene staff asignado, la coordinación normal debe llevarla esa persona. Desde aquí puedes mantener visibilidad total e intervenir como supervisión cuando haga falta.');
$page_guide = $is_linked_medical_staff_session
    ? 'Selecciona una solicitud asignada y luego elige fecha y hora para proponer o ajustar una coordinación con el paciente.'
    : ($is_care_only_coordination ? 'Selecciona una solicitud CARE y luego elige fecha y hora para registrar la coordinación MedTravel.' : 'Selecciona un hilo ITEM y luego elige fecha y hora para proponer un horario coordinado.');
$item_select_label = $is_linked_medical_staff_session ? 'Solicitud asignada' : 'Hilo ITEM';
$item_select_placeholder = $is_linked_medical_staff_session ? 'Selecciona una solicitud asignada...' : 'Selecciona un ITEM...';
if (!$can_admin_view && $provider_id <= 0 && $service_provider_id <= 0) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$can_create = $can_admin_view || $provider_id > 0 || $service_provider_id > 0;
$can_update = $can_create;
$can_delete = $can_admin_view;
$can_cancel = $can_update;
$calendar_request_base = $is_full_admin ? 'booking_requests.php' : ($is_care_only_coordination ? 'app_inbox.php' : 'my_booking_requests.php');
$calendar_request_mode = $is_care_only_coordination ? 'care_thread' : 'request';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - <?php echo htmlspecialchars($page_heading, ENT_QUOTES); ?></title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <link href="/assets/global/plugins/fullcalendar/fullcalendar.min.css" rel="stylesheet" type="text/css" />
    <style>
        #admin-calendar-create-modal .modal-dialog {
            width: 760px;
            max-width: calc(100% - 30px);
        }

        #admin-calendar-create-modal .input-group {
            width: 100%;
            table-layout: fixed;
        }

        #admin-calendar-create-modal .input-group .form-control,
        #admin-calendar-create-modal input[type="datetime-local"].form-control {
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        #admin-calendar-create-modal .input-group-addon {
            width: 1%;
            white-space: nowrap;
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
                    <li><a href="index.php">Inicio</a></li>
                    <li class="active"><?php echo htmlspecialchars($page_breadcrumb, ENT_QUOTES); ?></li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption">
                            <i class="icon-calendar font-blue"></i>
                            <span class="caption-subject font-blue bold uppercase"><?php echo htmlspecialchars($page_caption, ENT_QUOTES); ?></span>
                        </div>
                        <div class="actions">
                            <?php if ($is_full_admin): ?>
                            <select id="admin-calendar-filter" class="form-control input-sm" style="min-width:180px;">
                                <option value="ALL">Todos los eventos</option>
                                <option value="CARE">Hilos CARE</option>
                                <option value="ITEM">Hilos ITEM</option>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="alert alert-<?php echo $page_intro_class; ?>" style="margin-bottom:15px;">
                            <strong><?php echo htmlspecialchars($page_intro_title, ENT_QUOTES); ?></strong><br>
                            <?php echo htmlspecialchars($page_intro_body, ENT_QUOTES); ?>
                        </div>
                        <div id="admin-calendar-provider-guide" class="alert alert-info" style="display:none; margin-bottom:15px;">
                            <?php echo htmlspecialchars($page_guide, ENT_QUOTES); ?>
                        </div>
                        <div id="admin-calendar-item-selector-wrap" style="display:none; margin-bottom:15px;">
                            <label for="admin-calendar-item-select" style="font-weight:600; margin-right:8px;"><?php echo htmlspecialchars($item_select_label, ENT_QUOTES); ?></label>
                            <select id="admin-calendar-item-select" class="form-control input-sm" style="display:inline-block; min-width:260px; max-width:420px;">
                                <option value=""><?php echo htmlspecialchars($item_select_placeholder, ENT_QUOTES); ?></option>
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
                    <h4 class="modal-title"><i class="icon-calendar"></i> <span id="admin-calendar-create-title">Proponer horario</span></h4>
                    <p id="admin-calendar-create-subtitle" class="help-block" style="margin:8px 0 0; display:none;">Esta propuesta se enviará al paciente para su revisión.</p>
                </div>
                <div class="modal-body">
                    <div id="admin-calendar-create-summary" class="alert alert-info" style="display:none; margin-bottom:15px;"></div>
                    <div class="row">
                        <div class="col-sm-6">
                            <h5 style="margin-top:0; margin-bottom:12px;">Detalle de coordinación</h5>
                            <div class="form-group">
                                <label>Título</label>
                                <input type="text" class="form-control" name="title" required maxlength="255">
                            </div>
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea class="form-control" name="description" rows="4"></textarea>
                            </div>
                            <div class="form-group" id="admin-calendar-create-status-group">
                                <label>Estado</label>
                                <select class="form-control" name="status" id="admin-calendar-create-status">
                                    <option value="scheduled">scheduled</option>
                                    <option value="proposed">proposed</option>
                                    <option value="confirmed">confirmed</option>
                                    <option value="cancelled">cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Modalidad de la cita</label>
                                <select class="form-control" name="appointment_mode" id="admin-calendar-create-appointment-mode">
                                    <option value="in_person" selected>Presencial</option>
                                    <option value="virtual">Virtual</option>
                                    <option value="travel">Asociada a viaje</option>
                                </select>
                                <small class="text-muted">Define explícitamente si la coordinación es virtual, presencial o asociada a viaje.</small>
                            </div>
                            <div class="form-group" id="admin-calendar-create-status-readonly-group" style="display:none;">
                                <label>Estado</label>
                                <p class="form-control-static" id="admin-calendar-create-status-readonly" style="font-weight:600;">Propuesta enviada</p>
                                <small class="text-muted">El paciente deberá revisar esta propuesta de horario.</small>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <h5 style="margin-top:0; margin-bottom:12px;">Horario</h5>
                            <div class="form-group" id="admin-calendar-create-type-group">
                                <label>Tipo de evento</label>
                                <select class="form-control" name="event_type" id="admin-calendar-create-type">
                                    <?php if ($is_full_admin): ?>
                                    <option value="CARE">CARE</option>
                                    <option value="ITEM" selected>ITEM</option>
                                    <?php elseif ($is_care_only_coordination): ?>
                                    <option value="CARE" selected>CARE</option>
                                    <?php else: ?>
                                    <option value="ITEM" selected>ITEM</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group" id="admin-calendar-create-type-readonly-group" style="display:none;">
                                <label>Tipo de evento</label>
                                <p class="form-control-static" id="admin-calendar-create-type-readonly" style="font-weight:600;">ITEM</p>
                            </div>
                            <div class="form-group" id="admin-calendar-create-item-group">
                                <label>Item (obligatorio)</label>
                                <select class="form-control" name="item_id" id="admin-calendar-create-item-select">
                                    <option value="">Selecciona un item (obligatorio)</option>
                                </select>
                                <small class="text-muted">Selecciona el item al que pertenece esta coordinación.</small>
                                <div class="help-block text-danger" id="admin-calendar-create-item-error" style="display:none;"></div>
                            </div>
                            <div class="form-group" id="admin-calendar-create-request-group">
                                <label>Solicitud</label>
                                <select class="form-control" name="request_id" id="admin-calendar-create-request-select">
                                    <option value="">Selecciona una solicitud</option>
                                </select>
                                <div class="help-block text-danger" id="admin-calendar-create-request-error" style="display:none;"></div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Fecha y hora de inicio</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                            <input type="datetime-local" class="form-control" name="start_at" required>
                                        </div>
                                        <div class="help-block text-danger" id="admin-calendar-create-start-error" style="display:none;"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Fecha y hora de fin</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="fa fa-calendar-o"></i></span>
                                            <input type="datetime-local" class="form-control" name="end_at">
                                        </div>
                                        <div class="help-block text-danger" id="admin-calendar-create-end-error" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="mt-checkbox mt-checkbox-outline">
                                    <input type="checkbox" name="all_day" value="1"> Evento de todo el día
                                    <span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn blue" id="admin-calendar-create-submit">Proponer horario</button>
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
                    <h4 class="modal-title">Detalle de cita coordinada</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" class="form-control" name="title" id="admin-calendar-detail-title" required maxlength="255">
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Inicio</label>
                                <input type="datetime-local" class="form-control" name="start_at" id="admin-calendar-detail-start" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Fin</label>
                                <input type="datetime-local" class="form-control" name="end_at" id="admin-calendar-detail-end">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Tipo</label>
                                <input type="text" class="form-control" id="admin-calendar-detail-type" readonly>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Solicitud</label>
                                <input type="number" class="form-control" name="request_id" id="admin-calendar-detail-request">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Item</label>
                                <input type="number" class="form-control" name="item_id" id="admin-calendar-detail-item">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select class="form-control" name="status" id="admin-calendar-detail-status">
                            <option value="scheduled">scheduled</option>
                            <option value="proposed">proposed</option>
                            <option value="confirmed">confirmed</option>
                            <option value="cancelled">cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modalidad de la cita</label>
                        <select class="form-control" name="appointment_mode" id="admin-calendar-detail-appointment-mode">
                            <option value="in_person">Presencial</option>
                            <option value="virtual">Virtual</option>
                            <option value="travel">Asociada a viaje</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea class="form-control" name="description" id="admin-calendar-detail-description" rows="3"></textarea>
                    </div>
                    <div id="admin-calendar-detail-sync-note" class="alert alert-warning" style="display:none; margin-bottom:15px;"></div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="all_day" id="admin-calendar-detail-allday" value="1"> Todo el día</label>
                    </div>
                    <hr>
                    <p style="margin-bottom:0;">
                        <a href="#" id="admin-calendar-open-request" target="_blank"><?php echo $is_care_only_coordination ? 'Abrir seguimiento CARE' : 'Abrir solicitud'; ?></a> |
                        <a href="#" id="admin-calendar-open-inbox" target="_blank">Abrir hilo en Inbox</a>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn default" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn red" id="admin-calendar-delete-btn" <?php echo $can_cancel ? '' : 'style="display:none;"'; ?>>Cancelar evento</button>
                    <button type="submit" class="btn blue" <?php echo $can_update ? '' : 'style="display:none;"'; ?>>Guardar</button>
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
    isFullAdmin: <?php echo $is_full_admin ? 'true' : 'false'; ?>,
    isAdministrativeCoordination: <?php echo $is_administrative_coordination ? 'true' : 'false'; ?>,
    isCareOnlyCoordination: <?php echo $is_care_only_coordination ? 'true' : 'false'; ?>,
    isProvider: <?php echo (!$can_admin_view && ($provider_id > 0 || $service_provider_id > 0)) ? 'true' : 'false'; ?>,
    isLinkedMedicalStaffSession: <?php echo $is_linked_medical_staff_session ? 'true' : 'false'; ?>,
    canCreate: <?php echo $can_create ? 'true' : 'false'; ?>,
    canUpdate: <?php echo $can_update ? 'true' : 'false'; ?>,
    canCancel: <?php echo $can_cancel ? 'true' : 'false'; ?>,
    canDelete: <?php echo $can_delete ? 'true' : 'false'; ?>,
    listUrl: 'ajax/calendar.php',
    inboxBase: 'app_inbox.php',
    requestBase: '<?php echo $calendar_request_base; ?>',
    requestMode: '<?php echo $calendar_request_mode; ?>'
};
</script>
<script src="js/app_calendar.js" type="text/javascript"></script>
</body>
</html>
