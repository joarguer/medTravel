(function () {
    var table = null;

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

    function initTable() {
        var $table = $('#client_requests_table');
        if (!$table.length) {
            return;
        }

        if ($.fn.dataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        table = $table.DataTable({
            ajax: {
                url: '/client/ajax/list_requests.php',
                type: 'GET',
                dataSrc: function (res) {
                    return (res && res.ok && Array.isArray(res.data)) ? res.data : [];
                }
            },
            columns: [
                { data: 'id', render: function (v) { return '#' + parseInt(v || 0, 10); } },
                { data: 'created_at', defaultContent: '' },
                { data: 'service', defaultContent: '' },
                { data: 'status', render: function (v) { return statusBadge(v); } },
                { data: 'last_update', defaultContent: '' },
                {
                    data: 'view_url',
                    orderable: false,
                    searchable: false,
                    render: function (v) {
                        return '<a class="btn btn-xs btn-primary" href="' + esc(v || '#') + '">View</a>';
                    }
                }
            ],
            order: [[1, 'desc']],
            pageLength: 25,
            responsive: true,
            autoWidth: false
        });
    }

    $(function () {
        initTable();
        $('#btn-client-requests-reload').on('click', function () {
            if (table) {
                table.ajax.reload(null, false);
            }
        });
    });
})();

