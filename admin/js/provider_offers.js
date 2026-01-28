$(function(){
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

    function loadServices(cb){
        $.getJSON('ajax/service_catalog.php?tipo=list', function(res){
            if(res.ok){
                var sel = $('#offer-service'); sel.empty();
                $.each(res.data, function(i,r){ var txt = (r.name? r.name : (r.nombre? r.nombre : 'Servicio ' + r.id)); sel.append($('<option>').val(r.id).text(txt)); });
                // inicializar select2 si está disponible (Metronic)
                if ($.fn.select2) {
                    try { sel.select2({placeholder:'Seleccione', width: '100%'}); } catch(e) { console.warn('select2 init failed', e); }
                }
                if(cb) cb();
            }
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
                tr.append($('<td>').text(row.price_from));
                tr.append($('<td>').text(row.is_active==1? 'Sí':'No'));
                var actions = $('<td>');
                actions.append($('<button class="btn btn-xs btn-primary mr5">Editar</button>').click(function(){ openEdit(row.id); }));
                actions.append($('<button class="btn btn-xs btn-warning mr5">Fotos</button>').click(function(){ loadGallery(row.id); }));
                actions.append($('<button class="btn btn-xs btn-default">Toggle</button>').click(function(){ toggle(row.id); }));
                tr.append(actions);
                tbody.append(tr);
            });
        });
    }

    function openEdit(id){
        if(!id){
            $('#form-offer')[0].reset(); $('#offer-id').val(''); $('#offer-active').prop('checked',true);
            $('#offerModal').modal('show'); return;
        }
        $.getJSON('ajax/provider_offers.php?tipo=get&id='+id, function(res){
            if(!res.ok) return alert(res.error);
            var d = res.data;
            $('#offer-id').val(d.id);
            $('#offer-service').val(d.service_id);
            if ($.fn.select2) { try { $('#offer-service').trigger('change'); } catch(e){} }
            $('#offer-title').val(d.title);
            $('#offer-desc').val(d.description);
            $('#offer-price').val(d.price_from);
            $('#offer-currency').val(d.currency);
            $('#offer-active').prop('checked', d.is_active==1);
            renderGallery(d.media||[]);
            $('#offerModal').modal('show');
        });
    }

    function save(){
        var id = $('#offer-id').val();
        var data = {
            tipo: id? 'update':'create',
            service_id: $('#offer-service').val(),
            title: $('#offer-title').val(),
            description: $('#offer-desc').val(),
            price_from: $('#offer-price').val(),
            currency: $('#offer-currency').val(),
            is_active: $('#offer-active').is(':checked')?1:0
        };
        if(id) data.id = id;
        api(data, function(err,d){ if(err) return alert(err); $('#offerModal').modal('hide'); listOffers(); });
    }

    function toggle(id){ api({tipo:'toggle',id:id}, function(err,d){ if(err) return alert(err); listOffers(); }); }

    function upload(){
        var id = $('#offer-id').val(); if(!id) return alert('Abra o cree la oferta primero');
        var f = $('#offer-file')[0].files[0]; if(!f) return alert('Seleccione archivo');
        var fd = new FormData(); fd.append('tipo','upload_media'); fd.append('offer_id', id); fd.append('file', f);
        $.ajax({ url:'ajax/provider_offers.php', type:'POST', data:fd, contentType:false, processData:false, dataType:'json', success:function(res){ if(!res.ok) return alert(res.error); renderSingleMedia(res.data); }, error:function(){ alert('Error'); }});
    }

    function loadGallery(offer_id){
        $.getJSON('ajax/provider_offers.php?tipo=get&id='+offer_id, function(res){ if(!res.ok) return alert(res.error); renderGallery(res.data.media||[]); });
    }

    function renderGallery(list){
        var cont = $('#offer-gallery').empty();
        if(!list || list.length==0) { cont.html('<p>No hay fotos</p>'); return; }
        var row = $('<div class="row">');
        $.each(list, function(i,m){
            var col = $('<div class="col-xs-3">');
            col.append($('<img>').addClass('img-responsive').attr('src','../'+m.path).css({'margin-bottom':'10px'}));
            row.append(col);
        });
        cont.append(row);
    }

    function renderSingleMedia(m){
        var cont = $('#offer-gallery');
        var row = $('<div class="row">');
        var col = $('<div class="col-xs-3">');
        col.append($('<img>').addClass('img-responsive').attr('src','../'+m.path).css({'margin-bottom':'10px'}));
        row.append(col);
        cont.prepend(row);
    }

    $('#btn-new-offer').click(function(){ loadServices(function(){ openEdit(0); }); });
    $('#offer-save').click(save);
    $('#offer-upload').click(upload);

    // init
    loadServices(listOffers);
});
