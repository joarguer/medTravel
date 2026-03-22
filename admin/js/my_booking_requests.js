(function () {
    var table = null;
    var activeDetailItemId = 0;
    var activeDetailData = null;

    $(document).ready(function () {
        initTable();
        bindEvents();
        loadRows();
    });

    function initTable() {
        var $table = $('#my_booking_requests_table');
        if (!$table.length) {
            return;
        }

        if ($.fn.dataTable.isDataTable($table)) {
            $table.DataTable().clear().destroy();
        }

        table = $table.DataTable({
            destroy: true,
            data: [],
            columns: [
                { data: 'booking_request_id' },
                {
                    data: 'booking_created_at',
                    render: function (value) {
                        return formatDateTime(value);
                    }
                },
                {
                    data: null,
                    render: function (row) {
                        return escapeHtml(row.destination || '-') + '<br><small>' + escapeHtml(buildTimeline(row)) + '</small>';
                    }
                },
                {
                    data: 'item_type',
                    render: function (value) {
                        if (value === 'medical_offer') return '<span class="label label-info">Médico</span>';
                        if (value === 'complementary_service') return '<span class="label label-warning">Complementario</span>';
                        return '<span class="label label-default">' + escapeHtml(value || '-') + '</span>';
                    }
                },
                { data: 'item_name' },
                {
                    data: 'item_status',
                    render: function (value) {
                        return renderStatusBadge(value, { label: genericStatusLabelEs(value) });
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function (row) {
                        var html = '<button class="btn btn-xs btn-primary btn-view" data-id="' + row.item_id + '"><i class="fa fa-eye"></i> Ver</button>';
                        html += ' <button class="btn btn-xs purple btn-open-calendar" data-item-id="' + row.item_id + '"><i class="fa fa-calendar"></i> Calendario</button>';
                        return html;
                    }
                }
            ],
            order: [[1, 'desc']],
            pageLength: 25,
            responsive: true,
            autoWidth: false
        });
    }

    function bindEvents() {
        $('#btn-reload-my-bookings').on('click', function () {
            loadRows();
        });

        $('#my_booking_requests_table').on('click', '.btn-view', function () {
            var itemId = parseInt($(this).data('id'), 10) || 0;
            if (itemId > 0) {
                loadDetail(itemId);
            }
        });

        $('#my_booking_requests_table').on('click', '.btn-open-calendar', function () {
            var itemId = parseInt($(this).data('item-id'), 10) || 0;
            openCalendar(itemId);
        });

        $('#btn-provider-reject-save').on('click', function () {
            var itemId = parseInt($('#provider_reject_item_id').val(), 10) || 0;
            var reason = ($('#provider_reject_reason').val() || '').trim();
            if (itemId <= 0) return;
            if (!reason) {
                toastr.error('Debes ingresar un motivo de rechazo');
                return;
            }
            sendProviderAction('provider_reject', { item_id: itemId, reason: reason }, function () {
                $('#provider_reject_modal').modal('hide');
                reloadActiveDetail();
            });
        });

        $('#btn-provider-propose-save').on('click', function () {
            var itemId = parseInt($('#provider_propose_item_id').val(), 10) || 0;
            var notes = ($('#provider_proposed_notes').val() || '').trim();
            if (itemId <= 0) return;
            if (!notes) {
                toastr.error('Debes ingresar notas para proponer una cita');
                return;
            }

            sendProviderAction('provider_propose_change', {
                item_id: itemId,
                proposed_date_from: ($('#provider_proposed_date_from').val() || '').trim(),
                proposed_date_to: ($('#provider_proposed_date_to').val() || '').trim(),
                proposed_price: ($('#provider_proposed_price').val() || '').trim(),
                proposed_currency: ($('#provider_proposed_currency').val() || 'USD').trim(),
                provider_notes: notes
            }, function () {
                $('#provider_propose_modal').modal('hide');
                reloadActiveDetail();
            });
        });

        $('#my_booking_detail_modal').on('click', '#btn-modal-provider-confirm', function () {
            if (!activeDetailItemId) return;
            if (!confirm('¿Deseas aceptar este caso?')) return;
            sendProviderAction('provider_confirm', { item_id: activeDetailItemId }, reloadActiveDetail);
        });

        $('#my_booking_detail_modal').on('click', '#btn-modal-provider-reject', function () {
            if (!activeDetailItemId) return;
            $('#provider_reject_item_id').val(activeDetailItemId);
            $('#provider_reject_reason').val('');
            $('#provider_reject_modal').modal('show');
        });

        $('#my_booking_detail_modal').on('click', '#btn-modal-provider-propose', function () {
            if (!activeDetailItemId) return;
            var currency = ($('#provider-modal-currency').val() || 'USD').toString().toUpperCase();
            if (currency !== 'USD' && currency !== 'COP') {
                currency = 'USD';
            }
            $('#provider_propose_item_id').val(activeDetailItemId);
            $('#provider_proposed_date_from').val('');
            $('#provider_proposed_date_to').val('');
            $('#provider_proposed_price').val('');
            $('#provider_proposed_currency').val(currency);
            $('#provider_proposed_notes').val('');
            $('#provider_propose_modal').modal('show');
        });

        $('#my_booking_detail_modal').on('click', '#btn-modal-assign-staff', function () {
            if (!activeDetailItemId) return;
            openAssignStaffModal();
        });

        $('#btn-assign-staff-save').on('click', function () {
            var itemId = parseInt($('#assign_staff_item_id').val(), 10) || 0;
            var staffId = parseInt($('#assign_staff_select').val(), 10) || 0;
            if (itemId <= 0 || staffId <= 0) {
                toastr.error('Debes seleccionar un médico o staff');
                return;
            }

            sendProviderAction('assign_staff', {
                item_id: itemId,
                staff_id: staffId
            }, function (response) {
                $('#assign_staff_modal').modal('hide');
                if (response && response.data) {
                    applyAssignmentUpdate(response.data);
                    return;
                }
                reloadActiveDetail();
            }, 'Asignación guardada');
        });

        $('#my_booking_detail_modal').on('click', '.btn-modal-open-inbox, .btn-modal-open-calendar', function () {
            var $btn = $(this);
            if ($btn.is('[disabled]') || $btn.attr('aria-disabled') === 'true') {
                var message = ($btn.data('lockedMessage') || '').toString().trim();
                if (message) {
                    toastr.warning(message);
                }
                return false;
            }
        });
    }

    function loadRows() {
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: { action: 'list' },
            success: function (response) {
                if (!response || !response.ok) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo cargar la información');
                    table.clear().draw();
                    return;
                }
                table.clear();
                table.rows.add(response.data || []);
                table.draw();
            },
            error: function () {
                toastr.error('Error de conexión al cargar solicitudes');
            }
        });
    }

    function loadDetail(itemId) {
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: { action: 'get_detail', item_id: itemId },
            success: function (response) {
                if (!response || !response.ok || !response.data) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo cargar el detalle');
                    return;
                }

                activeDetailItemId = itemId;
                var detailData = response.data || {};
                detailData.items_history = response.items_history || [];
                activeDetailData = detailData;

                $('#my_booking_detail_content').html(renderDetail(detailData));
                $('#my_booking_detail_modal').modal('show');
                loadMessages(itemId);
            },
            error: function () {
                toastr.error('Error de conexión al cargar detalle');
            }
        });
    }

    function reloadActiveDetail() {
        if (activeDetailItemId > 0) {
            loadRows();
            loadDetail(activeDetailItemId);
        }
    }

    function loadAssignableStaff(detailData, onSuccess) {
        detailData = detailData || activeDetailData || {};
        var providerId = parseInt(detailData.provider_id, 10) || 0;
        var offerId = parseInt(detailData.offer_id, 10) || 0;
        var serviceId = parseInt(detailData.service_id, 10) || 0;
        if (providerId <= 0 || (offerId <= 0 && serviceId <= 0)) {
            toastr.error('El item no tiene contexto suficiente para asignar médico');
            return;
        }

        $.ajax({
            url: 'ajax/provider_medical_staff.php',
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list_assignable_staff',
                provider_id: providerId,
                offer_id: offerId,
                service_id: serviceId
            },
            success: function (response) {
                if (!response || !response.ok) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo cargar el staff elegible');
                    return;
                }
                if (typeof onSuccess === 'function') {
                    onSuccess(response.items || []);
                }
            },
            error: function () {
                toastr.error('Error de conexión al cargar staff elegible');
            }
        });
    }

    function openAssignStaffModal() {
        if (!activeDetailData || parseInt(activeDetailData.can_assign_staff, 10) !== 1) {
            toastr.warning('Este item no admite asignación manual de médico');
            return;
        }

        $('#assign_staff_item_id').val(activeDetailItemId || 0);
        $('#assign_staff_select').html('<option value="">Cargando staff elegible...</option>').prop('disabled', true);
        $('#assign_staff_current_label').text((activeDetailData.assigned_doctor || 'Sin asignar').toString());
        $('#btn-assign-staff-save').prop('disabled', true);
        $('#assign_staff_modal').modal('show');

        loadAssignableStaff(activeDetailData, function (items) {
            var currentAssignedStaffId = parseInt(activeDetailData.assigned_staff_id, 10) || 0;
            var options = ['<option value="">Selecciona un médico o staff</option>'];
            (items || []).forEach(function (item) {
                var staffId = parseInt(item.id, 10) || 0;
                var label = (item.full_name || 'Staff #' + staffId).toString();
                var specialty = (item.specialty || '').toString().trim();
                var clinic = (item.clinic_name || '').toString().trim();
                var suffix = [];
                if (specialty) suffix.push(specialty);
                if (clinic) suffix.push(clinic);
                if (suffix.length) {
                    label += ' - ' + suffix.join(' / ');
                }
                options.push('<option value="' + staffId + '"' + (currentAssignedStaffId === staffId ? ' selected' : '') + '>' + escapeHtml(label) + '</option>');
            });

            if (!(items || []).length) {
                options = ['<option value="">No hay staff elegible para este caso</option>'];
            }

            $('#assign_staff_select').html(options.join('')).prop('disabled', false);
            $('#btn-assign-staff-save').prop('disabled', !(items || []).length);
        });
    }

    function applyAssignmentUpdate(payload) {
        payload = payload || {};
        if (!activeDetailData) {
            reloadActiveDetail();
            return;
        }

        activeDetailData.assigned_staff_id = parseInt(payload.assigned_staff_id, 10) || 0;
        activeDetailData.assigned_doctor = payload.assigned_doctor || null;
        activeDetailData.clinic = payload.clinic || null;
        activeDetailData.assigned_staff = payload.assigned_staff || null;
        activeDetailData.summary = activeDetailData.summary || {};
        activeDetailData.summary.assigned_doctor = payload.assigned_doctor || null;
        rerenderActiveDetail();
    }

    function rerenderActiveDetail(tabId) {
        if (!activeDetailData) {
            return;
        }

        var targetTabId = tabId || getActiveDetailTabId();
        $('#my_booking_detail_content').html(renderDetail(activeDetailData));
        if (targetTabId) {
            $('#my_booking_detail_modal a[href="' + targetTabId + '"]').tab('show');
        }
        if (activeDetailItemId > 0) {
            loadMessages(activeDetailItemId);
        }
    }

    function getActiveDetailTabId() {
        var activeHref = $('#my_booking_detail_modal .nav-tabs li.active a').attr('href');
        return activeHref || '#mt-detail-tab-summary';
    }

    function renderDetail(d) {
        var canShowLegacyActions = String(d.item_status || '') === 'pending_provider';
        var fee = d.coordination_fee || {};
        var actionsLocked = parseInt(d.coordination_actions_locked || 0, 10) === 1;
        var lockMessage = (d.coordination_pending_message || fee.message || '').toString();
        var inboxHref = 'app_inbox.php?thread_id=ITEM:' + encodeURIComponent(String(d.item_id || 0));
        var calendarHref = 'app_calendar.php?thread_type=ITEM&item_id=' + encodeURIComponent(String(d.item_id || 0)) + '&thread_id=' + encodeURIComponent('ITEM:' + String(d.item_id || 0));

        var html = '';
        html += '<div class="mt-request-detail">';
        html += '<input type="hidden" id="provider-modal-currency" value="' + escapeHtml(d.item_currency || 'USD') + '">';
        html += '<div class="mt-detail-sticky">';
        html += renderDetailHeader(d, fee, actionsLocked, lockMessage, inboxHref, calendarHref);
        html += '</div>';
        html += renderWorkflowGuide();
        html += renderDetailTabs(d, {
            canShowLegacyActions: canShowLegacyActions,
            actionsLocked: actionsLocked,
            lockMessage: lockMessage,
            inboxHref: inboxHref,
            calendarHref: calendarHref
        });
        html += '</div>';
        return html;
    }

    function renderDetailHeader(d, fee, actionsLocked, lockMessage, inboxHref, calendarHref) {
        var appointmentStatus = d.appointment_status || d.medical_coordination_status || 'pending';
        var appointmentLabel = d.appointment_status_label_es || d.medical_coordination_status_label_es || genericStatusLabelEs(appointmentStatus);
        var nextAppointment = d.next_appointment && d.next_appointment.start_at ? d.next_appointment.start_at : ((d.summary && d.summary.next_appointment) || '');
        var cards = [
            { label: 'Estado general', value: renderStatusBadge(d.booking_status || 'pending', { label: d.booking_status_label_es }) },
            { label: 'Estado del prestador', value: renderStatusBadge(d.provider_status || d.item_status || 'pending_provider', { label: d.provider_status_label_es || d.item_status_label_es }) },
            { label: 'Estado de la cita', value: renderStatusBadge(appointmentStatus, { label: appointmentLabel }) },
            { label: 'Prestador asignado', value: escapeHtml((d.summary && d.summary.assigned_provider) || d.assigned_provider || 'Sin definir') },
            { label: 'Médico asignado', value: escapeHtml((d.summary && d.summary.assigned_doctor) || d.assigned_doctor || 'Pendiente de asignación') },
            { label: 'Próxima cita', value: escapeHtml(nextAppointment ? formatDateTime(nextAppointment) : 'Pendiente') }
        ];

        var html = '<div class="mt-detail-header">';
        html += '<div style="flex:1 1 auto; min-width:0;">';
        html += '<div class="mt-eyebrow">Caso / solicitud</div>';
        html += '<h4 class="mt-detail-title">Solicitud #' + escapeHtml(d.booking_request_id || '') + ' · ' + escapeHtml(d.item_name || ('Item #' + (d.item_id || ''))) + '</h4>';
        html += '<div class="mt-inline-meta">';
        html += '<span class="label label-default">Item #' + escapeHtml(d.item_id || '-') + '</span>';
        html += '<span class="label label-default">' + escapeHtml(d.item_type === 'medical_offer' ? 'Médico' : (d.item_type === 'complementary_service' ? 'Complementario' : (d.item_type || 'Caso'))) + '</span>';
        html += '<span>' + renderFeeBadge(fee.status || '', fee.status_label_es || d.fee_status_label_es) + '</span>';
        html += '</div>';
        html += '<div class="mt-header-summary">';
        cards.forEach(function (card) {
            html += '<div class="mt-header-summary-card"><div class="mt-summary-label">' + card.label + '</div><div class="mt-summary-value">' + card.value + '</div></div>';
        });
        html += '</div>';
        html += '</div>';
        html += '<div class="mt-header-actions">';
        html += renderCoordinationActionButton('btn-default btn-modal-open-inbox', 'Abrir conversación', inboxHref, actionsLocked, lockMessage);
        html += renderCoordinationActionButton('btn-primary btn-modal-open-calendar', 'Abrir calendario', calendarHref, actionsLocked, lockMessage);
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderWorkflowGuide() {
        var html = '<div class="mt-workflow-guide">';
        html += '<div class="mt-guide-card"><h6>Este modal</h6><p>Revisa el caso, valida contexto clínico, acepta o rechaza, propone una cita y asigna o reasigna médico.</p></div>';
        html += '<div class="mt-guide-card"><h6>Inbox</h6><p>Habla con el paciente, resuelve dudas, pide documentos y haz seguimiento conversacional del caso.</p></div>';
        html += '<div class="mt-guide-card"><h6>Calendario</h6><p>Propón, confirma o mueve citas desde la agenda para mantener la coordinación ordenada.</p></div>';
        html += '</div>';
        return html;
    }

    function renderDetailTabs(d, options) {
        options = options || {};
        var html = '<div class="mt-detail-tabs">';
        html += '<ul class="nav nav-tabs" role="tablist">';
        html += '<li class="active"><a href="#mt-detail-tab-summary" aria-controls="mt-detail-tab-summary" role="tab" data-toggle="tab">Resumen</a></li>';
        html += '<li><a href="#mt-detail-tab-patient" aria-controls="mt-detail-tab-patient" role="tab" data-toggle="tab">Paciente</a></li>';
        html += '<li><a href="#mt-detail-tab-clinical" aria-controls="mt-detail-tab-clinical" role="tab" data-toggle="tab">Atención clínica</a></li>';
        html += '<li><a href="#mt-detail-tab-conversation" aria-controls="mt-detail-tab-conversation" role="tab" data-toggle="tab">Conversación</a></li>';
        html += '<li><a href="#mt-detail-tab-history" aria-controls="mt-detail-tab-history" role="tab" data-toggle="tab">Historial</a></li>';
        html += '</ul>';
        html += '<div class="tab-content">';
        html += '<div role="tabpanel" class="tab-pane active mt-tab-pane" id="mt-detail-tab-summary">' + renderSummaryTab(d, options) + '</div>';
        html += '<div role="tabpanel" class="tab-pane mt-tab-pane" id="mt-detail-tab-patient">' + renderPatientTab(d) + '</div>';
        html += '<div role="tabpanel" class="tab-pane mt-tab-pane" id="mt-detail-tab-clinical">' + renderClinicalTab(d) + '</div>';
        html += '<div role="tabpanel" class="tab-pane mt-tab-pane" id="mt-detail-tab-conversation">' + renderConversationTab(d, options) + '</div>';
        html += '<div role="tabpanel" class="tab-pane mt-tab-pane" id="mt-detail-tab-history">' + renderHistoryTab(d) + '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderSummaryTab(d, options) {
        var html = '';
        html += '<div class="mt-panel">';
        html += '<h5 class="mt-panel-title">Resumen operativo</h5>';
        html += '<div class="row">';
        html += '<div class="col-md-6">';
        html += renderKeyValue('Servicio', d.item_name || '-');
        html += renderKeyValue('Destino / ciudad', d.destination || 'Sin definir');
        html += renderKeyValue('Fechas solicitadas', buildTimeline(d));
        html += renderKeyValue('Presupuesto', d.budget || '-');
        html += renderKeyValue('Oferta seleccionada', d.selected_offers || '-');
        html += '</div>';
        html += '<div class="col-md-6">';
        html += renderKeyValue('Prestador asignado', (d.summary && d.summary.assigned_provider) || d.assigned_provider || 'Sin definir');
        html += renderKeyValue('Médico asignado', d.assigned_doctor || 'Pendiente de asignación');
        html += renderKeyValue('Próxima cita', d.next_appointment && d.next_appointment.start_at ? formatDateTime(d.next_appointment.start_at) : 'Pendiente');
        html += renderKeyValue('Estado de la cita', d.appointment_status_label_es || d.medical_coordination_status_label_es || genericStatusLabelEs(d.appointment_status || d.medical_coordination_status || 'pending'));
        html += renderKeyValue('Creado', formatDateTime(d.booking_created_at || ''));
        html += '</div>';
        html += '</div>';
        html += '<p style="margin:12px 0 0;"><strong>Mensaje del paciente:</strong><br>' + nl2brSafe(d.special_request || 'Sin mensaje adicional') + '</p>';
        html += '</div>';
        html += renderQuickActionsPanel(d, options);
        return html;
    }

    function renderQuickActionsPanel(d, options) {
        options = options || {};
        var canAssignStaff = parseInt(d.can_assign_staff, 10) === 1;
        var hasActions = options.canShowLegacyActions || canAssignStaff;
        var html = '<div class="mt-panel">';
        html += '<h5 class="mt-panel-title">Acciones rápidas</h5>';
        html += '<p class="mt-panel-subtitle">Gestiona aquí solo la decisión operativa del caso. La conversación y la agenda se continúan desde sus módulos dedicados.</p>';

        if (hasActions) {
            html += '<div class="mt-quick-actions">';
            if (options.canShowLegacyActions) {
                html += '<button type="button" class="btn btn-success btn-sm" id="btn-modal-provider-confirm">Aceptar caso</button>';
                html += '<button type="button" class="btn btn-danger btn-sm" id="btn-modal-provider-reject">Rechazar caso</button>';
                html += '<button type="button" class="btn btn-warning btn-sm" id="btn-modal-provider-propose">Proponer cita</button>';
            }
            if (canAssignStaff) {
                html += '<button type="button" class="btn btn-info btn-sm" id="btn-modal-assign-staff"><i class="fa fa-user-md"></i> ' + escapeHtml(parseInt(d.assigned_staff_id, 10) > 0 ? 'Reasignar médico' : 'Asignar médico') + '</button>';
            }
            html += '</div>';
        } else {
            html += '<p class="text-muted" style="margin:0;">No hay acciones operativas pendientes en este momento para este item.</p>';
        }

        html += '<p class="mt-actions-note">Inbox: dudas, documentos y seguimiento. Calendario: propuesta, confirmación o cambio de cita.</p>';
        html += '</div>';
        return html;
    }

    function renderPatientTab(d) {
        var html = '';
        html += renderClientSection(d);
        html += renderPatientInsightsPanel(d);
        html += renderDocumentsSection(d);
        return html;
    }

    function renderPatientInsightsPanel(d) {
        var access = d.contact_access || {};
        var html = '<div class="mt-panel">';
        html += '<h5 class="mt-panel-title">Datos relevantes del paciente</h5>';
        if (access.note) {
            html += '<div class="alert alert-warning" style="margin-bottom:12px;">' + escapeHtml(access.note) + '</div>';
        }
        html += '<div class="row">';
        html += '<div class="col-md-6">';
        html += renderKeyValue('Origen', d.origin || 'Sin definir');
        html += renderKeyValue('Personas', d.persons || '-');
        html += renderKeyValue('Categoría', d.category || '-');
        html += '</div>';
        html += '<div class="col-md-6">';
        html += renderKeyValue('Categorías de servicio', d.service_categories || '-');
        html += renderKeyValue('Servicios médicos', d.medical_services || '-');
        html += renderKeyValue('Estado general del caso', d.booking_status_label_es || genericStatusLabelEs(d.booking_status || 'pending'));
        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderClinicalTab(d) {
        var html = '';
        html += renderProviderSection(d);
        html += renderMedicalStaffSection(d);
        html += renderAppointmentSection(d);
        html += renderCoordinationFeeSection(d);
        return html;
    }

    function renderConversationTab(d, options) {
        return renderConversationSection(d, options || {});
    }

    function renderHistoryTab(d) {
        var html = '';
        html += renderEventLogSection(d.event_log || []);
        html += renderItemsTimelineSection(d.items_history || []);
        return html;
    }

    function renderSummaryCards(d) {
        var summary = d.summary || {};
        var cards = [
            { label: 'Estado de coordinación', value: renderFeeBadge(summary.coordination_fee_status || '', summary.coordination_fee_status_label_es || d.fee_status_label_es) },
            { label: 'Coordinación disponible', value: renderBooleanBadge(summary.coordination_unlocked || 'no') },
            { label: 'Prestador asignado', value: escapeHtml(summary.assigned_provider || 'Sin definir') },
            { label: 'Médico asignado', value: escapeHtml(summary.assigned_doctor || 'Pendiente de asignación') },
            { label: 'Próxima cita', value: escapeHtml(formatDateTime(summary.next_appointment || 'Sin definir')) }
        ];

        var html = '<div class="mt-summary-grid" id="mt-detail-summary">';
        cards.forEach(function (card) {
            html += '<div class="mt-summary-card"><div class="mt-summary-label">' + card.label + '</div><div class="mt-summary-value">' + card.value + '</div></div>';
        });
        html += '</div>';
        return html;
    }

    function renderCoordinationFeeSection(d) {
        var fee = d.coordination_fee || {};
        var rows = [
            ['Estado de coordinación', renderFeeBadge(fee.status || '', fee.status_label_es || d.fee_status_label_es)],
            ['Monto', escapeHtml(formatMaybeAmount(fee.amount || 'No aplica'))],
            ['Pagada el', escapeHtml(formatDateTime(fee.paid_at || ''))],
            ['Exonerada el', escapeHtml(formatDateTime(fee.waived_at || ''))],
            ['Disponible desde', escapeHtml(formatDateTime(fee.unlocked_at || ''))],
            ['Alcance del desbloqueo', escapeHtml(fee.unlock_scope || 'Sin definir')]
        ];
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Estado de coordinación</h5></div>';
        if (fee.message) {
            html += '<div class="alert alert-warning" style="margin-bottom:12px;"><strong>' + escapeHtml(fee.message) + '</strong></div>';
        }
        html += '<div class="table-responsive"><table class="table table-bordered table-striped"><tbody>';
        rows.forEach(function (row) {
            html += '<tr><th style="width:220px;">' + escapeHtml(row[0]) + '</th><td>' + row[1] + '</td></tr>';
        });
        html += '</tbody></table></div>';
        html += '</section>';
        return html;
    }

    function renderCaseSection(d, canShowLegacyActions) {
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Caso</h5></div>';
        html += '<div class="row">';
        html += '<div class="col-md-6">';
        html += renderKeyValue('Creado', formatDateTime(d.booking_created_at || ''));
        html += renderKeyValue('Servicio', d.item_name || '-');
        html += renderKeyValue('Origen', d.origin || 'Sin definir');
        html += renderKeyValue('Destino / ciudad', d.destination || 'Sin definir');
        html += renderKeyValue('Fechas solicitadas', buildTimeline(d));
        html += renderKeyValue('Personas', d.persons || '-');
        html += renderKeyValue('Estado general', d.booking_status_label_es || genericStatusLabelEs(d.booking_status || 'pending'));
        html += '</div>';
        html += '<div class="col-md-6">';
        html += renderKeyValue('Categoría', d.category || '-');
        html += renderKeyValue('Categorías de servicio', d.service_categories || '-');
        html += renderKeyValue('Servicios médicos', d.medical_services || '-');
        html += renderKeyValue('Presupuesto', d.budget || '-');
        html += renderKeyValue('Ofertas seleccionadas', d.selected_offers || '-');
        html += '<p><strong>Mensaje del paciente:</strong><br>' + nl2brSafe(d.special_request || 'Sin mensaje adicional') + '</p>';
        html += '</div>';
        html += '</div>';

        if (canShowLegacyActions) {
            // TODO: "Solicitar información" es acción canónica futura. No se expone aún hasta tener flujo backend claro.
            html += '<div class="mt-inline-actions">';
            html += '<span class="mt-inline-label">Acciones del item</span>';
            html += '<button type="button" class="btn btn-success btn-xs" id="btn-modal-provider-confirm">Aceptar caso</button>';
            html += '<button type="button" class="btn btn-danger btn-xs" id="btn-modal-provider-reject">Rechazar caso</button>';
            html += '<button type="button" class="btn btn-warning btn-xs" id="btn-modal-provider-propose">Proponer cita</button>';
            html += '<input type="hidden" id="provider-modal-currency" value="' + escapeHtml(d.item_currency || 'USD') + '">';
            html += '</div>';
        }
        html += '</section>';
        return html;
    }

    function renderProviderSection(d) {
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Prestador</h5></div>';
        html += '<div class="row">';
        html += '<div class="col-md-6">' + renderKeyValue('Prestador asignado', d.assigned_provider || d.summary && d.summary.assigned_provider || 'Sin definir') + '</div>';
        html += '<div class="col-md-6"><p><strong>Estado del prestador:</strong> ' + renderStatusBadge(d.provider_status || d.item_status || 'pending_provider', { label: d.provider_status_label_es || d.item_status_label_es }) + '</p></div>';
        html += '</div>';
        html += '</section>';
        return html;
    }

    function renderMedicalStaffSection(d) {
        var buttonLabel = parseInt(d.assigned_staff_id, 10) > 0 ? 'Reasignar médico' : 'Asignar médico';
        var html = '<section class="mt-section" id="mt-medical-staff-section">';
        html += '<div class="mt-section-head"><h5>Médico / staff</h5></div>';
        html += '<div class="row">';
        html += '<div class="col-md-6">' + renderKeyValue('Médico asignado', d.assigned_doctor || 'Pendiente de asignación') + '</div>';
        html += '<div class="col-md-6">' + renderKeyValue('Clínica / sede', d.clinic || 'Sin definir') + '</div>';
        html += '</div>';
        if (parseInt(d.can_assign_staff, 10) === 1) {
            html += '<div class="mt-inline-actions">';
            html += '<span class="mt-inline-label">Asignación clínica</span>';
            html += '<button type="button" class="btn btn-info btn-xs" id="btn-modal-assign-staff"><i class="fa fa-user-md"></i> ' + escapeHtml(buttonLabel) + '</button>';
            html += '</div>';
        }
        html += '</section>';
        return html;
    }

    function renderAppointmentSection(d) {
        var nextAppointment = d.next_appointment && d.next_appointment.start_at ? d.next_appointment.start_at : '';
        var appointmentLabel = d.appointment_status_label_es || d.medical_coordination_status_label_es || genericStatusLabelEs(d.appointment_status || d.medical_coordination_status || '');
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Cita</h5></div>';
        html += '<div class="row">';
        html += '<div class="col-md-6">';
        html += renderKeyValue('Próxima cita', nextAppointment ? formatDateTime(nextAppointment) : 'Pendiente');
        html += renderKeyValue('Fecha propuesta', d.proposed_appointment_date ? formatDateTime(d.proposed_appointment_date) : 'Pendiente');
        html += renderKeyValue('Fecha confirmada', d.confirmed_appointment_date ? formatDateTime(d.confirmed_appointment_date) : 'Sin definir');
        html += '</div>';
        html += '<div class="col-md-6">';
        html += '<p><strong>Estado de la cita:</strong> ' + renderStatusBadge(d.appointment_status || d.medical_coordination_status || 'pending', { label: appointmentLabel || 'Pendiente' }) + '</p>';
        html += renderKeyValue('Ubicación / sede', d.location || 'Sin definir');
        html += renderKeyValue('Zona horaria', d.timezone || 'Sin definir');
        html += '</div>';
        html += '</div>';
        html += '</section>';
        return html;
    }

    function renderClientSection(d) {
        var access = d.contact_access || {};
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Datos del paciente</h5></div>';
        html += '<div class="row">';
        html += '<div class="col-md-4">' + renderKeyValue('Nombre', d.client_name || '-') + '</div>';
        html += '<div class="col-md-4">' + renderKeyValue('Correo', d.client_email || '-') + '</div>';
        html += '<div class="col-md-4">' + renderKeyValue('Teléfono', d.client_phone || '-') + '</div>';
        html += '</div>';
        if (access.note) {
            html += '<p class="text-warning" style="margin:8px 0 0;">' + escapeHtml(access.note) + '</p>';
        }
        html += '</section>';
        return html;
    }

    function renderDocumentsSection(d) {
        var docsAccess = d.documents_access || {};
        var documents = d.documents || [];
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Documentos médicos</h5></div>';

        if (docsAccess.locked) {
            html += '<p class="text-warning" style="margin:0;">' + escapeHtml(docsAccess.note || '') + '</p>';
            html += '</section>';
            return html;
        }

        if (d.documents_error) {
            html += '<p class="text-muted" style="margin:0;">TODO: faltan campos de alcance documental en base de datos (' + escapeHtml(d.documents_error) + ').</p>';
            html += '</section>';
            return html;
        }

        if (!documents.length) {
            html += '<p class="text-muted" style="margin:0;">Aún no se han compartido documentos.</p>';
            html += '</section>';
            return html;
        }

        html += '<div class="table-responsive"><table class="table table-striped table-bordered">';
        html += '<thead><tr><th>Documento</th><th>Tipo</th><th>Cargado</th><th>Tamaño</th></tr></thead><tbody>';
        documents.forEach(function (doc) {
            var name = doc.title || doc.original_filename || doc.filename || 'Documento';
            var url = doc.download_url || '#';
            html += '<tr>' +
                '<td><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(name) + '</a></td>' +
                '<td>' + escapeHtml(doc.document_type || '-') + '</td>' +
                '<td>' + escapeHtml(formatDateTime(doc.uploaded_at || '')) + '</td>' +
                '<td>' + escapeHtml(formatFileSize(doc.file_size)) + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        html += '</section>';
        return html;
    }

    function renderConversationSection(d, options) {
        options = options || {};
        var canShowLegacyActions = !!options.canShowLegacyActions;
        var actionsLocked = !!options.actionsLocked;
        var lockMessage = options.lockMessage || '';
        var inboxHref = options.inboxHref || ('app_inbox.php?thread_id=ITEM:' + encodeURIComponent(String(d.item_id || 0)));
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Conversación</h5></div>';
        html += '<div class="mt-conversation-cta">';
        html += '<div>';
        html += '<strong>Gestiona la conversación desde Inbox</strong>';
        html += '<p>Usa Inbox para resolver dudas, pedir documentos y mantener el seguimiento con el paciente fuera de este modal.</p>';
        html += '</div>';
        html += '<div>' + renderCoordinationActionButton('btn-primary btn-modal-open-inbox', 'Abrir conversación en Inbox', inboxHref, actionsLocked, lockMessage) + '</div>';
        html += '</div>';
        if (actionsLocked && lockMessage) {
            html += '<div class="alert alert-warning" style="margin-bottom:12px;">' + escapeHtml(lockMessage) + '</div>';
        }
        if (canShowLegacyActions) {
            html += '<p class="text-muted" style="margin-top:0;">Aceptar o rechazar el caso no implica atención realizada. La coordinación dependiente de la comisión se bloquea solo cuando aplica.</p>';
        }
        html += '<div id="provider-conversation-log" class="mt-conversation-log">Cargando mensajes...</div>';
        html += '</section>';
        return html;
    }

    function renderEventLogSection(events) {
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Bitácora del caso</h5></div>';
        if (!events || !events.length) {
            html += '<p class="text-muted" style="margin:0;">Aún no hay eventos registrados.</p>';
            html += '</section>';
            return html;
        }
        html += '<div class="table-responsive"><table class="table table-striped table-bordered">';
        html += '<thead><tr><th>Ámbito</th><th>Evento</th><th>Actor</th><th>Item</th><th>Fecha</th><th>Detalle</th></tr></thead><tbody>';
        events.forEach(function (event) {
            html += '<tr>' +
                '<td>' + escapeHtml(scopeLabelEs(event.scope || 'request')) + '</td>' +
                '<td>' + renderEventBadge(event.event_type || '', event.event_type_label_es) + '</td>' +
                '<td>' + renderRoleChip(event.actor_role || 'SYSTEM', event.actor_role_label_es) + '</td>' +
                '<td>' + escapeHtml(event.item_id ? ('#' + event.item_id) : '-') + '</td>' +
                '<td>' + escapeHtml(formatDateTime(event.time || '')) + '</td>' +
                '<td>' + escapeHtml(event.summary || '-') + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        html += '</section>';
        return html;
    }

    function renderItemsTimelineSection(items) {
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Historial de items</h5></div>';
        html += '<div class="table-responsive"><table class="table table-striped table-bordered">';
        html += '<thead><tr><th>Item</th><th>Tipo</th><th>Estado del prestador</th><th>Estado de la cita</th><th>Médico / Clínica</th><th>Fecha propuesta</th><th>Fecha confirmada</th><th>Última acción</th><th>Actualizado</th></tr></thead><tbody>';
        if (!items || !items.length) {
            html += '<tr><td colspan="9">No hay items asociados para este proveedor.</td></tr>';
        } else {
            items.forEach(function (item) {
                html += '<tr>' +
                    '<td>' + escapeHtml(item.item_name || '-') + '</td>' +
                    '<td>' + escapeHtml(item.item_type === 'medical_offer' ? 'Médico' : (item.item_type === 'complementary_service' ? 'Complementario' : (item.item_type || '-'))) + '</td>' +
                    '<td>' + renderStatusBadge(item.provider_status || item.item_status || '', { label: item.provider_status_label_es || item.item_status_label_es }) + '</td>' +
                    '<td>' + renderStatusBadge(item.medical_coordination_status || item.appointment_status || '', { label: item.appointment_status_label_es || item.medical_coordination_status_label_es }) + '</td>' +
                    '<td>' + escapeHtml(joinDoctorClinic(item)) + '</td>' +
                    '<td>' + escapeHtml(formatDateTime(item.proposed_appointment_date || '')) + '</td>' +
                    '<td>' + escapeHtml(formatDateTime(item.confirmed_appointment_date || '')) + '</td>' +
                    '<td>' + escapeHtml(item.last_provider_action || '-') + '</td>' +
                    '<td>' + escapeHtml(formatDateTime(item.updated_at || item.item_updated_at || '')) + '</td>' +
                    '</tr>';
            });
        }
        html += '</tbody></table></div>';
        html += '</section>';
        return html;
    }

    function renderCoordinationActionButton(extraClass, label, href, locked, lockedMessage) {
        var disabledAttr = locked ? ' disabled="disabled" tabindex="-1" aria-disabled="true"' : '';
        var lockedData = locked ? ' data-locked-message="' + escapeHtml(lockedMessage || '') + '"' : '';
        var hrefValue = locked ? '#' : href;
        return '<a class="btn ' + extraClass + '"' + disabledAttr + lockedData + ' href="' + escapeHtml(hrefValue) + '">' + escapeHtml(label) + '</a>';
    }

    function loadMessages(itemId) {
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: { action: 'list_messages', item_id: itemId },
            success: function (response) {
                if (!response || !response.ok) {
                    $('#provider-conversation-log').html('<p>No se pudo cargar conversación.</p>');
                    return;
                }
                renderConversation(response.messages || []);
            },
            error: function () {
                $('#provider-conversation-log').html('<p>Error de conexión al cargar conversación.</p>');
            }
        });
    }

    function renderConversation(messages) {
        var $log = $('#provider-conversation-log');
        if (!$log.length) return;
        if (!messages || !messages.length) {
            $log.html('<p class="text-muted" style="margin:0;">Sin mensajes todavía.</p>');
            return;
        }

        var html = '';
        messages.forEach(function (m) {
            var role = String(m.display_role || detectRoleFromSender(m.sender || '')).toUpperCase();
            html += '<div class="mt-message-row">' +
                '<div class="mt-message-meta">' +
                    renderRoleChip(role, m.display_role_label_es) +
                    '<span class="mt-message-time">' + escapeHtml(formatDateTime(m.time || '')) + '</span>' +
                    ((m.actor || '').toString().trim() ? '<span class="mt-message-actor">' + escapeHtml(m.actor) + '</span>' : '') +
                '</div>' +
                '<div class="mt-message-body">' + formatStructuredBody(m.body || '') + '</div>' +
                '</div>';
        });
        $log.html(html);
        $log.scrollTop($log[0].scrollHeight);
    }

    function sendProviderAction(action, payload, onSuccess, successMessage) {
        payload = payload || {};
        payload.action = action;

        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: payload,
            success: function (response) {
                if (!response || !response.ok) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo guardar la respuesta');
                    return;
                }
                if (typeof onSuccess === 'function') {
                    onSuccess(response);
                }
                toastr.success(successMessage || 'Respuesta guardada');
                loadRows();
            },
            error: function () {
                toastr.error('Error de conexión al guardar la respuesta');
            }
        });
    }

    function openCalendar(itemId) {
        itemId = parseInt(itemId, 10) || 0;
        if (itemId <= 0) return;
        var threadId = 'ITEM:' + itemId;
        window.location = 'app_calendar.php?thread_type=ITEM&item_id=' + encodeURIComponent(String(itemId)) + '&thread_id=' + encodeURIComponent(threadId);
    }

    function buildTimeline(row) {
        var from = (row.timeline_from || '').toString().trim();
        var to = (row.timeline_to || '').toString().trim();
        if (from && to) return from + ' - ' + to;
        if (from) return 'Desde ' + from;
        if (to) return 'Hasta ' + to;
        return row.timeline || 'Sin definir';
    }

    function renderStatusBadge(status, options) {
        options = options || {};
        status = String(status || '').trim();
        var css = 'label-default';
        if (['pending_provider', 'required_pending', 'pending'].indexOf(status) !== -1) css = 'label-warning';
        else if (['provider_confirmed', 'client_accepted', 'paid', 'waived', 'not_applicable', 'disabled_manually', 'date_confirmed', 'doctor_assigned', 'completed'].indexOf(status) !== -1) css = 'label-success';
        else if (['provider_rejected', 'client_rejected', 'cancelled'].indexOf(status) !== -1) css = 'label-danger';
        else if (['provider_proposed_change', 'awaiting_client', 'provider_reviewing', 'needs_more_info', 'date_proposed', 'rescheduled'].indexOf(status) !== -1) css = 'label-info';
        var label = options.label || genericStatusLabelEs(status);
        return '<span class="label ' + css + '">' + escapeHtml(label || '-') + '</span>';
    }

    function renderFeeBadge(status, label) {
        return renderStatusBadge(status || 'not_applicable', { label: label || feeStatusLabelEs(status || 'not_applicable') });
    }

    function renderEventBadge(eventType, label) {
        return '<span class="label label-default">' + escapeHtml(label || eventTypeLabelEs(eventType || '')) + '</span>';
    }

    function renderRoleChip(role, label) {
        role = String(role || 'SYSTEM').toUpperCase();
        var cls = 'default';
        if (role === 'CLIENT') cls = 'info';
        else if (role === 'PROVIDER') cls = 'success';
        else if (role === 'COORDINATOR') cls = 'warning';
        else if (role === 'DOCTOR') cls = 'primary';
        return '<span class="label label-' + cls + ' mt-role-chip">' + escapeHtml(label || roleLabelEs(role)) + '</span>';
    }

    function renderBooleanBadge(value) {
        var normalized = String(value || '').toLowerCase();
        if (normalized === 'yes' || normalized === '1' || normalized === 'true') {
            return '<span class="label label-success">Sí</span>';
        }
        return '<span class="label label-default">No</span>';
    }

    function renderKeyValue(label, value) {
        return '<p><strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(value || '-') + '</p>';
    }

    function joinDoctorClinic(item) {
        var doctor = (item.assigned_doctor || '').toString().trim();
        var clinic = (item.clinic || '').toString().trim();
        if (doctor && clinic) return doctor + ' / ' + clinic;
        return doctor || clinic || '-';
    }

    function detectRoleFromSender(sender) {
        sender = String(sender || '').toLowerCase().trim();
        if (sender === 'client') return 'CLIENT';
        if (sender === 'provider') return 'PROVIDER';
        if (sender === 'admin' || sender === 'patientcare' || sender === 'coordinator') return 'COORDINATOR';
        if (sender === 'doctor') return 'DOCTOR';
        return 'SYSTEM';
    }

    function feeStatusLabelEs(status) {
        var map = {
            pending: 'Pendiente',
            not_applicable: 'No aplica',
            required_pending: 'Comisión pendiente',
            paid: 'Comisión pagada',
            waived: 'Comisión exonerada',
            disabled_manually: 'Comisión desactivada manualmente'
        };
        status = String(status || '').toLowerCase();
        return map[status] || genericStatusLabelEs(status);
    }

    function genericStatusLabelEs(status) {
        var map = {
            pending: 'Pendiente',
            pending_provider: 'Pendiente de revisión del prestador',
            provider_reviewing: 'Pendiente de revisión del prestador',
            needs_more_info: 'Información adicional requerida',
            provider_confirmed: 'Caso aceptado',
            client_accepted: 'Caso aceptado',
            provider_rejected: 'Caso rechazado',
            client_rejected: 'Caso rechazado',
            awaiting_client: 'Pendiente de respuesta del paciente',
            provider_proposed_change: 'Cita propuesta',
            not_applicable: 'No aplica',
            required_pending: 'Comisión pendiente',
            paid: 'Comisión pagada',
            waived: 'Comisión exonerada',
            disabled_manually: 'Comisión desactivada manualmente',
            doctor_assigned: 'Médico asignado',
            date_proposed: 'Cita propuesta',
            date_confirmed: 'Cita confirmada',
            rescheduled: 'Cita reprogramada',
            completed: 'Atención realizada',
            cancelled: 'Caso cerrado',
            confirmed: 'Confirmado',
            scheduled: 'Programado'
        };
        status = String(status || '').toLowerCase();
        return map[status] || (status ? status : 'Sin definir');
    }

    function eventTypeLabelEs(eventType) {
        var map = {
            coordination_fee_required: 'Comisión pendiente',
            coordination_fee_paid: 'Comisión pagada',
            coordination_fee_waived: 'Comisión exonerada',
            contact_unlocked: 'Contacto desbloqueado',
            doctor_assigned: 'Médico asignado',
            medical_docs_requested: 'Documentos solicitados',
            medical_docs_uploaded: 'Documentos cargados',
            appointment_proposed: 'Cita propuesta',
            appointment_confirmed: 'Cita confirmada',
            appointment_rescheduled: 'Cita reprogramada',
            appointment_cancelled: 'Cita cancelada'
        };
        eventType = String(eventType || '').toLowerCase();
        return map[eventType] || (eventType ? eventType : 'Evento');
    }

    function roleLabelEs(role) {
        var map = {
            CLIENT: 'Cliente',
            PROVIDER: 'Prestador',
            COORDINATOR: 'Coordinación',
            DOCTOR: 'Médico',
            SYSTEM: 'Sistema'
        };
        role = String(role || 'SYSTEM').toUpperCase();
        return map[role] || role;
    }

    function scopeLabelEs(scope) {
        scope = String(scope || '').toLowerCase();
        if (scope === 'item') return 'Item';
        return 'Caso';
    }

    function formatStructuredBody(body) {
        var text = String(body || '');
        var trimmed = text.trim();
        var prefix = '';
        if (trimmed.indexOf('[ACTION]') === 0) {
            prefix = '<span class="label label-primary" style="margin-right:6px;">ACCIÓN</span>';
            trimmed = trimmed.replace(/^\[ACTION\]\s*/i, '');
        } else if (trimmed.indexOf('[REPLY]') === 0) {
            prefix = '<span class="label label-primary" style="margin-right:6px;">RESPUESTA</span>';
            trimmed = trimmed.replace(/^\[REPLY\]\s*/i, '');
        }
        return prefix + '<span style="white-space:pre-wrap;">' + escapeHtml(trimmed || text) + '</span>';
    }

    function formatDateTime(value) {
        var raw = String(value || '').trim();
        if (!raw) return '-';
        var normalized = raw.replace(' ', 'T');
        var date = new Date(normalized);
        if (isNaN(date.getTime())) return raw;
        return date.toLocaleString();
    }

    function formatMaybeAmount(value) {
        if (value === null || value === undefined || value === '') return '-';
        return String(value);
    }

    function formatFileSize(bytes) {
        var size = parseInt(bytes, 10);
        if (!size || size <= 0) return '-';
        if (size >= 1073741824) return (size / 1073741824).toFixed(2) + ' GB';
        if (size >= 1048576) return (size / 1048576).toFixed(2) + ' MB';
        if (size >= 1024) return (size / 1024).toFixed(2) + ' KB';
        return size + ' bytes';
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function nl2brSafe(text) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }
})();
