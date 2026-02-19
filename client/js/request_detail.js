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

    function buildItemInboxLinks(items, bookingId) {
        if (!items || !items.length) {
            return '<p class="text-muted" style="margin:0;">No item-specific threads available.</p>';
        }
        var html = '<div class="list-group">';
        items.forEach(function (item) {
            var itemId = parseInt(item.id || 0, 10);
            if (!itemId) {
                return;
            }
            var typeLabel = String(item.item_type_label || '');
            var itemName = String(item.name || ('Item #' + itemId));
            var label = 'Message ' + (typeLabel ? (typeLabel + ' Provider') : 'Provider');
            var url = '/client/app_inbox.php?request_id=' + encodeURIComponent(String(bookingId || 0)) +
                '&thread_type=ITEM&item_id=' + encodeURIComponent(String(itemId));
            html += '<a class="list-group-item" href="' + esc(url) + '">' +
                '<strong>' + esc(label) + '</strong>' +
                '<br><small>' + esc(itemName) + '</small>' +
                '</a>';
        });
        html += '</div>';
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

        var allItems = [];
        allItems = allItems.concat(medical || []);
        allItems = allItems.concat(complementary || []);
        var $linksBox = $('#client-inbox-item-links');
        if ($linksBox.length) {
            $linksBox.html(buildItemInboxLinks(allItems, booking.id || 0));
        }
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

    $(function () {
        var bookingId = parseInt((new URLSearchParams(window.location.search)).get('id') || '0', 10);
        if (!bookingId) return;
        loadDetail(bookingId);
    });
})();
