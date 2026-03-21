$(function(){
    var showOwnerColumn = Number($('#tbl-offers').data('show-owner')) === 1;
    var defaultServiceHelp = 'La oferta comercial se construye sobre un servicio ya habilitado en Mis Servicios.';
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
            data: data,
            dataType: 'json',
            success: function(res, status, xhr){
                if(res && res.ok) return cb(null, res.data);
                var err = (res && res.error) ? res.error : 'UNKNOWN_ERROR';
                cb(err);
            },
            error: function(xhr, status, err){
                var msg = 'NETWORK';
                try {
                    if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    else if (xhr && xhr.responseText) msg = xhr.responseText.substring(0, 500);
                } catch(e){ /* ignore */ }
                console.error('api error', status, err, xhr);
                cb(msg);
            }
        });
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
        $.getJSON('ajax/provider_offers.php?tipo=list_provider_services', function(res){
            if(res && res.ok){
                var sel = $('#offer-service');
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

    function listOffers(){
        api({tipo:'list'}, function(err,data){
            if(err) return alert(err);
            var tbody = $('#tbl-offers tbody').empty();
            $.each(data, function(i,row){
                var tr = $('<tr>');
                tr.append($('<td>').text(row.service_name));
                tr.append($('<td>').text(row.title));
                if (showOwnerColumn) {
                    tr.append($('<td>').text(row.provider_name || '-'));
                }
                tr.append($('<td>').text(row.price_from));
                tr.append($('<td>').text(row.is_active==1? 'Sí':'No'));
                var actions = $('<td>');
                actions.append($('<button class="btn btn-xs btn-primary mr5">Editar</button>').click(function(){ openEdit(row.id); }));
                actions.append($('<button class="btn btn-xs btn-warning mr5">Fotos</button>').click(function(){ loadGallery(row.id); }));
                var toggleLabel = (row.is_active==1) ? 'Desactivar' : 'Activar';
                actions.append($('<button class="btn btn-xs btn-default">'+toggleLabel+'</button>').click(function(){ toggle(row.id); }));
                tr.append(actions);
                tbody.append(tr);
            });
        });
    }

    function openEdit(id){
        if(!id){
            $('#form-offer')[0].reset(); 
            $('#offer-id').val(''); 
            $('#offer-active').prop('checked',true);
            $('#modal-title-text').text('Nueva Oferta de Servicio');
            setServiceSelectLocked(false);
            setServiceSelectValue('', '');
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
        $('#modal-title-text').text('Editar Oferta de Servicio');
        loadServices(function(){
            $.getJSON('ajax/provider_offers.php?tipo=get&id='+id, function(res){
                if(!res.ok) return alert(res.error);
                var d = res.data;
                $('#offer-id').val(d.id);
                setServiceSelectValue(d.provider_catalog_service_id, d.service_id);
                setServiceSelectLocked(true);
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
        api(data, function(err,d){
            if(err) return alert(err);
            var offerId = id || (d && d.id ? d.id : '');
            if (!offerId) {
                $('#offerModal').modal('hide');
                listOffers();
                return;
            }
            $('#offer-id').val(offerId);
            if (!selectedFile) {
                $('#offerModal').modal('hide');
                listOffers();
                return;
            }
            uploadForOffer(offerId, selectedFile, function(uploadErr){
                listOffers();
                if (uploadErr) return alert(uploadErr);
                $('#offer-file').val('');
                // Mantener modal abierto y mostrar pestaña de galería con la imagen recién subida
                $('.nav-tabs a[href="#tab-gallery"]').tab('show');
            });
        });
    }

    function toggle(id){ api({tipo:'toggle',id:id}, function(err,d){ if(err) return alert(err); listOffers(); }); }

    function upload(){
        var id = $('#offer-id').val(); if(!id) return alert('Abra o cree la oferta primero');
        var f = $('#offer-file')[0].files[0]; if(!f) return alert('Seleccione archivo');
        uploadForOffer(id, f, function(err){
            if (err) return alert(err);
            $('#offer-file').val('');
            if (typeof toastr !== 'undefined') {
                toastr.success('Imagen subida exitosamente', 'Éxito');
            }
        });
    }

    function refreshOfferMedia(offerId, cb){
        $.getJSON('ajax/provider_offers.php?tipo=get&id='+offerId, function(res){
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
        $.getJSON('ajax/provider_offers.php?tipo=get&id='+offer_id, function(res){ if(!res.ok) return alert(res.error); renderGallery(res.data.media||[], offer_id); });
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
        if (!mediaId) return alert('INVALID_IMAGE');
        if (!offerId) return alert('INVALID_OFFER');
        if (!window.confirm('¿Desea eliminar esta imagen de la galería?')) return;
        api({tipo:'delete_media', image_id: mediaId, offer_id: offerId}, function(err){
            if (err) return alert(err);
            refreshOfferMedia(offerId, function(refreshErr){
                if (refreshErr) return alert(refreshErr);
                if (typeof toastr !== 'undefined') {
                    toastr.success('Imagen eliminada', 'Éxito');
                }
            });
        });
    }

    $('#btn-new-offer').click(function(){ 
        loadServices(function(){
            initSummernote(); // Inicializar antes de abrir
            openEdit(0); 
        }); 
    });
    $('#offer-save').click(save);
    $('#offer-upload').click(upload);

    // init
    loadServices(listOffers);
    
    // Inicializar Summernote al abrir modal de edición
    $('#offerModal').on('shown.bs.modal', function(){
        if (!$('#offer-desc').data('summernote')) {
            initSummernote();
        }
    });
});
