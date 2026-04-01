$(function(){
    var offersCtx = window.PROVIDER_OFFERS_CTX || {};
    var isAdminMedical = !!offersCtx.isAdmin;
    var scopedMedicalProviderId = offersCtx.providerId ? parseInt(offersCtx.providerId, 10) : 0;
    var adminSelectedProviderId = 0;
    var defaultServiceHelp = 'La oferta comercial se construye sobre un servicio ya habilitado en Mis Servicios.';
    var pageParams = (function(){
        try {
            return new URLSearchParams(window.location.search || '');
        } catch (e) {
            return { get: function(){ return ''; } };
        }
    })();
    var deepLinkedProviderId = parseInt(pageParams.get('provider_id') || '0', 10) || 0;
    var deepLinkedProviderCatalogServiceId = parseInt(pageParams.get('provider_catalog_service_id') || '0', 10) || 0;
    var deepLinkedAction = String(pageParams.get('action') || '').toLowerCase();
    var deepLinkConsumed = false;

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

    function offersToast(type, message, title){
        if (typeof toastr !== 'undefined' && toastr[type]) {
            toastr[type](message, title || 'Mis Ofertas');
            return;
        }
        console[type === 'error' ? 'error' : 'log'](message);
    }

    function escapeHtml(text){
        if (text === null || typeof text === 'undefined') return '';
        return $('<div>').text(String(text)).html();
    }

    function buildPublicOfferUrl(offerId){
        return 'https://medtravel.com.co/offer_detail.php?id=' + encodeURIComponent(String(offerId || ''));
    }

    function fallbackCopyText(text){
        var temp = $('<textarea readonly style="position:absolute;left:-9999px;top:-9999px;"></textarea>');
        $('body').append(temp);
        temp.val(String(text || ''));
        if (temp[0]) {
            temp[0].focus();
            temp[0].select();
        }
        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (e) {
            copied = false;
        }
        temp.remove();
        return copied;
    }

    function copyPublicOfferUrl(offerId){
        var normalizedId = parseInt(offerId, 10) || 0;
        if (normalizedId <= 0) {
            offersToast('error', 'No fue posible resolver el ID real de la oferta.', 'Mis Ofertas');
            return;
        }

        var campaignUrl = buildPublicOfferUrl(normalizedId);

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(campaignUrl).then(function(){
                offersToast('success', 'Enlace comercial copiado: ' + campaignUrl, 'Mis Ofertas');
            }).catch(function(){
                if (fallbackCopyText(campaignUrl)) {
                    offersToast('success', 'Enlace comercial copiado: ' + campaignUrl, 'Mis Ofertas');
                    return;
                }
                offersToast('error', 'No fue posible copiar el enlace comercial.', 'Mis Ofertas');
            });
            return;
        }

        if (fallbackCopyText(campaignUrl)) {
            offersToast('success', 'Enlace comercial copiado: ' + campaignUrl, 'Mis Ofertas');
            return;
        }
        offersToast('error', 'No fue posible copiar el enlace comercial.', 'Mis Ofertas');
    }

    function getActiveProviderContextId(){
        return isAdminMedical ? adminSelectedProviderId : scopedMedicalProviderId;
    }

    function hasProviderContext(){
        return getActiveProviderContextId() > 0;
    }

    function getSelectedProviderName(){
        if(!isAdminMedical){
            return '';
        }
        var selected = $('#filter-provider option:selected').text() || '';
        return selected && selected.indexOf('Seleccione') === -1 ? selected : '';
    }

    function renderEmptyState(message){
        $('#tbl-offers tbody').html(
            '<tr><td colspan="5" class="text-center text-muted" style="padding:24px 12px;">'
            + escapeHtml(message)
            + '</td></tr>'
        );
    }

    function renderGalleryPlaceholder(message){
        $('#offer-gallery').html(
            '<p class="text-muted"><i class="fa fa-images"></i> '
            + escapeHtml(message)
            + '</p>'
        );
    }

    function buildStaffAssignmentUrl(providerCatalogServiceId, serviceId){
        var params = ['action=assign_staff'];
        if (providerCatalogServiceId > 0) {
            params.push('provider_catalog_service_id=' + encodeURIComponent(providerCatalogServiceId));
        }
        if (serviceId > 0) {
            params.push('service_id=' + encodeURIComponent(serviceId));
        }
        return 'staff_medico.php?' + params.join('&');
    }

    function updateNextStepCta(serviceName, providerCatalogServiceId, serviceId, mode){
        var cta = $('#offer-next-step-cta');
        if (!cta.length || isAdminMedical || providerCatalogServiceId <= 0) {
            cta.hide().empty();
            return;
        }

        var buttonLabel = mode === 'create' ? 'Continuar: asignar a staff' : 'Asignar a staff';
        var message = mode === 'create'
            ? 'Siguiente paso recomendado para el servicio habilitado <strong>' + escapeHtml(serviceName || ('#' + providerCatalogServiceId)) + '</strong>: asignarlo al staff médico que podrá atenderlo.'
            : 'Puedes continuar con la asignación clínica del servicio habilitado <strong>' + escapeHtml(serviceName || ('#' + providerCatalogServiceId)) + '</strong> al staff médico.';

        cta
            .html(
                message +
                ' <a class="btn btn-sm btn-info pull-right" href="' + buildStaffAssignmentUrl(providerCatalogServiceId, serviceId) + '">' + buttonLabel + '</a>'
            )
            .show();
    }

    function updateAdminContextState(){
        if(!isAdminMedical){
            return;
        }
        var hasContext = hasProviderContext();
        $('#btn-new-offer').prop('disabled', !hasContext);
        if(!hasContext){
            $('#provider-offers-admin-context-help')
                .removeClass('alert-warning')
                .addClass('alert-info')
                .text('Selecciona un prestador médico para listar y administrar sus ofertas comerciales. Esta vista no muestra ofertas de todos los prestadores mezcladas sin contexto.');
            renderEmptyState('Seleccione un prestador médico para ver sus ofertas comerciales.');
            renderGalleryPlaceholder('Selecciona una oferta del prestador en contexto para gestionar su galería de imágenes.');
            return;
        }

        var providerName = getSelectedProviderName();
        var suffix = providerName ? (' Prestador en contexto: ' + providerName + '.') : '';
        $('#provider-offers-admin-context-help')
            .removeClass('alert-warning')
            .addClass('alert-info')
            .text('Administrando las ofertas comerciales del prestador médico seleccionado.' + suffix);
    }

    function updateOfferContextNote(isEditing){
        var note = $('#offer-context-note');
        if(!isAdminMedical){
            note.hide().empty();
            return;
        }
        if(!hasProviderContext()){
            note.hide().empty();
            return;
        }

        var providerName = getSelectedProviderName();
        var actionLabel = isEditing ? 'editando' : 'creando';
        note
            .html(
                'Estás ' + actionLabel + ' una oferta comercial del prestador médico '
                + '<strong>' + escapeHtml(providerName || ('#' + getActiveProviderContextId())) + '</strong>. '
                + 'Esta publicación se apoya en un servicio ya habilitado del prestador y no modifica el catálogo maestro ni el staff.'
            )
            .show();
    }

    function updateDeepLinkContextMessage(serviceName, offerCount){
        if (!deepLinkedProviderCatalogServiceId || isAdminMedical) {
            return;
        }
        var message = offerCount > 0
            ? 'Mostrando las ofertas asociadas al servicio habilitado <strong>' + escapeHtml(serviceName || ('#' + deepLinkedProviderCatalogServiceId)) + '</strong>.'
            : 'Estás entrando desde Mis Servicios para crear la primera oferta comercial del servicio habilitado <strong>' + escapeHtml(serviceName || ('#' + deepLinkedProviderCatalogServiceId)) + '</strong>.';
        $('#offer-context-note').html(message).show();
        updateNextStepCta(serviceName, deepLinkedProviderCatalogServiceId, 0, offerCount > 0 ? 'view' : 'create');
    }

    function withProviderContext(data){
        var payload = $.extend({}, data || {});
        var providerId = getActiveProviderContextId();
        if(providerId > 0){
            payload.provider_id = providerId;
        }
        return payload;
    }

    // Inicializar Summernote
    function initSummernote(){
        if ($.fn.summernote) {
            $('.summernote').summernote({
                height: 250,
                toolbar: [
                    ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['height', ['height']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                placeholder: 'Describa su servicio médico de manera profesional...',
                dialogsInBody: true,
                callbacks: {
                    onInit: function() {
                        console.log('Summernote inicializado');
                    }
                }
            });
        }
    }
    
    function api(data, cb){
        $.ajax({
            url: 'ajax/provider_offers.php',
            method: 'POST',
            data: withProviderContext(data),
            dataType: 'json',
            success: function(res, status, xhr){
                if(res && res.ok) return cb(null, res.data, res);
                var err = (res && res.error) ? res.error : 'UNKNOWN_ERROR';
                cb(err, null, res);
            },
            error: function(xhr, status, err){
                var msg = 'NETWORK';
                try {
                    if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    else if (xhr && xhr.responseText) msg = xhr.responseText.substring(0, 500);
                } catch(e){ /* ignore */ }
                console.error('api error', status, err, xhr);
                cb(msg, null, null);
            }
        });
    }

    function parseApiError(err, res){
        if (res && res.message) {
            return res.message;
        }
        if (err === 'NETWORK') {
            return 'Error de conexión al procesar la operación.';
        }
        if (typeof err === 'string' && err.indexOf('DB_ERR:') === 0) {
            return 'Error de base de datos al guardar la oferta.';
        }
        return err || 'Ocurrió un error inesperado.';
    }

    function updateServiceHelp(message, isLocked){
        $('#offer-service-help').text(message || defaultServiceHelp);
        $('#offer-service-lock-note').toggle(!!isLocked);
    }

    function setServiceSelectValue(providerCatalogServiceId, serviceId){
        var sel = $('#offer-service');
        var value = providerCatalogServiceId ? String(providerCatalogServiceId) : '';

        if (!value && serviceId) {
            var fallback = sel.find('option').filter(function(){
                return String($(this).data('service-id')) === String(serviceId);
            }).first();
            if (fallback.length) {
                value = String(fallback.val());
            }
        }

        if (!value && serviceId) {
            var existing = sel.find('option').filter(function(){
                return String($(this).data('service-id')) === String(serviceId);
            }).first();
            if (!existing.length) {
                var fallbackOption = $('<option>')
                    .val('')
                    .attr('data-service-id', serviceId)
                    .text('Servicio actual no disponible en la lista habilitada');
                sel.append(fallbackOption);
            }
        }

        sel.val(value);
        if ($.fn.select2) {
            try { sel.trigger('change.select2'); } catch(e) {}
        }
    }

    function setServiceSelectLocked(locked){
        var sel = $('#offer-service');
        sel.prop('disabled', !!locked);
        if ($.fn.select2) {
            try { sel.trigger('change.select2'); } catch(e) {}
        }
        updateServiceHelp(
            locked
                ? 'La oferta comercial se construye sobre un servicio ya habilitado en Mis Servicios. En esta edición el servicio queda bloqueado.'
                : defaultServiceHelp,
            locked
        );
    }

    function loadServices(cb){
        var sel = $('#offer-service');
        sel.empty();
        sel.append($('<option>').val('').text('Seleccione un servicio habilitado'));

        if(isAdminMedical && !hasProviderContext()){
            if(cb) cb([]);
            return;
        }

        $.getJSON('ajax/provider_offers.php', withProviderContext({tipo:'list_provider_services'}), function(res){
            if(res && res.ok){
                sel.empty();
                sel.append($('<option>').val('').text('Seleccione un servicio habilitado'));
                $.each(res.data || [], function(i, r){
                    var txt = r.service_name ? r.service_name : ('Servicio ' + r.service_id);
                    var option = $('<option>')
                        .val(r.provider_catalog_service_id)
                        .attr('data-service-id', r.service_id)
                        .text(txt);
                    sel.append(option);
                });
                if ($.fn.select2) {
                    try {
                        if (sel.hasClass('select2-hidden-accessible')) {
                            sel.trigger('change.select2');
                        } else {
                            sel.select2({placeholder:'Seleccione', width: '100%'});
                        }
                    } catch(e) { console.warn('select2 init failed', e); }
                }
                if(cb) cb(res.data || []);
                return;
            }
            if (cb) cb([]);
        }).fail(function(){
            if (cb) cb([]);
        });
    }

    function listOffers(cb){
        if(isAdminMedical && !hasProviderContext()){
            updateAdminContextState();
            if (cb) cb(false, null, { require_provider_context: true });
            return;
        }

        api({tipo:'list'}, function(err,data,res){
            if(err) {
                offersToast('error', parseApiError(err, res));
                if (cb) cb(false, data, res);
                return;
            }
            if(res && res.require_provider_context){
                updateAdminContextState();
                if (cb) cb(false, data, res);
                return;
            }

            if(isAdminMedical){
                updateAdminContextState();
            }

            var filteredData = data || [];
            if (deepLinkedProviderCatalogServiceId > 0) {
                filteredData = filteredData.filter(function(row){
                    return parseInt(row.provider_catalog_service_id || 0, 10) === deepLinkedProviderCatalogServiceId;
                });
            }

            var tbody = $('#tbl-offers tbody').empty();
            if(!filteredData.length){
                if (deepLinkedProviderCatalogServiceId > 0) {
                    updateDeepLinkContextMessage('', 0);
                } else {
                    updateNextStepCta('', 0, 0, '');
                }
                renderEmptyState(
                    deepLinkedProviderCatalogServiceId > 0
                        ? 'Este servicio habilitado todavía no tiene ofertas comerciales registradas.'
                        : isAdminMedical
                        ? 'No hay ofertas comerciales registradas para el prestador seleccionado.'
                        : 'No hay ofertas comerciales registradas todavía.'
                );
                renderGalleryPlaceholder('Selecciona una oferta de la tabla para gestionar su galería de imágenes.');
                if (cb) cb(true, filteredData, res);
                return;
            }
            if (deepLinkedProviderCatalogServiceId > 0) {
                updateDeepLinkContextMessage(filteredData[0].service_name || '', filteredData.length);
            } else {
                updateNextStepCta('', 0, 0, '');
            }
            $.each(filteredData, function(i,row){
                var tr = $('<tr>');
                tr.append($('<td>').text(row.service_name));
                tr.append($('<td>').text(row.title));
                tr.append($('<td>').text(row.price_from));
                tr.append($('<td>').text(row.is_active==1? 'Sí':'No'));
                var actions = $('<td>');
                actions.append($('<button class="btn btn-xs btn-primary mr5">Editar</button>').click(function(){ openEdit(row.id); }));
                actions.append($('<button class="btn btn-xs btn-warning mr5">Fotos</button>').click(function(){ loadGallery(row.id); }));
                actions.append($('<button class="btn btn-xs btn-success mr5">Copiar link</button>').click(function(){ copyPublicOfferUrl(row.id); }));
                if (!isAdminMedical && parseInt(row.provider_catalog_service_id || 0, 10) > 0) {
                    actions.append($('<a class="btn btn-xs btn-info mr5">Asignar a staff</a>').attr('href', buildStaffAssignmentUrl(parseInt(row.provider_catalog_service_id || 0, 10), parseInt(row.service_id || 0, 10))));
                }
                var toggleLabel = (row.is_active==1) ? 'Desactivar' : 'Activar';
                actions.append($('<button class="btn btn-xs btn-default">'+toggleLabel+'</button>').click(function(){ toggle(row.id); }));
                tr.append(actions);
                tbody.append(tr);
            });
            if (cb) cb(true, filteredData, res);
        });
    }

    function openEdit(id){
        if(!id){
            $('#form-offer')[0].reset(); 
            $('#offer-id').val(''); 
            $('#offer-active').prop('checked',true);
            $('#modal-title-text').text('Nueva oferta comercial');
            setServiceSelectLocked(false);
            setServiceSelectValue('', '');
            updateOfferContextNote(false);
            // Limpiar Summernote
            if ($.fn.summernote) {
                $('#offer-desc').summernote('code', '');
            }
            $('#gallery-preview').empty();
            // Activar primera pestaña
            $('.nav-tabs a[href="#tab-general"]').tab('show');
            $('#offerModal').modal('show'); 
            return;
        }
        $('#modal-title-text').text('Editar oferta comercial');
        loadServices(function(){
            $.getJSON('ajax/provider_offers.php', withProviderContext({tipo:'get', id:id}), function(res){
                if(!res.ok) {
                    offersToast('error', res.message || res.error || 'No fue posible cargar la oferta.');
                    return;
                }
                var d = res.data;
                $('#offer-id').val(d.id);
                setServiceSelectValue(d.provider_catalog_service_id, d.service_id);
                setServiceSelectLocked(true);
                updateOfferContextNote(true);
                $('#offer-title').val(d.title);
                // Cargar HTML en Summernote
                if ($.fn.summernote) {
                    $('#offer-desc').summernote('code', d.description || '');
                } else {
                    $('#offer-desc').val(d.description);
                }
                $('#offer-price').val(d.price_from);
                $('#offer-currency').val(d.currency);
                $('#offer-active').prop('checked', d.is_active==1);
                renderGalleryInModal(d.media||[], d.id);
                // Activar primera pestaña
                $('.nav-tabs a[href="#tab-general"]').tab('show');
                $('#offerModal').modal('show');
            });
        });
    }

    function openCreateForService(providerCatalogServiceId){
        loadServices(function(){
            initSummernote();
            openEdit(0);
            if (providerCatalogServiceId > 0) {
                setServiceSelectValue(providerCatalogServiceId, '');
                updateServiceHelp('La oferta comercial se construye sobre un servicio ya habilitado en Mis Servicios. Llegaste aquí desde el servicio seleccionado.', false);
                var option = $('#offer-service option:selected');
                updateNextStepCta($.trim(option.text() || ''), providerCatalogServiceId, parseInt(option.data('service-id') || 0, 10) || 0, 'create');
            }
        });
    }

    function save(){
        var id = $('#offer-id').val();
        // Obtener HTML de Summernote
        var description = '';
        if ($.fn.summernote) {
            description = $('#offer-desc').summernote('code');
        } else {
            description = $('#offer-desc').val();
        }
        
        var data = {
            tipo: id? 'update':'create',
            provider_catalog_service_id: $('#offer-service').val(),
            service_id: $('#offer-service option:selected').data('service-id') || '',
            title: $('#offer-title').val(),
            description: description,
            price_from: $('#offer-price').val(),
            currency: $('#offer-currency').val(),
            is_active: $('#offer-active').is(':checked')?1:0
        };
        if(id) data.id = id;
        var selectedFile = ($('#offer-file')[0] && $('#offer-file')[0].files) ? $('#offer-file')[0].files[0] : null;
        api(data, function(err,d,res){
            if(err) {
                offersToast('error', parseApiError(err, res));
                return;
            }
            var offerId = id || (d && d.id ? d.id : '');
            var successMessage = (res && res.message) || (id ? 'Oferta comercial actualizada correctamente.' : 'Oferta comercial creada correctamente.');
            if (!offerId) {
                $('#offerModal').modal('hide');
                listOffers(function(ok){
                    if (ok) {
                        offersToast('success', successMessage);
                        return;
                    }
                    offersToast('error', 'La oferta se guardó, pero no fue posible refrescar el listado.');
                });
                return;
            }
            $('#offer-id').val(offerId);
            if (!selectedFile) {
                $('#offerModal').modal('hide');
                listOffers(function(ok){
                    if (ok) {
                        offersToast('success', successMessage);
                        return;
                    }
                    offersToast('error', 'La oferta se guardó, pero no fue posible refrescar el listado.');
                });
                return;
            }
            uploadForOffer(offerId, selectedFile, function(uploadErr){
                if (uploadErr) {
                    offersToast('error', parseApiError(uploadErr, null));
                    return;
                }
                listOffers(function(ok){
                    if (!ok) {
                        offersToast('error', 'La oferta se guardó y la imagen se subió, pero no fue posible refrescar el listado.');
                        return;
                    }
                    offersToast('success', successMessage);
                    $('#offer-file').val('');
                    // Mantener modal abierto y mostrar pestaña de galería con la imagen recién subida
                    $('.nav-tabs a[href="#tab-gallery"]').tab('show');
                });
            });
        });
    }

    function toggle(id){ api({tipo:'toggle',id:id}, function(err,d,res){ if(err) return offersToast('error', parseApiError(err, res)); listOffers(); }); }

    function upload(){
        var id = $('#offer-id').val(); if(!id) return offersToast('warning', 'Abra o cree la oferta primero.');
        var f = $('#offer-file')[0].files[0]; if(!f) return offersToast('warning', 'Seleccione un archivo.');
        uploadForOffer(id, f, function(err){
            if (err) return offersToast('error', parseApiError(err, null));
            $('#offer-file').val('');
            offersToast('success', 'Imagen subida exitosamente.', 'Éxito');
        });
    }

    function refreshOfferMedia(offerId, cb){
        $.getJSON('ajax/provider_offers.php', withProviderContext({tipo:'get', id: offerId}), function(res){
            if (!res || !res.ok) return cb((res && res.error) ? res.error : 'UNKNOWN_ERROR');
            var media = (res.data && res.data.media) ? res.data.media : [];
            renderGalleryInModal(media, offerId);
            renderGallery(media, offerId);
            cb(null, media);
        }).fail(function(){
            cb('NETWORK');
        });
    }

    function uploadForOffer(offerId, file, cb){
        var fd = new FormData();
        fd.append('tipo', 'upload_media');
        fd.append('offer_id', offerId);
        if (hasProviderContext()) {
            fd.append('provider_id', getActiveProviderContextId());
        }
        fd.append('file', file);
        $.ajax({
            url: 'ajax/provider_offers.php',
            type: 'POST',
            data: fd,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res){
                if(!res || !res.ok) return cb((res && res.error) ? res.error : 'UNKNOWN_ERROR');
                refreshOfferMedia(offerId, function(refreshErr){
                    if (refreshErr) return cb(refreshErr);
                    cb(null, res.data);
                });
            },
            error: function(xhr){
                var msg = 'NETWORK';
                try {
                    if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                } catch(e) { /* ignore */ }
                cb(msg);
            }
        });
    }

    function loadGallery(offer_id){
        $.getJSON('ajax/provider_offers.php', withProviderContext({tipo:'get', id: offer_id}), function(res){ if(!res.ok) return offersToast('error', res.message || res.error || 'No fue posible cargar la galería.'); renderGallery(res.data.media||[], offer_id); });
    }

    function renderGalleryInModal(list, offerId){
        var cont = $('#gallery-preview').empty();
        if(!list || list.length==0) { 
            cont.html('<div class="col-md-12"><div class="alert alert-info"><i class="fa fa-info-circle"></i> No hay imágenes subidas aún. Use el botón "Subir Imagen" para agregar fotos.</div></div>'); 
            return; 
        }
        $.each(list, function(i,m){
            var col = $('<div class="col-xs-6 col-sm-4 col-md-3" style="margin-bottom:15px;">');
            var imgWrap = $('<div style="position:relative; border:2px solid #e9ecef; border-radius:8px; overflow:hidden; padding:5px; background:#fff;">');
            imgWrap.append($('<img>').addClass('img-responsive').attr('src','../'+m.path).css({'border-radius':'4px', 'width':'100%', 'height':'150px', 'object-fit':'cover'}));
            var deleteBtn = $('<button type="button" class="btn btn-xs btn-danger" style="position:absolute;top:8px;right:8px;z-index:3;"><i class="fa fa-trash"></i> Eliminar</button>');
            deleteBtn.on('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                deleteMedia(m.id, offerId || $('#offer-id').val());
            });
            imgWrap.append(deleteBtn);
            col.append(imgWrap);
            cont.append(col);
        });
    }
    
    function renderGallery(list, offerId){
        var cont = $('#offer-gallery').empty();
        if(!list || list.length==0) { cont.html('<p>No hay fotos</p>'); return; }
        var row = $('<div class="row">');
        $.each(list, function(i,m){
            var col = $('<div class="col-xs-3">');
            var wrap = $('<div style="position:relative; margin-bottom:10px;">');
            wrap.append($('<img>').addClass('img-responsive').attr('src','../'+m.path).css({'margin-bottom':'10px'}));
            var deleteBtn = $('<button type="button" class="btn btn-xs btn-danger" style="position:absolute;top:6px;right:6px;z-index:3;"><i class="fa fa-trash"></i></button>');
            deleteBtn.on('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                deleteMedia(m.id, offerId || $('#offer-id').val());
            });
            wrap.append(deleteBtn);
            col.append(wrap);
            row.append(col);
        });
        cont.append(row);
    }

    function deleteMedia(mediaId, offerId){
        if (!mediaId) return offersToast('error', 'Imagen inválida.');
        if (!offerId) return offersToast('error', 'Oferta inválida.');
        if (!window.confirm('¿Desea eliminar esta imagen de la galería?')) return;
        api({tipo:'delete_media', image_id: mediaId, offer_id: offerId}, function(err, data, res){
            if (err) return offersToast('error', parseApiError(err, res));
            refreshOfferMedia(offerId, function(refreshErr){
                if (refreshErr) return offersToast('error', parseApiError(refreshErr, null));
                offersToast('success', 'Imagen eliminada.', 'Éxito');
            });
        });
    }

    $('#btn-new-offer').click(function(){ 
        if(isAdminMedical && !hasProviderContext()){
            offersToast('warning', 'Seleccione un prestador médico antes de crear una oferta comercial.');
            return;
        }
        loadServices(function(){
            initSummernote(); // Inicializar antes de abrir
            openEdit(0); 
        }); 
    });
    $('#offer-save').click(save);
    $('#offer-upload').click(upload);

    function loadMedicalProviders(){
        if(!isAdminMedical){
            return;
        }
        $.post('ajax/providers.php', { tipo: 'list', kind: 'medical' }, function(res){
            if(!res || !res.ok){
                return;
            }
            var opts = '<option value="">Seleccione un prestador...</option>';
            $.each(res.data || [], function(i, p){
                if(parseInt(p.is_active, 10) === 1){
                    opts += '<option value="' + p.id + '">' + escapeHtml(p.name) + '</option>';
                }
            });
            $('#filter-provider').html(opts);
            if (deepLinkedProviderId > 0) {
                adminSelectedProviderId = deepLinkedProviderId;
                $('#filter-provider').val(String(deepLinkedProviderId));
            }
            updateAdminContextState();
            loadServices();
            listOffers();
        }, 'json');
    }

    initToastr();

    if(isAdminMedical){
        loadMedicalProviders();
        updateAdminContextState();
    } else {
        loadServices(listOffers);
        if (deepLinkedProviderCatalogServiceId > 0 && deepLinkedAction === 'create' && !deepLinkConsumed) {
            deepLinkConsumed = true;
            openCreateForService(deepLinkedProviderCatalogServiceId);
        }
    }

    $('#filter-provider').change(function(){
        adminSelectedProviderId = parseInt($(this).val(), 10) || 0;
        loadServices();
        listOffers();
        if (deepLinkedProviderCatalogServiceId > 0 && deepLinkedAction === 'create' && !deepLinkConsumed && adminSelectedProviderId > 0) {
            deepLinkConsumed = true;
            openCreateForService(deepLinkedProviderCatalogServiceId);
        }
    });
    
    // Inicializar Summernote al abrir modal de edición
    $('#offerModal').on('shown.bs.modal', function(){
        if (!$('#offer-desc').data('summernote')) {
            initSummernote();
        }
    });
});
