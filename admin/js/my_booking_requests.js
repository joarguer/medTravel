(function () {
    var table = null;
    var activeDetailItemId = 0;

    $(document).ready(function () {
        initTable();
        bindEvents();
        loadRows();
    });

    function initTable() {
        var $table = $('#my_booking_requests_table');
        if (!$table.length) {
            return;
        }

        var expectedHeaders = [
            'Booking',
            'Fecha',
            'Destino / Timeline',
            'Tipo',
            'Servicio',
            'Estado',
            'Acciones'
        ];
        var $thead = $table.find('thead');
        var $headerRow = $thead.find('tr');
        if (!$headerRow.length || $headerRow.find('th').length !== expectedHeaders.length) {
            $table.find('thead').remove();
            var headHtml = '<thead><tr>';
            for (var i = 0; i < expectedHeaders.length; i++) {
                headHtml += '<th>' + expectedHeaders[i] + '</th>';
            }
            headHtml += '</tr></thead>';
            $table.prepend(headHtml);
        }

        if ($.fn.dataTable.isDataTable($table)) {
            $table.DataTable().clear().destroy();
        }

        table = $table.DataTable({
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
            responsive: true,
            autoWidth: false
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

        $('#my_booking_detail_modal').on('click', '#btn-provider-send-message', function () {
            if (!activeDetailItemId) {
                return;
            }
            var text = ($('#provider-message-text').val() || '').trim();
            if (!text) {
                toastr.warning('Escribe un mensaje antes de enviar');
                return;
            }
            sendProviderMessage(activeDetailItemId, text);
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

                var d = response.data || {};
                var itemsHistory = response.items_history || [];
                activeDetailItemId = itemId;

                var html = '' +
                    '<div class="row">' +
                        '<div class="col-md-12">' +
                            '<h4 style="margin-top:0;">Solicitud #' + escapeHtml(d.booking_request_id || '') + '</h4>' +
                        '</div>' +
                    '</div>' +
                    '<div class="row">' +
                        '<div class="col-md-6">' +
                            '<p><strong>Creado:</strong> ' + escapeHtml(d.booking_created_at || '-') + '</p>' +
                            '<p><strong>Estado booking:</strong> ' + renderStatusBadge(d.booking_status || 'pending') + '</p>' +
                            '<p><strong>Categoría:</strong> ' + escapeHtml(d.category || '-') + '</p>' +
                            '<p><strong>Service categories:</strong> ' + escapeHtml(d.service_categories || '-') + '</p>' +
                            '<p><strong>Medical services:</strong> ' + escapeHtml(d.medical_services || '-') + '</p>' +
                            '<p><strong>Budget:</strong> ' + escapeHtml(d.budget || '-') + '</p>' +
                            '<p><strong>Selected offers:</strong> ' + escapeHtml(d.selected_offers || '-') + '</p>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<p><strong>Origin:</strong> ' + escapeHtml(d.origin || '-') + '</p>' +
                            '<p><strong>Destination:</strong> ' + escapeHtml(d.destination || '-') + '</p>' +
                            '<p><strong>Timeline:</strong> ' + escapeHtml(buildTimeline(d)) + '</p>' +
                            '<p><strong>Persons:</strong> ' + escapeHtml(d.persons || '-') + '</p>' +
                            '<p><strong>Booking datetime:</strong> ' + escapeHtml(d.booking_datetime || '-') + '</p>' +
                            '<p><strong>Special request:</strong><br>' + nl2brSafe(d.special_request || '-') + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<hr>' +
                    '<div class="row">' +
                        '<div class="col-md-12">' +
                            '<h5>Datos del cliente</h5>' +
                            '<p><strong>Name:</strong> ' + escapeHtml(d.client_name || '-') + '</p>' +
                            '<p><strong>Email:</strong> ' + escapeHtml(d.client_email || '-') + '</p>' +
                            '<p><strong>Phone:</strong> ' + escapeHtml(d.client_phone || '-') + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<hr>' +
                    '<div class="row">' +
                        '<div class="col-md-12">' +
                            '<h5>Timeline de items (scope proveedor)</h5>' +
                            '<div class="table-responsive">' +
                                '<table class="table table-striped table-bordered">' +
                                    '<thead>' +
                                        '<tr>' +
                                            '<th>Item</th>' +
                                            '<th>Tipo</th>' +
                                            '<th>Estado</th>' +
                                            '<th>Notas / Respuesta</th>' +
                                            '<th>Fechas / Precio propuesto</th>' +
                                            '<th>Timestamps</th>' +
                                        '</tr>' +
                                    '</thead>' +
                                    '<tbody id="provider-item-history-body">' + renderItemsHistory(itemsHistory) + '</tbody>' +
                                '</table>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<hr>' +
                    '<div class="row">' +
                        '<div class="col-md-12">' +
                            '<h5>Conversación</h5>' +
                            '<div id="provider-conversation-log" style="max-height:260px; overflow:auto; border:1px solid #e5e5e5; padding:10px; background:#fafafa;">Cargando mensajes...</div>' +
                            '<div class="form-group" style="margin-top:12px;">' +
                                '<label for="provider-message-text">Enviar mensaje al cliente</label>' +
                                '<textarea id="provider-message-text" class="form-control" rows="3" maxlength="2000" placeholder="Escribe tu mensaje..."></textarea>' +
                            '</div>' +
                            '<button type="button" id="btn-provider-send-message" class="btn btn-primary btn-sm"><i class="fa fa-paper-plane"></i> Send message</button>' +
                        '</div>' +
                    '</div>';

                $('#my_booking_detail_content').html(html);
                $('#my_booking_detail_modal').modal('show');
                loadMessages(itemId);
            },
            error: function () {
                toastr.error('Error de conexión al cargar detalle');
            }
        });
    }

    function renderItemsHistory(items) {
        if (!items || !items.length) {
            return '<tr><td colspan="6">No hay items asociados para este proveedor.</td></tr>';
        }
        var html = '';
        items.forEach(function (item) {
            var typeLabel = item.item_type === 'medical_offer' ? 'Médico' : (item.item_type === 'complementary_service' ? 'Complementario' : (item.item_type || '-'));
            var notesBlock = [];
            if (item.provider_notes) notesBlock.push('<strong>Notas:</strong> ' + nl2brSafe(item.provider_notes));
            if (item.provider_reject_reason) notesBlock.push('<strong>Rechazo:</strong> ' + nl2brSafe(item.provider_reject_reason));
            if (!notesBlock.length) notesBlock.push('-');

            var proposalBlock = [];
            if (item.provider_proposed_date_from || item.provider_proposed_date_to) {
                proposalBlock.push('Fechas: ' + escapeHtml((item.provider_proposed_date_from || '-') + ' - ' + (item.provider_proposed_date_to || '-')));
            }
            if (item.provider_proposed_price) {
                proposalBlock.push('Precio: ' + escapeHtml((item.provider_proposed_currency || item.item_currency || 'USD') + ' ' + item.provider_proposed_price));
            }
            if (!proposalBlock.length) proposalBlock.push('-');

            var timeBlock = [];
            if (item.item_created_at) timeBlock.push('created: ' + escapeHtml(item.item_created_at));
            if (item.item_updated_at) timeBlock.push('updated: ' + escapeHtml(item.item_updated_at));
            if (item.provider_response_at) timeBlock.push('response: ' + escapeHtml(item.provider_response_at));
            if (!timeBlock.length) timeBlock.push('-');

            html += '<tr>' +
                '<td>' + escapeHtml(item.item_name || '') + '</td>' +
                '<td>' + escapeHtml(typeLabel) + '</td>' +
                '<td>' + renderStatusBadge(item.item_status || '') + '</td>' +
                '<td>' + notesBlock.join('<br>') + '</td>' +
                '<td>' + proposalBlock.join('<br>') + '</td>' +
                '<td>' + timeBlock.join('<br>') + '</td>' +
                '</tr>';
        });
        return html;
    }

    function loadMessages(itemId) {
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: { action: 'list_messages', item_id: itemId },
            success: function (response) {
                if (!response || !response.ok) {
                    $('#provider-conversation-log').html('<p>No se pudo cargar conversación.</p>');
                    return;
                }
                renderConversation(response.messages || []);
            },
            error: function () {
                $('#provider-conversation-log').html('<p>Error de conexión al cargar conversación.</p>');
            }
        });
    }

    function renderConversation(messages) {
        var $log = $('#provider-conversation-log');
        if (!$log.length) return;
        if (!messages || !messages.length) {
            $log.html('<p>Sin mensajes todavía.</p>');
            return;
        }

        var html = '';
        messages.forEach(function (m) {
            var sender = (m.sender || 'system').toString();
            var senderClass = sender === 'client' ? 'label-info' : (sender === 'provider' ? 'label-success' : 'label-default');
            var actor = (m.actor || '').toString().trim();
            html += '<div class="well well-sm" style="margin-bottom:8px;">' +
                '<div><span class="label ' + senderClass + '">' + escapeHtml(sender) + '</span>' +
                (actor ? ' <small>[' + escapeHtml(actor) + ']</small>' : '') +
                (m.time ? ' <small style="margin-left:6px;">' + escapeHtml(m.time) + '</small>' : '') +
                '</div>' +
                '<div style="margin-top:6px; white-space:pre-wrap;">' + escapeHtml(m.body || '') + '</div>' +
                '</div>';
        });
        $log.html(html);
        $log.scrollTop($log[0].scrollHeight);
    }

    function sendProviderMessage(itemId, text) {
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_message',
                item_id: itemId,
                message: text
            },
            success: function (response) {
                if (!response || !response.ok) {
                    toastr.error((response && response.message) ? response.message : 'No se pudo enviar mensaje');
                    return;
                }
                $('#provider-message-text').val('');
                toastr.success('Mensaje enviado');
                loadMessages(itemId);
            },
            error: function () {
                toastr.error('Error de conexión al enviar mensaje');
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
