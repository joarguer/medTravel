(function () {
    var table = null;
    var activeDetailItemId = 0;
    var activeDetailData = null;
    var cancelledMeetingKeys = {};
    var currentModalDocuments = [];
    var pageContext = window.MY_BOOKING_REQUESTS_CONTEXT || {};

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
                {
                    data: 'item_name',
                    render: function (value, type, row) {
                        return escapeHtml(value || '-') + '<br><small class="text-muted">' + escapeHtml(renderOperationalOwnerText(row)) + '</small>';
                    }
                },
                {
                    data: null,
                    render: function (row) {
                        return renderOperationalOwnerCell(row);
                    }
                },
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
            if (!confirmOperationalAction('rechazar este caso')) return;
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
            var startAt = ($('#provider_proposed_start_at').val() || '').trim();
            var endAt = ($('#provider_proposed_end_at').val() || '').trim();
            if (itemId <= 0) return;
            if (!confirmOperationalAction('proponer una cita')) return;
            if (!notes) {
                toastr.error('Debes ingresar notas para proponer una cita');
                return;
            }
            if (!startAt || !endAt) {
                toastr.error('Debes definir inicio y fin de la reunión');
                return;
            }

            sendProviderAction('provider_propose_change', {
                item_id: itemId,
                proposed_start_at: startAt,
                proposed_end_at: endAt,
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
            if (!confirmOperationalAction('aceptar este caso')) return;
            if (!confirm('¿Deseas aceptar este caso?')) return;
            sendProviderAction('provider_confirm', { item_id: activeDetailItemId }, reloadActiveDetail);
        });

        $('#my_booking_detail_modal').on('click', '#btn-modal-mark-treatment-completed', function () {
            if (!activeDetailItemId) return;
            if (!confirmOperationalAction('marcar este tratamiento como completado')) return;
            if (!confirm('¿Confirmas que el tratamiento de este item ya fue realizado?')) return;
            sendProviderAction('update_item_status', {
                item_id: activeDetailItemId,
                status: 'treatment_completed'
            }, reloadActiveDetail, 'Tratamiento marcado como completado');
        });

        $('#my_booking_detail_modal').on('click', '#btn-modal-start-post-follow-up', function () {
            if (!activeDetailItemId) return;
            if (!confirmOperationalAction('iniciar seguimiento post tratamiento')) return;
            if (!confirm('¿Deseas iniciar el seguimiento post tratamiento para este item?')) return;
            sendProviderAction('update_item_status', {
                item_id: activeDetailItemId,
                status: 'post_treatment_follow_up'
            }, reloadActiveDetail, 'Seguimiento post tratamiento iniciado');
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
            $('#provider_proposed_start_at').val('');
            $('#provider_proposed_end_at').val('');
            $('#provider_proposed_price').val('');
            $('#provider_proposed_currency').val(currency);
            $('#provider_proposed_notes').val('');
            $('#provider_propose_modal').modal('show');
        });

        $('#my_booking_detail_modal').on('click', '#btn-modal-cancel-meeting', function () {
            if (!activeDetailItemId) return;
            if (!confirmOperationalAction('cancelar esta reunión')) return;
            if (!confirm('¿Cancelar esta reunión? El caso seguirá activo para poder reagendar.')) return;
            sendProviderAction('cancel_meeting', { item_id: activeDetailItemId }, reloadActiveDetail, 'Reunión cancelada');
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

        // ─── Iniciar valoración virtual ───────────────────────────────────────
        $('#my_booking_detail_modal').on('click', '#btn-modal-start-virtual-assessment', function () {
            if (!activeDetailItemId) return;
            if (!confirmOperationalAction('iniciar la valoración virtual')) return;
            if (!confirm('¿Proponer este caso para valoración virtual? El estado cambiará a "Valoración virtual pendiente".')) return;
            sendProviderAction('update_item_status', {
                item_id: activeDetailItemId,
                status: 'virtual_assessment_pending'
            }, reloadActiveDetail, 'Valoración virtual iniciada');
        });

        // ─── Marcar valoración virtual realizada ──────────────────────────────
        $('#my_booking_detail_modal').on('click', '#btn-modal-assessment-done', function () {
            if (!activeDetailItemId) return;
            $('#assessment_done_item_id').val(activeDetailItemId);
            $('#assessment_notes').val('');
            $('#modal-assessment-done').modal('show');
        });

        $('#btn-assessment-done-save').on('click', function () {
            var itemId = parseInt($('#assessment_done_item_id').val(), 10) || 0;
            if (itemId <= 0) return;
            if (!confirmOperationalAction('registrar la valoración como realizada')) return;
            sendProviderAction('update_item_status', {
                item_id: itemId,
                status: 'virtual_assessment_done',
                assessment_notes: ($('#assessment_notes').val() || '').trim()
            }, function () {
                $('#modal-assessment-done').modal('hide');
                reloadActiveDetail();
            }, 'Valoración virtual registrada');
        });

        // ─── Registrar plan clínico acordado ──────────────────────────────────
        $('#my_booking_detail_modal').on('click', '#btn-modal-plan-agreed', function () {
            if (!activeDetailItemId) return;
            $('#plan_agreed_item_id').val(activeDetailItemId);
            var $modal = $('#modal-plan-agreed');
            $modal.modal('show');
        });

        $('#modal-plan-agreed').on('shown.bs.modal', function () {
            var $el = $('#plan_description');
            if ($.fn.summernote && !$el.data('summernote')) {
                $el.summernote({
                    height: 260,
                    minHeight: 180,
                    focus: true,
                    toolbar: [
                        ['style', ['bold', 'italic', 'underline', 'clear']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['hr']],
                        ['view', ['fullscreen', 'codeview']]
                    ],
                    placeholder: 'Describe el plan terapéutico acordado con el paciente: procedimientos, etapas, condiciones...'
                });
            } else if ($.fn.summernote) {
                $el.summernote('code', '');
                $el.summernote('focus');
            }
        });

        $('#modal-plan-agreed').on('hidden.bs.modal', function () {
            var $el = $('#plan_description');
            if ($.fn.summernote && $el.data('summernote')) {
                $el.summernote('destroy');
            }
        });

        $('#btn-plan-agreed-save').on('click', function () {
            var itemId = parseInt($('#plan_agreed_item_id').val(), 10) || 0;
            var plan;
            if ($.fn.summernote && $('#plan_description').data('summernote')) {
                plan = ($('#plan_description').summernote('code') || '').trim();
            } else {
                plan = ($('#plan_description').val() || '').trim();
            }
            if (itemId <= 0) return;
            if (!plan || plan === '<p><br></p>' || plan === '<br>') { toastr.error('Debes describir el plan acordado'); return; }
            if (!confirmOperationalAction('registrar el plan clínico acordado')) return;
            sendProviderAction('update_item_status', {
                item_id: itemId,
                status: 'treatment_plan_agreed',
                plan_description: plan
            }, function () {
                $('#modal-plan-agreed').modal('hide');
                reloadActiveDetail();
            }, 'Plan clínico registrado');
        });

        // ─── Agendar procedimiento presencial ─────────────────────────────────
        $('#my_booking_detail_modal').on('click', '#btn-modal-procedure-schedule', function () {
            if (!activeDetailItemId) return;
            var d = activeDetailData || {};
            var timelineFrom = (d.timeline_from || '').toString().trim();
            var timelineTo   = (d.timeline_to   || '').toString().trim();
            var hint = '';
            if (timelineFrom || timelineTo) {
                hint = 'Ventana del paciente: <strong>' + escapeHtml(timelineFrom || '?') + '</strong> — <strong>' + escapeHtml(timelineTo || '?') + '</strong>';
                $('#procedure_timeline_hint').html(hint).show();
            } else {
                $('#procedure_timeline_hint').hide();
            }
            $('#procedure_schedule_item_id').val(activeDetailItemId);
            $('#procedure_date').val('');
            $('#procedure_notes').val('');
            $('#modal-procedure-schedule').modal('show');
        });

        $('#btn-procedure-schedule-save').on('click', function () {
            var itemId = parseInt($('#procedure_schedule_item_id').val(), 10) || 0;
            var date   = ($('#procedure_date').val() || '').trim();
            var notes  = ($('#procedure_notes').val() || '').trim();
            if (itemId <= 0) return;
            if (!date) { toastr.error('Debes seleccionar la fecha del procedimiento'); return; }
            if (!confirmOperationalAction('agendar el procedimiento presencial')) return;
            sendProviderAction('update_item_status', {
                item_id: itemId,
                status: 'procedure_scheduled',
                procedure_date: date,
                procedure_notes: notes
            }, function () {
                $('#modal-procedure-schedule').modal('hide');
                reloadActiveDetail();
            }, 'Procedimiento agendado');
        });

        // ─── Cerrar caso ──────────────────────────────────────────────────────
        $('#my_booking_detail_modal').on('click', '#btn-modal-case-close', function () {
            if (!activeDetailItemId) return;
            $('#case_close_item_id').val(activeDetailItemId);
            $('#case_close_reason').val('');
            $('#modal-case-close').modal('show');
        });

        $('#btn-case-close-save').on('click', function () {
            var itemId = parseInt($('#case_close_item_id').val(), 10) || 0;
            var reason = ($('#case_close_reason').val() || '').trim();
            if (itemId <= 0) return;
            if (!reason) { toastr.error('Debes ingresar un resumen de cierre'); return; }
            if (!confirmOperationalAction('cerrar este caso clínico')) return;
            sendProviderAction('update_item_status', {
                item_id: itemId,
                status: 'case_closed',
                case_close_reason: reason
            }, function () {
                $('#modal-case-close').modal('hide');
                reloadActiveDetail();
            }, 'Caso cerrado');
        });

        // ─── Reversión de estado (admin) ──────────────────────────────────────
        $('#my_booking_detail_modal').on('click', '#btn-modal-reversal', function () {
            if (!activeDetailItemId) return;
            var targetStatus = ($(this).data('reversalTarget') || 'pending_provider').toString();
            var labelMap = {
                'pending_provider': 'Pendiente de proveedor (inicio del pipeline)',
                'provider_confirmed': 'Confirmado por proveedor',
                'virtual_assessment_pending': 'Valoración virtual pendiente'
            };
            $('#reversal_item_id').val(activeDetailItemId);
            $('#reversal_target_status').val(targetStatus);
            $('#reversal_target_label').text(labelMap[targetStatus] || targetStatus);
            $('#reversal_reason').val('');
            $('#modal-reversal').modal('show');
        });

        $('#btn-reversal-save').on('click', function () {
            var itemId = parseInt($('#reversal_item_id').val(), 10) || 0;
            var targetStatus = ($('#reversal_target_status').val() || '').trim();
            var reason = ($('#reversal_reason').val() || '').trim();
            if (itemId <= 0 || !targetStatus) return;
            if (!reason) { toastr.error('Debes ingresar el motivo de la reversión'); return; }
            if (!confirmOperationalAction('revertir el estado del caso')) return;
            sendProviderAction('update_item_status', {
                item_id: itemId,
                status: targetStatus,
                reversal_reason: reason
            }, function () {
                $('#modal-reversal').modal('hide');
                reloadActiveDetail();
            }, 'Estado revertido');
        });

        // ─── Actualizar ventana de fechas ─────────────────────────────────────
        $('#my_booking_detail_modal').on('click', '#btn-modal-update-timeline', function () {
            if (!activeDetailItemId) return;
            var d = activeDetailData || {};
            $('#update_timeline_booking_id').val(d.booking_request_id || 0);
            $('#update_timeline_from').val(d.timeline_from || '');
            $('#update_timeline_to').val(d.timeline_to || '');
            $('#update_timeline_reason').val('');
            $('#modal-update-timeline').modal('show');
        });

        $('#btn-update-timeline-save').on('click', function () {
            var bookingId = parseInt($('#update_timeline_booking_id').val(), 10) || 0;
            var from   = ($('#update_timeline_from').val() || '').trim();
            var to     = ($('#update_timeline_to').val() || '').trim();
            var reason = ($('#update_timeline_reason').val() || '').trim();
            if (bookingId <= 0) return;
            if (!from || !to) { toastr.error('Debes definir ambas fechas'); return; }
            if (!reason) { toastr.error('Debes ingresar el motivo del cambio'); return; }
            if (!confirmOperationalAction('actualizar la ventana de fechas del paciente')) return;
            sendProviderAction('update_timeline_window', {
                booking_id: bookingId,
                timeline_from: from,
                timeline_to: to,
                reason: reason
            }, function () {
                $('#modal-update-timeline').modal('hide');
                reloadActiveDetail();
            }, 'Fechas actualizadas');
        });

        $(document).on('click', '.mt-doc-preview-btn', function () {
            var docId = String($(this).data('doc-id') || '').trim();
            var doc = null;
            for (var i = 0; i < currentModalDocuments.length; i++) {
                if (String(currentModalDocuments[i].id || '') === docId) {
                    doc = currentModalDocuments[i];
                    break;
                }
            }
            if (doc) { openDocViewer(doc); }
        });

        $('#adminDocViewerModal').on('hidden.bs.modal', function () {
            $('#adminDocViewerPreview').html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<span>Vista previa no disponible.</span>' +
                '</div>'
            );
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
        activeDetailData.operational_owner_label = payload.operational_owner_label || activeDetailData.operational_owner_label || null;
        activeDetailData.operational_owner_short_label = payload.operational_owner_short_label || activeDetailData.operational_owner_short_label || null;
        activeDetailData.operational_owner_role_label_es = payload.operational_owner_role_label_es || activeDetailData.operational_owner_role_label_es || null;
        activeDetailData.operational_owner_note_es = payload.operational_owner_note_es || activeDetailData.operational_owner_note_es || null;
        activeDetailData.supervisor_override_required = parseInt(payload.supervisor_override_required, 10) || 0;
        activeDetailData.supervisor_override_message = payload.supervisor_override_message || '';
        activeDetailData.linked_staff_auto_claim_available = parseInt(payload.linked_staff_auto_claim_available, 10) || 0;
        activeDetailData.linked_staff_auto_claim_message = payload.linked_staff_auto_claim_message || '';
        activeDetailData.summary = activeDetailData.summary || {};
        activeDetailData.summary.assigned_doctor = payload.assigned_doctor || null;
        activeDetailData.summary.operational_owner = payload.operational_owner_short_label || activeDetailData.summary.operational_owner || null;
        rerenderActiveDetail();
    }

    function renderOperationalOwnerText(row) {
        row = row || {};
        return (row.operational_owner_short_label || row.operational_owner_label || row.assigned_doctor || 'Administración del prestador').toString();
    }

    function renderOperationalOwnerCell(row) {
        row = row || {};
        var ownerText = renderOperationalOwnerText(row);
        var roleLabel = (row.operational_owner_role_label_es || (parseInt(row.assigned_staff_id, 10) > 0 ? 'Staff asignado' : 'Administración del prestador')).toString();
        var modeLabel = (row.ownership_mode_label_es || '').toString().trim();
        var modeRole = 'SYSTEM';
        if (modeLabel === 'Supervisión') {
            modeRole = 'COORDINATOR';
        } else if (parseInt(row.assigned_staff_id, 10) > 0) {
            modeRole = 'DOCTOR';
        }
        var html = '<strong>' + escapeHtml(ownerText) + '</strong>';
        html += '<br><small class="text-muted">' + escapeHtml(roleLabel) + '</small>';
        if (modeLabel) {
            html += '<br>' + renderRoleChip(modeRole, modeLabel);
        }
        return html;
    }

    function confirmOperationalAction(actionLabel) {
        actionLabel = (actionLabel || 'continuar con esta acción').toString();
        if (!activeDetailData) {
            return true;
        }

        if (parseInt(activeDetailData.supervisor_override_required, 10) === 1) {
            return confirm((activeDetailData.supervisor_override_message || 'Este caso tiene un responsable operativo asignado.') + '\n\n¿Deseas ' + actionLabel + ' en modo supervisión?');
        }

        if (parseInt(activeDetailData.linked_staff_auto_claim_available, 10) === 1) {
            return confirm((activeDetailData.linked_staff_auto_claim_message || 'Asumirás este caso como responsable operativo.') + '\n\n¿Deseas continuar?');
        }

        return true;
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
        var canShowLegacyActions = normalizeItemStatus(d.item_status || d.provider_status || '') === 'pending_provider';
        var fee = d.coordination_fee || {};
        var actionsLocked = parseInt(d.coordination_actions_locked || 0, 10) === 1;
        var lockMessage = (d.coordination_pending_message || fee.message || '').toString();
        var inboxLocked = parseInt((d.coordination_inbox_locked !== undefined ? d.coordination_inbox_locked : d.coordination_actions_locked) || 0, 10) === 1;
        var inboxLockMessage = (d.coordination_inbox_pending_message || d.coordination_pending_message || fee.message || '').toString();
        var inboxHref = 'app_inbox.php?thread_id=ITEM:' + encodeURIComponent(String(d.item_id || 0));
        var calendarHref = 'app_calendar.php?thread_type=ITEM&item_id=' + encodeURIComponent(String(d.item_id || 0)) + '&thread_id=' + encodeURIComponent('ITEM:' + String(d.item_id || 0));

        var html = '';
        html += '<div class="mt-request-detail">';
        html += '<input type="hidden" id="provider-modal-currency" value="' + escapeHtml(d.item_currency || 'USD') + '">';
        html += '<div class="mt-detail-sticky">';
        html += renderDetailHeader(d, fee, inboxLocked, inboxLockMessage, actionsLocked, lockMessage, inboxHref, calendarHref);
        html += '</div>';
        html += renderWorkflowGuide();
        html += renderDetailTabs(d, {
            canShowLegacyActions: canShowLegacyActions,
            actionsLocked: actionsLocked,
            lockMessage: lockMessage,
            inboxLocked: inboxLocked,
            inboxLockMessage: inboxLockMessage,
            inboxHref: inboxHref,
            calendarHref: calendarHref
        });
        html += '</div>';
        return html;
    }

    function renderDetailHeader(d, fee, inboxLocked, inboxLockMessage, calendarLocked, calendarLockMessage, inboxHref, calendarHref) {
        var appointmentStatus = d.appointment_status || d.medical_coordination_status || 'pending';
        var appointmentLabel = d.appointment_status_label_es || d.medical_coordination_status_label_es || genericStatusLabelEs(appointmentStatus);
        var nextAppointment = d.next_appointment && d.next_appointment.start_at ? d.next_appointment.start_at : ((d.summary && d.summary.next_appointment) || '');
        var cards = [
            { label: 'Estado general', value: renderStatusBadge(d.booking_status || 'pending', { label: d.booking_status_label_es }) },
            { label: 'Estado del prestador', value: renderStatusBadge(d.provider_status || d.item_status || 'pending_provider', { label: d.provider_status_label_es || d.item_status_label_es }) },
            { label: 'Estado de la cita', value: renderStatusBadge(appointmentStatus, { label: appointmentLabel }) },
            { label: 'Prestador asignado', value: escapeHtml((d.summary && d.summary.assigned_provider) || d.assigned_provider || 'Sin definir') },
            { label: 'Responsable operativo', value: escapeHtml((d.summary && d.summary.operational_owner) || d.operational_owner_short_label || d.operational_owner_label || 'Administración del prestador') },
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
        html += renderCoordinationActionButton('btn-default btn-modal-open-inbox', 'Abrir conversación', inboxHref, inboxLocked, inboxLockMessage);
        html += renderCoordinationActionButton('btn-primary btn-modal-open-calendar', 'Abrir calendario', calendarHref, calendarLocked, calendarLockMessage);
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderWorkflowGuide() {
        var html = '<div class="mt-workflow-guide">';
        html += '<div class="mt-guide-card"><h6>Este módulo</h6><p>Revisa el caso, define quién lo lleva operativamente y usa solo las acciones que correspondan a tu rol actual.</p></div>';
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
        html += '<div role="tabpanel" class="tab-pane mt-tab-pane" id="mt-detail-tab-clinical">' + renderClinicalTab(d, options) + '</div>';
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
        html += renderKeyValue('Responsable operativo', d.operational_owner_label || 'Administración del prestador / sin asignar');
        html += renderKeyValue('Próxima cita', d.next_appointment && d.next_appointment.start_at ? formatDateTime(d.next_appointment.start_at) : 'Pendiente');
        html += renderKeyValue('Estado de la cita', d.appointment_status_label_es || d.medical_coordination_status_label_es || genericStatusLabelEs(d.appointment_status || d.medical_coordination_status || 'pending'));
        html += renderKeyValue('Creado', formatDateTime(d.booking_created_at || ''));
        html += '</div>';
        html += '</div>';
        html += '<p style="margin:12px 0 0;"><strong>Mensaje del paciente:</strong><br>' + nl2brSafe(d.special_request || 'Sin mensaje adicional') + '</p>';
        html += '</div>';
        return html;
    }

    function renderQuickActionsPanel(d, options) {
        options = options || {};
        var normalizedStatus = normalizeItemStatus(d.item_status || d.provider_status || '');
        var canMarkTreatmentCompleted = ['provider_confirmed', 'client_accepted', 'treatment_completed'].indexOf(normalizedStatus) !== -1;
        var alreadyCompleted = normalizedStatus === 'treatment_completed';
        var canStartPostFollowUp = ['treatment_completed', 'post_treatment_follow_up'].indexOf(normalizedStatus) !== -1;
        var alreadyInPostFollowUp = normalizedStatus === 'post_treatment_follow_up';
        var canAssignStaff = parseInt(d.can_assign_staff, 10) === 1;
        var hasActions = options.canShowLegacyActions || canAssignStaff || canMarkTreatmentCompleted || canStartPostFollowUp;
        var html = '<div class="mt-panel">';
        html += '<h5 class="mt-panel-title">Acciones rápidas</h5>';
        html += '<p class="mt-panel-subtitle">Gestiona aquí solo la decisión operativa del caso. La conversación y la agenda se continúan desde sus módulos dedicados.</p>';
        html += '<p class="mt-actions-note" style="margin-top:0;">Estas decisiones corresponden al prestador y su equipo tratante. MedTravel facilita la coordinación operativa.</p>';
        if (parseInt(d.supervisor_override_required, 10) === 1) {
            html += '<div class="alert alert-warning" style="margin-top:12px;"><strong>Modo supervisión.</strong> ' + escapeHtml(d.supervisor_override_message || 'Este item ya tiene responsable operativo asignado.') + '</div>';
        } else if (parseInt(d.linked_staff_auto_claim_available, 10) === 1) {
            html += '<div class="alert alert-info" style="margin-top:12px;"><strong>Asignación pendiente.</strong> ' + escapeHtml(d.linked_staff_auto_claim_message || 'Si continúas, asumirás este item como responsable operativo.') + '</div>';
        } else if (d.operational_owner_note_es) {
            html += '<div class="alert alert-info" style="margin-top:12px;"><strong>Responsabilidad operativa.</strong> ' + escapeHtml(d.operational_owner_note_es) + '</div>';
        }

        if (hasActions) {
            html += '<div class="mt-quick-actions">';
            if (options.canShowLegacyActions) {
                html += '<button type="button" class="btn btn-success btn-sm" id="btn-modal-provider-confirm">' + escapeHtml(parseInt(d.supervisor_override_required, 10) === 1 ? 'Aceptar como supervisión' : 'Aceptar caso') + '</button>';
                html += '<button type="button" class="btn btn-danger btn-sm" id="btn-modal-provider-reject">' + escapeHtml(parseInt(d.supervisor_override_required, 10) === 1 ? 'Rechazar como supervisión' : 'Rechazar caso') + '</button>';
                html += '<button type="button" class="btn btn-warning btn-sm" id="btn-modal-provider-propose">' + escapeHtml(parseInt(d.supervisor_override_required, 10) === 1 ? 'Proponer cita como supervisión' : 'Proponer cita') + '</button>';
            }
            if (canMarkTreatmentCompleted) {
                html += '<button type="button" class="btn btn-success btn-sm" id="btn-modal-mark-treatment-completed"' + (alreadyCompleted ? ' disabled="disabled"' : '') + '><i class="fa fa-check"></i> ' + escapeHtml(alreadyCompleted ? 'Tratamiento completado' : 'Marcar tratamiento completado') + '</button>';
            }
            if (canStartPostFollowUp) {
                html += '<button type="button" class="btn btn-info btn-sm" id="btn-modal-start-post-follow-up"' + (alreadyInPostFollowUp ? ' disabled="disabled"' : '') + '><i class="fa fa-stethoscope"></i> ' + escapeHtml(alreadyInPostFollowUp ? 'Seguimiento post tratamiento activo' : 'Iniciar seguimiento post tratamiento') + '</button>';
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
        html += renderKeyValue('Personas', d.persons || 'Not provided');
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

    function renderClinicalGuide() {
        var guideId = 'mt-clinical-guide-body';
        var html = '<div class="mt-panel" style="margin-bottom:12px;">';
        html += '<h5 class="mt-panel-title" style="cursor:pointer;margin-bottom:0;" data-toggle="collapse" data-target="#' + guideId + '" aria-expanded="false" aria-controls="' + guideId + '">';
        html += '<i class="fa fa-info-circle font-blue" style="margin-right:6px;"></i>';
        html += 'Gu\u00eda de atenci\u00f3n del caso';
        html += ' <i class="fa fa-chevron-down pull-right" style="font-size:11px;margin-top:3px;color:#aaa;"></i>';
        html += '</h5>';
        html += '<div id="' + guideId + '" class="collapse" style="margin-top:10px;">';
        html += '<ol style="padding-left:18px;margin:0 0 12px 0;line-height:1.8;">';
        html += '<li><strong>Valoraci\u00f3n inicial</strong> &mdash; Realiza la videollamada de valoraci\u00f3n con el paciente y registra observaciones.</li>';
        html += '<li><strong>Plan acordado</strong> &mdash; Documenta el plan cl\u00ednico confirmado con el paciente tras la valoraci\u00f3n.</li>';
        html += '<li><strong>Procedimiento presencial</strong> &mdash; Agenda el procedimiento. La fecha debe quedar dentro de la ventana de viaje vigente del paciente.</li>';
        html += '<li><strong>Procedimiento realizado</strong> &mdash; Marca el tratamiento como completado una vez ejecutado.</li>';
        html += '<li><strong>Seguimiento</strong> &mdash; Inicia el seguimiento post-tratamiento si aplica.</li>';
        html += '<li><strong>Cierre</strong> &mdash; Cierra el caso cuando el seguimiento haya concluido.</li>';
        html += '</ol>';
        html += '<div class="alert alert-info" style="margin-bottom:6px;padding:8px 12px;font-size:13px;">';
        html += '<i class="fa fa-video-camera" style="margin-right:5px;"></i>';
        html += 'La videollamada de valoraci\u00f3n virtual <strong>no es</strong> el procedimiento presencial. Son fases distintas.';
        html += '</div>';
        html += '<div class="alert alert-warning" style="margin-bottom:6px;padding:8px 12px;font-size:13px;">';
        html += '<i class="fa fa-calendar" style="margin-right:5px;"></i>';
        html += 'La fecha del procedimiento presencial debe quedar <strong>dentro de la ventana de viaje vigente</strong>. Si la ventana cambia por necesidad m\u00e9dica, debe actualizarse formalmente en el sistema.';
        html += '</div>';
        html += '<div class="alert alert-warning" style="margin-bottom:0;padding:8px 12px;font-size:13px;">';
        html += '<i class="fa fa-comments" style="margin-right:5px;"></i>';
        html += 'El <strong>chat es para conversaci\u00f3n</strong>. Los cambios formales de estado se hacen desde los botones del panel cl\u00ednico.';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderClinicalTab(d, options) {
        options = options || {};
        var normalizedStatus = normalizeItemStatus(d.item_status || d.provider_status || '');
        var html = '';
        html += renderClinicalGuide();
        html += renderClinicalStepper(normalizedStatus);
        html += renderClinicalStatusBlock(d, normalizedStatus);
        html += renderProviderSection(d);
        html += renderMedicalStaffSection(d);
        html += renderClinicalActionsPanel(d, options, normalizedStatus);
        html += renderMiniAppointmentBlock(d);
        html += renderCoordinationFeeSection(d);
        return html;
    }

    function getClinicalPhase(status) {
        if (['case_closed', 'cancelled', 'provider_rejected', 'client_rejected'].indexOf(status) !== -1) { return 6; }
        if (['post_treatment_follow_up'].indexOf(status) !== -1) { return 5; }
        if (['treatment_completed', 'procedure_scheduled'].indexOf(status) !== -1) { return 4; }
        if (['treatment_plan_agreed', 'virtual_assessment_done'].indexOf(status) !== -1) { return 3; }
        if (['provider_confirmed', 'awaiting_client', 'provider_proposed_change', 'client_accepted', 'virtual_assessment_pending'].indexOf(status) !== -1) { return 2; }
        return 1;
    }

    function renderClinicalStepper(normalizedStatus) {
        var phase = getClinicalPhase(normalizedStatus);
        var isTerminal = ['cancelled', 'provider_rejected', 'client_rejected'].indexOf(normalizedStatus) !== -1;
        var isClosed = normalizedStatus === 'case_closed';
        var phases = [
            { label: 'Triage', phase: 1 },
            { label: 'Valoraci\u00f3n', phase: 2 },
            { label: 'Plan', phase: 3 },
            { label: 'Procedimiento', phase: 4 },
            { label: 'Seguimiento', phase: 5 },
            { label: isTerminal ? 'Cancelado' : (isClosed ? 'Cerrado' : 'Cierre'), phase: 6 }
        ];
        var html = '<div class="mt-panel" style="padding-bottom:12px;">';
        html += '<div class="mt-section-head"><h5>Fase del caso</h5></div>';
        html += '<div style="display:table;width:100%;table-layout:fixed;">';
        phases.forEach(function (p) {
            var isDone = p.phase < phase;
            var isActive = p.phase === phase;
            var color, bg, border;
            if ((isTerminal || isClosed) && isActive) {
                color = isTerminal ? '#a94442' : '#3c763d';
                bg    = isTerminal ? '#f2dede' : '#dff0d8';
                border = isTerminal ? '#d9534f' : '#5cb85c';
            } else if (isDone) {
                color = '#3c763d'; bg = '#dff0d8'; border = '#5cb85c';
            } else if (isActive) {
                color = '#31708f'; bg = '#d9edf7'; border = '#31b0d5';
            } else {
                color = '#999'; bg = '#f5f5f5'; border = '#ccc';
            }
            html += '<div style="display:table-cell;text-align:center;padding:0 4px;">';
            html += '<div style="display:inline-block;width:28px;height:28px;line-height:26px;border-radius:50%;background:' + bg + ';border:2px solid ' + border + ';font-size:12px;font-weight:bold;color:' + color + ';">';
            html += isDone ? '&#10003;' : p.phase;
            html += '</div>';
            html += '<div style="font-size:11px;margin-top:4px;color:' + color + ';font-weight:' + (isActive ? '600' : '400') + ';">' + escapeHtml(p.label) + '</div>';
            html += '</div>';
        });
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderClinicalStatusBlock(d, normalizedStatus) {
        var statusLabel = d.provider_status_label_es || d.item_status_label_es || genericStatusLabelEs(normalizedStatus || 'pending_provider');
        var updatedAt = d.provider_response_at || d.updated_at || '';
        var html = '<div class="mt-panel" style="padding:10px 16px;">';
        html += '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';
        html += '<strong style="white-space:nowrap;">Estado clínico:</strong>';
        html += renderStatusBadge(normalizedStatus || 'pending_provider', { label: statusLabel });
        if (updatedAt) {
            html += '<span class="text-muted" style="font-size:12px;">Actualizado: ' + escapeHtml(formatDateTime(updatedAt)) + '</span>';
        }
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderClinicalActionsPanel(d, options, normalizedStatus) {
        options = options || {};
        normalizedStatus = normalizedStatus || normalizeItemStatus(d.item_status || d.provider_status || '');
        var isAdmin = !!(pageContext.isAdminSession);
        var isSupervisor = parseInt(d.supervisor_override_required, 10) === 1;
        var isTerminalStatus = ['cancelled', 'provider_rejected', 'client_rejected', 'case_closed'].indexOf(normalizedStatus) !== -1;

        // ─── Acciones triage / repropuesta ───────────────────────────────────
        var canReproposeAppointment = ['awaiting_client', 'provider_proposed_change', 'virtual_assessment_pending', 'procedure_scheduled'].indexOf(normalizedStatus) !== -1;

        // ─── Acciones valoración virtual ──────────────────────────────────────
        var canStartVirtualAssessment = ['provider_confirmed', 'client_accepted'].indexOf(normalizedStatus) !== -1;
        var canMarkAssessmentDone = normalizedStatus === 'virtual_assessment_pending';

        // ─── Acciones plan clínico ────────────────────────────────────────────
        var canRegisterPlan = ['virtual_assessment_done', 'provider_confirmed', 'client_accepted'].indexOf(normalizedStatus) !== -1;

        // ─── Acciones procedimiento ───────────────────────────────────────────
        var canScheduleProcedure = ['treatment_plan_agreed', 'virtual_assessment_done'].indexOf(normalizedStatus) !== -1;

        // ─── Acciones tratamiento ─────────────────────────────────────────────
        var canMarkTreatmentCompleted = normalizedStatus === 'procedure_scheduled';
        var alreadyCompleted = normalizedStatus === 'treatment_completed';

        // ─── Seguimiento ──────────────────────────────────────────────────────
        var canStartPostFollowUp = ['treatment_completed', 'post_treatment_follow_up'].indexOf(normalizedStatus) !== -1;
        var alreadyInPostFollowUp = normalizedStatus === 'post_treatment_follow_up';

        // ─── Cierre ───────────────────────────────────────────────────────────
        var canCloseCase = ['treatment_completed', 'post_treatment_follow_up'].indexOf(normalizedStatus) !== -1;

        // ─── Reversión (solo admin) ───────────────────────────────────────────
        var canReverseCase = isAdmin && !isTerminalStatus && normalizedStatus !== 'pending_provider';

        // ─── Actualizar ventana de fechas ─────────────────────────────────────
        var canUpdateTimeline = !isTerminalStatus && (isAdmin || !pageContext.isLinkedMedicalStaffSession);

        // ─── Staff ────────────────────────────────────────────────────────────
        var canAssignStaff = parseInt(d.can_assign_staff, 10) === 1;

        var hasActions = options.canShowLegacyActions || canReproposeAppointment || canAssignStaff
            || canMarkTreatmentCompleted || canStartPostFollowUp
            || canStartVirtualAssessment || canMarkAssessmentDone || canRegisterPlan
            || canScheduleProcedure || canCloseCase || canReverseCase || canUpdateTimeline;

        var html = '<div class="mt-panel">';
        html += '<h5 class="mt-panel-title">Acciones del caso</h5>';
        html += '<p class="mt-actions-note" style="margin-top:0;">Gestiona aqu\u00ed la decisi\u00f3n formal del caso. La conversaci\u00f3n y la agenda est\u00e1n en sus m\u00f3dulos dedicados.</p>';

        if (isSupervisor) {
            html += '<div class="alert alert-warning" style="margin-top:8px;"><strong>Modo supervisi\u00f3n.</strong> ' + escapeHtml(d.supervisor_override_message || 'Este item ya tiene responsable operativo asignado.') + '</div>';
        } else if (parseInt(d.linked_staff_auto_claim_available, 10) === 1) {
            html += '<div class="alert alert-info" style="margin-top:8px;"><strong>Asignaci\u00f3n pendiente.</strong> ' + escapeHtml(d.linked_staff_auto_claim_message || 'Si contin\u00faas, asumir\u00e1s este item como responsable operativo.') + '</div>';
        } else if (d.operational_owner_note_es) {
            html += '<div class="alert alert-info" style="margin-top:8px;"><strong>Responsabilidad operativa.</strong> ' + escapeHtml(d.operational_owner_note_es) + '</div>';
        }

        if (hasActions) {
            html += '<div class="mt-quick-actions">';

            // Triage
            if (options.canShowLegacyActions) {
                html += '<button type="button" class="btn btn-success btn-sm" id="btn-modal-provider-confirm">' + escapeHtml(isSupervisor ? 'Aceptar como supervisi\u00f3n' : 'Aceptar caso') + '</button>';
                html += '<button type="button" class="btn btn-danger btn-sm" id="btn-modal-provider-reject">' + escapeHtml(isSupervisor ? 'Rechazar como supervisi\u00f3n' : 'Rechazar caso') + '</button>';
                html += '<button type="button" class="btn btn-warning btn-sm" id="btn-modal-provider-propose">' + escapeHtml(isSupervisor ? 'Proponer cita como supervisi\u00f3n' : 'Proponer cita') + '</button>';
            }
            if (canReproposeAppointment) {
                html += '<button type="button" class="btn btn-warning btn-sm" id="btn-modal-provider-propose"><i class="fa fa-calendar"></i> Cambiar propuesta de cita</button>';
            }

            // Valoración virtual
            if (canStartVirtualAssessment) {
                html += '<button type="button" class="btn btn-primary btn-sm" id="btn-modal-start-virtual-assessment"><i class="fa fa-video-camera"></i> Iniciar valoraci\u00f3n virtual</button>';
            }
            if (canMarkAssessmentDone) {
                html += '<button type="button" class="btn btn-primary btn-sm" id="btn-modal-assessment-done"><i class="fa fa-check-circle"></i> Marcar valoraci\u00f3n realizada</button>';
            }

            // Plan clínico
            if (canRegisterPlan) {
                html += '<button type="button" class="btn btn-primary btn-sm" id="btn-modal-plan-agreed"><i class="fa fa-file-text"></i> Registrar plan acordado</button>';
            }

            // Procedimiento presencial
            if (canScheduleProcedure) {
                html += '<button type="button" class="btn btn-primary btn-sm" id="btn-modal-procedure-schedule"><i class="fa fa-hospital-o"></i> Agendar procedimiento presencial</button>';
            }

            // Tratamiento completado
            if (canMarkTreatmentCompleted) {
                html += '<button type="button" class="btn btn-success btn-sm" id="btn-modal-mark-treatment-completed"' + (alreadyCompleted ? ' disabled="disabled"' : '') + '><i class="fa fa-check"></i> ' + escapeHtml(alreadyCompleted ? 'Tratamiento completado' : 'Marcar tratamiento completado') + '</button>';
            }

            // Seguimiento
            if (canStartPostFollowUp) {
                html += '<button type="button" class="btn btn-info btn-sm" id="btn-modal-start-post-follow-up"' + (alreadyInPostFollowUp ? ' disabled="disabled"' : '') + '><i class="fa fa-stethoscope"></i> ' + escapeHtml(alreadyInPostFollowUp ? 'Seguimiento activo' : 'Iniciar seguimiento') + '</button>';
            }

            // Cierre
            if (canCloseCase) {
                html += '<button type="button" class="btn btn-success btn-sm" id="btn-modal-case-close"><i class="fa fa-lock"></i> Cerrar caso</button>';
            }

            // Staff
            if (canAssignStaff) {
                html += '<button type="button" class="btn btn-info btn-sm" id="btn-modal-assign-staff"><i class="fa fa-user-md"></i> ' + escapeHtml(parseInt(d.assigned_staff_id, 10) > 0 ? 'Reasignar m\u00e9dico' : 'Asignar m\u00e9dico') + '</button>';
            }

            // Reversión (admin)
            if (canReverseCase) {
                html += '<button type="button" class="btn btn-danger btn-sm" id="btn-modal-reversal" data-reversal-target="pending_provider"><i class="fa fa-undo"></i> Reabrir caso</button>';
            }

            // Actualizar ventana de fechas
            if (canUpdateTimeline) {
                html += '<button type="button" class="btn btn-default btn-sm" id="btn-modal-update-timeline"><i class="fa fa-calendar-o"></i> Actualizar fechas del paciente</button>';
            }

            html += '</div>';
        } else {
            html += '<p class="text-muted" style="margin:0;">No hay acciones disponibles para este estado y rol.</p>';
        }
        html += '</div>';
        return html;
    }

    function renderMiniAppointmentBlock(d) {
        var appt = d.next_appointment || null;
        var html = '<div class="mt-panel" style="padding-bottom:12px;">';
        html += '<div class="mt-section-head"><h5>Cita relacionada</h5></div>';
        if (!appt || !appt.start_at) {
            html += '<p class="text-muted" style="margin:0;">Sin cita próxima registrada.</p>';
        } else {
            var meetUrl = appt.google_meet_url || '';
            var calendarUrl = appt.google_html_link || '';
            var mode = String(appt.appointment_mode || '').toLowerCase();
            var modeLabel = mode === 'virtual' ? 'Virtual (Google Meet)' : (mode === 'in_person' ? 'Presencial' : (mode === 'travel' ? 'Viaje' : ''));
            html += '<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">';
            html += '<div>';
            html += '<strong>' + escapeHtml(formatDateTime(appt.start_at)) + '</strong>';
            if (appt.end_at) { html += '<span class="text-muted"> — ' + escapeHtml(formatDateTime(appt.end_at)) + '</span>'; }
            if (modeLabel) { html += '<br><span class="label label-default" style="margin-top:4px;display:inline-block;">' + escapeHtml(modeLabel) + '</span>'; }
            if (appt.status) { html += ' ' + renderStatusBadge(appt.status, { label: appt.status_label_es || genericStatusLabelEs(appt.status) }); }
            html += '</div>';
            if (meetUrl || calendarUrl) {
                html += '<div style="display:flex;gap:6px;align-items:center;">';
                if (meetUrl) { html += '<a class="btn btn-success btn-xs" href="' + escapeHtml(meetUrl) + '" target="_blank" rel="noopener"><i class="fa fa-video-camera"></i> Abrir Meet</a>'; }
                if (calendarUrl) { html += '<a class="btn btn-default btn-xs" href="' + escapeHtml(calendarUrl) + '" target="_blank" rel="noopener"><i class="fa fa-calendar"></i> Evento</a>'; }
                html += '</div>';
            }
            html += '</div>';
        }
        html += '</div>';
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
        html += renderKeyValue('Personas', d.persons || 'Not provided');
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
        html += '<div class="col-md-6">' + renderKeyValue('Responsable operativo', d.operational_owner_label || 'Administración del prestador / sin asignar') + '</div>';
        html += '<div class="col-md-6">' + renderKeyValue('Clínica / sede', d.clinic || 'Sin definir') + '</div>';
        html += '</div>';
        html += '<p class="text-muted" style="margin:8px 0 0;">' + escapeHtml(d.operational_owner_note_es || 'La asignación clínica define quién lleva el seguimiento operativo del item.') + '</p>';
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
        var nextAppointmentStatus = d.next_appointment && d.next_appointment.status ? String(d.next_appointment.status).toLowerCase() : '';
        var appointmentLabel = d.appointment_status_label_es || d.medical_coordination_status_label_es || genericStatusLabelEs(d.appointment_status || d.medical_coordination_status || '');
        var meetUrl = d.next_appointment && d.next_appointment.google_meet_url ? d.next_appointment.google_meet_url : '';
        var calendarUrl = d.next_appointment && d.next_appointment.google_html_link ? d.next_appointment.google_html_link : '';
        var canCancelMeeting = nextAppointment && nextAppointmentStatus === 'confirmed';
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
        if (meetUrl || calendarUrl) {
            html += '<div class="mt-inline-actions" style="margin-top:8px;">';
            html += '<span class="mt-inline-label">Enlaces de la reunión</span>';
            if (meetUrl) {
                html += '<a class="btn btn-success btn-xs" href="' + escapeHtml(meetUrl) + '" target="_blank" rel="noopener"><i class="fa fa-video-camera"></i> Abrir Meet</a>';
            }
            if (calendarUrl) {
                html += '<a class="btn btn-default btn-xs" href="' + escapeHtml(calendarUrl) + '" target="_blank" rel="noopener"><i class="fa fa-calendar"></i> Abrir evento</a>';
            }
            if (canCancelMeeting) {
                html += '<button type="button" class="btn btn-danger btn-xs" id="btn-modal-cancel-meeting"><i class="fa fa-times"></i> Cancelar reunión</button>';
            }
            html += '</div>';
        } else if (canCancelMeeting) {
            html += '<div class="mt-inline-actions" style="margin-top:8px;">';
            html += '<span class="mt-inline-label">Reunión confirmada</span>';
            html += '<button type="button" class="btn btn-danger btn-xs" id="btn-modal-cancel-meeting"><i class="fa fa-times"></i> Cancelar reunión</button>';
            html += '</div>';
        }
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

    // ── Doc Viewer helpers (same pattern as app_inbox) ──────────────────────
    function dvCleanTitleFallback(filename) {
        var raw = String(filename || '').trim();
        if (!raw) return 'Documento';
        var base = raw.replace(/\.[a-z0-9]{2,8}$/i, '').replace(/[_\-]+/g, ' ').replace(/\s+/g, ' ').trim();
        return base || raw;
    }
    function dvNormalizeTypeKey(type) {
        var key = String(type || 'other').toLowerCase().trim();
        var map = {
            history: 'medical_history', medical_history: 'medical_history',
            labs: 'lab_results', lab_results: 'lab_results',
            imaging: 'diagnostic_imaging', diagnostic_imaging: 'diagnostic_imaging',
            photos: 'photos', quote: 'quote', consent_form: 'consent_form',
            medical_order: 'medical_order', prescription: 'prescription',
            administrative_document: 'administrative_document',
            invoice: 'administrative_document', contract: 'administrative_document',
            insurance: 'administrative_document', passport: 'administrative_document',
            id_card: 'administrative_document', other: 'other'
        };
        return map[key] || 'other';
    }
    function dvTypeLabel(type) {
        var labels = {
            medical_history: 'Historia clínica', lab_results: 'Examen / laboratorio',
            diagnostic_imaging: 'Imagen diagnóstica', photos: 'Imagen clínica',
            quote: 'Cotización', consent_form: 'Consentimiento',
            medical_order: 'Orden médica', prescription: 'Fórmula / indicación',
            administrative_document: 'Documento administrativo', other: 'Otro'
        };
        return labels[dvNormalizeTypeKey(type)] || 'Otro';
    }
    function dvExtractExt(value) {
        var clean = String(value || '').trim().split('?')[0].split('#')[0];
        var dot = clean.lastIndexOf('.');
        return dot === -1 ? '' : clean.slice(dot + 1).toLowerCase();
    }
    function dvResolvePreviewType(mime, name, url) {
        var m = String(mime || '').toLowerCase();
        if (m === 'application/pdf' || m === 'application/x-pdf') return 'pdf';
        if (m === 'image/jpeg' || m === 'image/jpg' || m === 'image/png' || m === 'image/webp') return 'image';
        var ext = dvExtractExt(name) || dvExtractExt(url);
        if (ext === 'pdf') return 'pdf';
        if (ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'webp') return 'image';
        return '';
    }
    function openDocViewer(doc) {
        var safeDoc = doc || {};
        var originalName = String(safeDoc.original_filename || safeDoc.filename || 'Documento');
        var displayTitle = String(safeDoc.title || dvCleanTitleFallback(originalName));
        var typeKey = dvNormalizeTypeKey(safeDoc.document_type || 'other');
        var typeLabel = dvTypeLabel(typeKey);
        var mimeType = String(safeDoc.mime_type || '').toLowerCase().trim();
        var href = String(safeDoc.download_url || '').trim();
        if (!href && safeDoc.id) {
            href = '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(safeDoc.id));
        }
        var previewUrl = safeDoc.id
            ? '/admin/ajax/preview_medical_document.php?doc_id=' + encodeURIComponent(String(safeDoc.id))
            : href;
        var previewType = dvResolvePreviewType(mimeType, originalName, href);
        var typeCls = {
            lab_results: 'label-info', diagnostic_imaging: 'label-primary', photos: 'label-success',
            medical_history: 'label-warning', quote: 'label-primary', consent_form: 'label-warning',
            medical_order: 'label-info', prescription: 'label-success',
            administrative_document: 'label-default', other: 'label-default'
        };
        $('#adminDocViewerName').text(displayTitle);
        $('#adminDocViewerType').text(typeLabel).attr('class', 'label ' + (typeCls[typeKey] || 'label-default') + ' mt-dv-type-badge');
        var metaParts = ['Archivo: ' + originalName];
        var uploadedRaw = String(safeDoc.uploaded_at || safeDoc.created_at || '').trim();
        if (uploadedRaw) {
            var d = new Date(uploadedRaw.replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                var dd = (d.getDate() < 10 ? '0' : '') + d.getDate();
                var mo = ((d.getMonth() + 1) < 10 ? '0' : '') + (d.getMonth() + 1);
                metaParts.push('Cargado: ' + dd + '/' + mo + '/' + d.getFullYear());
            }
        }
        if (safeDoc.file_size > 0) { metaParts.push((safeDoc.file_size / 1024).toFixed(1) + ' KB'); }
        if (mimeType) { metaParts.push(mimeType); }
        $('#adminDocViewerMeta').text(metaParts.join(' · '));
        $('#adminDocViewerDownload').attr('href', href || '#');
        var $preview = $('#adminDocViewerPreview');
        if (previewType === 'image' && previewUrl) {
            $preview.html('<img src="' + escapeHtml(previewUrl) + '" alt="' + escapeHtml(originalName) + '">');
        } else if (previewType === 'pdf' && previewUrl) {
            $preview.html('<iframe src="' + escapeHtml(previewUrl) + '" title="' + escapeHtml(originalName) + '"></iframe>');
        } else {
            $preview.html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<div>Vista previa no disponible para este tipo de archivo.</div>' +
                    '<div style="margin-top:8px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">' +
                        '<a href="' + escapeHtml(href || '#') + '" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fa fa-external-link" aria-hidden="true"></i> Abrir en otra pestaña</a>' +
                        '<a href="' + escapeHtml(href || '#') + '" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><i class="fa fa-download" aria-hidden="true"></i> Descargar</a>' +
                    '</div>' +
                '</div>'
            );
        }
        $('#adminDocViewerModal').modal('show');
    }
    // ── End Doc Viewer helpers ───────────────────────────────────────────────

    function renderDocumentsSection(d) {
        var docsAccess = d.documents_access || {};
        var documents = d.documents || [];
        currentModalDocuments = documents;
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Documentos médicos</h5></div>';

        if (docsAccess.locked) {
            html += '<p class="text-warning" style="margin:0;">' + escapeHtml(docsAccess.note || '') + '</p>';
            html += '</section>';
            return html;
        }

        if (d.documents_error) {
            html += '<p class="text-muted" style="margin:0;">Faltan campos de alcance documental en base de datos (' + escapeHtml(d.documents_error) + ').</p>';
            html += '</section>';
            return html;
        }

        if (!documents.length) {
            html += '<p class="text-muted" style="margin:0;">Aún no se han compartido documentos.</p>';
            html += '</section>';
            return html;
        }

        html += '<div class="table-responsive"><table class="table table-striped table-bordered">';
        html += '<thead><tr><th>Documento</th><th>Tipo</th><th>Cargado</th><th>Tamaño</th><th></th></tr></thead><tbody>';
        documents.forEach(function (doc) {
            var name = doc.title || doc.original_filename || doc.filename || 'Documento';
            var url = doc.download_url || '#';
            var docId = String(doc.id || '');
            html += '<tr>' +
                '<td><button type="button" class="btn btn-link mt-doc-preview-btn" style="padding:0;text-align:left;" data-doc-id="' + escapeHtml(docId) + '">' + escapeHtml(name) + '</button></td>' +
                '<td>' + escapeHtml(dvTypeLabel(doc.document_type || 'other')) + '</td>' +
                '<td>' + escapeHtml(formatDateTime(doc.uploaded_at || '')) + '</td>' +
                '<td>' + escapeHtml(formatFileSize(doc.file_size)) + '</td>' +
                '<td><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" class="btn btn-xs btn-default"><i class="fa fa-download" aria-hidden="true"></i></a></td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        html += '</section>';
        return html;
    }

    function renderConversationSection(d, options) {
        options = options || {};
        var canShowLegacyActions = !!options.canShowLegacyActions;
        var inboxLocked = !!options.inboxLocked;
        var inboxLockMessage = options.inboxLockMessage || options.lockMessage || '';
        var inboxHref = options.inboxHref || ('app_inbox.php?thread_id=ITEM:' + encodeURIComponent(String(d.item_id || 0)));
        var html = '<section class="mt-section">';
        html += '<div class="mt-section-head"><h5>Conversación</h5></div>';
        html += '<div class="mt-conversation-cta">';
        html += '<div>';
        html += '<strong>Gestiona la conversación desde Inbox</strong>';
        html += '<p>Usa Inbox para resolver dudas, pedir documentos y mantener el seguimiento con el paciente fuera de este modal.</p>';
        html += '</div>';
        html += '<div>' + renderCoordinationActionButton('btn-primary btn-modal-open-inbox', 'Abrir conversación en Inbox', inboxHref, inboxLocked, inboxLockMessage) + '</div>';
        html += '</div>';
        if (inboxLocked && inboxLockMessage) {
            html += '<div class="alert alert-warning" style="margin-bottom:12px;">' + escapeHtml(inboxLockMessage) + '</div>';
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
            cancelledMeetingKeys = {};
            $log.html('<p class="text-muted" style="margin:0;">Sin mensajes todavía.</p>');
            return;
        }

        cancelledMeetingKeys = collectCancelledMeetingKeys(messages);
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
        status = normalizeItemStatus(status);
        var css = 'label-default';
        if (['pending_provider', 'required_pending', 'pending', 'virtual_assessment_pending'].indexOf(status) !== -1) css = 'label-warning';
        else if (['provider_confirmed', 'client_accepted', 'paid', 'waived', 'not_applicable', 'disabled_manually', 'date_confirmed', 'doctor_assigned', 'completed', 'treatment_completed', 'virtual_assessment_done', 'treatment_plan_agreed', 'case_closed'].indexOf(status) !== -1) css = 'label-success';
        else if (['provider_rejected', 'client_rejected', 'cancelled'].indexOf(status) !== -1) css = 'label-danger';
        else if (['provider_proposed_change', 'awaiting_client', 'provider_reviewing', 'needs_more_info', 'date_proposed', 'rescheduled', 'post_treatment_follow_up', 'procedure_scheduled'].indexOf(status) !== -1) css = 'label-info';
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
            appointment_proposed: 'Cita propuesta',
            appointment_confirmed: 'Cita confirmada',
            appointment_requested_change: 'Cambio de cita solicitado',
            appointment_cancelled: 'Cita cancelada',
            treatment_completed: 'Tratamiento completado',
            post_treatment_follow_up: 'Seguimiento post tratamiento',
            completed: 'Atención realizada',
            cancelled: 'Caso cerrado',
            confirmed: 'Confirmado',
            scheduled: 'Programado',
            // Estados clínicos (2026-04-15)
            virtual_assessment_pending: 'Valoración virtual pendiente',
            virtual_assessment_done: 'Valoración virtual realizada',
            treatment_plan_agreed: 'Plan clínico acordado',
            procedure_scheduled: 'Procedimiento presencial agendado',
            case_closed: 'Caso cerrado (exitoso)',
            new: 'Nuevo caso'
        };
        status = normalizeItemStatus(status);
        return map[status] || (status ? status : 'Sin definir');
    }

    function normalizeItemStatus(status) {
        status = String(status || '').toLowerCase().trim();
        if (status === '' || status === 'pending_admin' || status === 'pending_review') { return 'pending_provider'; }
        if (status === 'completed')                        { return 'treatment_completed'; }
        if (status === 'appointment_confirmed')            { return 'provider_confirmed'; }
        if (status === 'appointment_requested_change')     { return 'provider_proposed_change'; }
        if (status === 'appointment_proposed')             { return 'awaiting_client'; }
        if (status === 'appointment_cancelled')            { return 'cancelled'; }
        return status;
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
        if (trimmed.indexOf('[MEETING_PROPOSAL]') === 0) {
            return renderMeetingProposalBody(trimmed.replace(/^\[MEETING_PROPOSAL\]\s*/i, ''));
        }
        if (trimmed.indexOf('[MEETING_CONFIRMED]') === 0) {
            return renderMeetingConfirmedBody(trimmed.replace(/^\[MEETING_CONFIRMED\]\s*/i, ''));
        }
        if (trimmed.indexOf('[MEETING_CANCELLED]') === 0) {
            return renderMeetingCancelledBody(trimmed.replace(/^\[MEETING_CANCELLED\]\s*/i, ''));
        }
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

    function meetingIntegrationMeta(mode) {
        var normalized = String(mode || 'calendar_plus_meet').trim().toLowerCase();
        var map = {
            internal_only: { label: 'Reunión interna MedTravel', badge: 'MedTravel', badgeClass: 'label-default' },
            calendar_only: { label: 'Reunión con Google Calendar', badge: 'Calendar', badgeClass: 'label-info' },
            calendar_plus_meet: { label: 'Reunión con Google Meet', badge: 'Calendar + Meet', badgeClass: 'label-success' }
        };
        return map[normalized] || map.calendar_plus_meet;
    }

    function meetingEventKeyFromPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return '';
        }
        var eventId = String(payload.event_id || '').trim();
        if (eventId) {
            return 'g:' + eventId;
        }
        var calendarEventId = parseInt(payload.calendar_event_id || 0, 10) || 0;
        if (calendarEventId > 0) {
            return 'c:' + String(calendarEventId);
        }
        return '';
    }

    function collectCancelledMeetingKeys(messages) {
        var map = {};
        (messages || []).forEach(function (message) {
            var payload = null;
            var raw = String(message && message.body ? message.body : '').trim();
            if (raw.indexOf('[MEETING_CANCELLED]') === 0) {
                try {
                    payload = JSON.parse(raw.replace(/^\[MEETING_CANCELLED\]\s*/i, ''));
                } catch (e) {
                    payload = null;
                }
            }
            var key = meetingEventKeyFromPayload(payload);
            if (key) {
                map[key] = true;
            }
        });
        return map;
    }

    function isMeetingCancelledPayload(payload) {
        var key = meetingEventKeyFromPayload(payload);
        return !!(key && cancelledMeetingKeys[key]);
    }

    function renderMeetingProposalBody(jsonText) {
        var payload = null;
        try {
            payload = JSON.parse(jsonText);
        } catch (e) {
            payload = null;
        }
        if (!payload || typeof payload !== 'object') {
            return '<span style="white-space:pre-wrap;">' + escapeHtml(jsonText) + '</span>';
        }

        var integration = meetingIntegrationMeta(payload.integration_mode || 'calendar_plus_meet');
        var html = '<div class="panel panel-warning" style="margin:0;">';
        html += '<div class="panel-heading" style="padding:8px 10px;"><strong>' + escapeHtml(integration.label) + '</strong> <span class="label ' + escapeHtml(integration.badgeClass) + '" style="margin-left:6px;">' + escapeHtml(integration.badge) + '</span></div>';
        html += '<div class="panel-body" style="padding:10px;">';
        if (payload.start_at) {
            html += '<p style="margin:0 0 6px;"><strong>Inicio:</strong> ' + escapeHtml(formatDateTime(payload.start_at)) + '</p>';
        }
        if (payload.end_at) {
            html += '<p style="margin:0 0 6px;"><strong>Fin:</strong> ' + escapeHtml(formatDateTime(payload.end_at)) + '</p>';
        }
        if (payload.note) {
            html += '<p style="margin:0 0 6px;"><strong>Nota:</strong> ' + escapeHtml(payload.note) + '</p>';
        }
        html += '</div></div>';
        return html;
    }

    function renderMeetingConfirmedBody(jsonText) {
        var payload = null;
        try {
            payload = JSON.parse(jsonText);
        } catch (e) {
            payload = null;
        }
        if (!payload || typeof payload !== 'object') {
            return '<span style="white-space:pre-wrap;">' + escapeHtml(jsonText) + '</span>';
        }
        var integration = meetingIntegrationMeta(payload.integration_mode || (payload.meet_url ? 'calendar_plus_meet' : (payload.html_link ? 'calendar_only' : 'internal_only')));
        var isCancelled = isMeetingCancelledPayload(payload);

        var html = '<div class="panel ' + (isCancelled ? 'panel-warning' : 'panel-success') + '" style="margin:0;">';
        html += '<div class="panel-heading" style="padding:8px 10px;"><strong>' + escapeHtml(isCancelled ? 'Reunión cancelada' : 'Reunión confirmada') + '</strong> <span class="label ' + escapeHtml(integration.badgeClass) + '" style="margin-left:6px;">' + escapeHtml(integration.badge) + '</span></div>';
        html += '<div class="panel-body" style="padding:10px;">';
        if (payload.start_at) {
            html += '<p style="margin:0 0 6px;"><strong>Inicio:</strong> ' + escapeHtml(formatDateTime(payload.start_at)) + '</p>';
        }
        if (payload.end_at) {
            html += '<p style="margin:0 0 6px;"><strong>Fin:</strong> ' + escapeHtml(formatDateTime(payload.end_at)) + '</p>';
        }
        html += '<p style="margin:0 0 6px;"><strong>Tipo:</strong> ' + escapeHtml(integration.label) + '</p>';
        if (payload.organizer_email) {
            html += '<p style="margin:0 0 6px;"><strong>Organizador:</strong> ' + escapeHtml(payload.organizer_email) + '</p>';
        }
        html += '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">';
        if (!isCancelled && payload.meet_url) {
            html += '<a class="btn btn-success btn-xs" href="' + escapeHtml(payload.meet_url) + '" target="_blank" rel="noopener">Abrir Meet</a>';
        }
        if (!isCancelled && payload.html_link) {
            html += '<a class="btn btn-default btn-xs" href="' + escapeHtml(payload.html_link) + '" target="_blank" rel="noopener">Abrir evento</a>';
        }
        html += '</div>';
        if (isCancelled) {
            html += '<p class="text-muted" style="margin:8px 0 0;">El caso sigue vivo y puede reagendarse más adelante.</p>';
        }
        html += '</div></div>';
        return html;
    }

    function renderMeetingCancelledBody(jsonText) {
        var payload = null;
        try {
            payload = JSON.parse(jsonText);
        } catch (e) {
            payload = null;
        }
        if (!payload || typeof payload !== 'object') {
            return '<span style="white-space:pre-wrap;">' + escapeHtml(jsonText) + '</span>';
        }

        var integration = meetingIntegrationMeta(payload.integration_mode || 'calendar_plus_meet');
        var cancelledByRole = String(payload.cancelled_by_role || '').trim().toUpperCase();
        var byLabel = 'el equipo';
        if (cancelledByRole === 'CLIENT') {
            byLabel = 'el paciente';
        } else if (cancelledByRole === 'PROVIDER') {
            byLabel = 'el prestador';
        } else if (cancelledByRole === 'ADMIN') {
            byLabel = 'coordinación';
        }

        var html = '<div class="panel panel-warning" style="margin:0;">';
        html += '<div class="panel-heading" style="padding:8px 10px;"><strong>Reunión cancelada</strong> <span class="label ' + escapeHtml(integration.badgeClass) + '" style="margin-left:6px;">' + escapeHtml(integration.badge) + '</span></div>';
        html += '<div class="panel-body" style="padding:10px;">';
        if (payload.start_at) {
            html += '<p style="margin:0 0 6px;"><strong>Inicio:</strong> ' + escapeHtml(formatDateTime(payload.start_at)) + '</p>';
        }
        if (payload.end_at) {
            html += '<p style="margin:0 0 6px;"><strong>Fin:</strong> ' + escapeHtml(formatDateTime(payload.end_at)) + '</p>';
        }
        html += '<p style="margin:0 0 6px;"><strong>Cancelada por:</strong> ' + escapeHtml(byLabel) + '</p>';
        html += '<p class="text-muted" style="margin:8px 0 0;">El caso sigue vivo y puede reagendarse más adelante.</p>';
        html += '</div></div>';
        return html;
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
