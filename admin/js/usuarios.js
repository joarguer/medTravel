$(function(){
    var roles = [];
    var serviceProviders = [];
    var providers = [];
    var usersData = [];
    var filterKind = '';

    var ctx = window.USERS_CTX || {};
    var canEdit = !!ctx.canEdit || !!ctx.isAdmin;
    var isAdmin = !!ctx.isAdmin;
    var complementaryRoleId = parseInt(ctx.complementaryRoleId || 13, 10);
    var providerRoleId = parseInt(ctx.providerRoleId || 4, 10);
    var providerAdminRoleId = parseInt(ctx.providerAdminRoleId || 12, 10);
    var medicalRoleIds = [providerRoleId, providerAdminRoleId];
    var shownTempPasswordAlerts = {};

    function notifyError(msg){
        if(window.toastr){ toastr.error(msg); return; }
        alert(msg);
    }

    function notifySuccess(msg){
        if(window.toastr){ toastr.success(msg); return; }
        alert(msg);
    }

    function loadRoles(cb){
        $.get('ajax/usuarios.php', {action:'list_roles'}, function(res){
            roles = (res && res.success && Array.isArray(res.data)) ? res.data : [];
            if(cb) cb();
        }, 'json');
    }

    function loadProviders(cb){
        $.get('ajax/usuarios.php', {action:'list_providers'}, function(res){
            providers = (res && res.success && Array.isArray(res.data)) ? res.data : [];
            if(cb) cb();
        }, 'json');
    }

    function loadServiceProviders(cb){
        $.get('ajax/usuarios.php', {action:'list_service_providers'}, function(res){
            serviceProviders = (res && res.success && Array.isArray(res.data)) ? res.data : [];
            if(cb) cb();
        }, 'json');
    }

    function roleSelect(currentId){
        var sel = $('<select class="form-control input-sm role-select">');
        roles.forEach(function(r){
            var id = parseInt(r.id, 10);
            var name = r.name || ('Rol ' + id);
            var opt = $('<option>').val(id).text(name);
            if(id === parseInt(currentId || 0, 10)) opt.attr('selected', 'selected');
            sel.append(opt);
        });
        return sel;
    }

    function serviceProviderSelect(currentId){
        var sel = $('<select class="form-control input-sm service-provider-select" style="margin-top:6px;">');
        sel.append($('<option>').val('').text('Seleccione proveedor complementario'));
        serviceProviders.forEach(function(sp){
            var id = parseInt(sp.id, 10);
            var name = sp.provider_name || ('Proveedor ' + id);
            var opt = $('<option>').val(id).text(name);
            if(id === parseInt(currentId || 0, 10)) opt.attr('selected','selected');
            sel.append(opt);
        });
        return sel;
    }

    function toggleInlineServiceProviderControl(tr){
        var roleId = parseInt(tr.find('.role-select').val() || 0, 10);
        var sel = tr.find('.service-provider-select');
        if(!sel.length) return;

        if(roleId === complementaryRoleId){
            sel.show().prop('disabled', false);
        } else {
            sel.hide().prop('disabled', true).val('');
        }
    }

    function updateUserRoleInline(tr){
        var id = parseInt(tr.data('id') || 0, 10);
        var roleId = parseInt(tr.find('.role-select').val() || 0, 10);
        if(id <= 0 || roleId <= 0) return;

        var payload = { action:'update_role', id:id, role_id:roleId };
        if(medicalRoleIds.indexOf(roleId) !== -1){
            var providerId = parseInt(tr.data('provider-id') || 0, 10);
            if(!providerId){
                notifyError('Selecciona un prestador médico desde Editar para guardar ese rol');
                loadUsers();
                return;
            }
            payload.provider_id = providerId;
        }
        if(roleId === complementaryRoleId){
            var serviceProviderId = parseInt(tr.find('.service-provider-select').val() || 0, 10);
            if(!serviceProviderId){
                notifyError('Selecciona un proveedor complementario para guardar el rol');
                return;
            }
            payload.service_provider_id = serviceProviderId;
        }

        $.post('ajax/usuarios.php', payload, function(res){
            if(res && res.success){
                loadUsers();
            } else {
                notifyError((res && res.error) ? res.error : 'Error al actualizar rol');
                loadUsers();
            }
        }, 'json').fail(function(){
            notifyError('Error de conexión al actualizar rol');
            loadUsers();
        });
    }

    function renderTable(data){
        if(filterKind){
            data = data.filter(function(u){
                var pk = u.provider_kind || '';
                if(filterKind === 'sin') return !pk;
                return pk === filterKind;
            });
        }

        var tbody = $('#users-table tbody').empty();
        data.forEach(function(u){
            var tr = $('<tr>')
                .attr('data-id', u.id)
                .attr('data-provider-id', (u.provider_id || 0))
                .attr('data-service-provider-id', (u.service_provider_id || 0));
            tr.append($('<td>').text(u.id));
            tr.append($('<td>').text(u.usuario || ''));
            tr.append($('<td>').text(u.nombre || ''));
            tr.append($('<td>').text(u.email || ''));

            var roleCell = $('<td>');
            if(canEdit){
                roleCell.append(roleSelect(u.role_id || 0));
                roleCell.append(serviceProviderSelect(u.service_provider_id || ''));
            } else {
                roleCell.text(u.role_name || '');
            }
            tr.append(roleCell);

            var provText = u.provider || u.empresa || '';
            if(u.provider_kind){ provText += ' [' + u.provider_kind + ']'; }
            tr.append($('<td>').text(provText));

            tr.append($('<td>').text(u.activo === 1 ? 'Activo' : 'Inactivo'));

            var actions = $('<td>');
            if(canEdit){
                var editBtn = $('#users-edit-btn-template').find('button').first().clone();
                if(editBtn.length){
                    editBtn.attr('data-id', u.id);
                    actions.append(editBtn);
                } else {
                    actions.append('<button type="button" class="btn btn-xs btn-primary btn-user-edit edit-user" data-id="' + u.id + '" style="margin-right:6px;">EDITAR</button>');
                }
                if(isAdmin){
                    var resetBtn = $('#users-reset-btn-template').find('button').first().clone();
                    if(resetBtn.length){
                        resetBtn.attr('data-id', u.id);
                        actions.append(resetBtn);
                    } else {
                        actions.append('<button type="button" class="btn btn-xs btn-warning btn-user-reset-pass" data-id="' + u.id + '" style="margin-right:6px;">RESET PASS</button>');
                    }
                }
                actions.append('<button type="button" class="btn btn-xs btn-default toggle-active">' + (u.activo === 1 ? 'Desactivar' : 'Activar') + '</button>');
            }
            tr.append(actions);
            tbody.append(tr);

            if(canEdit){
                toggleInlineServiceProviderControl(tr);
            }
        });
    }

    function loadUsers(cb){
        $.get('ajax/usuarios.php', {action:'list'}, function(res){
            usersData = (res && res.success && Array.isArray(res.data)) ? res.data : [];
            renderTable(usersData);
            if(cb) cb();
        }, 'json');
    }

    function fillRoleOptions(selected){
        var sel = $('#edit-role-id').empty();
        sel.append($('<option>').val('').text('Seleccione rol'));
        roles.forEach(function(r){
            var id = parseInt(r.id, 10);
            var name = r.name || ('Rol ' + id);
            var opt = $('<option>').val(id).text(name);
            if(id === parseInt(selected || 0, 10)) opt.prop('selected', true);
            sel.append(opt);
        });
    }

    function fillProviderOptions(selected){
        var sel = $('#edit-provider-id').empty();
        sel.append($('<option>').val('').text('Seleccione prestador médico'));
        providers.forEach(function(p){
            var id = parseInt(p.id, 10);
            var name = p.name || ('Prestador ' + id);
            var opt = $('<option>').val(id).text(name);
            if(id === parseInt(selected || 0, 10)) opt.prop('selected', true);
            sel.append(opt);
        });
    }

    function fillServiceProviderOptions(selected){
        var sel = $('#edit-service-provider-id').empty();
        sel.append($('<option>').val('').text('Seleccione proveedor complementario'));
        serviceProviders.forEach(function(sp){
            var id = parseInt(sp.id, 10);
            var name = sp.provider_name || ('Proveedor ' + id);
            var opt = $('<option>').val(id).text(name);
            if(id === parseInt(selected || 0, 10)) opt.prop('selected', true);
            sel.append(opt);
        });
    }

    function toggleEditOwnershipFields(){
        var roleId = parseInt($('#edit-role-id').val() || 0, 10);
        var showMedicalProvider = (medicalRoleIds.indexOf(roleId) !== -1);
        var showComplementaryProvider = (roleId === complementaryRoleId);

        $('#edit-provider-group').toggle(showMedicalProvider);
        $('#edit-service-provider-group').toggle(showComplementaryProvider);

        if(!showMedicalProvider){
            $('#edit-provider-id').val('');
        }
        if(!showComplementaryProvider){
            $('#edit-service-provider-id').val('');
        }
    }

    function openEditModal(userId){
        $.get('ajax/usuarios.php', {action:'get_user', id:userId}, function(res){
            if(!(res && res.success && res.data)){
                notifyError((res && res.error) ? res.error : 'No se pudo cargar el usuario');
                return;
            }

            var u = res.data;
            $('#edit-id').val(u.id);
            $('#edit-email').val(u.email || '');
            $('#edit-usuario').val(u.usuario || '');
            fillRoleOptions(u.role_id || '');
            fillProviderOptions(u.provider_id || '');
            fillServiceProviderOptions(u.service_provider_id || '');
            $('#edit-activo').val(String(parseInt(u.activo, 10) === 1 ? 1 : 0));
            toggleEditOwnershipFields();

            $('#user-edit-modal').modal('show');
        }, 'json').fail(function(){
            notifyError('Error de conexión al cargar usuario');
        });
    }

    function isValidEmail(email){
        if(email.indexOf(',') !== -1) return false;
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function submitEditUser(){
        var id = parseInt($('#edit-id').val() || 0, 10);
        var email = $.trim($('#edit-email').val() || '');
        var usuario = $.trim($('#edit-usuario').val() || '');
        var roleId = parseInt($('#edit-role-id').val() || 0, 10);
        var activo = parseInt($('#edit-activo').val() || 0, 10);
        var providerId = $('#edit-provider-id').val() || '';
        var serviceProviderId = $('#edit-service-provider-id').val() || '';

        if(id <= 0){
            notifyError('Usuario inválido');
            return;
        }
        if(!isValidEmail(email)){
            notifyError('El email es inválido. Verifica formato y que no tenga comas.');
            return;
        }
        if(!usuario){
            notifyError('El usuario es obligatorio');
            return;
        }
        if(roleId <= 0){
            notifyError('El rol es obligatorio');
            return;
        }
        if(medicalRoleIds.indexOf(roleId) !== -1 && !providerId){
            notifyError('Debes seleccionar un prestador médico activo');
            return;
        }
        if(roleId === complementaryRoleId && !serviceProviderId){
            notifyError('Debes seleccionar un proveedor complementario activo');
            return;
        }

        var payload = {
            action: 'update_user',
            id: id,
            email: email,
            usuario: usuario,
            role_id: roleId,
            activo: activo,
            provider_id: providerId,
            service_provider_id: serviceProviderId
        };

        $.post('ajax/usuarios.php', payload, function(res){
            if(res && res.success){
                $('#user-edit-modal').modal('hide');
                notifySuccess('Usuario actualizado correctamente');
                loadUsers();
                return;
            }
            notifyError((res && res.error) ? res.error : 'Error al actualizar usuario');
        }, 'json').fail(function(xhr){
            var msg = 'Error al actualizar usuario';
            if(xhr && xhr.responseJSON && xhr.responseJSON.error){
                msg = xhr.responseJSON.error;
            }
            notifyError(msg);
        });
    }

    $('#filter-kind-users').on('change', function(){
        filterKind = $(this).val();
        renderTable(usersData);
    });

    $('#users-table').on('change', '.role-select', function(){
        var tr = $(this).closest('tr');
        toggleInlineServiceProviderControl(tr);
        updateUserRoleInline(tr);
    });

    $('#users-table').on('change', '.service-provider-select', function(){
        var tr = $(this).closest('tr');
        var roleId = parseInt(tr.find('.role-select').val() || 0, 10);
        if(roleId === complementaryRoleId){
            updateUserRoleInline(tr);
        }
    });

    $('#users-table').on('click', '.toggle-active', function(){
        var tr = $(this).closest('tr');
        var id = parseInt(tr.data('id') || 0, 10);
        if(id <= 0) return;
        var activeText = $.trim(tr.find('td').eq(6).text()).toLowerCase();
        var nextVal = activeText === 'activo' ? 0 : 1;

        $.post('ajax/usuarios.php', {action:'toggle_active', id:id, val:nextVal}, function(res){
            if(res && res.success){
                loadUsers();
            } else {
                notifyError((res && res.error) ? res.error : 'Error al cambiar estado');
            }
        }, 'json').fail(function(){
            notifyError('Error de conexión al cambiar estado');
        });
    });

    $('#users-table').on('click', '.btn-user-edit, .edit-user', function(){
        var userId = parseInt($(this).data('id') || $(this).closest('tr').data('id') || 0, 10);
        if(userId > 0){
            openEditModal(userId);
        }
    });

    $('#users-table').on('click', '.btn-user-reset-pass', function(){
        if(!isAdmin){
            notifyError('Acceso denegado');
            return;
        }

        var userId = parseInt($(this).data('id') || $(this).closest('tr').data('id') || 0, 10);
        if(userId <= 0){
            notifyError('Usuario inválido');
            return;
        }

        if(!window.confirm('¿Resetear contraseña de este usuario?')){
            return;
        }

        $.post('ajax/usuarios.php', { action:'reset_password', user_id:userId }, function(res){
            if(!(res && res.success)){
                notifyError((res && res.error) ? res.error : 'Error al resetear contraseña');
                return;
            }

            if(res.mail_failed === true && res.temp_password){
                notifySuccess('Password temporal generado. Correo pendiente.');
                if(!shownTempPasswordAlerts[userId]){
                    shownTempPasswordAlerts[userId] = true;
                    window.prompt('Password temporal generado (copiar):', res.temp_password);
                }
                return;
            }

            notifySuccess('Password temporal generado y enviado por correo');
        }, 'json').fail(function(xhr){
            var msg = 'Error al resetear contraseña';
            if(xhr && xhr.responseJSON && xhr.responseJSON.error){
                msg = xhr.responseJSON.error;
            }
            notifyError(msg);
        });
    });

    $('#edit-role-id').on('change', toggleEditOwnershipFields);
    $('#btn-save-user-edit').on('click', submitEditUser);

    loadRoles(function(){
        loadProviders(function(){
            loadServiceProviders(loadUsers);
        });
    });
});
