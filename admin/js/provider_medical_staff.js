(function ($) {
    'use strict';

    var PROVIDER_ID = (typeof window !== 'undefined' && typeof window.PROVIDER_ID !== 'undefined')
        ? parseInt(window.PROVIDER_ID, 10)
        : 0;
    var ENDPOINT = 'ajax/provider_medical_staff.php';

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
            '<tr><td colspan="7" class="text-center text-muted" style="padding:24px 12px;">' +
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

            return '' +
                '<tr data-id="' + escapeHtml(item.id) + '">' +
                    '<td>' +
                        '<strong>' + escapeHtml(withFallback(item.full_name, '-')) + '</strong>' +
                        '<div class="text-muted small">' + escapeHtml(withFallback(item.specialty, 'Sin especialidad registrada')) + '</div>' +
                    '</td>' +
                    '<td>' + escapeHtml(withFallback(item.professional_license, 'Sin registro')) + '</td>' +
                    '<td>' +
                        '<div>' + escapeHtml(withFallback(item.email, 'Sin correo')) + '</div>' +
                        '<div class="text-muted small">' + escapeHtml(withFallback(item.phone, 'Sin teléfono')) + '</div>' +
                    '</td>' +
                    '<td>' + escapeHtml(withFallback(item.clinic_name, 'Sin definir')) + '</td>' +
                    '<td>' + stateBadge + '</td>' +
                    '<td>' + escapeHtml(withFallback(item.updated_at, item.created_at || '-')) + '</td>' +
                    '<td class="text-right">' +
                        '<button type="button" class="btn btn-xs btn-default staff-edit"><i class="fa fa-pencil"></i> Editar</button> ' +
                        '<button type="button" class="btn btn-xs ' + (active ? 'btn-warning' : 'btn-success') + ' staff-toggle" data-value="' + toggleValue + '">' +
                            '<i class="fa ' + (active ? 'fa-pause' : 'fa-check') + '"></i> ' + toggleLabel +
                        '</button>' +
                    '</td>' +
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

    function resetForm() {
        var form = document.getElementById('form-provider-medical-staff');
        if (form) {
            form.reset();
        }
        $('#pms-id').val('');
        $('#pms-active').prop('checked', true);
        $('#providerMedicalStaffModalLabel').text('Agregar médico');
        $('#pms-save-msg').hide().empty();
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
        $('#providerMedicalStaffModalLabel').text('Editar médico');
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
        $('#providerMedicalStaffModal').modal('show');
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
            fillForm(res.item);
            $('#providerMedicalStaffModal').modal('show');
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
            notes: $.trim($('#pms-notes').val() || '')
        };
        if ($('#pms-active').is(':checked')) {
            payload.active = 1;
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

        $('#tbl-provider-medical-staff').on('click', '.staff-edit', function () {
            var staffId = parseInt($(this).closest('tr').data('id'), 10) || 0;
            if (staffId > 0) {
                openEditModal(staffId);
            }
        });

        $('#tbl-provider-medical-staff').on('click', '.staff-toggle', function () {
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
