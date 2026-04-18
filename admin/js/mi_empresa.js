var miEmpresaCtx = window.MI_EMPRESA_CTX || {};
var providerUrlRules = {
    website: { label: 'Website', hosts: [] },
    instagram_url: { label: 'Instagram', hosts: ['instagram.com', 'www.instagram.com'] },
    facebook_url: { label: 'Facebook', hosts: ['facebook.com', 'www.facebook.com', 'fb.me'] },
    linkedin_url: { label: 'LinkedIn', hosts: ['linkedin.com', 'www.linkedin.com'] },
    youtube_url: { label: 'YouTube', hosts: ['youtube.com', 'www.youtube.com', 'youtu.be'] },
    whatsapp_url: { label: 'WhatsApp comercial', hosts: ['wa.me', 'api.whatsapp.com'] }
};

$(document).ready(function() {
    if (!miEmpresaCtx.canEditSelf) {
        setReadOnlyMode();
    }

    loadSelfCompany();

    $('#form-empresa').on('submit', function(e) {
        e.preventDefault();

        if (!miEmpresaCtx.canEditSelf) {
            toastr.warning('Tu perfil no tiene edición de empresa en esta vista.', 'Acceso restringido');
            return;
        }

        var $btn = $('#btn-guardar');
        var btnText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');

        var formData = {
            action: 'update_self_company',
            name: $('#name').val(),
            description: $('#description').val(),
            city: $('#city').val(),
            address: $('#address').val(),
            phone: $('#phone').val(),
            email: $('#email').val(),
            website: $('#website').val(),
            instagram_url: $('#instagram_url').val(),
            facebook_url: $('#facebook_url').val(),
            linkedin_url: $('#linkedin_url').val(),
            youtube_url: $('#youtube_url').val(),
            whatsapp_url: $('#whatsapp_url').val(),
            calendar_capacity: $('#calendar_capacity').val()
        };

        $.ajax({
            url: 'ajax/mi_empresa.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.ok) {
                    toastr.success(response.message || 'Saved', 'Saved');
                    if (response.data) {
                        populateCompanyForm(response.data);
                    }
                } else {
                    toastr.error(response.error || 'Could not save', 'Could not save');
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Could not save', 'Could not save');
            },
            complete: function() {
                $btn.prop('disabled', !miEmpresaCtx.canEditSelf).html(btnText);
            }
        });
    });

    $('#logo').on('change', function() {
        if (!miEmpresaCtx.canUploadLogo) {
            return;
        }

        var file = this.files[0];
        if (!file) {
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            toastr.error('El archivo excede el tamaño máximo de 2MB', 'Error');
            $(this).val('');
            return;
        }

        var allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            toastr.error('Formato no permitido. Use JPG, PNG o WEBP', 'Error');
            $(this).val('');
            return;
        }

        uploadLogo(file);
    });

    $('#email').on('blur', function() {
        var email = $(this).val();
        if (email && !isValidEmail(email)) {
            toastr.warning('El formato del email no es válido', 'Validación');
        }
    });

    Object.keys(providerUrlRules).forEach(function(field) {
        $('#' + field).on('blur', function() {
            var url = $(this).val();
            if (url && !isValidProviderUrl(field, url)) {
                toastr.warning('El enlace de ' + providerUrlRules[field].label + ' no es válido para ese canal.', 'Validación');
            }
        });
    });
});

function setReadOnlyMode() {
    $('#form-empresa').find('input, textarea, select').not('#company_scope_id').prop('readonly', true);
    $('#form-empresa').find('input[type="file"]').prop('disabled', true);
    $('#btn-guardar').prop('disabled', true);
}

function loadSelfCompany() {
    $.ajax({
        url: 'ajax/mi_empresa.php',
        type: 'POST',
        data: { action: 'get_self_company' },
        dataType: 'json',
        success: function(response) {
            if (!response.ok) {
                toastr.error(response.error || 'No fue posible cargar la empresa.', 'Error');
                return;
            }

            if (response.data) {
                populateCompanyForm(response.data);
            }

            if (typeof response.can_edit_self !== 'undefined') {
                miEmpresaCtx.canEditSelf = !!response.can_edit_self;
            }
            if (typeof response.can_upload_logo !== 'undefined') {
                miEmpresaCtx.canUploadLogo = !!response.can_upload_logo;
            }

            if (!miEmpresaCtx.canEditSelf) {
                setReadOnlyMode();
            }
        },
        error: function() {
            toastr.error('Error de conexión al cargar la empresa.', 'Error');
        }
    });
}

function populateCompanyForm(data) {
    $('#name').val(data.name || '');
    $('#description').val(data.description || '');
    $('#city').val(data.city || '');
    $('#address').val(data.address || '');
    $('#phone').val(data.phone || '');
    $('#email').val(data.email || '');
    $('#website').val(data.website || '');
    $('#instagram_url').val(data.instagram_url || '');
    $('#facebook_url').val(data.facebook_url || '');
    $('#linkedin_url').val(data.linkedin_url || '');
    $('#youtube_url').val(data.youtube_url || '');
    $('#whatsapp_url').val(data.whatsapp_url || '');
    $('#calendar_capacity').val(Math.max(1, parseInt(data.calendar_capacity || 1, 10)));

    if ($('#company-type-text').length) {
        $('#company-type-text').text(data.type_label || 'N/A');
    }

    if ($('#company-status-badges').length) {
        var badges = [];
        if (data.domain === 'medical') {
            var statusClassMap = {
                verified: 'badge-success',
                in_review: 'badge-warning',
                pending: 'badge-default',
                rejected: 'badge-danger'
            };
            var statusLabelMap = {
                verified: 'Verificado',
                in_review: 'En revisión',
                pending: 'Pendiente',
                rejected: 'Rechazado'
            };
            var statusClass = statusClassMap[data.status] || 'badge-default';
            var statusLabel = statusLabelMap[data.status] || (data.status || 'Pendiente');
            badges.push('<span class="badge ' + statusClass + '">' + statusLabel + '</span>');
            badges.push('<span class="badge ' + (parseInt(data.is_active, 10) === 1 ? 'badge-info' : 'badge-default') + '">' + (parseInt(data.is_active, 10) === 1 ? 'Activo' : 'Inactivo') + '</span>');
        } else if (data.domain === 'complementary') {
            badges.push('<span class="badge badge-info">Proveedor Complementario</span>');
            badges.push('<span class="badge ' + (parseInt(data.is_active, 10) === 1 ? 'badge-success' : 'badge-default') + '">' + (parseInt(data.is_active, 10) === 1 ? 'Activo' : 'Inactivo') + '</span>');
        }
        $('#company-status-badges').html(badges.join(' '));
    }

    if ($('#company-status-meta').length) {
        if (data.domain === 'medical') {
            var meta = 'Nivel: <strong>' + (data.verification_level || 'basic') + '</strong>' +
                ' &nbsp;·&nbsp; Avance checklist: <strong>' + (parseInt(data.completion_percent || 0, 10)) + '%</strong>';
            if (data.verified_at) {
                meta += ' &nbsp;·&nbsp; Verificado: ' + data.verified_at;
            }
            $('#company-status-meta').html(meta);
        } else if (data.domain === 'complementary') {
            $('#company-status-meta').text('Gestión de empresa limitada al proveedor complementario asociado a tu sesión.');
        }
    }

    if (data.logo_url) {
        $('#logo-preview').attr('src', data.logo_url + '?t=' + new Date().getTime());
    }

    if (data.address_available === false) {
        $('#address').prop('readonly', true);
        if (!$('#address-unavailable-hint').length) {
            $('#address').after('<span id="address-unavailable-hint" class="help-block">No disponible para proveedores complementarios.</span>');
        }
    }
}

function uploadLogo(file) {
    var formData = new FormData();
    formData.append('action', 'upload_logo');
    formData.append('logo', file);

    toastr.info('Subiendo logo...', 'Procesando');

    $.ajax({
        url: 'ajax/mi_empresa.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.ok) {
                toastr.success(response.message || 'Logo actualizado correctamente', 'Éxito');
                if (response.url) {
                    var newUrl = response.url + '?t=' + new Date().getTime();
                    $('#logo-preview').attr('src', newUrl);
                    $('.fileinput-preview img').attr('src', newUrl);
                }
            } else {
                toastr.error(response.error || 'Error al subir el logo', 'Error');
            }
        },
        error: function(xhr, status, error) {
            toastr.error('Error de conexión: ' + error, 'Error');
        }
    });
}

function isValidEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidURL(url) {
    var re = /^https?:\/\/.+/i;
    return re.test(url);
}

// -----------------------------------------------------------------------
// Checklist de documentación
// -----------------------------------------------------------------------

var checklistLoaded = false;

// Bootstrap 3 / Metronic: escuchar desde document y filtrar por target es más fiable
// que binding directo en el <a>, que puede perderse si Metronic intercepta el tab.
$(document).on('shown.bs.tab', function(e) {
    if ($(e.target).attr('href') === '#tab-documentacion' && !checklistLoaded) {
        loadMyChecklist();
    }
});

// Fallback: click directo por si shown.bs.tab no llega (temas que manejan tabs con JS propio)
$(document).ready(function() {
    $(document).on('click', '#tab-documentacion-link', function() {
        setTimeout(function() {
            if (!checklistLoaded) { loadMyChecklist(); }
        }, 80);
    });
});

function loadMyChecklist() {
    $('#checklist-loading').show();
    $('#checklist-table, #checklist-empty, #checklist-unavailable, #checklist-error').hide();

    $.ajax({
        url: 'ajax/mi_empresa.php',
        type: 'POST',
        data: { action: 'get_my_checklist' },
        dataType: 'json',
        success: function(response) {
            $('#checklist-loading').hide();
            if (!response.ok) {
                $('#checklist-error').text(response.error || 'No fue posible cargar el checklist.').show();
                return;
            }
            if (response.checklist_unavailable) {
                $('#checklist-unavailable').show();
                return;
            }
            if (!response.items || response.items.length === 0) {
                $('#checklist-empty').show();
                return;
            }
            renderChecklist(response.items);
            checklistLoaded = true;
        },
        error: function() {
            $('#checklist-loading').hide();
            $('#checklist-error').text('Error de conexión al cargar el checklist.').show();
        }
    });
}

var categoryLabels = {
    legal: 'Legal',
    medical: 'Médico',
    facilities: 'Instalaciones',
    identity: 'Identidad',
    insurance: 'Seguros',
    other: 'Otro'
};
var categoryColors = {
    legal: 'badge-primary',
    medical: 'badge-info',
    facilities: 'badge-warning',
    identity: 'badge-default',
    insurance: 'badge-success',
    other: 'badge-default'
};

function renderChecklist(items) {
    var $tbody = $('#checklist-tbody').empty();
    items.forEach(function(item) {
        var catLabel = categoryLabels[item.item_category] || item.item_category;
        var catColor = categoryColors[item.item_category] || 'badge-default';
        var reqBadge = item.is_required
            ? '<span class="label label-danger">Sí</span>'
            : '<span class="label label-default">No</span>';

        var statusHtml;
        if (item.is_checked) {
            statusHtml = '<span class="label label-success"><i class="fa fa-check"></i> Validado</span>';
        } else if (item.has_document) {
            statusHtml = '<span class="label label-warning"><i class="fa fa-clock-o"></i> En revisión</span>';
        } else {
            statusHtml = item.is_required
                ? '<span class="label label-danger"><i class="fa fa-times"></i> Faltante</span>'
                : '<span class="label label-default"><i class="fa fa-minus"></i> Sin subir</span>';
        }

        var actionsHtml = '';
        if (item.view_url) {
            actionsHtml += '<a href="' + item.view_url + '" target="_blank" class="btn btn-xs btn-default" style="margin-right: 4px;" title="Ver archivo"><i class="fa fa-eye"></i></a>';
        }
        if (!item.is_checked) {
            var label = item.has_document ? 'Reemplazar' : 'Subir';
            actionsHtml += '<button type="button" class="btn btn-xs btn-primary btn-upload-doc" data-item-id="' + item.id + '">'
                + '<i class="fa fa-upload"></i> ' + label + '</button>';
        }
        if (item.has_document && item.original_filename) {
            actionsHtml += '<br><small class="text-muted" style="font-size: 11px;">' + $('<span>').text(item.original_filename).html() + '</small>';
        }

        var $tr = $('<tr>')
            .append($('<td>').html('<strong>' + $('<span>').text(item.item_label).html() + '</strong><br>'
                + '<small class="text-muted">' + $('<span>').text(item.item_description || '').html() + '</small>'))
            .append($('<td>').html('<span class="badge ' + catColor + '">' + catLabel + '</span>'))
            .append($('<td style="text-align:center;">').html(reqBadge))
            .append($('<td>').html(statusHtml))
            .append($('<td>').html(actionsHtml));

        $tbody.append($tr);
    });

    $('#checklist-table').show();

    $('#checklist-table').on('click', '.btn-upload-doc', function() {
        var itemId = $(this).data('item-id');
        triggerDocUpload(itemId);
    });
}

function triggerDocUpload(itemId) {
    var $input = $('<input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none;">');
    $('body').append($input);
    $input.on('change', function() {
        var file = this.files[0];
        if (!file) { $input.remove(); return; }
        uploadProviderDocument(itemId, file, function() { $input.remove(); });
    });
    $input.trigger('click');
}

function uploadProviderDocument(itemId, file, onDone) {
    var formData = new FormData();
    formData.append('action', 'upload_my_document');
    formData.append('item_id', itemId);
    formData.append('document', file);

    toastr.info('Subiendo documento...', 'Procesando');

    $.ajax({
        url: 'ajax/mi_empresa.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.ok) {
                toastr.success(response.message || 'Documento subido', 'Éxito');
                checklistLoaded = false;
                loadMyChecklist();
            } else {
                toastr.error(response.error || 'Error al subir el documento', 'Error');
            }
        },
        error: function() {
            toastr.error('Error de conexión al subir el documento', 'Error');
        },
        complete: function() {
            if (typeof onDone === 'function') { onDone(); }
        }
    });
}

function isValidProviderUrl(field, url) {
    if (!isValidURL(url)) {
        return false;
    }

    var rule = providerUrlRules[field];
    if (!rule || !rule.hosts || rule.hosts.length === 0) {
        return true;
    }

    try {
        var parsed = new URL(url);
        return rule.hosts.indexOf(parsed.hostname.toLowerCase()) !== -1;
    } catch (err) {
        return false;
    }
}
