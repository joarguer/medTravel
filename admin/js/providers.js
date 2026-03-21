$(document).ready(function(){
    const url = 'ajax/providers.php';
    const urlCats = 'ajax/service_categories.php';
    const urlServices = 'ajax/service_catalog.php';
    var currentOwnerState = 'new';

    function escapeHtml(text){
        if(!text) return '';
        return $('<div>').text(text).html();
    }

    function humanizeKind(kind){
        return 'Prestador medico';
    }

    function loadLists(onComplete){
        let pending = 2;

        function done(){
            pending -= 1;
            if(pending === 0 && typeof onComplete === 'function'){
                onComplete();
            }
        }

        $.post(urlCats, { tipo: 'list' }, function(res){
            if(res && res.ok){
                let opts = '';
                res.data.forEach(function(c){
                    if(c.is_active == 1) opts += '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
                });
                $('#prov-categories').html(opts);
            }
            done();
        }, 'json');

        $.post(urlServices, { tipo: 'list' }, function(res){
            if(res && res.ok){
                let opts = '';
                res.data.forEach(function(s){
                    if(s.is_active == 1) opts += '<option value="' + s.id + '">' + escapeHtml(s.name) + ' (' + escapeHtml(s.category_name || '') + ')</option>';
                });
                $('#prov-services').html(opts);
            }
            done();
        }, 'json');
    }

    function buildOwnerSummaryCell(provider){
        if(provider.owner_admin_username){
            let meta = '<div class="small text-muted" style="margin-top:4px;">Owner/admin: <strong>' + escapeHtml(provider.owner_admin_username) + '</strong>';
            if(provider.owner_source === 'legacy_fallback'){
                meta += ' <span class="label label-warning">Compatibilidad</span>';
            } else if(provider.owner_source === 'provider_users'){
                meta += ' <span class="label label-success">Explicito</span>';
            }
            meta += '</div>';
            return meta;
        }

        return '<div class="small text-warning" style="margin-top:4px;">Sin owner/admin inicial visible</div>';
    }

    function loadProviders(){
        $.post(url, { tipo: 'list', kind: 'medical' }, function(res){
            if(!res || !res.ok) return;

            let tbody = '';
            res.data.forEach(function(p){
                const statusMap = {
                    verified: { cls: 'label label-success', text: 'Verificado' },
                    in_review: { cls: 'label label-warning', text: 'En revision' },
                    pending: { cls: 'label label-default', text: 'Pendiente' },
                    rejected: { cls: 'label label-danger', text: 'Rechazado' }
                };
                const st = statusMap[p.verification_status] || statusMap.pending;
                const completion = p.completion_percent ? ' (' + p.completion_percent + '%)' : '';
                const providerCell = '<strong>' + escapeHtml(p.name) + '</strong>' + buildOwnerSummaryCell(p);

                tbody += '<tr data-id="' + p.id + '">';
                tbody += '<td>' + providerCell + '</td>';
                tbody += '<td>' + escapeHtml(p.type) + '</td>';
                tbody += '<td>' + escapeHtml(humanizeKind(p.kind || 'medical')) + '</td>';
                tbody += '<td>' + escapeHtml(p.city || '') + '</td>';
                tbody += '<td><span class="' + st.cls + '">' + st.text + '</span>' + completion + ' <a href="provider_verification.php" class="ml10">Gestionar</a></td>';
                tbody += '<td>' + (p.is_active == 1 ? '<button class="btn btn-xs btn-success toggle-active" data-val="0">Activo</button>' : '<button class="btn btn-xs btn-default toggle-active" data-val="1">Inactivo</button>') + '</td>';
                tbody += '<td>'
                    + '<button class="btn btn-sm btn-primary edit">Editar</button> '
                    + '<a href="providers_edit.php?id=' + p.id + '" class="btn btn-sm btn-default" title="Commission Settings"><i class="fa fa-usd"></i></a> '
                    + '<button class="btn btn-sm btn-danger soft-delete" title="Eliminar (Soft)"><i class="fa fa-trash"></i></button>'
                    + '</td>';
                tbody += '</tr>';
            });

            $('#tbl-providers tbody').html(tbody);
        }, 'json');
    }

    function setKindPresentation(){
        $('#prov-kind').val('medical');
        $('#prov-kind-help').text('Este onboarding canonico crea y administra exclusivamente prestadores medicos.');
    }

    function setPasswordRequirement(required, helpText){
        $('#prov-password').val('').prop('required', required);
        if(required){
            $('#password-required').show();
        } else {
            $('#password-required').hide();
        }
        $('#password-help').text(helpText);
    }

    function setOwnerSummary(state, title, message, alertClass){
        currentOwnerState = state;
        $('#owner-summary')
            .attr('data-owner-state', state)
            .removeClass('alert-info alert-warning alert-success')
            .addClass(alertClass || 'alert-info');
        $('#owner-summary-title').text(title);
        $('#owner-summary-text').html(message);
    }

    function openCreateModal(){
        $('#form-provider')[0].reset();
        $('#prov-id').val('');
        $('#prov-username').val('').prop('required', true);
        $('#prov-categories option').prop('selected', false);
        $('#prov-services option').prop('selected', false);
        $('#provider-modal-title').text('Alta de prestador medico');
        $('#provider-modal-intro').html('Este flujo crea el <strong>prestador medico</strong> y su <strong>cuenta owner/admin inicial</strong>.');
        $('#prov-save').text('Crear prestador medico');
        $('#username-help').text('Esta sera la cuenta principal de acceso administrativo del prestador medico.');
        setKindPresentation();
        setPasswordRequirement(true, 'Define la contrasena inicial de la cuenta owner/admin.');
        setOwnerSummary(
            'new',
            'Se creara la cuenta owner/admin inicial',
            'Al guardar este alta se creara tambien la cuenta owner/admin inicial del prestador medico.',
            'alert-info'
        );
        $('#providerModal').modal('show');
    }

    function openEditModal(res){
        const p = res.data.provider;
        const user = res.data.user || null;
        const ux = res.data.ux || {};

        if((p.kind || 'medical') !== 'medical'){
            alert('Este registro pertenece al dominio complementario y debe administrarse desde providers_complementary.php.');
            return;
        }

        $('#prov-id').val(p.id);
        $('#prov-type').val(p.type);
        $('#prov-name').val(p.name);
        $('#prov-legal-name').val(p.legal_name || '');
        $('#prov-city').val(p.city || '');
        $('#prov-address').val(p.address || '');
        $('#prov-phone').val(p.phone || '');
        $('#prov-email').val(p.email || '');
        $('#prov-website').val(p.website || '');
        $('#prov-desc').val(p.description || '');
        $('#prov-verified').prop('checked', p.is_verified == 1);
        $('#prov-active').prop('checked', p.is_active == 1);
        $('#prov-username').val(user && user.usuario ? user.usuario : '').prop('required', true);

        if(Array.isArray(res.data.category_ids)){
            $('#prov-categories').val(res.data.category_ids.map(String));
        }
        if(Array.isArray(res.data.service_ids)){
            $('#prov-services').val(res.data.service_ids.map(String));
        }

        $('#provider-modal-title').text('Editar prestador medico');
        $('#provider-modal-intro').html('Aqui editas el <strong>prestador medico</strong> y su <strong>cuenta owner/admin inicial</strong>.');
        $('#prov-save').text('Guardar cambios');
        $('#username-help').text('Username de acceso de la cuenta owner/admin inicial del prestador medico.');
        setKindPresentation();

        if(ux.owner_state === 'missing'){
            setOwnerSummary(
                'missing',
                'Falta la cuenta owner/admin inicial',
                'Este prestador no tiene una cuenta owner/admin inicial visible. Para guardar cambios debes crearla ahora y definir una contrasena.',
                'alert-warning'
            );
            setPasswordRequirement(true, 'Este prestador no tiene owner/admin inicial. Define la contrasena para crear esa cuenta al guardar.');
        } else if(ux.owner_state === 'legacy_fallback'){
            setOwnerSummary(
                'legacy_fallback',
                'Owner/admin detectado por compatibilidad',
                'Se detecto una cuenta administrativa legacy asociada a este prestador. Si guardas, quedara formalizada como owner/admin inicial explicito.',
                'alert-warning'
            );
            setPasswordRequirement(false, 'Deja en blanco para mantener la contrasena actual de la cuenta owner/admin.');
        } else {
            const username = user && user.usuario ? escapeHtml(user.usuario) : 'sin username';
            setOwnerSummary(
                'explicit',
                'Owner/admin inicial actual',
                'Cuenta owner/admin inicial actual: <strong>' + username + '</strong>.',
                'alert-success'
            );
            setPasswordRequirement(false, 'Deja en blanco para mantener la contrasena actual de la cuenta owner/admin.');
        }

        $('#providerModal').modal('show');
    }

    loadLists();
    loadProviders();

    $('#btn-new-provider').click(function(){
        openCreateModal();
    });

    $('#prov-save').click(function(){
        let id = $('#prov-id').val();
        let type = $('#prov-type').val();
        let name = $('#prov-name').val().trim();
        let username = $('#prov-username').val().trim();
        let password = $('#prov-password').val();
        let selectedKind = 'medical';

        if(!type || !name){
            alert('Tipo y nombre del prestador medico son requeridos');
            return;
        }
        if(!username){
            alert('El username del owner/admin inicial es requerido');
            return;
        }
        if(!id && !password){
            alert('La contrasena del owner/admin inicial es requerida al crear un nuevo prestador medico');
            return;
        }
        if(id && currentOwnerState === 'missing' && !password){
            alert('Este prestador no tiene owner/admin inicial. Debes definir una contrasena para crear esa cuenta al guardar.');
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
            is_verified: $('#prov-verified').is(':checked') ? 1 : 0,
            is_active: $('#prov-active').is(':checked') ? 1 : 0
        };

        if(password){
            data.password = password;
        }

        let catVals = $('#prov-categories').val() || [];
        let svcVals = $('#prov-services').val() || [];
        catVals.forEach(function(v){
            data['category_ids[]'] = data['category_ids[]'] || [];
            data['category_ids[]'].push(v);
        });
        svcVals.forEach(function(v){
            data['service_ids[]'] = data['service_ids[]'] || [];
            data['service_ids[]'].push(v);
        });

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
                alert('Error: ' + (res && res.message ? res.message : (res && res.error ? res.error : 'unknown')));
            }
        }, 'json');
    });

    $('#tbl-providers').on('click', '.edit', function(){
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        $.post(url, { tipo: 'get', id: id }, function(res){
            if(res && res.ok){
                loadLists(function(){
                    openEditModal(res);
                });
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
        if(parseInt(val, 10) === 0 && !confirm('¿Deseas desactivar?')) return;
        $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){
            if(res && res.ok) loadProviders();
            else alert('Error');
        }, 'json');
    });
});
