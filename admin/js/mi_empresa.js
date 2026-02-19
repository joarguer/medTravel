var miEmpresaCtx = window.MI_EMPRESA_CTX || {};

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

    $('#website').on('blur', function() {
        var url = $(this).val();
        if (url && !isValidURL(url)) {
            toastr.warning('El formato de la URL no es válido', 'Validación');
        }
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
