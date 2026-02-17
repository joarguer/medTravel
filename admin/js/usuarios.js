$(function(){
    var roles = {};
    var serviceProviders = [];
    var filterKind = '';
    var complementaryRoleId = parseInt((window.USERS_CTX && window.USERS_CTX.complementaryRoleId) || 12, 10);

    function loadRoles(cb){
        $.get('ajax/usuarios.php',{action:'list_roles'}, function(res){
            if(res && res.success && res.data){
                roles = {};
                res.data.forEach(function(r){ roles[r.id] = r.name; });
            }
            if(cb) cb();
        },'json');
    }

    function loadServiceProviders(cb){
        $.get('ajax/usuarios.php',{action:'list_service_providers'}, function(res){
            if(res && res.success && res.data){
                serviceProviders = res.data.slice();
            } else {
                serviceProviders = [];
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

    function serviceProviderSelect(currentId){
        var sel = $('<select class="form-control input-sm service-provider-select" style="margin-top:6px;">');
        sel.append($('<option>').val('').text('Seleccione proveedor complementario'));
        serviceProviders.forEach(function(sp){
            var opt = $('<option>').val(sp.id).text(sp.provider_name);
            if(parseInt(sp.id, 10) === parseInt(currentId || 0, 10)) opt.attr('selected','selected');
            sel.append(opt);
        });
        return sel;
    }

    function toggleServiceProviderControl(tr){
        var roleId = parseInt(tr.find('.role-select').val() || 0, 10);
        var sel = tr.find('.service-provider-select');
        if(!sel.length) return;
        if(roleId === complementaryRoleId){
            sel.show().prop('disabled', false);
        } else {
            sel.hide().prop('disabled', true).val('');
        }
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
                var roleSel = roleSelect(u.role_id || 0);
                var spSel = serviceProviderSelect(u.service_provider_id || '');
                roleCell.append(roleSel).append(spSel);
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
            if(window.USERS_CTX.canEdit){
                toggleServiceProviderControl(tr);
            }
        });
    }

    function loadUsers(){
        $.get('ajax/usuarios.php',{action:'list'}, function(res){
            if(res && res.success){ renderTable(res.data || []); }
        },'json');
    }

    function updateUserRole(tr){
        var id = tr.data('id');
        var roleId = parseInt(tr.find('.role-select').val(),10);
        var payload = { action:'update_role', id:id, role_id: roleId };
        if(roleId === complementaryRoleId){
            var serviceProviderId = parseInt(tr.find('.service-provider-select').val() || 0, 10);
            if(!serviceProviderId){
                alert('Debes seleccionar un proveedor complementario activo para este rol');
                return;
            }
            payload.service_provider_id = serviceProviderId;
        }
        $.post('ajax/usuarios.php', payload, function(res){
            if(res && res.success){
                loadUsers();
            } else {
                alert(res && res.error ? res.error : 'Error al actualizar usuario');
                loadUsers();
            }
        },'json');
    }

    $('#users-table').on('change', '.role-select', function(){
        var tr = $(this).closest('tr');
        toggleServiceProviderControl(tr);
        var roleId = parseInt($(this).val() || 0,10);
        if(roleId === complementaryRoleId && !tr.find('.service-provider-select').val()){
            alert('Selecciona un proveedor complementario para guardar el rol');
            return;
        }
        updateUserRole(tr);
    });

    $('#users-table').on('change', '.service-provider-select', function(){
        var tr = $(this).closest('tr');
        var roleId = parseInt(tr.find('.role-select').val() || 0,10);
        if(roleId !== complementaryRoleId){
            return;
        }
        updateUserRole(tr);
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

    loadRoles(function(){
        loadServiceProviders(loadUsers);
    });
});
