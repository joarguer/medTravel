(function () {
    var table = null;

    $(document).ready(function () {
        initTable();
        bindEvents();
        loadRows();
    });

    function initTable() {
        table = $('#my_booking_requests_table').DataTable({
            destroy: true,
            data: [],
            columns: [
                { data: 'booking_request_id' },
                {
                    data: 'booking_created_at',
                    render: function (value) {
                        return value ? new Date(value).toLocaleString() : '';
                    }
                },
                {
                    data: null,
                    render: function (row) {
                        var destination = escapeHtml(row.destination || '-');
                        var timeline = escapeHtml(buildTimeline(row));
                        return destination + '<br><small>' + timeline + '</small>';
                    }
                },
                {
                    data: 'item_type',
                    render: function (value) {
                        if (value === 'medical_offer') return '<span class="label label-info">Médico</span>';
                        if (value === 'complementary_service') return '<span class="label label-warning">Complementario</span>';
                        return '<span class="label label-default">' + escapeHtml(value || '') + '</span>';
                    }
                },
                { data: 'item_name' },
                {
                    data: 'item_status',
                    render: function (value) {
                        return renderStatusBadge(value);
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function (row) {
                        var html = '<button class="btn btn-xs btn-primary btn-view" data-id="' + row.item_id + '"><i class="fa fa-eye"></i> Ver</button>';
                        if (row.item_status === 'pending_provider') {
                            html += ' <button class="btn btn-xs btn-success btn-provider-confirm" data-id="' + row.item_id + '"><i class="fa fa-check"></i> Confirmar</button>';
                            html += ' <button class="btn btn-xs btn-danger btn-provider-reject" data-id="' + row.item_id + '"><i class="fa fa-times"></i> Rechazar</button>';
                            html += ' <button class="btn btn-xs btn-warning btn-provider-propose" data-id="' + row.item_id + '" data-currency="' + escapeHtml(row.item_currency || 'USD') + '"><i class="fa fa-edit"></i> Proponer</button>';
                        }
                        return html;
                    }
                }
            ],
            order: [[1, 'desc']],
            pageLength: 25,
            responsive: true
        });
    }

    function bindEvents() {
        $('#btn-reload-my-bookings').on('click', function () {
            loadRows();
        });

        $('#my_booking_requests_table').on('click', '.btn-view', function () {
            var itemId = parseInt($(this).data('id'), 10) || 0;
            if (itemId > 0) {
                loadDetail(itemId);
            }
        });

        $('#my_booking_requests_table').on('click', '.btn-provider-confirm', function () {
            var itemId = parseInt($(this).data('id'), 10) || 0;
            if (itemId <= 0) return;
            if (!confirm('¿Deseas confirmar disponibilidad para este item?')) {
                return;
            }
            sendProviderAction('provider_confirm', { item_id: itemId });
        });

        $('#my_booking_requests_table').on('click', '.btn-provider-reject', function () {
            var itemId = parseInt($(this).data('id'), 10) || 0;
            if (itemId <= 0) return;
            $('#provider_reject_item_id').val(itemId);
            $('#provider_reject_reason').val('');
            $('#provider_reject_modal').modal('show');
        });

        $('#my_booking_requests_table').on('click', '.btn-provider-propose', function () {
            var itemId = parseInt($(this).data('id'), 10) || 0;
            if (itemId <= 0) return;
            var currency = ($(this).data('currency') || 'USD').toString().toUpperCase();
            if (currency !== 'USD' && currency !== 'COP') {
                currency = 'USD';
            }

            $('#provider_propose_item_id').val(itemId);
            $('#provider_proposed_date_from').val('');
            $('#provider_proposed_date_to').val('');
            $('#provider_proposed_price').val('');
            $('#provider_proposed_currency').val(currency);
            $('#provider_proposed_notes').val('');
            $('#provider_propose_modal').modal('show');
        });

        $('#btn-provider-reject-save').on('click', function () {
            var itemId = parseInt($('#provider_reject_item_id').val(), 10) || 0;
            var reason = ($('#provider_reject_reason').val() || '').trim();
            if (itemId <= 0) return;
            if (!reason) {
                toastr.error('Debes ingresar un motivo de rechazo');
                return;
            }
            sendProviderAction('provider_reject', {
                item_id: itemId,
                reason: reason
            }, function () {
                $('#provider_reject_modal').modal('hide');
            });
        });

        $('#btn-provider-propose-save').on('click', function () {
            var itemId = parseInt($('#provider_propose_item_id').val(), 10) || 0;
            var notes = ($('#provider_proposed_notes').val() || '').trim();
            if (itemId <= 0) return;
            if (!notes) {
                toastr.error('Debes ingresar notas para proponer un cambio');
                return;
            }

            sendProviderAction('provider_propose_change', {
                item_id: itemId,
                proposed_date_from: ($('#provider_proposed_date_from').val() || '').trim(),
                proposed_date_to: ($('#provider_proposed_date_to').val() || '').trim(),
                proposed_price: ($('#provider_proposed_price').val() || '').trim(),
                proposed_currency: ($('#provider_proposed_currency').val() || 'USD').trim(),
                provider_notes: notes
            }, function () {
                $('#provider_propose_modal').modal('hide');
            });
        });
    }

    function loadRows() {
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: { action: 'list' },
            success: function (response) {
                if (!response || !response.ok) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo cargar la información');
                    table.clear().draw();
                    return;
                }
                table.clear();
                table.rows.add(response.data || []);
                table.draw();
            },
            error: function () {
                toastr.error('Error de conexión al cargar solicitudes');
            }
        });
    }

    function loadDetail(itemId) {
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: { action: 'get_detail', item_id: itemId },
            success: function (response) {
                if (!response || !response.ok || !response.data) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo cargar el detalle');
                    return;
                }

                var d = response.data;
                var notes = buildRequestNotes(d);
                var timeline = buildTimeline(d);
                var responseMeta = '';

                if (d.provider_response_at) {
                    responseMeta += '<p><strong>Respondido:</strong> ' + escapeHtml(d.provider_response_at) + '</p>';
                }
                if (d.provider_reject_reason) {
                    responseMeta += '<p><strong>Motivo rechazo:</strong><br>' + nl2brSafe(d.provider_reject_reason) + '</p>';
                }
                if (d.provider_notes) {
                    responseMeta += '<p><strong>Notas proveedor:</strong><br>' + nl2brSafe(d.provider_notes) + '</p>';
                }
                if (d.provider_proposed_date_from || d.provider_proposed_date_to) {
                    responseMeta += '<p><strong>Fechas propuestas:</strong> ' + escapeHtml((d.provider_proposed_date_from || '-') + ' - ' + (d.provider_proposed_date_to || '-')) + '</p>';
                }
                if (d.provider_proposed_price) {
                    responseMeta += '<p><strong>Precio propuesto:</strong> ' + escapeHtml((d.provider_proposed_currency || 'USD') + ' ' + d.provider_proposed_price) + '</p>';
                }

                var html = '' +
                    '<p><strong>Booking:</strong> #' + escapeHtml(d.booking_request_id || '') + '</p>' +
                    '<p><strong>Servicio:</strong> ' + escapeHtml(d.item_name || '') + '</p>' +
                    '<p><strong>Estado:</strong> ' + renderStatusBadge(d.item_status || '') + '</p>' +
                    '<hr>' +
                    '<p><strong>Destino:</strong> ' + escapeHtml(d.destination || '-') + '</p>' +
                    '<p><strong>Timeline:</strong> ' + escapeHtml(timeline) + '</p>' +
                    '<p><strong>Notas de solicitud:</strong><br>' + nl2brSafe(notes) + '</p>' +
                    (responseMeta ? '<hr>' + responseMeta : '');

                $('#my_booking_detail_content').html(html);
                $('#my_booking_detail_modal').modal('show');
            },
            error: function () {
                toastr.error('Error de conexión al cargar detalle');
            }
        });
    }

    function sendProviderAction(action, payload, onSuccess) {
        payload = payload || {};
        payload.action = action;

        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: payload,
            success: function (response) {
                if (!response || !response.ok) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo guardar la respuesta');
                    return;
                }
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
                toastr.success('Respuesta guardada');
                loadRows();
            },
            error: function () {
                toastr.error('Error de conexión al guardar la respuesta');
            }
        });
    }

    function buildTimeline(row) {
        var from = (row.timeline_from || '').toString().trim();
        var to = (row.timeline_to || '').toString().trim();
        if (from && to) return from + ' - ' + to;
        if (from) return 'Desde ' + from;
        if (to) return 'Hasta ' + to;
        return (row.timeline || 'Sin definir');
    }

    function buildRequestNotes(row) {
        var parts = [];
        var special = (row.special_request || '').toString().trim();
        var additional = (row.additional_notes || '').toString().trim();
        if (special) parts.push(special);
        if (additional) parts.push(additional);
        if (!parts.length) {
            return 'Sin notas';
        }
        return parts.join('\n\n');
    }

    function renderStatusBadge(status) {
        status = (status || '').toString();
        var css = 'label-default';
        if (status === 'pending_provider') css = 'label-warning';
        else if (status === 'provider_confirmed' || status === 'client_accepted') css = 'label-success';
        else if (status === 'provider_rejected' || status === 'client_rejected') css = 'label-danger';
        else if (status === 'provider_proposed_change' || status === 'awaiting_client') css = 'label-info';
        else if (status === 'cancelled') css = 'label-default';
        return '<span class="label ' + css + '">' + escapeHtml(status || 'pending_provider') + '</span>';
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function nl2brSafe(text) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }
})();
