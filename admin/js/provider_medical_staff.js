(function ($) {
    'use strict';

    var PROVIDER_ID = (typeof window !== 'undefined' && typeof window.PROVIDER_ID !== 'undefined')
        ? parseInt(window.PROVIDER_ID, 10)
        : 0;
    var ENDPOINT = 'ajax/provider_medical_staff.php';
    var linkableUsers = [];
    var providerServices = [];
    var staffCatalogs = { roles: [], specialties: [] };
    var providerClinics = [];
    var currentItems = [];
    var MANAGE_DISABLED_BY_CONTEXT = false;

    function canManageStaff() {
        return !MANAGE_DISABLED_BY_CONTEXT && !$('#btn-add-medical-staff').prop('disabled');
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function withFallback(value, fallback) {
        var text = $.trim(value || '');
        return text !== '' ? text : (fallback || 'Sin definir');
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
        $('#btn-add-medical-staff').prop('disabled', MANAGE_DISABLED_BY_CONTEXT || !!isLoading);
        if (isLoading) {
            renderEmpty('Cargando staff médico...');
        }
    }

    function shortText(value, maxLength) {
        var text = $.trim(value || '');
        if (!text || text.length <= maxLength) {
            return text;
        }
        return text.substring(0, maxLength - 1) + '…';
    }

    function initials(name) {
        var parts = $.trim(name || '').split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return 'SM';
        }
        return parts.slice(0, 2).map(function (part) {
            return part.charAt(0).toUpperCase();
        }).join('');
    }

    function avatarHtml(item) {
        var photo = $.trim(item.photo || '');
        if (photo) {
            return '<img src="' + escapeHtml(photo) + '" alt="' + escapeHtml(item.full_name || 'Foto') + '" ' +
                'style="width:46px;height:46px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">';
        }
        return '<div style="width:46px;height:46px;border-radius:50%;background:#eef3f7;color:#2f4050;' +
            'display:flex;align-items:center;justify-content:center;font-weight:700;border:1px solid #dce4ec;">' +
            escapeHtml(initials(item.full_name || '')) +
            '</div>';
    }

    function renderBooleanBadge(isTrue, trueLabel, falseLabel) {
        return isTrue
            ? '<span class="label label-success">' + escapeHtml(trueLabel) + '</span>'
            : '<span class="label label-default">' + escapeHtml(falseLabel) + '</span>';
    }

    function renderRows(items) {
        currentItems = Array.isArray(items) ? items.slice() : [];
        if (!currentItems.length) {
            renderEmpty('Aún no hay staff médico registrado para este prestador.');
            return;
        }

        var rows = currentItems.map(function (item, index) {
            var active = parseInt(item.is_active || item.active, 10) === 1;
            var isPrimary = parseInt(item.is_primary_doctor, 10) === 1;
            var roleTitle = withFallback(item.role_title, 'Sin cargo definido');
            var specialty = withFallback(item.specialty, 'Sin especialidad definida');
            var toggleLabel = active ? 'Desactivar' : 'Activar';
            var toggleValue = active ? 0 : 1;
            var serviceSummary = withFallback(item.service_summary, 'Sin servicios asignados');
            var linkedUser = $.trim(item.linked_user_label || '');
            var bio = shortText(item.bio_short_preview || item.bio_short || '', 110);
            var actionsHtml;

            if (canManageStaff()) {
                actionsHtml = ''
                    + '<button type="button" class="btn btn-xs btn-default staff-edit"><i class="fa fa-pencil"></i> Editar</button> '
                    + '<button type="button" class="btn btn-xs btn-default staff-move" data-direction="up"' + (index === 0 ? ' disabled' : '') + '>'
                    + '<i class="fa fa-arrow-up"></i></button> '
                    + '<button type="button" class="btn btn-xs btn-default staff-move" data-direction="down"' + (index === currentItems.length - 1 ? ' disabled' : '') + '>'
                    + '<i class="fa fa-arrow-down"></i></button> '
                    + '<button type="button" class="btn btn-xs ' + (active ? 'btn-warning' : 'btn-success') + ' staff-toggle" data-value="' + toggleValue + '">'
                    + '<i class="fa ' + (active ? 'fa-pause' : 'fa-check') + '"></i> ' + toggleLabel + '</button>';
            } else {
                actionsHtml = '<span class="text-muted">Solo lectura</span>';
            }

            return ''
                + '<tr data-id="' + escapeHtml(item.id) + '">'
                + '<td class="text-center">' + avatarHtml(item) + '</td>'
                + '<td>'
                + '<strong>' + escapeHtml(withFallback(item.full_name, 'Sin nombre')) + '</strong>'
                + '<div class="text-muted small">' + escapeHtml(withFallback(item.email, 'Sin correo')) + ' · ' + escapeHtml(withFallback(item.phone, 'Sin teléfono')) + '</div>'
                + '<div class="small"><span class="label label-default">' + escapeHtml(serviceSummary) + '</span></div>'
                + '</td>'
                + '<td>'
                + '<div>' + escapeHtml(roleTitle) + '</div>'
                + '<div class="text-muted small">' + escapeHtml(linkedUser || 'Sin usuario vinculado') + '</div>'
                + '</td>'
                + '<td>'
                + '<div>' + escapeHtml(specialty) + '</div>'
                + '<div class="text-muted small">' + escapeHtml(bio || 'Sin bio corta') + '</div>'
                + '</td>'
                + '<td>' + renderBooleanBadge(isPrimary, 'Sí', 'No') + '</td>'
                + '<td>' + renderBooleanBadge(active, 'Activo', 'Inactivo') + '</td>'
                + '<td>'
                + '<strong>#' + escapeHtml(item.sort_order || 0) + '</strong>'
                + '<div class="text-muted small">' + escapeHtml(withFallback(item.updated_at, item.created_at || '-')) + '</div>'
                + '</td>'
                + '<td class="text-right">' + actionsHtml + '</td>'
                + '</tr>';
        }).join('');

        $('#tbl-provider-medical-staff tbody').html(rows);
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

    // ── render y load: catálogos de roles, especialidades y sedes ──────────────

    function renderSelectOptions(selectId, items, selectedValue, emptyLabel) {
        var opts = ['<option value="">' + emptyLabel + '</option>'];
        items.forEach(function (item) {
            var val = typeof item === 'object' ? (item.value || '') : String(item);
            var lbl = typeof item === 'object' ? (item.label || val) : val;
            var sel = val === selectedValue ? ' selected' : '';
            opts.push('<option value="' + escapeHtml(val) + '"' + sel + '>' + escapeHtml(lbl) + '</option>');
        });
        $('#' + selectId).html(opts.join(''));
    }

    function renderRoleOptions(roles, selectedValue) {
        renderSelectOptions('pms-role-title', roles || staffCatalogs.roles, selectedValue || '',
            '— Seleccionar cargo —');
        // Compatibilidad legacy: si el valor no está en las opciones, añadirlo
        if (selectedValue) { ensureSelectOption('pms-role-title', selectedValue); }
    }

    function renderSpecialtyOptions(specialties, selectedValue) {
        renderSelectOptions('pms-specialty', specialties || staffCatalogs.specialties, selectedValue || '',
            '— Seleccionar especialidad —');
        if (selectedValue) { ensureSelectOption('pms-specialty', selectedValue); }
    }

    function renderClinicOptions(clinics, selectedValue) {
        var opts = ['<option value="">— Sin sede específica —</option>'];
        (clinics || providerClinics).forEach(function (item) {
            var val = typeof item === 'object' ? (item.value || '') : String(item);
            var lbl = typeof item === 'object' ? (item.label || val) : val;
            if (!val) { return; }
            var sel = val === selectedValue ? ' selected' : '';
            opts.push('<option value="' + escapeHtml(val) + '"' + sel + '>' + escapeHtml(lbl) + '</option>');
        });
        opts.push('<option value="__other__">Otra sede (escribir)…</option>');
        $('#pms-clinic').html(opts.join(''));
        // Toggle companion input
        if (selectedValue) {
            var found = (clinics || providerClinics).some(function (c) {
                var v = typeof c === 'object' ? c.value : c;
                return v === selectedValue;
            });
            if (found) {
                $('#pms-clinic').val(selectedValue);
                $('#pms-clinic-other').val('').hide();
            } else {
                $('#pms-clinic').val('__other__');
                $('#pms-clinic-other').val(selectedValue).show();
            }
        } else {
            $('#pms-clinic-other').val('').hide();
        }
    }

    function loadStaffCatalogs() {
        return $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: { action: 'list_staff_catalogs', provider_id: PROVIDER_ID }
        }).done(function (res) {
            staffCatalogs.roles = (res && Array.isArray(res.roles)) ? res.roles : [];
            staffCatalogs.specialties = (res && Array.isArray(res.specialties)) ? res.specialties : [];
            renderRoleOptions(staffCatalogs.roles, '');
            renderSpecialtyOptions(staffCatalogs.specialties, '');
        }).fail(function () {
            $('#pms-role-title').html('<option value="">No disponible</option>');
            $('#pms-specialty').html('<option value="">No disponible</option>');
        });
    }

    function loadProviderClinics(selectedValue) {
        return $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: { action: 'list_provider_clinics', provider_id: PROVIDER_ID }
        }).done(function (res) {
            providerClinics = (res && Array.isArray(res.clinics)) ? res.clinics : [];
            renderClinicOptions(providerClinics, selectedValue || '');
        }).fail(function () {
            providerClinics = [];
            $('#pms-clinic').html('<option value="">No disponible</option>');
        });
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

    function resetForm() {
        var form = document.getElementById('form-provider-medical-staff');
        if (form) {
            form.reset();
        }
        $('#pms-id').val('');
        $('#pms-sort-order').val('');
        $('#pms-is-active').prop('checked', true);
        $('#pms-is-primary-doctor').prop('checked', false);
        $('#pms-can-access-admin').prop('checked', false);
        $('#pms-access-status').text('Sin usuario vinculado');
        $('#providerMedicalStaffModalLabel').text('Agregar staff médico');
        $('#pms-save-msg').hide().empty();
        // Foto: limpiar preview y campo hidden
        $('#pms-photo').val('');
        $('#pms-photo-file').val('');
        $('#pms-photo-preview').attr('src', '');
        $('#pms-photo-preview-wrap').hide();
        // Signaling de carga para selects dinámicos
        $('#pms-role-title').html('<option value="">Cargando...</option>');
        $('#pms-specialty').html('<option value="">Cargando...</option>');
        $('#pms-clinic').html('<option value="">Cargando...</option>');
        $('#pms-clinic-other').val('').hide();
        providerServices = [];
        renderServiceOptions([], []);
        renderLinkedUserOptions([], 0);
        setAccessSectionMode('create');
    }

    // Garantiza que un valor legacy exista como option en el select
    function ensureSelectOption(selectId, value) {
        if (!value) {
            $('#' + selectId).val('');
            return;
        }
        var $sel = $('#' + selectId);
        if ($sel.find('option').filter(function () { return $(this).val() === value; }).length === 0) {
            $sel.append($('<option>').val(value).text(value + ' ✓'));
        }
        $sel.val(value);
    }

    function fillForm(item) {
        $('#pms-id').val(item.id || '');
        $('#pms-full-name').val(item.full_name || '');
        // Selects dinámicos (catálogos ya cargados antes de llegar aquí)
        renderRoleOptions(staffCatalogs.roles, item.role_title || '');
        renderSpecialtyOptions(staffCatalogs.specialties, item.specialty || '');
        $('#pms-sort-order').val(item.sort_order != null ? item.sort_order : '');
        // Foto: guardar ruta en hidden y mostrar preview
        var photoUrl = $.trim(item.photo || '');
        $('#pms-photo').val(photoUrl);
        $('#pms-photo-file').val('');
        if (photoUrl) {
            var previewSrc = (photoUrl.indexOf('://') === -1) ? '../../' + photoUrl : photoUrl;
            $('#pms-photo-preview').attr('src', previewSrc);
            $('#pms-photo-preview-wrap').show();
        } else {
            $('#pms-photo-preview').attr('src', '');
            $('#pms-photo-preview-wrap').hide();
        }
        $('#pms-bio-short').val(item.bio_short || '');
        $('#pms-email').val(item.email || '');
        $('#pms-phone').val(item.phone || '');
        $('#pms-license').val(item.professional_license || '');
        // clinic_name: renderClinicOptions gestiona la lógica __other__
        renderClinicOptions(providerClinics, item.clinic_name || '');
        $('#pms-notes').val(item.notes || '');
        $('#pms-is-active').prop('checked', parseInt(item.is_active || item.active, 10) === 1);
        $('#pms-is-primary-doctor').prop('checked', parseInt(item.is_primary_doctor, 10) === 1);
        $('#pms-can-access-admin').prop('checked', parseInt(item.can_access_admin, 10) === 1);
        $('#pms-access-status').text(item.access_status_label || 'Sin usuario vinculado');
        renderServiceOptions(providerServices, item.service_ids || []);
        $('#providerMedicalStaffModalLabel').text('Editar staff médico');
        setAccessSectionMode('edit');
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
        $.when(
            loadStaffCatalogs(),
            loadProviderClinics(''),
            loadProviderServices([])
        ).always(function () {
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
                loadProviderServices(res.item.service_ids || []),
                loadStaffCatalogs(),
                loadProviderClinics(res.item.clinic_name || '')
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

        var fd = new FormData();
        fd.append('action',      'save_staff');
        fd.append('provider_id', PROVIDER_ID);
        fd.append('id',          $('#pms-id').val() || '');
        fd.append('full_name',   fullName);
        fd.append('role_title',  $.trim($('#pms-role-title').val() || ''));
        fd.append('specialty',   $.trim($('#pms-specialty').val() || ''));
        fd.append('sort_order',  $.trim($('#pms-sort-order').val() || ''));
        fd.append('photo',       $.trim($('#pms-photo').val() || ''));
        fd.append('bio_short',   $.trim($('#pms-bio-short').val() || ''));
        fd.append('professional_license', $.trim($('#pms-license').val() || ''));
        fd.append('email',       $.trim($('#pms-email').val() || ''));
        fd.append('phone',       $.trim($('#pms-phone').val() || ''));
        var clinicVal = $.trim($('#pms-clinic').val() || '') === '__other__'
            ? $.trim($('#pms-clinic-other').val() || '')
            : $.trim($('#pms-clinic').val() || '');
        fd.append('clinic_name', clinicVal);
        fd.append('linked_user_id', $('#pms-linked-user-id').val() || '');
        fd.append('notes',       $.trim($('#pms-notes').val() || ''));

        // Archivo de foto (si el usuario eligió uno)
        var photoFileInput = document.getElementById('pms-photo-file');
        if (photoFileInput && photoFileInput.files && photoFileInput.files[0]) {
            fd.append('photo_file', photoFileInput.files[0]);
        }

        ($('#pms-service-ids').val() || []).forEach(function (serviceId) {
            fd.append('service_ids[]', serviceId);
        });

        if ($('#pms-is-active').is(':checked'))        { fd.append('is_active', 1); }
        if ($('#pms-is-primary-doctor').is(':checked')) { fd.append('is_primary_doctor', 1); }
        if ($('#pms-can-access-admin').is(':checked'))  { fd.append('can_access_admin', 1); }

        var $btn = $('#btn-save-medical-staff');
        var $msg = $('#pms-save-msg');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
        $msg.hide().empty();

        $.ajax({
            url:         ENDPOINT,
            method:      'POST',
            dataType:    'json',
            data:        fd,
            processData: false,
            contentType: false
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

    function reorderStaff(staffId, direction) {
        $.ajax({
            url: ENDPOINT,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'reorder_staff',
                provider_id: PROVIDER_ID,
                id: staffId,
                direction: direction
            }
        }).done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'No fue posible reordenar el staff.', 'error');
                return;
            }
            toast(res.message || 'Orden actualizado', 'success');
            if (Array.isArray(res.items)) {
                renderRows(res.items);
                setTabCount(res.items.length, res.items.filter(function (item) {
                    return parseInt(item.is_active || item.active, 10) === 1;
                }).length);
            } else {
                loadStaffList();
            }
        }).fail(function (xhr) {
            var message = 'No fue posible reordenar el staff.';
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

        MANAGE_DISABLED_BY_CONTEXT = $('#btn-add-medical-staff').prop('disabled');

        loadStaffList();

        $('#btn-add-medical-staff').on('click', openCreateModal);
        $('#btn-save-medical-staff').on('click', function () {
            $('#form-provider-medical-staff').trigger('submit');
        });
        $('#form-provider-medical-staff').on('submit', saveStaff);
        $('#providerMedicalStaffModal').on('hidden.bs.modal', resetForm);

        // Preview de foto en tiempo real al seleccionar archivo
        $(document).on('change', '#pms-photo-file', function () {
            var file = this.files && this.files[0];
            if (!file) { return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#pms-photo-preview').attr('src', e.target.result);
                $('#pms-photo-preview-wrap').show();
            };
            reader.readAsDataURL(file);
        });

        // Quitar foto: limpiar preview, archivo e hidden
        $(document).on('click', '#pms-photo-clear', function () {
            $('#pms-photo').val('');
            $('#pms-photo-file').val('');
            $('#pms-photo-preview').attr('src', '');
            $('#pms-photo-preview-wrap').hide();
        });

        // Toggle companion input para "Otra sede..."
        $(document).on('change', '#pms-clinic', function () {
            if ($(this).val() === '__other__') {
                $('#pms-clinic-other').show().focus();
            } else {
                $('#pms-clinic-other').val('').hide();
            }
        });

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
            var staffId = parseInt($(this).closest('tr').data('id'), 10) || 0;
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

        $('#tbl-provider-medical-staff').on('click', '.staff-move', function () {
            if (!canManageStaff() || $(this).prop('disabled')) {
                return;
            }
            var staffId = parseInt($(this).closest('tr').data('id'), 10) || 0;
            var direction = $(this).data('direction');
            if (staffId > 0 && (direction === 'up' || direction === 'down')) {
                reorderStaff(staffId, direction);
            }
        });
    });
}(jQuery));
