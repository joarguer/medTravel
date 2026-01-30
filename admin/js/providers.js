$(document).ready(function(){
    const url = 'ajax/providers.php';
    const urlCats = 'ajax/service_categories.php';
    const urlServices = 'ajax/service_catalog.php';

    function escapeHtml(text){ if(!text) return ''; return $('<div>').text(text).html(); }

    function loadLists(){
        // categories for multiselect
        $.post(urlCats, { tipo: 'list' }, function(res){ if(res && res.ok){ let opts=''; res.data.forEach(function(c){ if(c.is_active==1) opts += '<option value="'+c.id+'">'+escapeHtml(c.name)+'</option>'; }); $('#prov-categories').html(opts); } }, 'json');
        // services for multiselect
        $.post(urlServices, { tipo: 'list' }, function(res){ if(res && res.ok){ let opts=''; res.data.forEach(function(s){ if(s.is_active==1) opts += '<option value="'+s.id+'">'+escapeHtml(s.name)+' ('+escapeHtml(s.category_name||'')+')'+'</option>'; }); $('#prov-services').html(opts); } }, 'json');
    }

    function loadProviders(){
        $.post(url, { tipo: 'list' }, function(res){ if(!res || !res.ok) return; let tbody=''; res.data.forEach(function(p){ tbody += '<tr data-id="'+p.id+'">'; tbody += '<td>'+escapeHtml(p.name)+'</td>'; tbody += '<td>'+escapeHtml(p.type)+'</td>'; tbody += '<td>'+escapeHtml(p.city||'')+'</td>'; tbody += '<td>'+(p.is_verified==1?'<span class="label label-success">Sí</span>':'<span class="label label-default">No</span>')+'</td>'; tbody += '<td>'+(p.is_active==1?'<button class="btn btn-xs btn-success toggle-active" data-val="0">Activo</button>':'<button class="btn btn-xs btn-default toggle-active" data-val="1">Inactivo</button>')+'</td>'; tbody += '<td><button class="btn btn-sm btn-primary edit">Editar</button> <button class="btn btn-sm btn-danger delete">Desactivar</button></td>'; tbody += '</tr>'; }); $('#tbl-providers tbody').html(tbody); }, 'json');
    }

    loadLists(); loadProviders();

    $('#btn-new-provider').click(function(){ 
        $('#form-provider')[0].reset(); 
        $('#prov-id').val(''); 
        $('#prov-username').val('').prop('required', true);
        $('#prov-password').val('').prop('required', true);
        $('#password-required').show();
        $('#password-help').text('Contraseña para acceso al sistema');
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
        
        let data = {
            type: type,
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

    $('#tbl-providers').on('click', '.delete', function(){ if(!confirm('Desactivar este prestador?')) return; let id = $(this).closest('tr').data('id'); $.post(url, { tipo: 'toggle', id: id, val: 0 }, function(res){ if(res && res.ok) loadProviders(); else alert('Error'); }, 'json'); });

    $('#tbl-providers').on('click', '.toggle-active', function(){ let btn = $(this); let id = btn.closest('tr').data('id'); let val = btn.data('val'); $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){ if(res && res.ok) loadProviders(); else alert('Error'); }, 'json'); });

});
