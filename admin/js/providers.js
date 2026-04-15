$(document).ready(function(){
    const url = 'ajax/providers.php';
    const urlCats = 'ajax/service_categories.php';
    const urlServices = 'ajax/service_catalog.php';
    const verificationBaseUrl = 'provider_verification.php';
    var currentOwnerState = 'new';
    var currentListFilter = 'active';
    var providerMultiSelectNeedsInit = false;
    const providerDocumentChecklist = [
        { key: 'business_registration', label: 'Registro empresarial', description: 'Cámara de comercio o registro de empresa vigente.', category: 'Legal', required: true },
        { key: 'tax_id', label: 'RUT o Tax ID', description: 'Identificación tributaria vigente del prestador.', category: 'Legal', required: true },
        { key: 'medical_license', label: 'Licencia médica', description: 'Licencia profesional vigente cuando aplique al dominio clínico.', category: 'Médico', required: true },
        { key: 'professional_certifications', label: 'Certificaciones profesionales', description: 'Soportes de especialización o entrenamiento clínico.', category: 'Médico', required: false },
        { key: 'clinic_accreditation', label: 'Acreditación de clínica', description: 'Habilitación o acreditación institucional de la sede clínica.', category: 'Médico', required: true },
        { key: 'facility_photos', label: 'Fotos de instalaciones', description: 'Consultorios, quirófanos, recuperación y áreas visibles del prestador.', category: 'Instalaciones', required: true },
        { key: 'equipment_certification', label: 'Certificación de equipos', description: 'Documentos de calibración o certificación de equipos médicos.', category: 'Instalaciones', required: false },
        { key: 'owner_identity', label: 'Identidad del responsable', description: 'Documento del owner/admin o responsable principal del prestador.', category: 'Identidad', required: true },
        { key: 'staff_credentials', label: 'Credenciales del personal', description: 'Listado o soportes del personal médico y sus licencias.', category: 'Identidad', required: false },
        { key: 'liability_insurance', label: 'Seguro de responsabilidad', description: 'Póliza vigente de responsabilidad civil o equivalente.', category: 'Seguros', required: true },
        { key: 'malpractice_insurance', label: 'Seguro de mala praxis', description: 'Póliza profesional adicional cuando aplique.', category: 'Seguros', required: false }
    ];

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

    function normalizeListFilter(filter){
        return ['active', 'archived', 'all'].indexOf(filter) >= 0 ? filter : 'active';
    }

    function updateProvidersViewNote(){
        const copy = {
            active: {
                caption: 'Vista actual: prestadores activos.',
                noteClass: 'alert-warning',
                html: '<strong>Prestadores activos:</strong> aquí ves la operación vigente. Archivar saca al prestador de esta vista sin borrar su historial, documentos, bookings ni relaciones.'
            },
            archived: {
                caption: 'Vista actual: prestadores archivados.',
                noteClass: 'alert-danger',
                html: '<strong>Prestadores archivados:</strong> estos registros ya no participan en la operación activa. Puedes restaurarlos desde esta misma pantalla sin perder historial, documentos ni bookings.'
            },
            all: {
                caption: 'Vista actual: todos los prestadores.',
                noteClass: 'alert-info',
                html: '<strong>Todos los prestadores:</strong> esta vista mezcla activos, inactivos y archivados para revisión operativa. Archivar sigue siendo reversible y no elimina físicamente el registro.'
            }
        };
        const selected = copy[normalizeListFilter(currentListFilter)] || copy.active;
        $('#providers-filter-caption').text(selected.caption);
        $('#providers-view-note')
            .removeClass('alert-warning alert-danger alert-info')
            .addClass(selected.noteClass)
            .html(selected.html);
    }

    function verificationUrlForProvider(providerId){
        if(!providerId){
            return verificationBaseUrl;
        }
        return verificationBaseUrl + '?provider_id=' + encodeURIComponent(providerId);
    }

    function renderProviderDocumentChecklist(providerType){
        const isMedicalPerson = (providerType || '').toLowerCase() === 'medico';
        const html = providerDocumentChecklist.map(function(item){
            let description = item.description;
            if(item.key === 'medical_license' && !isMedicalPerson){
                description = 'Licencia profesional del médico responsable o del personal clínico principal.';
            }
            if(item.key === 'clinic_accreditation' && isMedicalPerson){
                description = 'Si atiende desde sede propia o aliada, soporta habilitación o acreditación de la clínica/consultorio.';
            }
            return ''
                + '<div class="provider-doc-item">'
                + '  <div class="provider-doc-card">'
                + '    <div class="provider-doc-meta">'
                + '      <span class="label label-default">' + escapeHtml(item.category) + '</span>'
                +        (item.required ? '<span class="label label-danger">Obligatorio</span>' : '<span class="label label-info">Opcional</span>')
                + '    </div>'
                + '    <strong>' + escapeHtml(item.label) + '</strong>'
                + '    <p>' + escapeHtml(description) + '</p>'
                + '  </div>'
                + '</div>';
        }).join('');
        $('#provider-doc-checklist').html(html);
    }

    function updateProviderDocumentsSection(providerId, providerName, providerType){
        renderProviderDocumentChecklist(providerType || $('#prov-type').val());

        const hasProvider = !!providerId;
        const buttonText = hasProvider ? 'Ir a Verificación de Prestadores' : 'Guardar para habilitar verificación';
        const helpText = hasProvider
            ? 'La consola documental permite inicializar el checklist, subir evidencias y validar documentos del prestador.'
            : 'Guarda primero el prestador para habilitar la carga y validación de documentos.';
        const summaryText = hasProvider
            ? 'Checklist documental estándar: gestiona la evidencia del prestador y su trust score desde la consola de verificación.'
            : 'Checklist documental estándar: después del alta, completa y evidencia estos documentos desde la consola de verificación.';

        $('#provider-documents-summary').html('<strong>Checklist documental estándar:</strong> ' + summaryText);
        $('#prov-docs-manage')
            .prop('disabled', !hasProvider)
            .data('provider-id', providerId || '')
            .data('provider-name', providerName || '')
            .text(buttonText);
        $('#prov-docs-help').text(helpText);
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
        let meta = '';
        if(provider.owner_admin_username){
            meta = '<div class="small text-muted" style="margin-top:4px;">Owner/admin: <strong>' + escapeHtml(provider.owner_admin_username) + '</strong>';
            if(provider.owner_source === 'legacy_fallback'){
                meta += ' <span class="label label-warning">Compatibilidad</span>';
            } else if(provider.owner_source === 'provider_users'){
                meta += ' <span class="label label-success">Explicito</span>';
            }
            meta += '</div>';
        } else {
            meta = '<div class="small text-warning" style="margin-top:4px;">Sin owner/admin inicial visible</div>';
        }

        if(provider.is_deleted == 1){
            meta += '<div class="small text-warning" style="margin-top:4px;">Prestador archivado';
            if(provider.deleted_at){
                meta += ' desde <strong>' + escapeHtml(provider.deleted_at) + '</strong>';
            }
            meta += '</div>';
            if(provider.archive_reason){
                meta += '<div class="small text-muted" style="margin-top:2px;">Motivo: ' + escapeHtml(provider.archive_reason) + '</div>';
            }
        }

        return meta;
    }

    function buildProviderStatusCell(provider){
        if(parseInt(provider.is_deleted || 0, 10) === 1){
            return '<span class="label label-warning">Archivado</span>';
        }
        return provider.is_active == 1
            ? '<button class="btn btn-xs btn-success toggle-active" data-val="0">Activo</button>'
            : '<button class="btn btn-xs btn-default toggle-active" data-val="1">Inactivo</button>';
    }

    function buildProviderActions(provider){
        if(parseInt(provider.is_deleted || 0, 10) === 1){
            return '<button class="btn btn-sm btn-default restore-provider"><i class="fa fa-undo"></i> Restaurar</button>';
        }

        return ''
            + '<button class="btn btn-sm btn-primary edit">Editar</button> '
            + '<a href="providers_edit.php?id=' + provider.id + '" class="btn btn-sm btn-default" title="Commission Settings"><i class="fa fa-usd"></i></a> '
            + '<button class="btn btn-sm btn-warning archive-provider" title="Archivar prestador"><i class="fa fa-archive"></i> Archivar</button>';
    }

    function archiveImpactValue(value, available){
        if(available === false){
            return 'No disponible';
        }
        return String(value || 0);
    }

    function renderArchiveImpactGrid(preview){
        const impact = preview && preview.impact ? preview.impact : {};
        const cards = [
            { label: 'Usuarios owner/admin', value: archiveImpactValue(impact.owner_admin_users), help: 'Cuentas administrativas principales ligadas al prestador.' },
            { label: 'Usuarios asociados', value: archiveImpactValue(impact.provider_users), help: 'Registros en provider_users o cuentas asociadas al prestador.' },
            { label: 'Staff médico', value: archiveImpactValue(impact.medical_staff), help: 'Personal médico interno asociado al prestador.' },
            { label: 'Servicios habilitados', value: archiveImpactValue(impact.enabled_services), help: 'Servicios del catálogo habilitados para este prestador.' },
            { label: 'Ofertas activas', value: archiveImpactValue(impact.active_offers), help: 'Ofertas activas que dejarán de operar en la vista activa.' },
            { label: 'Ofertas totales', value: archiveImpactValue(impact.total_offers), help: 'Todas las ofertas históricas ligadas al prestador.' },
            { label: 'Documentos', value: archiveImpactValue(impact.documents), help: 'Documentos de verificación o compliance conservados.' },
            { label: 'Bookings históricos', value: archiveImpactValue(impact.historical_booking_items), help: 'Items históricos en booking_request_items vinculados al prestador.' },
            { label: 'Bookings activos o pendientes', value: archiveImpactValue(impact.active_or_pending_booking_items, impact.active_or_pending_booking_items_available), help: 'Items abiertos o no terminales si el estado es distinguible en la base.' }
        ];

        const html = cards.map(function(card){
            return ''
                + '<div class="archive-impact-item">'
                + '  <div class="archive-impact-card">'
                + '    <strong>' + escapeHtml(card.label) + '</strong>'
                + '    <div class="archive-impact-value">' + escapeHtml(card.value) + '</div>'
                + '    <div class="archive-impact-help">' + escapeHtml(card.help) + '</div>'
                + '  </div>'
                + '</div>';
        }).join('');

        $('#archive-impact-grid').html(html);
    }

    function resetArchiveModal(){
        $('#archive-provider-id').val('');
        $('#archive-provider-name').val('');
        $('#archive-reason').val('');
        $('#archive-confirm-text').val('');
        $('#archive-provider-label').text('Este prestador dejara de aparecer en la operacion activa.');
        $('#archive-impact-grid').html('');
    }

    function openArchiveModal(preview){
        const provider = preview && preview.provider ? preview.provider : {};
        resetArchiveModal();
        $('#archive-provider-id').val(provider.id || '');
        $('#archive-provider-name').val(provider.name || '');
        $('#archive-provider-label').text((provider.name || 'Este prestador') + ' dejara de aparecer en la operacion activa.');
        renderArchiveImpactGrid(preview);
        $('#providerArchiveModal').modal('show');
    }

    function loadProviders(filterOverride){
        currentListFilter = normalizeListFilter(filterOverride || currentListFilter);
        updateProvidersViewNote();

        $.post(url, { tipo: 'list', kind: 'medical', state: currentListFilter }, function(res){
            if(!res || !res.ok){
                providerToast('error', 'No fue posible cargar el listado de prestadores.', 'Providers');
                return;
            }

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
                const rowClass = parseInt(p.is_deleted || 0, 10) === 1 ? ' class="provider-archived-row"' : '';

                tbody += '<tr data-id="' + p.id + '" data-name="' + escapeHtml(p.name) + '"' + rowClass + '>';
                tbody += '<td>' + providerCell + '</td>';
                tbody += '<td>' + escapeHtml(p.type) + '</td>';
                tbody += '<td>' + escapeHtml(humanizeKind(p.kind || 'medical')) + '</td>';
                tbody += '<td>' + escapeHtml(p.city || '') + '</td>';
                tbody += '<td><span class="' + st.cls + '">' + st.text + '</span>' + completion + ' <a href="provider_verification.php?provider_id=' + p.id + '" class="ml10">Gestionar</a></td>';
                tbody += '<td>' + buildProviderStatusCell(p) + '</td>';
                tbody += '<td>' + buildProviderActions(p) + '</td>';
                tbody += '</tr>';
            });

            if(!tbody){
                tbody = '<tr><td colspan="7" class="text-center text-muted">No hay prestadores para esta vista.</td></tr>';
            }

            $('#tbl-providers tbody').html(tbody);
        }, 'json').fail(function(){
            providerToast('error', 'Error de conexion al cargar el listado de prestadores.', 'Providers');
        });
    }

    function setKindPresentation(){
        $('#prov-kind').val('medical');
        $('#prov-kind-help').text('Este onboarding canonico crea y administra exclusivamente prestadores medicos.');
    }

    function setOwnerRequirements(required, helpText){
        $('#prov-owner-name').prop('required', required);
        $('#prov-owner-email').val($('#prov-owner-email').val() || '').prop('required', required);
        $('#owner-name-required').toggle(required);
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
        $('#prov-owner-name').val('');
        $('#prov-owner-email').val('');
        $('#prov-owner-role').val('');
        $('#prov-owner-phone').val('');
        $('#prov-owner-city').val('');
        destroyProviderMultiSelect('#prov-categories');
        destroyProviderMultiSelect('#prov-services');
        $('#prov-categories option').prop('selected', false);
        $('#prov-services option').prop('selected', false);
        $('#provider-modal-title').text('Alta de prestador medico');
        $('#provider-modal-intro').html('Este flujo crea el <strong>prestador medico</strong> y su <strong>cuenta owner/admin inicial</strong>.');
        $('#prov-save').text('Crear prestador medico');
        setOwnerRequirements(true, 'Este email sera la identidad de acceso del owner/admin y recibira la invitacion segura para crear su password. No reemplaza el email general del prestador.');
        setKindPresentation();
        setOwnerSummary(
            'new',
            'Se creara la cuenta owner/admin inicial',
            'Al guardar este alta se creara tambien la cuenta owner/admin inicial del prestador medico y se enviara una invitacion de acceso por email.',
            'alert-info'
        );
        updateProviderDocumentsSection('', '', $('#prov-type').val());
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
        $('#prov-owner-name').val(user && user.nombre ? user.nombre : '');
        $('#prov-owner-email').val(user && (user.email || user.usuario) ? (user.email || user.usuario) : '');
        $('#prov-owner-role').val(user && user.cargo ? user.cargo : '');
        $('#prov-owner-phone').val(user && (user.telefono || user.celular) ? (user.telefono || user.celular) : '');
        $('#prov-owner-city').val(user && user.ciudad ? user.ciudad : '');

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
        setOwnerRequirements(false, 'Este email queda asociado como identidad de acceso del owner/admin inicial. Si lo dejas en blanco, se conserva el email actual cuando exista.');
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

        updateProviderDocumentsSection(p.id, p.name, p.type);
        $('#providerModal').modal('show');
    }

    loadLists();
    loadProviders(currentListFilter);

    $('#btn-new-provider').click(function(){
        openCreateModal();
    });

    $('input[name="provider-view-filter"]').on('change', function(){
        loadProviders($(this).val());
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

    $('#providerArchiveModal').on('hidden.bs.modal', function(){
        resetArchiveModal();
    });

    $('#prov-type').on('change', function(){
        updateProviderDocumentsSection($('#prov-id').val(), $('#prov-name').val().trim(), $(this).val());
    });

    $('#prov-name').on('input', function(){
        updateProviderDocumentsSection($('#prov-id').val(), $(this).val().trim(), $('#prov-type').val());
    });

    $('#prov-docs-manage').click(function(){
        const providerId = $(this).data('provider-id');
        if(!providerId){
            providerToast('warning', 'Guarda primero el prestador para habilitar la verificacion documental.', 'Providers');
            return;
        }
        window.open(verificationUrlForProvider(providerId), '_blank');
    });

    $('#prov-save').click(function(){
        let id = $('#prov-id').val();
        let type = $('#prov-type').val();
        let name = $('#prov-name').val().trim();
        let ownerName = $('#prov-owner-name').val().trim();
        let ownerEmail = $('#prov-owner-email').val().trim();
        let selectedKind = 'medical';

        if(!type || !name){
            providerToast('warning', 'Tipo y nombre del prestador medico son requeridos', 'Validacion');
            return;
        }
        if(!id && !ownerName){
            providerToast('warning', 'El nombre del owner/admin inicial es requerido al crear un nuevo prestador medico', 'Validacion');
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
        if(id && currentOwnerState === 'missing' && !ownerName){
            providerToast('warning', 'Este prestador no tiene owner/admin inicial visible. Debes definir el nombre del responsable para crear esa cuenta al guardar.', 'Validacion');
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
            owner_name: ownerName,
            owner_email: ownerEmail,
            owner_role: $('#prov-owner-role').val().trim(),
            owner_phone: $('#prov-owner-phone').val().trim(),
            owner_city: $('#prov-owner-city').val().trim(),
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

    $('#tbl-providers').on('click', '.archive-provider', function(){
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        $.post(url, { tipo: 'archive_preview', id: id }, function(res){
            if(res && res.ok){
                openArchiveModal(res.data || {});
            } else {
                providerToast('error', res && res.message ? res.message : 'Error', 'Providers');
            }
        }, 'json').fail(function(){
            providerToast('error', 'Error de conexion al cargar el impacto del archivado', 'Providers');
        });
    });

    $('#confirm-provider-archive').on('click', function(){
        let providerId = $('#archive-provider-id').val();
        let archiveReason = $('#archive-reason').val().trim();
        let confirmText = $('#archive-confirm-text').val().trim();

        if(!providerId){
            providerToast('error', 'No hay un prestador seleccionado para archivar.', 'Providers');
            return;
        }
        if(!archiveReason){
            providerToast('warning', 'Debes registrar el motivo de archivado.', 'Validacion');
            return;
        }
        if(!confirmText){
            providerToast('warning', 'Debes escribir ARCHIVAR o el nombre del prestador para confirmar.', 'Validacion');
            return;
        }

        $.post(url, {
            tipo: 'archive',
            id: providerId,
            archive_reason: archiveReason,
            confirm_text: confirmText
        }, function(res){
            if(res && res.ok){
                $('#providerArchiveModal').modal('hide');
                loadProviders();
                providerToast('success', res.message || 'Prestador archivado', 'Providers');
            } else {
                providerToast('error', res && res.message ? res.message : 'No fue posible archivar el prestador.', 'Providers');
            }
        }, 'json').fail(function(xhr){
            let message = 'Error de conexion al archivar el prestador';
            if(xhr && xhr.responseJSON && xhr.responseJSON.message){
                message = xhr.responseJSON.message;
            }
            providerToast('error', message, 'Providers');
        });
    });

    $('#tbl-providers').on('click', '.restore-provider', function(){
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        let name = tr.data('name') || 'este prestador';
        if(!confirm('¿Restaurar a ' + name + ' y devolverlo a la operación activa? Esta acción no borra historial.')) return;
        $.post(url, { tipo: 'restore', id: id }, function(res){
            if(res && res.ok){
                loadProviders();
                providerToast('success', res.message || 'Prestador restaurado', 'Providers');
            } else {
                providerToast('error', res && res.message ? res.message : 'No fue posible restaurar el prestador.', 'Providers');
            }
        }, 'json').fail(function(){
            providerToast('error', 'Error de conexion al restaurar el prestador', 'Providers');
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
