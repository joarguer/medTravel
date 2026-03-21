$(document).ready(function () {
    const ctx = window.PROVIDERS_COMPLEMENTARY_CTX || {};
    const scopedProviderId = (ctx.isComplementary && ctx.serviceProviderId) ? parseInt(ctx.serviceProviderId, 10) : 0;
    const apiUrl = 'ajax/service_providers.php';
    const isScopedComplementary = scopedProviderId > 0;
    const table = $('#providers_table').DataTable({
        processing: true,
        serverSide: false,
        ajax: function (_data, callback) {
            $.getJSON(apiUrl, { action: 'list' }, function (res) {
                if (!res || !res.ok) {
                    toastr.error(res && res.message ? res.message : 'No se pudo cargar proveedores');
                    callback({ data: [] });
                    return;
                }
                callback({ data: res.data });
            });
        },
        columns: [
            { data: 'id' },
            { data: 'provider_name' },
            { data: 'provider_type', render: renderType },
            { data: 'contact_name', render: renderContact },
            { data: 'contact_email', render: safe },
            { data: 'contact_phone', render: renderPhone },
            { data: null, render: renderLocation, orderable: false },
            { data: 'rating', render: renderRating },
            { data: 'is_active', render: renderStatus },
            { data: null, orderable: false, render: renderActions }
        ],
        order: [[1, 'asc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json',
            emptyTable: isScopedComplementary
                ? 'No hay datos disponibles para el proveedor complementario asociado a tu scope actual.'
                : 'No hay proveedores complementarios registrados todavía.'
        }
    });

    function safe(val) { return val ? $('<div>').text(val).html() : ''; }

    function renderType(type) {
        if (!type) return '';
        const classes = {
            airline: 'badge-airline',
            hotel: 'badge-hotel',
            transport: 'badge-transport',
            restaurant: 'badge-restaurant',
            support: 'badge-tour_operator',
            tour_operator: 'badge-tour_operator',
            other: 'badge-other'
        };
        const labels = {
            airline: 'Aerolínea',
            hotel: 'Hotel',
            transport: 'Transporte',
            restaurant: 'Restaurante',
            support: 'Apoyo logístico',
            tour_operator: 'Operador turístico (legacy)',
            other: 'Otro'
        };
        const cls = classes[type] || 'badge-other';
        const label = labels[type] || type;
        return '<span class="badge-type ' + cls + '">' + safe(label) + '</span>';
    }

    function renderContact(val, _type, row) {
        const name = safe(val);
        const email = row.contact_email ? '<div class="text-muted">' + safe(row.contact_email) + '</div>' : '';
        return name + email;
    }

    function renderPhone(val, _type, row) {
        if (!val && !row.contact_mobile) return '';
        const phone = val ? safe(val) : '';
        const mobile = row.contact_mobile ? '<div class="text-muted">Móvil: ' + safe(row.contact_mobile) + '</div>' : '';
        return phone + mobile;
    }

    function renderLocation(_val, _type, row) {
        const city = row.city ? safe(row.city) : '';
        const country = row.country ? safe(row.country) : '';
        return city || country ? city + (city && country ? ', ' : '') + country : '';
    }

    function renderRating(val) {
        if (val === null || val === undefined || val === '') return '';
        return '<span class="rating">★ ' + parseFloat(val).toFixed(1) + '</span>';
    }

    function renderStatus(val) {
        return val == 1
            ? '<span class="label label-success">Activo</span>'
            : '<span class="label label-default">Inactivo</span>';
    }

    function renderActions(_val, _type, row) {
        const toggleLabel = row.is_active == 1 ? 'Desactivar' : 'Activar';
        const toggleClass = row.is_active == 1 ? 'btn-warning' : 'btn-success';
        if (scopedProviderId) {
            return [
                '<button class="btn btn-xs btn-primary edit" data-id="' + row.id + '"><i class="fa fa-pencil"></i></button>',
                ' ',
                '<button class="btn btn-xs ' + toggleClass + ' toggle" data-id="' + row.id + '">' + toggleLabel + '</button>'
            ].join('');
        }
        return [
            '<button class="btn btn-xs btn-primary edit" data-id="' + row.id + '"><i class="fa fa-pencil"></i></button>',
            ' ',
            '<button class="btn btn-xs ' + toggleClass + ' toggle" data-id="' + row.id + '">' + toggleLabel + '</button>',
            ' ',
            '<button class="btn btn-xs btn-danger delete" data-id="' + row.id + '"><i class="fa fa-trash"></i></button>'
        ].join('');
    }

    function resetForm() {
        $('#providerForm')[0].reset();
        $('#provider_id').val('');
        $('#country').val('Colombia');
        $('#rating').val('');
        $('#is_active').prop('checked', true);
        $('#is_preferred').prop('checked', false);
        $('#provider_type').val('');
        $('#provider-modal-context').html(
            'Esta ficha administra la <strong>entidad complementaria</strong> y sus datos operativos/comerciales. '
            + 'El acceso administrativo sigue el scoping vigente del usuario; aquí no se configura todavía una cuenta owner/admin inicial explícita como en el dominio médico.'
        );
    }

    function normalizeProviderTypeForForm(type) {
        return type === 'tour_operator' ? 'support' : (type || '');
    }

    function openCreateModal() {
        if (isScopedComplementary) {
            toastr.warning('No tienes permisos para crear nuevos proveedores.');
            return;
        }
        resetForm();
        $('#providerModalTitle').text('Nuevo proveedor complementario');
        $('#providerModal').modal('show');
    }

    function openEditModal(id) {
        $.getJSON(apiUrl, { action: 'get', id: id }, function (res) {
            if (!res || !res.ok) {
                toastr.error(res && res.message ? res.message : 'No se pudo cargar el proveedor');
                return;
            }
            const p = res.data;
            $('#provider_id').val(p.id);
            $('#provider_name').val(p.provider_name);
            $('#provider_type').val(normalizeProviderTypeForForm(p.provider_type));
            $('#tax_id').val(p.tax_id);
            $('#country').val(p.country);
            $('#city').val(p.city);
            $('#address').val(p.address);
            $('#contact_name').val(p.contact_name);
            $('#contact_position').val(p.contact_position);
            $('#contact_email').val(p.contact_email);
            $('#contact_phone').val(p.contact_phone);
            $('#contact_mobile').val(p.contact_mobile);
            $('#website').val(p.website);
            $('#payment_terms').val(p.payment_terms);
            $('#bank_account').val(p.bank_account);
            $('#preferred_payment_method').val(p.preferred_payment_method);
            $('#rating').val(p.rating);
            $('#notes').val(p.notes);
            $('#contract_details').val(p.contract_details);
            $('#is_active').prop('checked', p.is_active == 1);
            $('#is_preferred').prop('checked', p.is_preferred == 1);
            if (p.provider_type === 'tour_operator') {
                $('#provider-modal-context').html(
                    'Esta ficha administra la <strong>entidad complementaria</strong> y sus datos operativos/comerciales. '
                    + 'Se detectó una tipología legacy de operador turístico; al guardar, la categoría visible se normaliza como <strong>Apoyo logístico</strong>.'
                );
            } else {
                $('#provider-modal-context').html(
                    'Esta ficha administra la <strong>entidad complementaria</strong> y sus datos operativos/comerciales. '
                    + 'El acceso administrativo sigue el scoping vigente del usuario; aquí no se configura todavía una cuenta owner/admin inicial explícita como en el dominio médico.'
                );
            }
            $('#providerModalTitle').text('Editar proveedor complementario');
            $('#providerModal').modal('show');
        });
    }

    function serializeForm() {
        const payload = {
            provider_name: $('#provider_name').val().trim(),
            provider_type: $('#provider_type').val(),
            tax_id: $('#tax_id').val().trim(),
            country: $('#country').val().trim(),
            city: $('#city').val().trim(),
            address: $('#address').val().trim(),
            contact_name: $('#contact_name').val().trim(),
            contact_position: $('#contact_position').val().trim(),
            contact_email: $('#contact_email').val().trim(),
            contact_phone: $('#contact_phone').val().trim(),
            contact_mobile: $('#contact_mobile').val().trim(),
            website: $('#website').val().trim(),
            payment_terms: $('#payment_terms').val().trim(),
            bank_account: $('#bank_account').val().trim(),
            preferred_payment_method: $('#preferred_payment_method').val(),
            rating: $('#rating').val() || 0,
            is_active: $('#is_active').is(':checked') ? 1 : 0,
            is_preferred: $('#is_preferred').is(':checked') ? 1 : 0,
            notes: $('#notes').val().trim(),
            contract_details: $('#contract_details').val().trim()
        };
        const id = $('#provider_id').val();
        if (id) payload.id = id;
        return payload;
    }

    function saveProvider(e) {
        e.preventDefault();
        const payload = serializeForm();
        if (isScopedComplementary && !payload.id) {
            toastr.error('No autorizado');
            return;
        }
        if (!payload.provider_name) {
            toastr.warning('El nombre es obligatorio');
            return;
        }
        if (!payload.provider_type) {
            toastr.warning('Selecciona un tipo de proveedor complementario');
            return;
        }
        const action = payload.id ? 'update' : 'create';
        payload.action = action;

        $.post(apiUrl, payload, function (res) {
            if (res && res.ok) {
                $('#providerModal').modal('hide');
                table.ajax.reload(null, false);
                toastr.success(res.message || 'Proveedor guardado');
            } else {
                toastr.error(res && res.message ? res.message : 'No se pudo guardar');
            }
        }, 'json');
    }

    function toggleStatus(id, nextVal) {
        $.post(apiUrl, { action: 'toggle_status', id: id, val: nextVal }, function (res) {
            if (res && res.ok) {
                table.ajax.reload(null, false);
                toastr.success('Estado actualizado');
            } else {
                toastr.error(res && res.message ? res.message : 'No se pudo actualizar estado');
            }
        }, 'json');
    }

    function deleteProvider(id) {
        if (scopedProviderId) {
            toastr.error('No autorizado');
            return;
        }
        $.post(apiUrl, { action: 'delete', id: id }, function (res) {
            if (res && res.ok) {
                table.ajax.reload(null, false);
                toastr.success(res.message || 'Eliminado');
            } else {
                toastr.error(res && res.message ? res.message : 'No se pudo eliminar');
            }
        }, 'json');
    }

    if (isScopedComplementary) {
        $('#btnNewProvider').hide();
    }
    $('#btnNewProvider').on('click', openCreateModal);
    $('#providerForm').on('submit', saveProvider);

    $('#providers_table').on('click', '.edit', function () {
        const id = $(this).data('id');
        openEditModal(id);
    });

    $('#providers_table').on('click', '.toggle', function () {
        const id = $(this).data('id');
        const tr = $(this).closest('tr');
        const statusText = $.trim(tr.find('td').eq(8).text()).toLowerCase();
        const isActive = statusText === 'activo';
        const nextVal = isActive ? 0 : 1;
        if (nextVal === 0 && !window.confirm('¿Deseas desactivar?')) {
            return;
        }
        toggleStatus(id, nextVal);
    });

    $('#providers_table').on('click', '.delete', function () {
        const id = $(this).data('id');
        if (confirm('¿Deseas eliminar (soft)?')) {
            deleteProvider(id);
        }
    });
});
