$(document).ready(function(){
    const url = 'ajax/providers.php';
    const urlCats = 'ajax/service_categories.php';
    const urlServices = 'ajax/service_catalog.php';
    var currentKindFilter = '';

    function escapeHtml(text){ if(!text) return ''; return $('<div>').text(text).html(); }

    function loadLists(){
        // categories for multiselect
        $.post(urlCats, { tipo: 'list' }, function(res){ if(res && res.ok){ let opts=''; res.data.forEach(function(c){ if(c.is_active==1) opts += '<option value="'+c.id+'">'+escapeHtml(c.name)+'</option>'; }); $('#prov-categories').html(opts); } }, 'json');
        // services for multiselect
        $.post(urlServices, { tipo: 'list' }, function(res){ if(res && res.ok){ let opts=''; res.data.forEach(function(s){ if(s.is_active==1) opts += '<option value="'+s.id+'">'+escapeHtml(s.name)+' ('+escapeHtml(s.category_name||'')+')'+'</option>'; }); $('#prov-services').html(opts); } }, 'json');
    }

    function loadProviders(){
        $.post(url, { tipo: 'list', kind: currentKindFilter }, function(res){
            if(!res || !res.ok) return;
            let tbody='';
            res.data.forEach(function(p){
                const statusMap = {
                    verified: { cls: 'label label-success', text: 'Verificado' },
                    in_review: { cls: 'label label-warning', text: 'En revisión' },
                    pending: { cls: 'label label-default', text: 'Pendiente' },
                    rejected: { cls: 'label label-danger', text: 'Rechazado' }
                };
                const st = statusMap[p.verification_status] || statusMap.pending;
                const completion = p.completion_percent ? ' ('+p.completion_percent+'%)' : '';
                tbody += '<tr data-id="'+p.id+'">';
                tbody += '<td>'+escapeHtml(p.name)+'</td>';
                tbody += '<td>'+escapeHtml(p.type)+'</td>';
                tbody += '<td>'+escapeHtml(p.kind || 'medical')+'</td>';
                tbody += '<td>'+escapeHtml(p.city||'')+'</td>';
                const verLink = '<a href="provider_verification.php" class="ml10">Gestionar</a>';
                tbody += '<td><span class="'+st.cls+'">'+st.text+'</span>'+completion+' '+verLink+'</td>';
                tbody += '<td>'+(p.is_active==1?'<button class="btn btn-xs btn-success toggle-active" data-val="0">Activo</button>':'<button class="btn btn-xs btn-default toggle-active" data-val="1">Inactivo</button>')+'</td>';
                tbody += '<td>'
                       + '<button class="btn btn-sm btn-primary edit">Editar</button> '
                       + '<a href="providers_edit.php?id='+p.id+'" class="btn btn-sm btn-default" title="Commission Settings"><i class="fa fa-percent"></i></a> '
                       + '<button class="btn btn-sm btn-danger soft-delete" title="Eliminar (Soft)"><i class="fa fa-trash"></i></button>'
                       + '</td>';
                tbody += '</tr>';
            });
            $('#tbl-providers tbody').html(tbody);
        }, 'json');
    }

    loadLists(); loadProviders();

    $('#btn-new-provider').click(function(){ 
        $('#form-provider')[0].reset(); 
        $('#prov-id').val(''); 
        $('#prov-username').val('').prop('required', true);
        $('#prov-password').val('').prop('required', true);
        $('#password-required').show();
        $('#password-help').text('Contraseña para acceso al sistema');
        $('#prov-kind').val('medical');
        $('#prov-categories option').prop('selected',false); 
        $('#prov-services option').prop('selected',false); 
        $('#providerModal').modal('show'); 
    });

    $('#prov-save').click(function(){
        let id = $('#prov-id').val();
        let type = $('#prov-type').val(); 
        let name = $('#prov-name').val().trim(); 
        let username = $('#prov-username').val().trim();
        let password = $('#prov-password').val();
        let selectedKind = $('#prov-kind').val() || 'medical';
        
        if(!type || !name){ 
            alert('Tipo y nombre son requeridos'); 
            return; 
        }
        if(!username){ 
            alert('Usuario es requerido'); 
            return; 
        }
        if(!id && !password){ 
            alert('Contraseña es requerida al crear nuevo proveedor'); 
            return; 
        }
        if(!id && selectedKind === 'partner'){
            alert('Legacy complementario — usar service_providers');
            return;
        }
        
        let data = {
            type: type,
            kind: selectedKind,
            name: name,
            legal_name: $('#prov-legal-name').val().trim(),
            username: username,
            description: $('#prov-desc').val().trim(),
            city: $('#prov-city').val().trim(),
            address: $('#prov-address').val().trim(),
            phone: $('#prov-phone').val().trim(),
            email: $('#prov-email').val().trim(),
            website: $('#prov-website').val().trim(),
            is_verified: $('#prov-verified').is(':checked')?1:0,
            is_active: $('#prov-active').is(':checked')?1:0
        };
        
        // Solo agregar password si se ingresó
        if(password){
            data.password = password;
        }
        
        // categories
        let catVals = $('#prov-categories').val() || [];
        let svcVals = $('#prov-services').val() || [];
        // append arrays
        catVals.forEach(function(v){ data['category_ids[]'] = data['category_ids[]'] || []; data['category_ids[]'].push(v); });
        svcVals.forEach(function(v){ data['service_ids[]'] = data['service_ids[]'] || []; data['service_ids[]'].push(v); });
        
        if(id){ 
            data.id = id; 
            data.tipo = 'update'; 
        } else { 
            data.tipo = 'create'; 
        }
        
        $.post(url, data, function(res){ 
            if(res && res.ok){ 
                $('#providerModal').modal('hide'); 
                loadProviders(); 
                alert(res.message || 'Guardado exitosamente');
            } else { 
                alert('Error: '+(res && res.message ? res.message : (res && res.error ? res.error : 'unknown'))); 
            } 
        }, 'json');
    });

    // edit: fetch via tipo=get
    $('#tbl-providers').on('click', '.edit', function(){ 
        let tr = $(this).closest('tr'); 
        let id = tr.data('id'); 
        $.post(url, { tipo: 'get', id: id }, function(res){ 
            if(res && res.ok){ 
                let p = res.data.provider; 
                $('#prov-id').val(p.id); 
                $('#prov-type').val(p.type); 
                $('#prov-kind').val(p.kind || 'medical');
                $('#prov-name').val(p.name); 
                $('#prov-legal-name').val(p.legal_name || '');
                $('#prov-city').val(p.city); 
                $('#prov-address').val(p.address); 
                $('#prov-phone').val(p.phone); 
                $('#prov-email').val(p.email); 
                $('#prov-website').val(p.website); 
                $('#prov-desc').val(p.description); 
                $('#prov-verified').prop('checked', p.is_verified==1); 
                $('#prov-active').prop('checked', p.is_active==1);
                
                // Cargar datos de usuario si existen
                if(res.data.user){
                    $('#prov-username').val(res.data.user.usuario);
                } else {
                    $('#prov-username').val('');
                }
                $('#prov-password').val('').prop('required', false);
                $('#password-required').hide();
                $('#password-help').text('Dejar en blanco para mantener la contraseña actual');
                
                // load lists then set selected
                loadLists(); 
                setTimeout(function(){ 
                    if(Array.isArray(res.data.category_ids)){ 
                        $('#prov-categories').val(res.data.category_ids.map(String)); 
                    } 
                    if(Array.isArray(res.data.service_ids)){ 
                        $('#prov-services').val(res.data.service_ids.map(String)); 
                    } 
                }, 300);
                
                $('#providerModal').modal('show'); 
            } else { 
                alert('No encontrado'); 
            } 
        }, 'json'); 
    });

    $('#tbl-providers').on('click', '.soft-delete', function(){
        if(!confirm('¿Deseas eliminar (soft)?')) return;
        let id = $(this).closest('tr').data('id');
        $.post(url, { tipo: 'soft_delete', id: id }, function(res){
            if(res && res.ok) loadProviders();
            else alert('Error');
        }, 'json');
    });

    $('#tbl-providers').on('click', '.toggle-active', function(){
        let btn = $(this);
        let id = btn.closest('tr').data('id');
        let val = btn.data('val');
        if(parseInt(val,10) === 0 && !confirm('¿Deseas desactivar?')) return;
        $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){
            if(res && res.ok) loadProviders();
            else alert('Error');
        }, 'json');
    });

    $('#filter-kind').on('change', function(){
        currentKindFilter = $(this).val();
        loadProviders();
    });

});
