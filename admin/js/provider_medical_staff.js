(function ($) {
    'use strict';

    var PROVIDER_ID = (typeof window !== 'undefined' && typeof window.PROVIDER_ID !== 'undefined')
        ? parseInt(window.PROVIDER_ID, 10)
        : 0;
    var CAN_MANAGE_STAFF = (typeof window !== 'undefined' && typeof window.CAN_MANAGE_STAFF !== 'undefined')
        ? !!window.CAN_MANAGE_STAFF
        : false;
    var ENDPOINT = 'ajax/provider_medical_staff.php';
    var linkableUsers = [];
    var providerServices = [];
    var staffCatalogs = { roles: [], specialties: [] };
    var staffCatalogMeta = {
        roles: { table_ready: true, real_count: 0, uses_fallback: false },
        specialties: { table_ready: true, real_count: 0, uses_fallback: false }
    };
    var providerClinics = [];
    var ownerAdminListItem = null;
    var ownerConversionContext = null;
    var currentItems = [];
    var MANAGE_DISABLED_BY_CONTEXT = false;
    var serviceMultiSelectNeedsInit = false;
    var pageParams = (function () {
        try {
            return new URLSearchParams(window.location.search || '');
        } catch (e) {
            return { get: function () { return ''; } };
        }
    }());
    var deepLinkedProviderCatalogServiceId = parseInt(pageParams.get('provider_catalog_service_id') || '0', 10) || 0;
    var deepLinkedServiceId = parseInt(pageParams.get('service_id') || '0', 10) || 0;
    var deepLinkedAction = $.trim(pageParams.get('action') || '').toLowerCase();
    var deepLinkHandled = false;
    var emailValidationRequest = null;
    var emailValidationTimer = null;
    var emailValidationState = {
        checkedEmail: '',
        staffId: 0,
        valid: false,
        pending: false,
        status: 'empty',
        mode: 'empty',
        message: ''
    };

    function serviceMultiSelectContainerSelector() {
        return '#ms-pms-service-ids';
    }

    function hasServiceMultiSelectRendered() {
        return $(serviceMultiSelectContainerSelector()).length > 0 || $('#pms-service-ids').next('.ms-container').length > 0;
    }

    function canManageStaff() {
        return !!CAN_MANAGE_STAFF;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function withFallback(value, fallback) {
        var text = $.trim(value || '');
        return text !== '' ? text : (fallback || 'Sin definir');
    }

    function normalizeEmail(value) {
        return $.trim(value || '').toLowerCase();
    }

    function isValidEmailFormat(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($.trim(value || ''));
    }

    function getCurrentStaffId() {
        return parseInt($('#pms-id').val() || 0, 10) || 0;
    }

    function isAccessEnabledRequested() {
        return $('#pms-enable-user-access').is(':checked');
    }

    function getCurrentEmailValidation() {
        var currentEmail = normalizeEmail($('#pms-email').val() || '');
        if (!currentEmail || emailValidationState.checkedEmail !== currentEmail || emailValidationState.staffId !== getCurrentStaffId()) {
            return null;
        }
        return emailValidationState;
    }

    function renderEmailValidationState() {
        var currentEmail = $.trim($('#pms-email').val() || '');
        var state = getCurrentEmailValidation();
        var $feedback = $('#pms-email-validation');
        var $group = $('#pms-email').closest('.form-group');

        $group.removeClass('has-error has-success');
        $feedback.hide().removeClass('text-danger text-success text-info text-warning').text('');

        if (!isAccessEnabledRequested()) {
            return;
        }

        if (!currentEmail) {
            return;
        }

        if (state && state.pending) {
            $feedback.addClass('text-info').text('Validando disponibilidad segura del email...').show();
            return;
        }

        if (!isValidEmailFormat(currentEmail)) {
            $group.addClass('has-error');
            $feedback.addClass('text-danger').text('Ingresa un email válido para aprovisionar acceso al staff.').show();
            return;
        }

        if (!state) {
            $feedback.addClass('text-warning').text('Verifica este email antes de guardar el acceso del staff.').show();
            return;
        }

        if (state.valid) {
            $group.addClass('has-success');
            $feedback.addClass(state.mode === 'reuse' ? 'text-info' : 'text-success').text(state.message || 'Este email puede usarse para crear el acceso del staff.').show();
            return;
        }

        $group.addClass('has-error');
        $feedback.addClass('text-danger').text(state.message || 'Este email no puede usarse para aprovisionar acceso al staff.').show();
    }

    function setEmailValidationState(nextState) {
        emailValidationState = $.extend({}, emailValidationState, nextState || {});
        renderEmailValidationState();
        syncAccessUi();
    }

    function requestAccessEmailValidation(force) {
        var deferred = $.Deferred();
        var email = $.trim($('#pms-email').val() || '');
        var normalizedEmail = normalizeEmail(email);
        var staffId = getCurrentStaffId();

        if (!isAccessEnabledRequested()) {
            setEmailValidationState({
                checkedEmail: '',
                staffId: staffId,
                valid: false,
                pending: false,
                status: 'disabled',
                mode: 'disabled',
                message: ''
            });
            deferred.resolve(emailValidationState);
            return deferred.promise();
        }

        if (!email) {
            setEmailValidationState({
                checkedEmail: '',
                staffId: staffId,
                valid: false,
                pending: false,
                status: 'empty',
                mode: 'empty',
                message: ''
            });
            deferred.resolve(emailValidationState);
            return deferred.promise();
        }

        if (!isValidEmailFormat(email)) {
            setEmailValidationState({
                checkedEmail: normalizedEmail,
                staffId: staffId,
                valid: false,
                pending: false,
                status: 'invalid_email',
                mode: 'blocked',
                message: 'Ingresa un email válido para aprovisionar acceso al staff.'
            });
            deferred.resolve(emailValidationState);
            return deferred.promise();
        }

        if (!force && !emailValidationState.pending && emailValidationState.checkedEmail === normalizedEmail && emailValidationState.staffId === staffId) {
            deferred.resolve(emailValidationState);
            return deferred.promise();
        }

        if (emailValidationRequest && typeof emailValidationRequest.abort === 'function') {
            emailValidationRequest.abort();
        }

        setEmailValidationState({
            checkedEmail: normalizedEmail,
            staffId: staffId,
            pending: true,
            message: 'Validando disponibilidad segura del email...'
        });

        emailValidationRequest = $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'validate_staff_access_email',
                provider_id: PROVIDER_ID,
                staff_id: staffId || '',
                email: email
            }
        }).done(function (res) {
            setEmailValidationState({
                checkedEmail: normalizedEmail,
                staffId: staffId,
                valid: !!(res && res.valid),
                pending: false,
                status: (res && res.status) || 'validation_failed',
                mode: (res && res.mode) || 'blocked',
                message: (res && res.message) || 'No fue posible validar este email para acceso de staff.'
            });
        }).fail(function (xhr, textStatus) {
            if (textStatus === 'abort') {
                deferred.reject();
                return;
            }

            var message = 'No fue posible validar este email para acceso de staff. Inténtalo de nuevo antes de guardar.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            setEmailValidationState({
                checkedEmail: normalizedEmail,
                staffId: staffId,
                valid: false,
                pending: false,
                status: (xhr && xhr.responseJSON && xhr.responseJSON.status) || 'validation_failed',
                mode: 'blocked',
                message: message
            });
        }).always(function () {
            emailValidationRequest = null;
            deferred.resolve(emailValidationState);
        });

        return deferred.promise();
    }

    function scheduleAccessEmailValidation(immediate) {
        if (emailValidationTimer) {
            window.clearTimeout(emailValidationTimer);
            emailValidationTimer = null;
        }

        if (immediate) {
            return requestAccessEmailValidation(true);
        }

        emailValidationTimer = window.setTimeout(function () {
            requestAccessEmailValidation(true);
        }, 350);
        return $.Deferred().resolve(emailValidationState).promise();
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
        if (status === 'invalid_role_title') {
            return 'Selecciona un cargo o rol válido del catálogo del staff.';
        }
        if (status === 'invalid_specialty') {
            return 'Selecciona una especialidad válida del catálogo del staff.';
        }
        if (status === 'staff_access_requires_valid_email') {
            return 'Para configurar acceso al panel debes registrar un email válido del profesional.';
        }
        if (status === 'existing_user_belongs_to_other_provider') {
            return 'Este email ya pertenece a una cuenta existente que no puede vincularse como staff. Usa otro email.';
        }
        if (status === 'existing_user_sensitive_account' || status === 'existing_user_privileged_or_incompatible_role' || status === 'existing_user_not_staff_eligible' || status === 'linked_user_not_allowed_for_staff') {
            return 'Este email ya pertenece a una cuenta existente que no puede vincularse como staff. Usa otro email.';
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

    function renderRows(items, ownerItem) {
        currentItems = Array.isArray(items) ? items.slice() : [];
        ownerAdminListItem = ownerItem || null;
        if (!currentItems.length && !ownerAdminListItem) {
            renderEmpty('Aún no hay staff médico registrado para este prestador.');
            return;
        }

        var rows = [];

        if (ownerAdminListItem) {
            var ownerActionsHtml = canManageStaff()
                ? '<button type="button" class="btn btn-xs btn-primary owner-convert-to-staff"><i class="fa fa-user-md"></i> Convertir en staff</button>'
                : '<span class="text-muted">Solo lectura</span>';
            rows.push(''
                + '<tr class="info" data-owner-admin="1">'
                + '<td class="text-center">' + avatarHtml(ownerAdminListItem) + '</td>'
                + '<td>'
                + '<strong>' + escapeHtml(withFallback(ownerAdminListItem.full_name, 'Owner / admin inicial')) + '</strong> '
                + '<span class="label label-info">Owner/admin</span>'
                + '<div class="text-muted small">' + escapeHtml(withFallback(ownerAdminListItem.email, 'Sin correo')) + ' · ' + escapeHtml(withFallback(ownerAdminListItem.phone, 'Sin teléfono')) + '</div>'
                + '<div class="small"><span class="label label-default">Owner/admin del provider</span></div>'
                + '</td>'
                + '<td>'
                + '<div>' + escapeHtml(withFallback(ownerAdminListItem.role_title, 'Owner / admin inicial')) + '</div>'
                + '<div class="text-muted small">' + escapeHtml(ownerAdminListItem.linked_user_label || 'Usuario owner/admin vinculado') + '</div>'
                + '<div class="small"><span class="label ' + (parseInt(ownerAdminListItem.is_active || 0, 10) === 1 ? 'label-success' : 'label-default') + '">' + escapeHtml(ownerAdminListItem.access_status_label || 'Owner/admin del provider') + '</span></div>'
                + '</td>'
                + '<td>'
                + '<div>' + escapeHtml(withFallback(ownerAdminListItem.specialty, 'Gestión del prestador')) + '</div>'
                + '<div class="text-muted small">Visible en este listado por alcance operativo; no nace desde el alta de staff.</div>'
                + '</td>'
                + '<td><span class="label label-default">No</span></td>'
                + '<td>' + renderBooleanBadge(parseInt(ownerAdminListItem.is_active || 0, 10) === 1, 'Activo', 'Inactivo') + '</td>'
                + '<td><strong>#0</strong><div class="text-muted small">Ownership explícito</div></td>'
                + '<td class="text-right">' + ownerActionsHtml + '</td>'
                + '</tr>');
        }

        rows = rows.concat(currentItems.map(function (item, index) {
            var active = parseInt(item.is_active || item.active, 10) === 1;
            var isPrimary = parseInt(item.is_primary_doctor, 10) === 1;
            var roleTitle = withFallback(item.role_title, 'Sin cargo definido');
            var specialty = withFallback(item.specialty, 'Sin especialidad definida');
            var toggleLabel = active ? 'Desactivar' : 'Activar';
            var toggleValue = active ? 0 : 1;
            var serviceSummary = withFallback(item.service_summary, 'Sin servicios asignados');
            var linkedUser = $.trim(item.linked_user_label || '');
            var linkedUserId = parseInt(item.linked_user_id || 0, 10) || 0;
            var linkedUserActive = parseInt(item.linked_user_active || 0, 10) === 1;
            var accessEnabled = parseInt(item.can_access_admin || 0, 10) === 1;
            var accessOperational = linkedUserId > 0 && accessEnabled && linkedUserActive;
            var accessStatusText = linkedUserId > 0
                ? (item.access_status_label || (accessOperational ? 'Acceso al panel activo' : 'Acceso al panel desactivado'))
                : 'Sin acceso al panel';
            var accessToggleLabel = accessOperational ? 'Desactivar acceso' : 'Activar acceso';
            var accessToggleValue = accessOperational ? 0 : 1;
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
                if (linkedUserId > 0) {
                    actionsHtml += ' '
                        + '<button type="button" class="btn btn-xs ' + (accessOperational ? 'btn-danger' : 'btn-info') + ' staff-toggle-access" data-value="' + accessToggleValue + '">'
                        + '<i class="fa ' + (accessOperational ? 'fa-lock' : 'fa-unlock') + '"></i> ' + accessToggleLabel + '</button>';
                }
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
                + '<div class="small"><span class="label ' + (accessOperational ? 'label-success' : 'label-default') + '">' + escapeHtml(accessStatusText) + '</span></div>'
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
            }));

            $('#tbl-provider-medical-staff tbody').html(rows.join(''));
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

    function catalogConfig(catalogType) {
        if (catalogType === 'roles') {
            return {
                selectId: 'pms-role-title',
                emptyNoteId: 'pms-role-title-empty-note',
                fieldLabel: 'cargo o rol',
                createButtonLabel: 'Nuevo cargo'
            };
        }
        return {
            selectId: 'pms-specialty',
            emptyNoteId: 'pms-specialty-empty-note',
            fieldLabel: 'especialidad',
            createButtonLabel: 'Nueva especialidad'
        };
    }

    function updateCatalogEmptyState(catalogType) {
        var config = catalogConfig(catalogType);
        var meta = staffCatalogMeta[catalogType] || {};
        var items = staffCatalogs[catalogType] || [];
        var $note = $('#' + config.emptyNoteId);
        var message = '';

        if (!meta.table_ready) {
            message = 'El catálogo no está disponible todavía en esta instalación.';
        } else if (!items.length) {
            message = 'Aún no hay opciones cargadas. Crea una desde aquí para continuar.';
        } else if (meta.uses_fallback) {
            message = 'Se están usando opciones temporales del sistema porque el catálogo aún no tiene datos persistidos.';
        }

        if (message) {
            $note.text(message).show();
        } else {
            $note.hide().text('');
        }
    }

    function openCatalogCreateModal(catalogType) {
        if (!canManageStaff()) {
            return;
        }
        var config = catalogConfig(catalogType);
        $('#pms-catalog-type').val(catalogType);
        $('#pms-catalog-name').val('');
        $('#pms-catalog-modal-feedback').hide().text('');
        $('#pms-catalog-name-label').text(catalogType === 'roles' ? 'Nombre del nuevo cargo' : 'Nombre de la nueva especialidad');
        $('#providerStaffCatalogModalLabel').html('<strong>' + escapeHtml(config.createButtonLabel) + '</strong>');
        $('#providerStaffCatalogModal').modal('show');
        window.setTimeout(function () {
            $('#pms-catalog-name').focus();
        }, 120);
    }

    function saveCatalogItemFromModal() {
        var catalogType = $.trim($('#pms-catalog-type').val() || '');
        var name = $.trim($('#pms-catalog-name').val() || '');
        var config = catalogConfig(catalogType);
        var $feedback = $('#pms-catalog-modal-feedback');
        var $button = $('#btn-save-staff-catalog-item');

        if (!catalogType || !config.selectId) {
            return;
        }
        if (!name) {
            $feedback.text('El nombre no puede estar vacío.').show();
            return;
        }

        $feedback.hide().text('');
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');

        $.ajax({
            url: ENDPOINT,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'save_staff_catalog_item',
                provider_id: PROVIDER_ID,
                catalog_type: catalogType,
                name: name
            }
        }).done(function (res) {
            if (!res || !res.ok) {
                var failureMessage = (res && res.message) || 'No fue posible guardar el catálogo.';
                $feedback.text(failureMessage).show();
                toast(failureMessage, 'error');
                return;
            }

            loadStaffCatalogs({
                role_title: catalogType === 'roles' ? (res.name || name) : $.trim($('#pms-role-title').val() || ''),
                specialty: catalogType === 'specialties' ? (res.name || name) : $.trim($('#pms-specialty').val() || '')
            }).done(function () {
                if (catalogType === 'roles') {
                    $('#pms-role-title').val(res.name || name);
                } else {
                    $('#pms-specialty').val(res.name || name);
                }
                $('#providerStaffCatalogModal').modal('hide');
                toast((catalogType === 'roles' ? 'Cargo' : 'Especialidad') + ' guardado correctamente.', 'success');
            }).fail(function () {
                $feedback.text('El catálogo se guardó, pero no pudo refrescarse el selector.').show();
                toast('El catálogo se guardó, pero no pudo refrescarse el selector.', 'error');
            });
        }).fail(function (xhr) {
            var message = 'No fue posible guardar el catálogo.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $feedback.text(message).show();
            toast(message, 'error');
        }).always(function () {
            $button.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar');
        });
    }

    function setLegacySelectState(selectId, legacyValue) {
        var normalized = $.trim(legacyValue || '');
        $('#' + selectId).attr('data-current-legacy-value', normalized);
    }

    function getLegacySelectState(selectId) {
        return $.trim($('#' + selectId).attr('data-current-legacy-value') || '');
    }

    function validateCatalogSelectValue(selectId, fieldLabel) {
        var $select = $('#' + selectId);
        var value = $.trim($select.val() || '');
        var legacyValue = getLegacySelectState(selectId);
        if (!value) {
            return { valid: true, value: '' };
        }

        var $option = $select.find('option').filter(function () {
            return $(this).val() === value;
        }).first();

        if (!$option.length) {
            return { valid: false, message: 'Selecciona un ' + fieldLabel + ' válido del catálogo.' };
        }

        if ($option.attr('data-legacy-option') === '1' && value !== legacyValue) {
            return { valid: false, message: 'Selecciona un ' + fieldLabel + ' válido del catálogo.' };
        }

        return { valid: true, value: value, legacy: $option.attr('data-legacy-option') === '1' };
    }

    function renderRoleOptions(roles, selectedValue) {
        setLegacySelectState('pms-role-title', '');
        renderSelectOptions('pms-role-title', roles || staffCatalogs.roles, selectedValue || '',
            '— Seleccionar cargo —');
        // Compatibilidad legacy: si el valor no está en las opciones, añadirlo
        if (selectedValue) {
            ensureSelectOption('pms-role-title', selectedValue, {
                legacy: true,
                legacyLabelSuffix: ' (valor legacy)'
            });
        }
    }

    function renderSpecialtyOptions(specialties, selectedValue) {
        setLegacySelectState('pms-specialty', '');
        renderSelectOptions('pms-specialty', specialties || staffCatalogs.specialties, selectedValue || '',
            '— Seleccionar especialidad —');
        if (selectedValue) {
            ensureSelectOption('pms-specialty', selectedValue, {
                legacy: true,
                legacyLabelSuffix: ' (valor legacy)'
            });
        }
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

    function loadStaffCatalogs(selectedValues) {
        selectedValues = selectedValues || {};
        return $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: { action: 'list_staff_catalogs', provider_id: PROVIDER_ID }
        }).done(function (res) {
            var rolesMeta = (res && res.roles_meta) ? res.roles_meta : {};
            var specialtiesMeta = (res && res.specialties_meta) ? res.specialties_meta : {};

            staffCatalogMeta.roles = {
                table_ready: rolesMeta.table_ready !== false,
                real_count: parseInt(rolesMeta.real_count || 0, 10) || 0,
                uses_fallback: !!rolesMeta.uses_fallback
            };
            staffCatalogMeta.specialties = {
                table_ready: specialtiesMeta.table_ready !== false,
                real_count: parseInt(specialtiesMeta.real_count || 0, 10) || 0,
                uses_fallback: !!specialtiesMeta.uses_fallback
            };

            staffCatalogs.roles = (res && Array.isArray(res.roles)) ? res.roles : [];
            staffCatalogs.specialties = (res && Array.isArray(res.specialties)) ? res.specialties : [];

            if (staffCatalogMeta.roles.table_ready && staffCatalogMeta.roles.real_count === 0) {
                staffCatalogs.roles = [];
            }
            if (staffCatalogMeta.specialties.table_ready && staffCatalogMeta.specialties.real_count === 0) {
                staffCatalogs.specialties = [];
            }

            renderRoleOptions(staffCatalogs.roles, selectedValues.role_title || '');
            renderSpecialtyOptions(staffCatalogs.specialties, selectedValues.specialty || '');
            updateCatalogEmptyState('roles');
            updateCatalogEmptyState('specialties');
        }).fail(function () {
            $('#pms-role-title').html('<option value="">No disponible</option>');
            $('#pms-specialty').html('<option value="">No disponible</option>');
            staffCatalogs.roles = [];
            staffCatalogs.specialties = [];
            staffCatalogMeta.roles = { table_ready: false, real_count: 0, uses_fallback: false };
            staffCatalogMeta.specialties = { table_ready: false, real_count: 0, uses_fallback: false };
            updateCatalogEmptyState('roles');
            updateCatalogEmptyState('specialties');
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

    function buildOwnerAdminConversionItem(ownerItem) {
        if (!ownerItem) {
            return null;
        }

        return {
            id: '',
            full_name: ownerItem.full_name || '',
            role_title: '',
            specialty: '',
            sort_order: '',
            photo: '',
            bio_short: '',
            email: ownerItem.email || ownerItem.linked_user_email || '',
            phone: ownerItem.phone || '',
            professional_license: '',
            clinic_name: '',
            notes: '',
            is_active: parseInt(ownerItem.is_active || ownerItem.active || 1, 10) === 1 ? 1 : 0,
            active: parseInt(ownerItem.is_active || ownerItem.active || 1, 10) === 1 ? 1 : 0,
            is_primary_doctor: 0,
            can_access_admin: 1,
            linked_user_id: parseInt(ownerItem.linked_user_id || 0, 10) || 0,
            access_status_label: ownerItem.access_status_label || 'Owner/admin con acceso al panel',
            provider_catalog_service_ids: [],
            service_ids: []
        };
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
        $('#pms-enable-user-access').prop('checked', true);
        $('#pms-can-access-admin').prop('checked', false);
        $('input[name="pms_access_level"][value="scoped"]').prop('checked', true);
        $('input[name="pms_access_level"][value="admin"]').prop('checked', false);
        $('#pms-access-status').text('Solo sus asignaciones');
        if (emailValidationTimer) {
            window.clearTimeout(emailValidationTimer);
            emailValidationTimer = null;
        }
        if (emailValidationRequest && typeof emailValidationRequest.abort === 'function') {
            emailValidationRequest.abort();
        }
        emailValidationRequest = null;
        emailValidationState = {
            checkedEmail: '',
            staffId: 0,
            valid: false,
            pending: false,
            status: 'empty',
            mode: 'empty',
            message: ''
        };
        ownerConversionContext = null;
        $('#providerMedicalStaffModalLabel').text('Agregar staff médico');
        $('#pms-owner-conversion-note').hide();
        $('#pms-save-msg').hide().empty();
        // Foto: limpiar preview y campo hidden
        $('#pms-photo').val('');
        $('#pms-photo-file').val('');
        $('#pms-photo-preview').attr('src', '');
        $('#pms-photo-preview-wrap').hide();
        // Signaling de carga para selects dinámicos
        $('#pms-role-title').html('<option value="">Cargando...</option>');
        $('#pms-specialty').html('<option value="">Cargando...</option>');
        $('#pms-role-title-empty-note').hide().text('');
        $('#pms-specialty-empty-note').hide().text('');
        $('#pms-clinic').html('<option value="">Cargando...</option>');
        $('#pms-clinic-other').val('').hide();
        providerServices = [];
        staffCatalogs.roles = [];
        staffCatalogs.specialties = [];
        staffCatalogMeta.roles = { table_ready: true, real_count: 0, uses_fallback: false };
        staffCatalogMeta.specialties = { table_ready: true, real_count: 0, uses_fallback: false };
        $('#pms-service-empty-state').text('Este prestador no tiene servicios habilitados disponibles.').hide();
        renderServiceOptions([], [], []);
        renderLinkedUserOptions([], 0);
        setAccessSectionMode('create');
        renderEmailValidationState();
    }

    // Garantiza que un valor legacy exista como option en el select
    function ensureSelectOption(selectId, value) {
        if (!value) {
            $('#' + selectId).val('');
            return;
        }
        var $sel = $('#' + selectId);
        var config = arguments.length > 2 ? (arguments[2] || {}) : {};
        var $existingOption = $sel.find('option').filter(function () { return $(this).val() === value; }).first();
        if (!$existingOption.length) {
            var $option = $('<option>').val(value).text(value + (config.legacyLabelSuffix || ' ✓'));
            if (config.legacy) {
                $option.attr('data-legacy-option', '1');
                setLegacySelectState(selectId, value);
            }
            $sel.append($option);
        } else if (config.legacy) {
            $existingOption.attr('data-legacy-option', '1');
            if (config.legacyLabelSuffix && $existingOption.text().indexOf(config.legacyLabelSuffix) === -1) {
                $existingOption.text(value + config.legacyLabelSuffix);
            }
            setLegacySelectState(selectId, value);
        }
        $sel.val(value);
    }

    function fillForm(item, options) {
        options = options || {};
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
        $('#pms-enable-user-access').prop('checked', parseInt(item.can_access_admin, 10) === 1);
        $('#pms-can-access-admin').prop('checked', parseInt(item.can_access_admin, 10) === 1);
        $('input[name="pms_access_level"][value="scoped"]').prop('checked', true);
        $('input[name="pms_access_level"][value="admin"]').prop('checked', parseInt(item.can_access_admin, 10) === 1);
        $('input[name="pms_access_level"][value="scoped"]').prop('checked', parseInt(item.can_access_admin, 10) === 1 ? false : true);
        renderServiceOptions(providerServices, item.provider_catalog_service_ids || [], item.service_ids || []);
        $('#providerMedicalStaffModalLabel').text(options.title || (item.id ? 'Editar staff médico' : 'Agregar staff médico'));
        if (options.ownerConversion) {
            $('#pms-owner-conversion-note').show();
        }
        setAccessSectionMode(options.mode || (item.id ? 'edit' : 'create'));
        syncAccessUi(item.access_status_label || '');
        scheduleAccessEmailValidation(true);
    }

    function openOwnerAdminConversionModal() {
        var ownerItem = ownerAdminListItem;
        var ownerAsStaffItem = buildOwnerAdminConversionItem(ownerItem);

        if (!ownerItem || !ownerAsStaffItem || !ownerAsStaffItem.linked_user_id) {
            toast('No fue posible preparar la conversión del owner/admin a staff.', 'error');
            return;
        }

        resetForm();
        ownerConversionContext = {
            linkedUserId: ownerAsStaffItem.linked_user_id,
            ownerItem: ownerItem
        };

        $.when(
            loadLinkableUsers(ownerAsStaffItem.linked_user_id || 0, 0),
            loadProviderServices([], []),
            loadStaffCatalogs(),
            loadProviderClinics('')
        ).always(function () {
            fillForm(ownerAsStaffItem, {
                mode: 'create',
                ownerConversion: true,
                title: 'Convertir owner/admin en staff clínico'
            });
            $('#pms-linked-user-id').val(String(ownerAsStaffItem.linked_user_id));
            $('#providerMedicalStaffModal').modal('show');
            window.setTimeout(ensureServiceMultiSelectRendered, 120);
        });
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
        var accessLevel = $('input[name="pms_access_level"]:checked').val() || 'scoped';
        var email = $.trim($('#pms-email').val() || '');
        var validation = getCurrentEmailValidation();

        if (!isAccessEnabledRequested()) {
            return 'Acceso al panel desactivado';
        }

        if (!email) {
            return 'Pendiente de completar email';
        }

        if (validation && validation.pending) {
            return 'Validando email para acceso';
        }

        if (validation && !validation.valid) {
            return 'Acceso bloqueado por email no apto';
        }

        if (accessLevel === 'admin') {
            return fallbackText || 'Acceso al panel: permisos administrativos';
        }
        return fallbackText || 'Acceso al panel: solo sus asignaciones';
    }

    function syncAccessUi(fallbackStatusText) {
        var accessLevel = $('input[name="pms_access_level"]:checked').val() || 'scoped';
        var accessEnabled = isAccessEnabledRequested();
        var email = $.trim($('#pms-email').val() || '');
        var validation = getCurrentEmailValidation();
        var summaryClass = email ? 'alert alert-info' : 'alert alert-warning';
        var summaryText = 'Se requiere un email válido para aprovisionar acceso al panel. El correo de bienvenida se enviará con cualquiera de los niveles de acceso.';

        $('.staff-permission-option').removeClass('is-active');
        if (accessEnabled) {
            $('.staff-permission-option[data-access-level="' + accessLevel + '"]').addClass('is-active');
        }
        $('#pms-can-access-admin').prop('checked', accessEnabled);
        $('#pms-access-level-options').toggleClass('text-muted', !accessEnabled).css('opacity', accessEnabled ? 1 : 0.55);
        $('#pms-access-level-options input').prop('disabled', !accessEnabled);

        $('#pms-email-label').text('Email del profesional');
        $('#pms-email-help').text(accessEnabled
            ? 'Se requiere un email válido para aprovisionar acceso al panel. El correo de bienvenida se envía tanto para perfiles de solo asignaciones como para perfiles con permisos administrativos.'
            : 'Puedes guardar este staff sin acceso al panel. Si más adelante habilitas acceso, se validará el email antes de aprovisionarlo.');

        if (!accessEnabled) {
            summaryClass = 'alert alert-warning';
            summaryText = 'Este staff quedará registrado sin acceso al panel. Podrás activarlo más adelante sin perder el vínculo del usuario.';
        } else if (!email) {
            summaryText = 'Se requiere un email válido para aprovisionar acceso al panel y enviar el correo de bienvenida, tanto en solo asignaciones como en permisos administrativos.';
        } else if (validation && validation.pending) {
            summaryClass = 'alert alert-info';
            summaryText = 'Validando si este email puede usarse de forma segura para el acceso del staff.';
        } else if (validation && !validation.valid) {
            summaryClass = 'alert alert-danger';
            summaryText = validation.message || 'Este email no puede usarse para aprovisionar acceso al staff.';
        } else if (validation && validation.valid && validation.mode === 'reuse') {
            summaryClass = accessLevel === 'admin' ? 'alert alert-success' : 'alert alert-info';
            summaryText = validation.message || 'Ya existe un usuario reutilizable para este staff dentro del mismo prestador.';
        } else if (email) {
            summaryClass = accessLevel === 'admin' ? 'alert alert-success' : 'alert alert-info';
            summaryText = accessLevel === 'admin'
                ? 'Este profesional tendrá acceso al panel con permisos administrativos. Se enviará su correo de bienvenida.'
                : 'Este profesional tendrá acceso al panel para gestionar sus asignaciones. Se enviará su correo de bienvenida.';
        }

        $('#pms-access-summary').attr('class', summaryClass).text(summaryText);
        $('#pms-access-status').text(resolveAccessStatusText(fallbackStatusText));
        renderEmailValidationState();
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
            setTabCount(res.total_universe || res.total || 0, res.active_total_universe || res.active_total || 0);
            renderRows(res.items || [], res.owner_admin_item || null);
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
        return openCreateModalWithService([], []);
    }

    function resolveDeepLinkedServiceSelection(items) {
        var selectedProviderCatalogServiceIds = [];
        var selectedServiceIds = [];

        if (!Array.isArray(items) || !items.length) {
            return {
                providerCatalogServiceIds: selectedProviderCatalogServiceIds,
                serviceIds: selectedServiceIds,
                item: null
            };
        }

        var matchedItem = null;
        items.some(function (item) {
            var providerCatalogServiceId = parseInt(item.provider_catalog_service_id || item.id, 10) || 0;
            var serviceId = parseInt(item.service_id || 0, 10) || 0;
            if (deepLinkedProviderCatalogServiceId > 0 && providerCatalogServiceId === deepLinkedProviderCatalogServiceId) {
                matchedItem = item;
                return true;
            }
            if (deepLinkedProviderCatalogServiceId <= 0 && deepLinkedServiceId > 0 && serviceId === deepLinkedServiceId) {
                matchedItem = item;
                return true;
            }
            return false;
        });

        if (matchedItem) {
            var matchedProviderCatalogServiceId = parseInt(matchedItem.provider_catalog_service_id || matchedItem.id, 10) || 0;
            var matchedServiceId = parseInt(matchedItem.service_id || 0, 10) || 0;
            if (matchedProviderCatalogServiceId > 0) {
                selectedProviderCatalogServiceIds.push(matchedProviderCatalogServiceId);
            }
            if (matchedServiceId > 0) {
                selectedServiceIds.push(matchedServiceId);
            }
        }

        return {
            providerCatalogServiceIds: selectedProviderCatalogServiceIds,
            serviceIds: selectedServiceIds,
            item: matchedItem
        };
    }

    function updateServiceContextNote(selectedItem) {
        var $note = $('#staff-service-context-note');
        if (!$note.length) {
            return;
        }
        if (!selectedItem) {
            $note.hide().empty();
            return;
        }

        var label = $.trim(selectedItem.label || selectedItem.service_name || '');
        $note
            .html('Llegaste desde Mis Servicios para asignar el servicio habilitado <strong>' + escapeHtml(label || ('#' + (selectedItem.provider_catalog_service_id || selectedItem.id || ''))) + '</strong> a uno o más miembros del staff médico.')
            .show();
    }

    function openCreateModalWithService(selectedProviderCatalogServiceIds, selectedServiceIds) {
        resetForm();
        $.when(
            loadStaffCatalogs(),
            loadProviderClinics(''),
            loadProviderServices(selectedProviderCatalogServiceIds || [], selectedServiceIds || []),
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
        var $msg = $('#pms-save-msg');
        var accessEnabled = isAccessEnabledRequested();
        if (!fullName) {
            $msg.html('<span class="text-danger">El nombre completo es obligatorio.</span>').show();
            return;
        }
        $msg.hide().empty();

        var validationPromise = accessEnabled
            ? requestAccessEmailValidation(true)
            : $.Deferred().resolve({ valid: true, message: '' }).promise();

        validationPromise.done(function (validation) {
            var email = $.trim($('#pms-email').val() || '');
            if (accessEnabled && (!email || !validation || !validation.valid)) {
                $msg.html('<span class="text-danger">' + escapeHtml((validation && validation.message) || 'Se requiere un email válido y apto para aprovisionar acceso al staff.') + '</span>').show();
                $('#pms-email').focus();
                return;
            }

            var roleValidation = validateCatalogSelectValue('pms-role-title', 'cargo o rol');
            if (!roleValidation.valid) {
                $msg.html('<span class="text-danger">' + escapeHtml(roleValidation.message) + '</span>').show();
                toast(roleValidation.message, 'error');
                $('#pms-role-title').focus();
                return;
            }

            var specialtyValidation = validateCatalogSelectValue('pms-specialty', 'especialidad');
            if (!specialtyValidation.valid) {
                $msg.html('<span class="text-danger">' + escapeHtml(specialtyValidation.message) + '</span>').show();
                toast(specialtyValidation.message, 'error');
                $('#pms-specialty').focus();
                return;
            }

            var fd = new FormData();
            fd.append('action',      'save_staff');
            fd.append('provider_id', PROVIDER_ID);
            fd.append('id',          $('#pms-id').val() || '');
            fd.append('full_name',   fullName);
            fd.append('role_title',  roleValidation.value || '');
            fd.append('specialty',   specialtyValidation.value || '');
            fd.append('sort_order',  $.trim($('#pms-sort-order').val() || ''));
            fd.append('photo',       $.trim($('#pms-photo').val() || ''));
            fd.append('bio_short',   $.trim($('#pms-bio-short').val() || ''));
            fd.append('professional_license', $.trim($('#pms-license').val() || ''));
            fd.append('email',       email);
            fd.append('phone',       $.trim($('#pms-phone').val() || ''));
            var clinicVal = $.trim($('#pms-clinic').val() || '') === '__other__'
                ? $.trim($('#pms-clinic-other').val() || '')
                : $.trim($('#pms-clinic').val() || '');
            fd.append('clinic_name', clinicVal);
            fd.append('linked_user_id', $('#pms-linked-user-id').val() || '');
            fd.append('notes',       $.trim($('#pms-notes').val() || ''));

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
            if (accessEnabled) { fd.append('can_access_admin', 1); }

            var $btn = $('#btn-save-medical-staff');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');

            $.ajax({
                url:         ENDPOINT,
                method:      'POST',
                dataType:    'json',
                data:        fd,
                processData: false,
                contentType: false
            }).done(function (res) {
                if (!res || !res.ok) {
                    var failureMessage = (res && res.message) || 'No fue posible guardar.';
                    $msg.html('<span class="text-danger">' + escapeHtml(failureMessage) + '</span>').show();
                    toast(failureMessage, 'error');
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
                message = normalizeAccessError(message, status);
                $msg.html('<span class="text-danger">' + escapeHtml(message) + '</span>').show();
                toast(message, 'error');
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar');
            });
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

    function toggleStaffAccess(staffId, nextValue) {
        $.ajax({
            url: ENDPOINT,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'toggle_staff_access',
                provider_id: PROVIDER_ID,
                id: staffId,
                value: nextValue
            }
        }).done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'No fue posible actualizar el acceso del usuario vinculado.', 'error');
                return;
            }
            toast(res.message || 'Acceso actualizado', 'success');
            loadStaffList();
        }).fail(function (xhr) {
            var message = 'No fue posible actualizar el acceso del usuario vinculado.';
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
                var ownerItem = res.owner_admin_item || ownerAdminListItem || null;
                renderRows(res.items, ownerItem);
                setTabCount((res.total_universe || (res.items.length + (ownerItem ? 1 : 0))), (res.active_total_universe || (res.items.filter(function (item) {
                    return parseInt(item.is_active || item.active, 10) === 1;
                }).length + ((ownerItem && parseInt(ownerItem.is_active || 0, 10) === 1) ? 1 : 0))));
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

        MANAGE_DISABLED_BY_CONTEXT = !CAN_MANAGE_STAFF;

        loadStaffList();

        if (!deepLinkHandled && deepLinkedAction === 'assign_staff' && CAN_MANAGE_STAFF) {
            deepLinkHandled = true;
            loadProviderServices([], []).always(function () {
                var resolvedSelection = resolveDeepLinkedServiceSelection(providerServices);
                updateServiceContextNote(resolvedSelection.item);
                openCreateModalWithService(resolvedSelection.providerCatalogServiceIds, resolvedSelection.serviceIds);
            });
        }

        $('#btn-add-medical-staff').on('click', openCreateModal);
        $('#btn-save-medical-staff').on('click', function () {
            $('#form-provider-medical-staff').trigger('submit');
        });
        $('#btn-save-staff-catalog-item').on('click', saveCatalogItemFromModal);
        $('#form-provider-staff-catalog-item').on('submit', function (e) {
            e.preventDefault();
            saveCatalogItemFromModal();
        });
        $(document).on('click', '.pms-open-catalog-modal', function () {
            openCatalogCreateModal($(this).data('catalog-type'));
        });
        $('#form-provider-medical-staff').on('submit', saveStaff);
        $('#providerMedicalStaffModal').on('hidden.bs.modal', resetForm);
        $('#providerMedicalStaffModal').on('shown.bs.modal', function () {
            if (serviceMultiSelectNeedsInit) {
                ensureServiceMultiSelectRendered();
            }
        });
        $('#providerStaffCatalogModal').on('hidden.bs.modal', function () {
            $('#pms-catalog-type').val('');
            $('#pms-catalog-name').val('');
            $('#pms-catalog-modal-feedback').hide().text('');
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
            syncAccessUi();
        });

        $('#pms-enable-user-access').on('change', function () {
            syncAccessUi();
            if ($(this).is(':checked')) {
                scheduleAccessEmailValidation(true);
            }
        });

        $('#pms-email').on('input blur', function () {
            syncAccessUi();
            if (isAccessEnabledRequested()) {
                scheduleAccessEmailValidation($(this).is(':focus') ? false : true);
            }
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

        $('#tbl-provider-medical-staff').on('click', '.owner-convert-to-staff', function () {
            if (!canManageStaff()) {
                return;
            }
            openOwnerAdminConversionModal();
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

        $('#tbl-provider-medical-staff').on('click', '.staff-toggle-access', function () {
            if (!canManageStaff()) {
                return;
            }
            var staffId = parseInt($(this).closest('tr').data('id'), 10) || 0;
            var nextValue = parseInt($(this).data('value'), 10);
            if (staffId <= 0 || (nextValue !== 0 && nextValue !== 1)) {
                return;
            }
            var question = nextValue === 1
                ? '¿Deseas activar el acceso al panel para el usuario vinculado de este staff?'
                : '¿Deseas desactivar el acceso al panel para el usuario vinculado de este staff?';
            if (!window.confirm(question)) {
                return;
            }
            toggleStaffAccess(staffId, nextValue);
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
