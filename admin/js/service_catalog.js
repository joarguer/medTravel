$(document).ready(function(){
    const url = 'ajax/service_catalog.php';
    const urlCats = 'ajax/service_categories.php';
    const urlProviders = 'ajax/providers.php';
    const catalogCtx = window.SERVICE_CATALOG_CTX || {};
    const isAdminMedical = !!catalogCtx.isAdmin;
    const scopedMedicalProviderId = catalogCtx.providerId ? parseInt(catalogCtx.providerId, 10) : 0;
    let adminSelectedProviderId = 0;
    let categoryFilterHydrated = false;

    function initToastr(){
        if (typeof toastr === 'undefined') {
            return;
        }
        toastr.options = $.extend({}, toastr.options, {
            closeButton: true,
            newestOnTop: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3500
        });
    }

    function serviceToast(type, message, title){
        if (typeof toastr !== 'undefined' && toastr[type]) {
            toastr[type](message, title || 'Mis Servicios');
            return;
        }
        if (type === 'error') {
            alert(message);
        }
    }

    function initCategorySelect2(){
        if (!$.fn.select2) {
            return;
        }

        if ($('#filter-category').data('select2')) {
            $('#filter-category').select2('destroy');
        }
        $('#filter-category').select2({
            theme: 'bootstrap',
            width: '100%',
            placeholder: 'Todas las categorías',
            allowClear: true
        });

        if ($('#svc-category').data('select2')) {
            $('#svc-category').select2('destroy');
        }
        $('#svc-category').select2({
            theme: 'bootstrap',
            width: '100%',
            placeholder: 'Seleccionar categoría',
            dropdownParent: $('#serviceModal')
        });
    }

    function escapeHtml(text){ if(!text) return ''; return $('<div>').text(text).html(); }

    function buildOffersUrl(rowProviderId, providerCatalogServiceId, offerCount){
        var params = [];
        if(isAdminMedical && rowProviderId > 0){
            params.push('provider_id=' + encodeURIComponent(rowProviderId));
        }
        if(providerCatalogServiceId > 0){
            params.push('provider_catalog_service_id=' + encodeURIComponent(providerCatalogServiceId));
        }
        if(offerCount <= 0){
            params.push('action=create');
        }
        return 'provider_offers.php' + (params.length ? ('?' + params.join('&')) : '');
    }

    function buildStaffAssignmentAction(serviceId, providerCatalogServiceId){
        if (isAdminMedical) {
            return '<button type="button" class="btn btn-sm btn-default mr5" disabled title="Disponible desde la vista del prestador.">Asignar a staff</button>';
        }

        var params = ['action=assign_staff'];
        if (providerCatalogServiceId > 0) {
            params.push('provider_catalog_service_id=' + encodeURIComponent(providerCatalogServiceId));
        }
        if (serviceId > 0) {
            params.push('service_id=' + encodeURIComponent(serviceId));
        }

        return '<a class="btn btn-sm btn-info mr5" href="staff_medico.php?' + params.join('&') + '">Asignar a staff</a>';
    }

    function renderEmptyState(message){
        $('#tbl-services tbody').html(
            '<tr><td colspan="6" class="text-center text-muted" style="padding:24px 12px;">'
            + escapeHtml(message)
            + '</td></tr>'
        );
    }

    function getActiveProviderContextId(){
        return isAdminMedical ? adminSelectedProviderId : scopedMedicalProviderId;
    }

    function hasProviderContext(){
        return getActiveProviderContextId() > 0;
    }

    function updateAdminContextState(providerName){
        if(!isAdminMedical){
            return;
        }
        var hasContext = hasProviderContext();
        $('#btn-new-service').prop('disabled', !hasContext);
        if(!hasContext){
            $('#service-catalog-admin-context-help')
                .removeClass('alert-warning')
                .addClass('alert-info')
                .text('Selecciona un prestador médico para listar y administrar sus servicios habilitados. Esta vista no muestra todos los servicios mezclados sin contexto.');
            return;
        }
        var suffix = providerName ? (' Prestador en contexto: ' + providerName + '.') : '';
        $('#service-catalog-admin-context-help')
            .removeClass('alert-warning')
            .addClass('alert-info')
            .text('Administrando los servicios habilitados del prestador seleccionado.' + suffix);
    }

    function applyProviderScopeUI(){
        if(isAdminMedical){
            $('#svc-provider-wrapper').show();
            $('#svc-provider').prop('disabled', false);
            return;
        }
        $('#svc-provider-wrapper').hide();
        $('#svc-provider').prop('disabled', true);
    }

    function loadCategories(selectSelector, includeAll){
        $.post(urlCats, { tipo: 'list' }, function(res){
            if(!res || !res.ok) return;
            let opts = includeAll ? '<option value="">Todas</option>' : '';
            res.data.forEach(function(c){
                if(c.is_active == 1) opts += '<option value="'+c.id+'">'+escapeHtml(c.name)+'</option>';
            });
            $(selectSelector).html(opts);
            if(selectSelector === '#filter-category' || selectSelector === '#svc-category'){
                initCategorySelect2();
                if(selectSelector === '#filter-category' && !categoryFilterHydrated){
                    categoryFilterHydrated = true;
                    $(selectSelector).val('');
                }
                $(selectSelector).trigger('change.select2');
            }
        }, 'json');
    }

    function loadMedicalProviders(){
        if(!isAdminMedical){
            return;
        }
        $.post(urlProviders, { tipo: 'list', kind: 'medical' }, function(res){
            if(!res || !res.ok) return;
            let filterOpts = '<option value="">Seleccione un prestador...</option>';
            let opts = '<option value="">Seleccionar prestador...</option>';
            res.data.forEach(function(p){
                if(parseInt(p.is_active, 10) === 1){
                    filterOpts += '<option value="'+p.id+'">'+escapeHtml(p.name)+'</option>';
                    opts += '<option value="'+p.id+'">'+escapeHtml(p.name)+'</option>';
                }
            });
            $('#filter-provider').html(filterOpts);
            $('#svc-provider').html(opts);
            updateAdminContextState('');
        }, 'json');
    }

    function loadList(cb){
        let providerId = getActiveProviderContextId();
        if(isAdminMedical && providerId <= 0){
            renderEmptyState('Seleccione un prestador médico para ver sus servicios habilitados.');
            updateAdminContextState('');
            if (cb) cb(false);
            return;
        }
        let cat = $('#filter-category').val() || '';
        let data = { tipo: 'list', category_id: cat };
        if(providerId > 0){
            data.provider_id = providerId;
        }
        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function(res){
            if(!res || !res.ok) {
                renderEmptyState('No fue posible cargar los servicios habilitados del prestador en contexto.');
                serviceToast('error', (res && res.error) ? ('Error al listar servicios: ' + res.error) : 'No fue posible cargar los servicios habilitados.');
                if (cb) cb(false, res);
                return;
            }
            if(res.require_provider_context){
                renderEmptyState('Seleccione un prestador médico para ver sus servicios habilitados.');
                updateAdminContextState('');
                if (cb) cb(false, res);
                return;
            }
            updateAdminContextState(res.provider_name || '');
            let tbody = '';
            res.data.forEach(function(r){
                const serviceId = r.id ? parseInt(r.id, 10) : 0;
                const categoryId = r.category_id ? parseInt(r.category_id, 10) : 0;
                const rowProviderId = r.provider_id ? parseInt(r.provider_id, 10) : providerId;
                const providerCatalogServiceId = r.provider_catalog_service_id ? parseInt(r.provider_catalog_service_id, 10) : 0;
                const offerCount = r.offer_count ? parseInt(r.offer_count, 10) : 0;
                const offersUrl = buildOffersUrl(rowProviderId, providerCatalogServiceId, offerCount);
                const offersLabel = offerCount > 0 ? ('Ver ofertas' + (offerCount > 1 ? ' (' + offerCount + ')' : '')) : 'Crear oferta';
                const offersClass = offerCount > 0 ? 'btn btn-sm btn-default' : 'btn btn-sm btn-success';
                const staffAction = buildStaffAssignmentAction(serviceId, providerCatalogServiceId);
                tbody += '<tr data-id="'+r.id+'" data-category-id="'+categoryId+'" data-provider-id="'+rowProviderId+'" data-short-description="'+escapeHtml(r.short_description || '')+'">';
                tbody += '<td>'+escapeHtml(r.category_name || '')+'</td>';
                tbody += '<td>'+escapeHtml(r.name)+'</td>';
                tbody += '<td>'+escapeHtml(r.slug)+'</td>';
                tbody += '<td>'+r.sort_order+'</td>';
                tbody += '<td>'+(r.is_active == 1 ? '<button class="btn btn-xs btn-success toggle-active" data-val="0">Activo</button>' : '<button class="btn btn-xs btn-default toggle-active" data-val="1">Inactivo</button>')+'</td>';
                tbody += '<td><a class="'+offersClass+' mr5" href="'+offersUrl+'">'+offersLabel+'</a> ' + staffAction + ' <button class="btn btn-sm btn-primary edit">Editar</button> <button class="btn btn-sm btn-danger delete">Eliminar</button></td>';
                tbody += '</tr>';
            });
            $('#tbl-services tbody').html(tbody || '<tr><td colspan="6" class="text-center text-muted" style="padding:24px 12px;">No hay servicios habilitados para el prestador seleccionado.</td></tr>');
            if (cb) cb(true, res);
        }).fail(function(xhr){
            renderEmptyState('No fue posible cargar los servicios habilitados del prestador en contexto.');
            var message = 'Error de conexión al listar servicios.';
            try {
                if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                    message = 'Error al listar servicios: ' + xhr.responseJSON.error;
                }
            } catch (e) {}
            serviceToast('error', message);
            if (cb) cb(false, null);
        });
    }

    function resetFormDefaults(){
        $('#form-service')[0].reset();
        $('#svc-id').val('');
        $('#svc-order').val(1);
        $('#svc-active').prop('checked', true);
        $('#svc-desc').val('');
        $('#svc-category').val('').trigger('change');
        if(isAdminMedical){
            $('#svc-provider').val(adminSelectedProviderId > 0 ? String(adminSelectedProviderId) : '');
        } else if(scopedMedicalProviderId > 0){
            $('#svc-provider').val(String(scopedMedicalProviderId));
        }
    }

    initToastr();
    applyProviderScopeUI();
    loadCategories('#filter-category', true);
    loadCategories('#svc-category', false);
    loadMedicalProviders();
    loadList();

    $('#filter-category').change(function(){ loadList(); });

    $('#filter-provider').change(function(){
        adminSelectedProviderId = parseInt($(this).val(), 10) || 0;
        $('#svc-provider').val(adminSelectedProviderId > 0 ? String(adminSelectedProviderId) : '');
        loadList();
    });

    $('#btn-new-service').click(function(){
        if(isAdminMedical && !hasProviderContext()){
            serviceToast('warning', 'Seleccione un prestador médico antes de crear o editar servicios en esta vista.');
            return;
        }
        resetFormDefaults();
        $('#serviceModal').modal('show');
    });

    $('#svc-save').click(function(){
        let id = $('#svc-id').val();
        let category_id = parseInt($('#svc-category').val(), 10) || 0;
        let name = $('#svc-name').val().trim();
        if(!category_id){ serviceToast('warning', 'Seleccione una categoría para el servicio.'); return; }
        if(name === ''){ serviceToast('warning', 'El nombre del servicio es requerido.'); return; }

        let providerIdPayload = 0;
        if(isAdminMedical){
            providerIdPayload = parseInt($('#svc-provider').val(), 10) || 0;
            if(providerIdPayload <= 0){
                serviceToast('warning', 'Seleccione un prestador médico.');
                return;
            }
        } else if(scopedMedicalProviderId > 0){
            providerIdPayload = scopedMedicalProviderId;
        }

        let data = {
            category_id: category_id,
            provider_id: providerIdPayload,
            name: name,
            short_description: $('#svc-desc').val().trim(),
            sort_order: parseInt($('#svc-order').val(), 10) || 1,
            is_active: $('#svc-active').is(':checked') ? 1 : 0
        };
        if(id){ data.id = id; data.tipo = 'update'; }
        else { data.tipo = 'create'; }

        $.post(url, data, function(res){
            if(res && res.ok){
                $('#serviceModal').modal('hide');
                let activeCategoryFilter = parseInt($('#filter-category').val(), 10) || 0;
                if(activeCategoryFilter > 0 && activeCategoryFilter !== category_id){
                    $('#filter-category').val('').trigger('change');
                    serviceToast('success', id ? 'Servicio actualizado correctamente.' : 'Servicio guardado correctamente.');
                    return;
                }
                loadList(function(ok){
                    if (ok) {
                        serviceToast('success', id ? 'Servicio actualizado correctamente.' : 'Servicio guardado correctamente.');
                        return;
                    }
                    serviceToast('error', 'El servicio se guardó, pero no fue posible refrescar la tabla de Mis Servicios.');
                });
            } else {
                serviceToast('error', 'No fue posible guardar el servicio: ' + (res && res.error ? res.error : 'unknown'));
            }
        }, 'json').fail(function(xhr){
            var message = 'Error de conexión al guardar el servicio.';
            try {
                if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                    message = 'No fue posible guardar el servicio: ' + xhr.responseJSON.error;
                }
            } catch (e) {}
            serviceToast('error', message);
        });
    });

    $('#tbl-services').on('click', '.edit', function(){
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        let categoryId = parseInt(tr.attr('data-category-id'), 10) || 0;
        let providerId = parseInt(tr.attr('data-provider-id'), 10) || getActiveProviderContextId();
        let shortDescription = tr.attr('data-short-description') || '';
        let name = tr.find('td').eq(1).text();
        let order = tr.find('td').eq(3).text();
        let activeText = tr.find('td').eq(4).text();

        $('#svc-id').val(id);
        $('#svc-name').val(name);
        $('#svc-desc').val(shortDescription);
        $('#svc-order').val(order);
        $('#svc-active').prop('checked', activeText.trim().toLowerCase().indexOf('activo') !== -1);
        $('#svc-category').val(categoryId ? String(categoryId) : '').trigger('change');

        if(isAdminMedical){
            $('#svc-provider').val(providerId ? String(providerId) : '');
        } else if(scopedMedicalProviderId > 0){
            $('#svc-provider').val(String(scopedMedicalProviderId));
        }

        $('#serviceModal').modal('show');
    });

    $('#tbl-services').on('click', '.delete', function(){
        if(!confirm('Desactivar este servicio?')) return;
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        $.post(url, { tipo: 'toggle', id: id, val: 0 }, function(res){
            if(res && res.ok) loadList();
            else serviceToast('error', 'No fue posible actualizar el estado del servicio.');
        }, 'json');
    });

    $('#tbl-services').on('click', '.toggle-active', function(){
        let btn = $(this);
        let tr = btn.closest('tr');
        let id = tr.data('id');
        let val = btn.data('val');
        $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){
            if(res && res.ok) loadList();
            else serviceToast('error', 'No fue posible actualizar el estado del servicio.');
        }, 'json');
    });
});
