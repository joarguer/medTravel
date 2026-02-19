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

    function loadDashboard() {
        $.ajax({
            url: '/client/ajax/list_requests.php',
            method: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                return;
            }
            var rows = res.data || [];
            $('#client-dashboard-total').text(rows.length);

            var html = '';
            rows.slice(0, 5).forEach(function (row) {
                html += '<tr>' +
                    '<td>#' + parseInt(row.id || 0, 10) + '</td>' +
                    '<td>' + esc(row.created_at || '') + '</td>' +
                    '<td>' + esc(row.service || '') + '</td>' +
                    '<td>' + statusBadge(row.status || '') + '</td>' +
                    '<td>' + esc(row.last_update || '') + '</td>' +
                    '<td><a class="btn btn-xs btn-primary" href="' + esc(row.view_url || '#') + '">View</a></td>' +
                    '</tr>';
            });
            if (!html) {
                html = '<tr><td colspan="6">No requests yet.</td></tr>';
            }
            $('#client-dashboard-requests-table tbody').html(html);
        });

        $.ajax({
            url: '/client/ajax/get_notifications.php',
            method: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                return;
            }
            $('#client-dashboard-notifications').text(parseInt(res.count || 0, 10));
        });
    }

    $(function () {
        loadDashboard();
    });
})();

