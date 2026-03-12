(function ($) {
    'use strict';

    var PROVIDER_ID = (typeof window !== 'undefined' && typeof window.PROVIDER_ID !== 'undefined')
        ? parseInt(window.PROVIDER_ID, 10)
        : 0;
    var ENDPOINT = 'ajax/provider_medical_staff.php';
    var linkableUsers = [];
    var providerServices = [];

    function canManageStaff() {
        return !$('#btn-add-medical-staff').prop('disabled');
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function toast(message, type) {
        if (typeof toastr !== 'undefined') {
            toastr.options = { positionClass: 'toast-top-right', timeOut: 3500 };
            if (type === 'success') {
                toastr.success(message);
            } else {
                toastr.error(message);
            }
            return;
        }
        if (type !== 'success') {
            alert(message);
        }
    }

    function withFallback(value, fallback) {
        var text = $.trim(value || '');
        return text !== '' ? text : (fallback || 'Sin definir');
    }

    function setTabCount(total, activeTotal) {
        $('#staff-count-badge').text(total || 0);
        $('#staff-active-counter').text(activeTotal || 0);
    }

    function renderEmpty(message) {
        $('#tbl-provider-medical-staff tbody').html(
            '<tr><td colspan="8" class="text-center text-muted" style="padding:24px 12px;">' +
            escapeHtml(message) +
            '</td></tr>'
        );
    }

    function renderRows(items) {
        if (!Array.isArray(items) || items.length === 0) {
            renderEmpty('Aún no hay staff médico registrado para este prestador.');
            return;
        }

        var rows = items.map(function (item) {
            var active = parseInt(item.active, 10) === 1;
            var stateBadge = active
                ? '<span class="label label-success">Activo</span>'
                : '<span class="label label-default">Inactivo</span>';
            var toggleLabel = active ? 'Desactivar' : 'Activar';
            var toggleValue = active ? 0 : 1;
            var accessBadgeClass = 'label label-default';
            if (item.access_status === 'enabled') {
                accessBadgeClass = 'label label-info';
            } else if (item.access_status === 'linked_user_inactive') {
                accessBadgeClass = 'label label-warning';
            }

            var actionsHtml = canManageStaff()
                ? '<button type="button" class="btn btn-xs btn-default staff-edit"><i class="fa fa-pencil"></i> Editar</button> ' +
                  '<button type="button" class="btn btn-xs ' + (active ? 'btn-warning' : 'btn-success') + ' staff-toggle" data-value="' + toggleValue + '">' +
                      '<i class="fa ' + (active ? 'fa-pause' : 'fa-check') + '"></i> ' + toggleLabel +
                  '</button>'
                : '<span class="text-muted">Solo lectura</span>';

            return '' +
                '<tr data-id="' + escapeHtml(item.id) + '">' +
                    '<td>' +
                        '<strong>' + escapeHtml(withFallback(item.full_name, '-')) + '</strong>' +
                        '<div class="small"><span class="label label-info">' + escapeHtml(withFallback(item.service_summary, 'Sin servicios asignados')) + '</span></div>' +
                        '<div class="text-muted small">' + escapeHtml(withFallback(item.specialty, 'Sin especialidad complementaria')) + '</div>' +
                    '</td>' +
                    '<td>' + escapeHtml(withFallback(item.professional_license, 'Sin registro')) + '</td>' +
                    '<td>' +
                        '<div>' + escapeHtml(withFallback(item.email, 'Sin correo')) + '</div>' +
                        '<div class="text-muted small">' + escapeHtml(withFallback(item.phone, 'Sin teléfono')) + '</div>' +
                    '</td>' +
                    '<td>' + escapeHtml(withFallback(item.clinic_name, 'Sin definir')) + '</td>' +
                    '<td>' +
                        '<div>' + escapeHtml(withFallback(item.linked_user_label, 'Sin usuario vinculado')) + '</div>' +
                        '<div class="small"><span class="' + accessBadgeClass + '">' + escapeHtml(withFallback(item.access_status_label, 'Sin usuario vinculado')) + '</span></div>' +
                    '</td>' +
                    '<td>' + stateBadge + '</td>' +
                    '<td>' + escapeHtml(withFallback(item.updated_at, item.created_at || '-')) + '</td>' +
                    '<td class="text-right">' + actionsHtml + '</td>' +
                '</tr>';
        }).join('');

        $('#tbl-provider-medical-staff tbody').html(rows);
    }

    function showInlineError(message) {
        $('#medical-staff-feedback').html(
            '<div class="alert alert-danger">' +
                '<i class="fa fa-exclamation-triangle"></i> ' + escapeHtml(message) +
            '</div>'
        );
    }

    function clearInlineFeedback() {
        $('#medical-staff-feedback').empty();
    }

    function setListLoading(isLoading) {
        $('#btn-add-medical-staff').prop('disabled', !!isLoading);
        if (isLoading) {
            renderEmpty('Cargando staff médico...');
        }
    }

    function setAccessSectionMode(mode) {
        var isEdit = mode === 'edit';
        $('#pms-access-section').toggle(isEdit);
        $('#pms-linked-user-id').prop('disabled', !isEdit);
        $('#pms-can-access-admin').prop('disabled', !isEdit);
    }

    function renderServiceOptions(items, selectedIds) {
        var selectedMap = {};
        (selectedIds || []).forEach(function (id) {
            selectedMap[parseInt(id, 10)] = true;
        });

        if (!Array.isArray(items) || items.length === 0) {
            $('#pms-service-ids').html('<option value="">Este prestador no tiene servicios activos habilitados</option>');
            return;
        }

        var options = items.map(function (item) {
            var serviceId = parseInt(item.service_id || item.id, 10);
            var selected = selectedMap[serviceId] ? ' selected' : '';
            var label = item.label || item.service_name || ('Servicio #' + serviceId);
            return '<option value="' + escapeHtml(serviceId) + '"' + selected + '>' + escapeHtml(label) + '</option>';
        });
        $('#pms-service-ids').html(options.join(''));
    }

    function loadProviderServices(selectedIds) {
        return $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list_provider_services',
                provider_id: PROVIDER_ID
            }
        }).done(function (res) {
            providerServices = (res && Array.isArray(res.items)) ? res.items : [];
            renderServiceOptions(providerServices, selectedIds || []);
        }).fail(function (xhr) {
            providerServices = [];
            var message = 'No fue posible cargar los servicios habilitados.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $('#pms-service-ids').html('<option value="">' + escapeHtml(message) + '</option>');
        });
    }

    function resetForm() {
        var form = document.getElementById('form-provider-medical-staff');
        if (form) {
            form.reset();
        }
        $('#pms-id').val('');
        $('#pms-active').prop('checked', true);
        $('#pms-can-access-admin').prop('checked', false);
        $('#pms-access-status').text('Sin usuario vinculado');
        $('#providerMedicalStaffModalLabel').text('Agregar médico');
        $('#pms-save-msg').hide().empty();
        providerServices = [];
        renderServiceOptions([], []);
        renderLinkedUserOptions([], 0);
        setAccessSectionMode('create');
    }

    function fillForm(item) {
        $('#pms-id').val(item.id || '');
        $('#pms-full-name').val(item.full_name || '');
        $('#pms-specialty').val(item.specialty || '');
        $('#pms-license').val(item.professional_license || '');
        $('#pms-email').val(item.email || '');
        $('#pms-phone').val(item.phone || '');
        $('#pms-clinic').val(item.clinic_name || '');
        $('#pms-notes').val(item.notes || '');
        $('#pms-active').prop('checked', parseInt(item.active, 10) === 1);
        $('#pms-can-access-admin').prop('checked', parseInt(item.can_access_admin, 10) === 1);
        $('#pms-access-status').text(item.access_status_label || 'Sin usuario vinculado');
        renderServiceOptions(providerServices, item.service_ids || []);
        $('#providerMedicalStaffModalLabel').text('Editar médico');
        setAccessSectionMode('edit');
    }

    function renderLinkedUserOptions(items, selectedId) {
        var options = ['<option value="">Sin usuario vinculado</option>'];
        items.forEach(function (item) {
            var disabled = item.available ? '' : ' disabled';
            var selected = parseInt(selectedId, 10) === parseInt(item.id, 10) ? ' selected' : '';
            var suffix = '';
            if (!item.available && item.linked_staff_name) {
                suffix = ' (Vinculado a ' + item.linked_staff_name + ')';
            } else if (parseInt(item.activo, 10) !== 1) {
                suffix = ' (Usuario inactivo)';
            }
            options.push(
                '<option value="' + escapeHtml(item.id) + '"' + selected + disabled + '>' +
                escapeHtml((item.label || ('Usuario #' + item.id)) + suffix) +
                '</option>'
            );
        });
        $('#pms-linked-user-id').html(options.join(''));
    }

    function loadLinkableUsers(selectedId, currentStaffId) {
        $('#pms-linked-user-id').html('<option value="">Cargando usuarios...</option>');
        return $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list_linkable_users',
                provider_id: PROVIDER_ID,
                staff_id: currentStaffId || ''
            }
        }).done(function (res) {
            linkableUsers = (res && Array.isArray(res.items)) ? res.items : [];
            renderLinkedUserOptions(linkableUsers, selectedId || 0);
        }).fail(function (xhr) {
            var message = 'No fue posible cargar los usuarios vinculables.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $('#pms-linked-user-id').html('<option value="">' + escapeHtml(message) + '</option>');
        });
    }

    function loadStaffList() {
        if (!PROVIDER_ID) {
            return;
        }
        clearInlineFeedback();
        setListLoading(true);

        $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list_staff',
                provider_id: PROVIDER_ID
            }
        }).done(function (res) {
            if (!res || !res.ok) {
                showInlineError((res && res.message) || 'No fue posible cargar el staff médico.');
                renderEmpty('No fue posible cargar el staff médico.');
                return;
            }
            setTabCount(res.total || 0, res.active_total || 0);
            renderRows(res.items || []);
        }).fail(function (xhr) {
            var message = 'No fue posible cargar el staff médico.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showInlineError(message);
            renderEmpty(message);
        }).always(function () {
            setListLoading(false);
        });
    }

    function openCreateModal() {
        resetForm();
        loadProviderServices([]).always(function () {
            $('#providerMedicalStaffModal').modal('show');
        });
    }

    function openEditModal(staffId) {
        resetForm();
        $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'get_staff',
                provider_id: PROVIDER_ID,
                id: staffId
            }
        }).done(function (res) {
            if (!res || !res.ok || !res.item) {
                toast((res && res.message) || 'No fue posible cargar el registro.', 'error');
                return;
            }
            $.when(
                loadLinkableUsers(res.item.linked_user_id || 0, res.item.id || 0),
                loadProviderServices(res.item.service_ids || [])
            ).always(function () {
                fillForm(res.item);
                $('#pms-linked-user-id').val(res.item.linked_user_id || '');
                $('#providerMedicalStaffModal').modal('show');
            });
        }).fail(function (xhr) {
            var message = 'No fue posible cargar el registro.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toast(message, 'error');
        });
    }

    function saveStaff(e) {
        e.preventDefault();

        var fullName = $.trim($('#pms-full-name').val() || '');
        if (!fullName) {
            $('#pms-save-msg').html('<span class="text-danger">El nombre completo es obligatorio.</span>').show();
            return;
        }
        if ($('#pms-can-access-admin').is(':checked') && !$('#pms-linked-user-id').val()) {
            $('#pms-save-msg').html('<span class="text-danger">Debes seleccionar un usuario vinculado para habilitar acceso al admin.</span>').show();
            return;
        }

        var payload = {
            action: 'save_staff',
            provider_id: PROVIDER_ID,
            id: $('#pms-id').val() || '',
            full_name: fullName,
            specialty: $.trim($('#pms-specialty').val() || ''),
            professional_license: $.trim($('#pms-license').val() || ''),
            email: $.trim($('#pms-email').val() || ''),
            phone: $.trim($('#pms-phone').val() || ''),
            clinic_name: $.trim($('#pms-clinic').val() || ''),
            linked_user_id: $('#pms-linked-user-id').val() || '',
            notes: $.trim($('#pms-notes').val() || '')
        };
        ($('#pms-service-ids').val() || []).forEach(function (serviceId) {
            payload['service_ids[]'] = payload['service_ids[]'] || [];
            payload['service_ids[]'].push(serviceId);
        });
        if ($('#pms-active').is(':checked')) {
            payload.active = 1;
        }
        if ($('#pms-can-access-admin').is(':checked')) {
            payload.can_access_admin = 1;
        }

        var $btn = $('#btn-save-medical-staff');
        var $msg = $('#pms-save-msg');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
        $msg.hide().empty();

        $.ajax({
            url: ENDPOINT,
            method: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (res) {
            if (!res || !res.ok) {
                $msg.html('<span class="text-danger">' + escapeHtml((res && res.message) || 'No fue posible guardar.') + '</span>').show();
                return;
            }
            toast(res.message || 'Registro guardado', 'success');
            $('#providerMedicalStaffModal').modal('hide');
            loadStaffList();
        }).fail(function (xhr) {
            var message = 'No fue posible guardar el registro.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $msg.html('<span class="text-danger">' + escapeHtml(message) + '</span>').show();
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar');
        });
    }

    function toggleStaff(staffId, nextValue) {
        $.ajax({
            url: ENDPOINT,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'toggle_staff',
                provider_id: PROVIDER_ID,
                id: staffId,
                value: nextValue
            }
        }).done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'No fue posible actualizar el estado.', 'error');
                return;
            }
            toast(res.message || 'Estado actualizado', 'success');
            loadStaffList();
        }).fail(function (xhr) {
            var message = 'No fue posible actualizar el estado.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toast(message, 'error');
        });
    }

    $(document).ready(function () {
        if (!PROVIDER_ID) {
            return;
        }

        loadStaffList();

        $('#btn-add-medical-staff').on('click', openCreateModal);
        $('#btn-save-medical-staff').on('click', function () {
            $('#form-provider-medical-staff').trigger('submit');
        });
        $('#form-provider-medical-staff').on('submit', saveStaff);
        $('#providerMedicalStaffModal').on('hidden.bs.modal', resetForm);
        $('#pms-linked-user-id').on('change', function () {
            if ($(this).val()) {
                var selected = linkableUsers.find(function (item) {
                    return parseInt(item.id, 10) === parseInt($('#pms-linked-user-id').val(), 10);
                });
                if (selected && parseInt(selected.activo, 10) !== 1) {
                    $('#pms-access-status').text('Usuario vinculado inactivo');
                } else if ($('#pms-can-access-admin').is(':checked')) {
                    $('#pms-access-status').text('Médico con acceso propio');
                } else {
                    $('#pms-access-status').text('Usuario vinculado sin acceso');
                }
            } else {
                $('#pms-access-status').text('Sin usuario vinculado');
                $('#pms-can-access-admin').prop('checked', false);
            }
        });
        $('#pms-can-access-admin').on('change', function () {
            if (!$('#pms-linked-user-id').val()) {
                $('#pms-access-status').text('Sin usuario vinculado');
                if ($(this).is(':checked')) {
                    $('#pms-save-msg').html('<span class="text-danger">Selecciona un usuario antes de habilitar acceso.</span>').show();
                }
                return;
            }
            $('#pms-access-status').text($(this).is(':checked') ? 'Médico con acceso propio' : 'Usuario vinculado sin acceso');
        });

        $('#tbl-provider-medical-staff').on('click', '.staff-edit', function () {
            if (!canManageStaff()) {
                return;
            }
            var staffId = parseInt($(this).closest('tr').data('id'), 10) || 0;
            if (staffId > 0) {
                openEditModal(staffId);
            }
        });

        $('#tbl-provider-medical-staff').on('click', '.staff-toggle', function () {
            if (!canManageStaff()) {
                return;
            }
            var $row = $(this).closest('tr');
            var staffId = parseInt($row.data('id'), 10) || 0;
            var nextValue = parseInt($(this).data('value'), 10);
            if (staffId <= 0 || (nextValue !== 0 && nextValue !== 1)) {
                return;
            }
            var question = nextValue === 1
                ? '¿Deseas activar este registro del staff médico?'
                : '¿Deseas desactivar este registro del staff médico?';
            if (!window.confirm(question)) {
                return;
            }
            toggleStaff(staffId, nextValue);
        });
    });
}(jQuery));
