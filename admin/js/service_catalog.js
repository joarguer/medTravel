$(document).ready(function(){
    const url = 'ajax/service_catalog.php';
    const urlCats = 'ajax/service_categories.php';
    const urlProviders = 'ajax/providers.php';
    const catalogCtx = window.SERVICE_CATALOG_CTX || {};
    const isAdminMedical = !!catalogCtx.isAdmin;
    const scopedMedicalProviderId = catalogCtx.providerId ? parseInt(catalogCtx.providerId, 10) : 0;

    function escapeHtml(text){ if(!text) return ''; return $('<div>').text(text).html(); }

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
        }, 'json');
    }

    function loadMedicalProviders(){
        if(!isAdminMedical){
            return;
        }
        $.post(urlProviders, { tipo: 'list', kind: 'medical' }, function(res){
            if(!res || !res.ok) return;
            let opts = '<option value="">Seleccionar prestador...</option>';
            res.data.forEach(function(p){
                if(parseInt(p.is_active, 10) === 1){
                    opts += '<option value="'+p.id+'">'+escapeHtml(p.name)+'</option>';
                }
            });
            $('#svc-provider').html(opts);
        }, 'json');
    }

    function loadList(){
        let cat = $('#filter-category').val() || '';
        $.post(url, { tipo: 'list', category_id: cat }, function(res){
            if(!res || !res.ok) return;
            let tbody = '';
            res.data.forEach(function(r){
                const categoryId = r.category_id ? parseInt(r.category_id, 10) : 0;
                const providerId = r.provider_id ? parseInt(r.provider_id, 10) : '';
                tbody += '<tr data-id="'+r.id+'" data-category-id="'+categoryId+'" data-provider-id="'+providerId+'">';
                tbody += '<td>'+escapeHtml(r.category_name || '')+'</td>';
                tbody += '<td>'+escapeHtml(r.name)+'</td>';
                tbody += '<td>'+escapeHtml(r.slug)+'</td>';
                tbody += '<td>'+r.sort_order+'</td>';
                tbody += '<td>'+(r.is_active == 1 ? '<button class="btn btn-xs btn-success toggle-active" data-val="0">Activo</button>' : '<button class="btn btn-xs btn-default toggle-active" data-val="1">Inactivo</button>')+'</td>';
                tbody += '<td><button class="btn btn-sm btn-primary edit">Editar</button> <button class="btn btn-sm btn-danger delete">Eliminar</button></td>';
                tbody += '</tr>';
            });
            $('#tbl-services tbody').html(tbody);
        }, 'json');
    }

    function resetFormDefaults(){
        $('#form-service')[0].reset();
        $('#svc-id').val('');
        $('#svc-order').val(1);
        $('#svc-active').prop('checked', true);
        if(isAdminMedical){
            $('#svc-provider').val('');
        } else if(scopedMedicalProviderId > 0){
            $('#svc-provider').val(String(scopedMedicalProviderId));
        }
    }

    applyProviderScopeUI();
    loadCategories('#filter-category', true);
    loadCategories('#svc-category', false);
    loadMedicalProviders();
    loadList();

    $('#filter-category').change(function(){ loadList(); });

    $('#btn-new-service').click(function(){
        resetFormDefaults();
        $('#serviceModal').modal('show');
    });

    $('#svc-save').click(function(){
        let id = $('#svc-id').val();
        let category_id = parseInt($('#svc-category').val(), 10) || 0;
        let name = $('#svc-name').val().trim();
        if(!category_id){ alert('Seleccione categoría'); return; }
        if(name === ''){ alert('El nombre es requerido'); return; }

        let providerIdPayload = 0;
        if(isAdminMedical){
            providerIdPayload = parseInt($('#svc-provider').val(), 10) || 0;
            if(providerIdPayload <= 0){
                alert('Seleccione un prestador médico');
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
                loadList();
            } else {
                alert('Error: '+(res && res.error ? res.error : 'unknown'));
            }
        }, 'json');
    });

    $('#tbl-services').on('click', '.edit', function(){
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        let categoryId = parseInt(tr.attr('data-category-id'), 10) || 0;
        let providerId = parseInt(tr.attr('data-provider-id'), 10) || 0;
        let name = tr.find('td').eq(1).text();
        let order = tr.find('td').eq(3).text();
        let activeText = tr.find('td').eq(4).text();

        $('#svc-id').val(id);
        $('#svc-name').val(name);
        $('#svc-order').val(order);
        $('#svc-active').prop('checked', activeText.trim().toLowerCase().indexOf('activo') !== -1);
        $('#svc-category').val(categoryId ? String(categoryId) : '');

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
            else alert('Error');
        }, 'json');
    });

    $('#tbl-services').on('click', '.toggle-active', function(){
        let btn = $(this);
        let tr = btn.closest('tr');
        let id = tr.data('id');
        let val = btn.data('val');
        $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){
            if(res && res.ok) loadList();
            else alert('Error');
        }, 'json');
    });
});
