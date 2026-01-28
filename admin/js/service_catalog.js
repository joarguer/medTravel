$(document).ready(function(){
    const url = 'ajax/service_catalog.php';
    const urlCats = 'ajax/service_categories.php';

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

    function loadList(){
        let cat = $('#filter-category').val() || '';
        $.post(url, { tipo: 'list', category_id: cat }, function(res){
            if(!res || !res.ok) return;
            let tbody = '';
            res.data.forEach(function(r){
                tbody += '<tr data-id="'+r.id+'">';
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

    function escapeHtml(text){ if(!text) return ''; return $('<div>').text(text).html(); }

    loadCategories('#filter-category', true);
    loadCategories('#svc-category', false);
    loadList();

    $('#filter-category').change(function(){ loadList(); });

    $('#btn-new-service').click(function(){
        $('#form-service')[0].reset(); $('#svc-id').val(''); $('#serviceModal').modal('show');
    });

    $('#svc-save').click(function(){
        let id = $('#svc-id').val();
        let category_id = parseInt($('#svc-category').val()) || 0;
        let name = $('#svc-name').val().trim();
        if(!category_id){ alert('Seleccione categoría'); return; }
        if(name === ''){ alert('El nombre es requerido'); return; }
        let data = {
            category_id: category_id,
            name: name,
            short_description: $('#svc-desc').val().trim(),
            sort_order: parseInt($('#svc-order').val()) || 1,
            is_active: $('#svc-active').is(':checked') ? 1 : 0
        };
        if(id){ data.id = id; data.tipo = 'update'; }
        else { data.tipo = 'create'; }
        $.post(url, data, function(res){ if(res && res.ok){ $('#serviceModal').modal('hide'); loadList(); } else { alert('Error: '+(res && res.error ? res.error : 'unknown')) } }, 'json');
    });

    $('#tbl-services').on('click', '.edit', function(){
        let tr = $(this).closest('tr'); let id = tr.data('id');
        let catName = tr.find('td').eq(0).text();
        let name = tr.find('td').eq(1).text();
        let slug = tr.find('td').eq(2).text();
        let order = tr.find('td').eq(3).text();
        let activeText = tr.find('td').eq(4).text();
        $('#svc-id').val(id);
        // try select category by matching name (safer to re-fetch item, but keeping simple)
        $('#svc-name').val(name);
        $('#svc-order').val(order);
        $('#svc-active').prop('checked', activeText.trim().toLowerCase().indexOf('activo') !== -1);
        // set category select by value: fetch list and then set selected by category_name match
        $.post(urlCats, { tipo: 'list' }, function(res){ if(res && res.ok){ let sel = ''; res.data.forEach(function(c){ if(c.is_active==1) sel += '<option value="'+c.id+'">'+escapeHtml(c.name)+'</option>'; }); $('#svc-category').html(sel); // try select by category name
                $('#svc-category option').filter(function(){ return $(this).text().trim() == catName.trim(); }).prop('selected', true);
                $('#serviceModal').modal('show'); } }, 'json');
    });

    $('#tbl-services').on('click', '.delete', function(){ if(!confirm('Desactivar este servicio?')) return; let tr = $(this).closest('tr'); let id = tr.data('id'); $.post(url, { tipo: 'toggle', id: id, val: 0 }, function(res){ if(res && res.ok) loadList(); else alert('Error'); }, 'json'); });

    $('#tbl-services').on('click', '.toggle-active', function(){ let btn = $(this); let tr = btn.closest('tr'); let id = tr.data('id'); let val = btn.data('val'); $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){ if(res && res.ok) loadList(); else alert('Error'); }, 'json'); });

});
