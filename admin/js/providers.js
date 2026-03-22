$(document).ready(function(){
    const url = 'ajax/providers.php';
    const urlCats = 'ajax/service_categories.php';
    const urlServices = 'ajax/service_catalog.php';
    var currentOwnerState = 'new';
    var providerMultiSelectNeedsInit = false;

    function providerToast(type, message, title){
        if(!window.toastr){
            return;
        }
        toastr.options = {
            closeButton: true,
            newestOnTop: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: type === 'error' ? '6000' : '3200'
        };
        toastr[type](message, title || '');
    }

    function escapeHtml(text){
        if(!text) return '';
        return $('<div>').text(text).html();
    }

    function humanizeKind(kind){
        return 'Prestador medico';
    }

    function isValidEmail(email){
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((email || '').trim());
    }

    function providerMultiSelectContainerSelector(fieldSelector){
        return fieldSelector === '#prov-categories' ? '#ms-prov-categories' : '#ms-prov-services';
    }

    function hasProviderMultiSelectRendered(fieldSelector){
        return $(providerMultiSelectContainerSelector(fieldSelector)).length > 0 || $(fieldSelector).next('.ms-container').length > 0;
    }

    function destroyProviderMultiSelect(fieldSelector){
        const $field = $(fieldSelector);
        $(providerMultiSelectContainerSelector(fieldSelector)).remove();
        $field.next('.ms-container').remove();
        if($field.length && typeof $.fn.multiSelect === 'function' && $field.data('multiselect')){
            $field.multiSelect('destroy');
        }
        $field.css('position', '').css('left', '');
    }

    function buildServiceOptionGroups(services){
        const groups = [];
        let currentGroup = null;

        services.forEach(function(service){
            const groupName = $.trim(service.category_name || 'Sin categoría');
            if(!currentGroup || currentGroup.name !== groupName){
                currentGroup = { name: groupName, options: [] };
                groups.push(currentGroup);
            }
            currentGroup.options.push(
                '<option value="' + escapeHtml(service.id) + '">' + escapeHtml(service.name) + ' (' + escapeHtml(service.category_name || '') + ')</option>'
            );
        });

        return groups.map(function(group){
            return '<optgroup label="' + escapeHtml(group.name) + '">' + group.options.join('') + '</optgroup>';
        }).join('');
    }

    function initProviderMultiSelect(fieldSelector){
        const $field = $(fieldSelector);
        if(!$field.length || typeof $.fn.multiSelect !== 'function' || !$field.find('option').length){
            return;
        }

        destroyProviderMultiSelect(fieldSelector);
        $field.multiSelect({
            selectableOptgroup: true,
            selectableHeader: '<div class="provider-multiselect-header">Disponibles</div>',
            selectionHeader: '<div class="provider-multiselect-header">Seleccionados</div>'
        });
    }

    function ensureProviderMultiSelectRendered(){
        const $modal = $('#providerModal');
        ['#prov-categories', '#prov-services'].forEach(function(fieldSelector){
            const $field = $(fieldSelector);
            if(!$field.length || !$field.find('option').length){
                return;
            }
            if(hasProviderMultiSelectRendered(fieldSelector)){
                return;
            }
            if(!$modal.is(':visible')){
                providerMultiSelectNeedsInit = true;
                return;
            }
            initProviderMultiSelect(fieldSelector);
        });
        providerMultiSelectNeedsInit = false;
    }

    function loadLists(onComplete){
        let pending = 2;

        destroyProviderMultiSelect('#prov-categories');
        destroyProviderMultiSelect('#prov-services');

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
                let activeServices = res.data.filter(function(s){ return s.is_active == 1; });
                let opts = buildServiceOptionGroups(activeServices);
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
                tbody += '<td><span class="' + st.cls + '">' + st.text + '</span>' + completion + ' <a href="provider_verification.php?provider_id=' + p.id + '" class="ml10">Gestionar</a></td>';
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

    function setOwnerEmailRequirement(required, helpText){
        $('#prov-owner-email').val($('#prov-owner-email').val() || '').prop('required', required);
        if(required){
            $('#owner-email-required').show();
        } else {
            $('#owner-email-required').hide();
        }
        $('#owner-email-help').text(helpText);
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
        $('#prov-owner-email').val('');
        destroyProviderMultiSelect('#prov-categories');
        destroyProviderMultiSelect('#prov-services');
        $('#prov-categories option').prop('selected', false);
        $('#prov-services option').prop('selected', false);
        $('#provider-modal-title').text('Alta de prestador medico');
        $('#provider-modal-intro').html('Este flujo crea el <strong>prestador medico</strong> y su <strong>cuenta owner/admin inicial</strong>.');
        $('#prov-save').text('Crear prestador medico');
        setOwnerEmailRequirement(true, 'Este email sera la identidad de acceso del owner/admin y recibira la invitacion segura para crear su password. No reemplaza el email general del prestador.');
        setKindPresentation();
        setOwnerSummary(
            'new',
            'Se creara la cuenta owner/admin inicial',
            'Al guardar este alta se creara tambien la cuenta owner/admin inicial del prestador medico y se enviara una invitacion de acceso por email.',
            'alert-info'
        );
        $('#providerModal').modal('show');
    }

    function openEditModal(res){
        const p = res.data.provider;
        const user = res.data.user || null;
        const ux = res.data.ux || {};

        if((p.kind || 'medical') !== 'medical'){
            providerToast('warning', 'Este registro pertenece al dominio complementario y debe administrarse desde providers_complementary.php.', 'Dominio incorrecto');
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
        $('#prov-owner-email').val(user && (user.email || user.usuario) ? (user.email || user.usuario) : '');

        destroyProviderMultiSelect('#prov-categories');
        destroyProviderMultiSelect('#prov-services');

        if(Array.isArray(res.data.category_ids)){
            $('#prov-categories').val(res.data.category_ids.map(String));
        }
        if(Array.isArray(res.data.service_ids)){
            $('#prov-services').val(res.data.service_ids.map(String));
        }

        $('#provider-modal-title').text('Editar prestador medico');
        $('#provider-modal-intro').html('Aqui editas el <strong>prestador medico</strong> y su <strong>cuenta owner/admin inicial</strong>.');
        $('#prov-save').text('Guardar cambios');
        setOwnerEmailRequirement(false, 'Este email queda asociado como identidad de acceso del owner/admin inicial. Si lo dejas en blanco, se conserva el email actual cuando exista.');
        setKindPresentation();

        if(ux.owner_state === 'missing'){
            setOwnerSummary(
                'missing',
                'Falta la cuenta owner/admin inicial',
                'Este prestador no tiene una cuenta owner/admin inicial visible. Al guardar se creara una cuenta basada en email y se enviara una invitacion segura de acceso.',
                'alert-warning'
            );
        } else if(ux.owner_state === 'legacy_fallback'){
            setOwnerSummary(
                'legacy_fallback',
                'Owner/admin detectado por compatibilidad',
                'Se detecto una cuenta administrativa legacy asociada a este prestador. Si guardas, quedara formalizada como owner/admin inicial explicito usando email como acceso.',
                'alert-warning'
            );
        } else {
            const accessEmail = user && (user.email || user.usuario) ? escapeHtml(user.email || user.usuario) : 'sin email';
            setOwnerSummary(
                'explicit',
                'Owner/admin inicial actual',
                'Cuenta owner/admin inicial actual: <strong>' + accessEmail + '</strong>.',
                'alert-success'
            );
        }

        $('#providerModal').modal('show');
    }

    loadLists();
    loadProviders();

    $('#btn-new-provider').click(function(){
        openCreateModal();
    });

    $('#providerModal').on('shown.bs.modal', function(){
        if(providerMultiSelectNeedsInit || !hasProviderMultiSelectRendered('#prov-categories') || !hasProviderMultiSelectRendered('#prov-services')){
            ensureProviderMultiSelectRendered();
        }
    });

    $('#providerModal').on('hidden.bs.modal', function(){
        destroyProviderMultiSelect('#prov-categories');
        destroyProviderMultiSelect('#prov-services');
        providerMultiSelectNeedsInit = false;
    });

    $('#prov-save').click(function(){
        let id = $('#prov-id').val();
        let type = $('#prov-type').val();
        let name = $('#prov-name').val().trim();
        let ownerEmail = $('#prov-owner-email').val().trim();
        let selectedKind = 'medical';

        if(!type || !name){
            providerToast('warning', 'Tipo y nombre del prestador medico son requeridos', 'Validacion');
            return;
        }
        if(!id && !ownerEmail){
            providerToast('warning', 'El email del owner/admin inicial es requerido al crear un nuevo prestador medico', 'Validacion');
            return;
        }
        if(ownerEmail && !isValidEmail(ownerEmail)){
            providerToast('warning', 'El email del owner/admin inicial no es valido', 'Validacion');
            return;
        }
        if(id && currentOwnerState === 'missing' && !ownerEmail){
            providerToast('warning', 'Este prestador no tiene owner/admin inicial visible. Debes definir el email para crear esa cuenta al guardar.', 'Validacion');
            return;
        }
        let data = {
            type: type,
            kind: selectedKind,
            name: name,
            legal_name: $('#prov-legal-name').val().trim(),
            owner_email: ownerEmail,
            description: $('#prov-desc').val().trim(),
            city: $('#prov-city').val().trim(),
            address: $('#prov-address').val().trim(),
            phone: $('#prov-phone').val().trim(),
            email: $('#prov-email').val().trim(),
            website: $('#prov-website').val().trim(),
            is_verified: $('#prov-verified').is(':checked') ? 1 : 0,
            is_active: $('#prov-active').is(':checked') ? 1 : 0
        };

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
                providerToast('success', res.message || 'Guardado exitosamente', 'Providers');
            } else {
                providerToast('error', res && res.message ? res.message : (res && res.error ? res.error : 'unknown'), 'Providers');
            }
        }, 'json').fail(function(xhr){
            let message = 'Error de conexion al guardar el provider';
            if(xhr && xhr.responseJSON && xhr.responseJSON.message){
                message = xhr.responseJSON.message;
            }
            providerToast('error', message, 'Providers');
        });
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
                providerToast('error', 'No encontrado', 'Providers');
            }
        }, 'json').fail(function(){
            providerToast('error', 'Error de conexion al cargar el provider', 'Providers');
        });
    });

    $('#tbl-providers').on('click', '.soft-delete', function(){
        if(!confirm('¿Deseas eliminar (soft)?')) return;
        let id = $(this).closest('tr').data('id');
        $.post(url, { tipo: 'soft_delete', id: id }, function(res){
            if(res && res.ok){
                loadProviders();
                providerToast('success', res.message || 'Prestador eliminado', 'Providers');
            } else {
                providerToast('error', res && res.message ? res.message : 'Error', 'Providers');
            }
        }, 'json').fail(function(){
            providerToast('error', 'Error de conexion al eliminar el provider', 'Providers');
        });
    });

    $('#tbl-providers').on('click', '.toggle-active', function(){
        let btn = $(this);
        let id = btn.closest('tr').data('id');
        let val = btn.data('val');
        if(parseInt(val, 10) === 0 && !confirm('¿Deseas desactivar?')) return;
        $.post(url, { tipo: 'toggle', id: id, val: val }, function(res){
            if(res && res.ok){
                loadProviders();
                providerToast('success', 'Estado actualizado', 'Providers');
            } else {
                providerToast('error', res && res.message ? res.message : 'Error', 'Providers');
            }
        }, 'json').fail(function(){
            providerToast('error', 'Error de conexion al actualizar el estado', 'Providers');
        });
    });
});
