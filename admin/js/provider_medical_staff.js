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
    var serviceMultiSelectNeedsInit = false;

    function serviceMultiSelectContainerSelector() {
        return '#ms-pms-service-ids';
    }

    function hasServiceMultiSelectRendered() {
        return $(serviceMultiSelectContainerSelector()).length > 0 || $('#pms-service-ids').next('.ms-container').length > 0;
    }

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

    function accessFeedbackMessage(res) {
        if (!res || !res.status) {
            return res && res.message ? res.message : 'Registro guardado';
        }
        switch (res.status) {
            case 'created_user_and_mail_sent':
                return 'Staff guardado. El acceso al panel quedó configurado correctamente.';
            case 'created_user_mail_failed':
                return 'Staff guardado. El acceso quedó configurado, pero no se pudo enviar la notificación.';
            case 'linked_existing_user_mail_sent':
                return 'Staff guardado. El acceso al panel quedó actualizado correctamente.';
            case 'linked_existing_user_mail_failed':
                return 'Staff guardado. El acceso quedó actualizado, pero no se pudo enviar la notificación.';
            default:
                return res.message || 'Registro guardado';
        }
    }

    function destroyServiceMultiSelect() {
        var $field = $('#pms-service-ids');
        $(serviceMultiSelectContainerSelector()).remove();
        $field.next('.ms-container').remove();
        if ($field.length && typeof $.fn.multiSelect === 'function' && $field.data('multiselect')) {
            $field.multiSelect('destroy');
        }
        $field.css('position', '').css('left', '');
        serviceMultiSelectNeedsInit = false;
    }

    function initServiceMultiSelect() {
        var $field = $('#pms-service-ids');
        if (!$field.length || typeof $.fn.multiSelect !== 'function' || $field.prop('disabled') || !$field.find('option').length) {
            return;
        }

        destroyServiceMultiSelect();
        $field.multiSelect({
            selectableOptgroup: true,
            selectableHeader: '<div class="staff-services-header">Disponibles</div>',
            selectionHeader: '<div class="staff-services-header">Seleccionados</div>',
            afterInit: function () {
                updateServiceSelectionSummary();
            },
            afterSelect: function () {
                updateServiceSelectionSummary();
            },
            afterDeselect: function () {
                updateServiceSelectionSummary();
            }
        });
        serviceMultiSelectNeedsInit = false;

        if (!hasServiceMultiSelectRendered()) {
            window.setTimeout(function () {
                if (!hasServiceMultiSelectRendered() && !$field.prop('disabled') && $field.find('option').length) {
                    destroyServiceMultiSelect();
                    $field.multiSelect({
                        selectableOptgroup: true,
                        selectableHeader: '<div class="staff-services-header">Disponibles</div>',
                        selectionHeader: '<div class="staff-services-header">Seleccionados</div>',
                        afterInit: function () {
                            updateServiceSelectionSummary();
                        },
                        afterSelect: function () {
                            updateServiceSelectionSummary();
                        },
                        afterDeselect: function () {
                            updateServiceSelectionSummary();
                        }
                    });
                }
            }, 80);
        }
    }

    function ensureServiceMultiSelectRendered() {
        var $modal = $('#providerMedicalStaffModal');
        var $field = $('#pms-service-ids');
        if (!$field.length || $field.prop('disabled') || !$field.find('option').length) {
            return;
        }
        if (hasServiceMultiSelectRendered()) {
            serviceMultiSelectNeedsInit = false;
            return;
        }
        if (!$modal.is(':visible')) {
            serviceMultiSelectNeedsInit = true;
            return;
        }
        initServiceMultiSelect();
    }

    function normalizeAccessError(message, status) {
        if (status === 'staff_access_requires_valid_email') {
            return 'Para configurar acceso al panel debes registrar un email válido del profesional.';
        }
        if (status === 'existing_user_belongs_to_other_provider') {
            return 'El email ingresado ya está asociado a otro prestador. Usa otro correo para este profesional.';
        }
        if (status === 'user_already_linked_to_other_staff') {
            return 'No fue posible configurar el acceso porque ese usuario ya está asociado a otro miembro del staff.';
        }
        return message || 'No fue posible guardar el registro.';
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
        var allowAccessSetup = (mode === 'edit' || mode === 'create');
        $('#pms-access-section').toggle(allowAccessSetup);
        $('#pms-can-access-admin').prop('disabled', !allowAccessSetup);
        $('#pms-linked-user-id').prop('disabled', !allowAccessSetup);
        if (allowAccessSetup) {
            syncAccessUi();
        }
    }

    function buildSelectedMap(values) {
        var selectedMap = {};
        (values || []).forEach(function (id) {
            var parsed = parseInt(id, 10);
            if (parsed > 0) {
                selectedMap[parsed] = true;
            }
        });
        return selectedMap;
    }

    function updateServiceSelectionSummary() {
        var selectedTexts = ($('#pms-service-ids option:selected').map(function () {
            return $.trim($(this).text() || '');
        }).get() || []).filter(Boolean);

        if (!selectedTexts.length) {
            $('#pms-service-selection-summary').text('Sin servicios seleccionados.');
            return;
        }

        $('#pms-service-selection-summary').text(selectedTexts.length + ' servicio(s) seleccionado(s).');
    }

    function renderServiceOptions(items, selectedProviderCatalogServiceIds, selectedServiceIds) {
        var selectedProviderCatalogMap = buildSelectedMap(selectedProviderCatalogServiceIds || []);
        var selectedServiceMap = buildSelectedMap(selectedServiceIds || []);
        var $select = $('#pms-service-ids');

        if (!Array.isArray(items) || items.length === 0) {
            destroyServiceMultiSelect();
            $select.html('');
            $select.prop('disabled', true);
            $('#pms-service-empty-state').show();
            updateServiceSelectionSummary();
            return;
        }

        var groups = [];
        var currentGroup = null;
        items.forEach(function (item) {
            var groupName = $.trim(item.category_name || 'Sin categoría');
            if (!currentGroup || currentGroup.name !== groupName) {
                currentGroup = { name: groupName, options: [] };
                groups.push(currentGroup);
            }

            var providerCatalogServiceId = parseInt(item.provider_catalog_service_id || item.id, 10);
            var serviceId = parseInt(item.service_id || 0, 10);
            var selected = (
                (providerCatalogServiceId > 0 && selectedProviderCatalogMap[providerCatalogServiceId])
                || (serviceId > 0 && selectedServiceMap[serviceId])
            ) ? ' selected' : '';
            var label = item.label || item.service_name || ('Servicio #' + (serviceId || providerCatalogServiceId));

            currentGroup.options.push(
                '<option value="' + escapeHtml(providerCatalogServiceId) + '" data-service-id="' + escapeHtml(serviceId) + '"' + selected + '>'
                + escapeHtml(label)
                + '</option>'
            );
        });

        var html = groups.map(function (group) {
            return '<optgroup label="' + escapeHtml(group.name) + '">' + group.options.join('') + '</optgroup>';
        }).join('');

        $('#pms-service-empty-state').hide();
        $select.prop('disabled', false).html(html);
        serviceMultiSelectNeedsInit = true;
        ensureServiceMultiSelectRendered();
        updateServiceSelectionSummary();
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
        $('#pms-linked-user-id').val(selectedId ? String(selectedId) : '');
        syncAccessUi();
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

    function loadProviderServices(selectedProviderCatalogServiceIds, selectedServiceIds) {
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
            renderServiceOptions(providerServices, selectedProviderCatalogServiceIds || [], selectedServiceIds || []);
        }).fail(function (xhr) {
            providerServices = [];
            var message = 'No fue posible cargar los servicios habilitados.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            destroyServiceMultiSelect();
            $('#pms-service-ids').html('').prop('disabled', true);
            $('#pms-service-empty-state').text(message).show();
            updateServiceSelectionSummary();
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
        $('input[name="pms_access_level"][value="scoped"]').prop('checked', true);
        $('input[name="pms_access_level"][value="admin"]').prop('checked', false);
        $('#pms-access-status').text('Solo sus asignaciones');
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
        $('#pms-service-empty-state').text('Este prestador no tiene servicios habilitados disponibles.').hide();
        renderServiceOptions([], [], []);
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
        $('input[name="pms_access_level"][value="admin"]').prop('checked', parseInt(item.can_access_admin, 10) === 1);
        $('input[name="pms_access_level"][value="scoped"]').prop('checked', parseInt(item.can_access_admin, 10) !== 1);
        renderServiceOptions(providerServices, item.provider_catalog_service_ids || [], item.service_ids || []);
        $('#providerMedicalStaffModalLabel').text('Editar staff médico');
        setAccessSectionMode('edit');
        syncAccessUi(item.access_status_label || '');
    }

    function findSelectedLinkedUser() {
        var selectedId = parseInt($('#pms-linked-user-id').val() || 0, 10);
        if (selectedId <= 0) {
            return null;
        }
        for (var i = 0; i < linkableUsers.length; i += 1) {
            if (parseInt(linkableUsers[i].id, 10) === selectedId) {
                return linkableUsers[i];
            }
        }
        return null;
    }

    function resolveAccessStatusText(fallbackText) {
        var allowAccess = $('#pms-can-access-admin').is(':checked');
        var email = $.trim($('#pms-email').val() || '');

        if (!allowAccess) {
            return 'Solo sus asignaciones';
        }

        if (!email) {
            return 'Pendiente de completar email';
        }

        return fallbackText || 'Permisos administrativos';
    }

    function syncAccessUi(fallbackStatusText) {
        var allowAccess = $('#pms-can-access-admin').is(':checked');
        var email = $.trim($('#pms-email').val() || '');
        var summaryClass = 'alert alert-info';
        var summaryText = 'Este profesional tendrá un acceso estándar orientado a sus asignaciones.';

        $('.staff-permission-option').removeClass('is-active');
        $('.staff-permission-option[data-access-level="' + (allowAccess ? 'admin' : 'scoped') + '"]').addClass('is-active');

        if (allowAccess) {
            $('#pms-email-label').text('Email del profesional');
            $('#pms-email-help').text('Se usará para el acceso al panel y para las notificaciones relacionadas con su cuenta.');
            summaryClass = email ? 'alert alert-success' : 'alert alert-warning';
            summaryText = email
                ? 'Este profesional tendrá permisos administrativos en el panel.'
                : 'Para asignar permisos administrativos debes registrar un email válido del profesional.';
        } else {
            $('#pms-email-label').text('Email del profesional');
            $('#pms-email-help').text('Opcional. Úsalo para contacto del profesional y para su acceso al panel si necesita ingresar.');
        }

        $('#pms-access-summary').attr('class', summaryClass).text(summaryText);
        $('#pms-access-status').text(resolveAccessStatusText(fallbackStatusText));
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
            loadProviderServices([], []),
            loadLinkableUsers(0, 0)
        ).always(function () {
            $('#providerMedicalStaffModal').modal('show');
            window.setTimeout(ensureServiceMultiSelectRendered, 120);
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
                loadProviderServices(res.item.provider_catalog_service_ids || [], res.item.service_ids || []),
                loadStaffCatalogs(),
                loadProviderClinics(res.item.clinic_name || '')
            ).always(function () {
                fillForm(res.item);
                $('#pms-linked-user-id').val(res.item.linked_user_id || '');
                $('#providerMedicalStaffModal').modal('show');
                window.setTimeout(ensureServiceMultiSelectRendered, 120);
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
        if ($('#pms-can-access-admin').is(':checked')) {
            var email = $.trim($('#pms-email').val() || '');
            if (!email) {
                $('#pms-save-msg').html('<span class="text-danger">Para asignar permisos administrativos debes registrar un email válido del profesional.</span>').show();
                $('#pms-email').focus();
                return;
            }
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

        ($('#pms-service-ids').val() || []).forEach(function (providerCatalogServiceId) {
            var $option = $('#pms-service-ids option[value="' + providerCatalogServiceId + '"]');
            var serviceId = parseInt($option.attr('data-service-id') || 0, 10);
            fd.append('provider_catalog_service_ids[]', providerCatalogServiceId);
            if (serviceId > 0) {
                fd.append('service_ids[]', serviceId);
            }
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
            var feedback = accessFeedbackMessage(res);
            if (Array.isArray(res.warnings) && res.warnings.length) {
                $msg.html('<span class="text-warning">' + escapeHtml(res.warnings.join(' ')) + '</span>').show();
            }
            toast(feedback, (res.status && res.status.indexOf('mail_failed') !== -1) ? 'error' : 'success');
            $('#providerMedicalStaffModal').modal('hide');
            loadStaffList();
        }).fail(function (xhr) {
            var message = 'No fue posible guardar el registro.';
            var status = '';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            if (xhr && xhr.responseJSON && xhr.responseJSON.status) {
                status = xhr.responseJSON.status;
            }
            $msg.html('<span class="text-danger">' + escapeHtml(normalizeAccessError(message, status)) + '</span>').show();
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
        $('#providerMedicalStaffModal').on('shown.bs.modal', function () {
            if (serviceMultiSelectNeedsInit) {
                ensureServiceMultiSelectRendered();
            }
        });

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

        $('input[name="pms_access_level"]').on('change', function () {
            $('#pms-can-access-admin').prop('checked', $(this).val() === 'admin');
            syncAccessUi();
        });

        $('#pms-email').on('input blur', function () {
            syncAccessUi();
        });

        $('#pms-service-ids').on('change', function () {
            updateServiceSelectionSummary();
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
