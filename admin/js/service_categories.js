$(document).ready(function(){
    const url = 'ajax/service_categories.php';
    function loadList(){
        $.post(url, { tipo: 'list' }, function(res){
            if(!res) return;
            if(!res.ok) return console.error(res.error);
            let tbody = '';
            res.data.forEach(function(row){
                tbody += '<tr data-id="'+row.id+'">';
                tbody += '<td>'+escapeHtml(row.name)+'</td>';
                tbody += '<td>'+escapeHtml(row.slug)+'</td>';
                tbody += '<td>'+row.sort_order+'</td>';
                tbody += '<td>'+(row.is_active == 1 ? '<button class="btn btn-xs btn-success toggle-active" data-val="0">Activo</button>' : '<button class="btn btn-xs btn-default toggle-active" data-val="1">Inactivo</button>')+'</td>';
                tbody += '<td><button class="btn btn-sm btn-primary edit">Editar</button> <button class="btn btn-sm btn-danger delete">Eliminar</button></td>';
                tbody += '</tr>';
            });
            $('#tbl-categories tbody').html(tbody);
        }, 'json');
    }

    function escapeHtml(text){
        if(!text) return '';
        return $('<div>').text(text).html();
    }

    loadList();

    $('#btn-new-category').click(function(){
        $('#form-category')[0].reset();
        $('#cat-id').val('');
        $('#categoryModal').modal('show');
    });

    // save
    $('#cat-save').click(function(){
        let id = $('#cat-id').val();
        let name = $('#cat-name').val().trim();
        if(name === ''){ alert('El nombre es requerido'); return; }
        let data = {
            name: name,
            description: $('#cat-desc').val().trim(),
            sort_order: parseInt($('#cat-order').val()) || 1,
            is_active: $('#cat-active').is(':checked') ? 1 : 0
        };
        if(id){ data.id = id; data.tipo = 'update'; }
        else { data.tipo = 'create'; }
        $.post(url, data, function(res){
            if(res && res.ok){ $('#categoryModal').modal('hide'); loadList(); }
            else { alert('Error: '+(res && res.error ? res.error : 'unknown')) }
        }, 'json');
    });

    // edit
    $('#tbl-categories').on('click', '.edit', function(){
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        // fetch single? reuse list data by reading cells
        let name = tr.find('td').eq(0).text();
        let slug = tr.find('td').eq(1).text();
        let order = tr.find('td').eq(2).text();
        let activeText = tr.find('td').eq(3).text();
        $('#cat-id').val(id);
        $('#cat-name').val(name);
        $('#cat-desc').val('');
        $('#cat-order').val(order);
        $('#cat-active').prop('checked', activeText.trim().toLowerCase().indexOf('activo') !== -1);
        $('#categoryModal').modal('show');
    });

    // delete = soft disable
    $('#tbl-categories').on('click', '.delete', function(){
        if(!confirm('Desactivar esta categoría?')) return;
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        $.post(url, { tipo: 'toggle', id: id, val: 0 }, function(res){ if(res && res.ok) loadList(); else alert('Error'); }, 'json');
    });

    // toggle active button
    $('#tbl-categories').on('click', '.toggle-active', function(){
        let btn = $(this);
        let tr = btn.closest('tr');
        let id = tr.data('id');
        let val = btn.data('val');
        $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){ if(res && res.ok) loadList(); else alert('Error'); }, 'json');
    });

});
