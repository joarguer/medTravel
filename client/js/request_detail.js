(function () {
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function statusBadge(status) {
        var s = String(status || 'pending_provider');
        var cls = 'default';
        if (s === 'provider_confirmed' || s === 'client_accepted') cls = 'success';
        else if (s === 'provider_rejected' || s === 'client_rejected' || s === 'cancelled') cls = 'danger';
        else if (s === 'awaiting_client' || s === 'provider_proposed_change') cls = 'warning';
        else if (s === 'pending_provider') cls = 'info';
        return '<span class="label label-' + cls + '">' + esc(s) + '</span>';
    }

    function renderItemsTable(items) {
        if (!items || !items.length) {
            return '<p>No itemized services found.</p>';
        }
        var html = '<div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr>' +
            '<th>Type</th><th>Service</th><th>Provider</th><th>Status</th><th>Price</th></tr></thead><tbody>';
        items.forEach(function (item) {
            html += '<tr>' +
                '<td>' + esc(item.item_type_label || '') + '</td>' +
                '<td>' + esc(item.name || '') + '</td>' +
                '<td>' + esc(item.provider || '') + '</td>' +
                '<td>' + statusBadge(item.item_status || '') + '</td>' +
                '<td>' + esc(item.price_display || 'On request') + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function renderDetail(payload) {
        var booking = payload.booking || {};
        var items = payload.items || {};
        var medical = items.medical || [];
        var complementary = items.complementary || [];
        var totalDisplay = (items.totals && items.totals.display) ? items.totals.display : 'On request';

        var html = '<div class="row">' +
            '<div class="col-md-6"><p><strong>ID:</strong> #' + parseInt(booking.id || 0, 10) + '</p></div>' +
            '<div class="col-md-6"><p><strong>Date:</strong> ' + esc(booking.created_at || '') + '</p></div>' +
            '</div>' +
            '<div class="row">' +
            '<div class="col-md-6"><p><strong>Destination:</strong> ' + esc(booking.destination || '') + '</p></div>' +
            '<div class="col-md-6"><p><strong>Timeline:</strong> ' + esc(booking.timeline || '') + '</p></div>' +
            '</div>' +
            '<div class="row">' +
            '<div class="col-md-6"><p><strong>Status:</strong> ' + statusBadge(booking.status || '') + '</p></div>' +
            '<div class="col-md-6"><p><strong>Total estimated:</strong> ' + esc(totalDisplay) + '</p></div>' +
            '</div>';

        if (booking.special_request) {
            html += '<p><strong>Special request:</strong> ' + esc(booking.special_request) + '</p>';
        }

        html += '<h4>Medical services</h4>' + renderItemsTable(medical);
        html += '<h4>Complementary services</h4>' + renderItemsTable(complementary);

        $('#client-request-detail-body').html(html);
    }

    function renderMessages(messages) {
        var $box = $('#client-request-messages');
        if (!$box.length) return;
        if (!messages || !messages.length) {
            $box.html('<p>No messages yet.</p>');
            return;
        }

        var html = '';
        messages.forEach(function (m) {
            var sender = esc(m.sender || 'system');
            var body = esc(m.body || '');
            var time = esc(m.time || '');
            var labelCls = sender === 'client' ? 'info' : (sender === 'provider' ? 'success' : 'default');
            html += '<div class="well well-sm" style="margin-bottom:10px;">' +
                '<div><span class="label label-' + labelCls + '">' + sender + '</span>' +
                (time ? '<small style="margin-left:8px;">' + time + '</small>' : '') +
                '</div>' +
                '<div style="margin-top:6px;white-space:pre-wrap;">' + body + '</div>' +
                '</div>';
        });
        $box.html(html);
        $box.scrollTop($box[0].scrollHeight);
    }

    function loadDetail(bookingId) {
        $.ajax({
            url: '/client/ajax/get_request_detail.php',
            method: 'GET',
            data: { id: bookingId },
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error('Could not load request detail');
                return;
            }
            renderDetail(res);
        }).fail(function () {
            toastr.error('Could not load request detail');
        });
    }

    function loadMessages(bookingId) {
        $.ajax({
            url: '/client/ajax/list_messages.php',
            method: 'GET',
            data: { booking_id: bookingId },
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                return;
            }
            renderMessages(res.messages || []);
        });
    }

    function bindSendMessage(bookingId) {
        $('#client-send-message-form').on('submit', function (e) {
            e.preventDefault();
            var text = $.trim($('#client-message-text').val() || '');
            if (!text) {
                toastr.warning('Please write a message');
                return;
            }

            $.ajax({
                url: '/client/ajax/send_message.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    booking_id: bookingId,
                    message: text
                }
            }).done(function (res) {
                if (!res || res.ok !== true) {
                    toastr.error((res && res.message) ? res.message : 'Could not send message');
                    return;
                }
                $('#client-message-text').val('');
                toastr.success('Message sent');
                loadMessages(bookingId);
            }).fail(function () {
                toastr.error('Could not send message');
            });
        });
    }

    $(function () {
        var bookingId = parseInt($('#client-booking-id').val() || '0', 10);
        if (!bookingId) return;
        loadDetail(bookingId);
        loadMessages(bookingId);
        bindSendMessage(bookingId);
    });
})();

