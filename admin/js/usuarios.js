$(function(){
    var roles = {};
    var filterKind = '';
    function loadRoles(cb){
        $.get('ajax/usuarios.php',{action:'list_roles'}, function(res){
            if(res && res.success && res.data){
                roles = {};
                res.data.forEach(function(r){ roles[r.id] = r.name; });
            }
            if(cb) cb();
        },'json');
    }

    function roleSelect(currentId){
        var sel = $('<select class="form-control input-sm role-select">');
        Object.keys(roles).forEach(function(id){
            var opt = $('<option>').val(id).text(roles[id]);
            if(parseInt(id,10) === currentId) opt.attr('selected','selected');
            sel.append(opt);
        });
        return sel;
    }

    function renderTable(data){
        // filtrar por tipo de prestador
        if(filterKind){
            data = data.filter(function(u){
                var pk = u.provider_kind || '';
                if(filterKind === 'sin') return !pk;
                return pk === filterKind;
            });
        }
        var tbody = $('#users-table tbody').empty();
        data.forEach(function(u){
            var tr = $('<tr>').attr('data-id', u.id);
            tr.append($('<td>').text(u.id));
            tr.append($('<td>').text(u.usuario || ''));
            tr.append($('<td>').text(u.nombre || ''));
            tr.append($('<td>').text(u.email || ''));
            var roleCell = $('<td>');
            if(window.USERS_CTX.canEdit){
                roleCell.append(roleSelect(u.role_id || 0));
            } else {
                roleCell.text(u.role_name || '');
            }
            tr.append(roleCell);
            var provText = u.provider || u.empresa || '';
            if(u.provider_kind){ provText += ' ['+u.provider_kind+']'; }
            tr.append($('<td>').text(provText));
            tr.append($('<td>').text(u.activo === 1 ? 'Activo' : 'Inactivo'));
            var actions = $('<td>');
            if(window.USERS_CTX.canEdit){
                var toggleBtn = $('<button class="btn btn-xs btn-default toggle-active">').text(u.activo === 1 ? 'Desactivar' : 'Activar');
                actions.append(toggleBtn);
            }
            tr.append(actions);
            tbody.append(tr);
        });
    }

    function loadUsers(){
        $.get('ajax/usuarios.php',{action:'list'}, function(res){
            if(res && res.success){ renderTable(res.data || []); }
        },'json');
    }

    $('#users-table').on('change', '.role-select', function(){
        var tr = $(this).closest('tr');
        var id = tr.data('id');
        var roleId = parseInt($(this).val(),10);
        $.post('ajax/usuarios.php',{action:'update_role', id:id, role_id: roleId}, function(res){
            if(!(res && res.success)) alert(res && res.error ? res.error : 'Error al actualizar rol');
        },'json');
    });

    $('#users-table').on('click', '.toggle-active', function(){
        var tr = $(this).closest('tr');
        var id = tr.data('id');
        var current = tr.find('td').eq(6).text().toLowerCase().indexOf('inactivo') === -1 ? 1 : 0;
        var next = current ? 0 : 1;
        $.post('ajax/usuarios.php',{action:'toggle_active', id:id, val:next}, function(res){
            if(res && res.success){ loadUsers(); }
            else alert(res && res.error ? res.error : 'Error al cambiar estado');
        },'json');
    });

    $('#filter-kind-users').on('change', function(){
        var val = $(this).val();
        filterKind = val;
        loadUsers();
    });

    loadRoles(loadUsers);
});
