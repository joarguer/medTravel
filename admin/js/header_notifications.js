(function () {
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderSummary(count) {
        var $badge = $('.admin-notif-badge');
        var $summary = $('#admin-notification-summary');
        $badge.text(String(count));
        if ($summary.length) {
            if (count > 0) {
                $summary.html('<span class="bold">' + count + '</span> unread message(s)');
            } else {
                $summary.text('No unread messages');
            }
        }
    }

    function renderItems(items) {
        var $list = $('#admin-notification-list');
        if (!$list.length) return;

        if (!items || !items.length) {
            $list.html(
                '<li><a href="app_inbox.php"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No unread messages</span></a></li>'
            );
            return;
        }

        var html = '';
        items.forEach(function (item) {
            var label = esc(item.label || 'Inbox update');
            var preview = esc(item.preview || '');
            var time = esc(item.created_at || '');
            var url = esc(item.url || 'app_inbox.php');
            var unread = parseInt(item.unread_count || 0, 10);
            var details = label +
                (preview ? '<br><small>' + preview + '</small>' : '') +
                (unread > 0 ? '<br><small><strong>' + unread + ' unread</strong></small>' : '');
            html += '<li><a href="' + url + '"><span class="details"><span class="label label-sm label-icon label-info md-skip"><i class="fa fa-envelope"></i></span> ' + details + '</span>' +
                (time ? '<span class="time">' + time + '</span>' : '') +
                '</a></li>';
        });
        $list.html(html);
    }

    function loadNotifications() {
        if (!$('#admin-notification-list').length) {
            return;
        }
        $.ajax({
            url: '/admin/ajax/get_notifications.php',
            method: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                return;
            }
            renderSummary(parseInt(res.count || 0, 10));
            renderItems(res.items || []);
        });
    }

    $(function () {
        window.adminReloadNotifications = loadNotifications;
        loadNotifications();
        setInterval(loadNotifications, 60000);
    });
})();
