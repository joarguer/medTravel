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

    function termsDisplay(booking) {
        var accepted = parseInt(booking.terms_accepted || 0, 10) === 1;
        if (!accepted) {
            return 'Not recorded';
        }
        var version = booking.terms_version ? (' (' + esc(booking.terms_version) + ')') : '';
        var date = booking.terms_accepted_at ? (' on ' + esc(booking.terms_accepted_at)) : '';
        return 'Yes' + version + date;
    }

    function firstItemId(items) {
        if (!items || !items.length) {
            return 0;
        }
        for (var i = 0; i < items.length; i++) {
            var itemId = parseInt(items[i] && items[i].id ? items[i].id : 0, 10);
            if (itemId > 0) {
                return itemId;
            }
        }
        return 0;
    }

    function updateCommunicationLinks(medical, complementary, bookingId) {
        var requestId = parseInt(bookingId || 0, 10);
        if (requestId <= 0) {
            return;
        }

        var medicalItemId = firstItemId(medical);
        var complementaryItemId = firstItemId(complementary);

        var careUrl = '/client/app_inbox.php?request_id=' + encodeURIComponent(String(requestId)) + '&thread_type=CARE';
        var medicalUrl = medicalItemId > 0
            ? '/client/app_inbox.php?request_id=' + encodeURIComponent(String(requestId)) + '&thread_type=ITEM&item_id=' + encodeURIComponent(String(medicalItemId))
            : careUrl;
        var complementaryUrl = complementaryItemId > 0
            ? '/client/app_inbox.php?request_id=' + encodeURIComponent(String(requestId)) + '&thread_type=ITEM&item_id=' + encodeURIComponent(String(complementaryItemId))
            : careUrl;

        var $medicalBtn = $('#client-open-inbox-medical');
        if ($medicalBtn.length) {
            $medicalBtn.attr('href', medicalUrl);
        }
        var $complementaryBtn = $('#client-open-inbox-complementary');
        if ($complementaryBtn.length) {
            $complementaryBtn.attr('href', complementaryUrl);
        }

        var $linksBox = $('#client-inbox-item-links');
        if ($linksBox.length) {
            var info = [];
            if (medicalItemId > 0) {
                info.push('Medical item #' + medicalItemId);
            }
            if (complementaryItemId > 0) {
                info.push('Complementary item #' + complementaryItemId);
            }
            var text = info.length
                ? ('Buttons route to: ' + info.join(' · '))
                : 'No item-specific thread found yet. Buttons will open general inbox.';
            $linksBox.find('p.text-muted').text(text);
        }
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
            '</div>' +
            '<div class="row">' +
            '<div class="col-md-6"><p><strong>Terms accepted:</strong> ' + termsDisplay(booking) + '</p></div>' +
            '<div class="col-md-6"></div>' +
            '</div>';

        if (booking.special_request) {
            html += '<p><strong>Special request:</strong> ' + esc(booking.special_request) + '</p>';
        }

        html += '<h4>Medical services</h4>' + renderItemsTable(medical);
        html += '<h4>Complementary services</h4>' + renderItemsTable(complementary);

        $('#client-request-detail-body').html(html);

        updateCommunicationLinks(medical, complementary, booking.id || 0);
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
