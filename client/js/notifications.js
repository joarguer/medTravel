(function () {
    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderNotifications(items) {
        var $list = $('#client-notification-list');
        if (!$list.length) {
            return;
        }
        if (!items || !items.length) {
            $list.html(
                '<li><a href="/client/app_inbox.php"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No unread messages</span></a></li>'
            );
            return;
        }

        var html = '';
        items.forEach(function (item) {
            var label = escapeHtml(item.label || 'Inbox update');
            var preview = escapeHtml(item.preview || '');
            var time = escapeHtml(item.created_at || '');
            var url = escapeHtml(item.url || '/client/app_inbox.php');
            var unread = parseInt(item.unread_count || 0, 10);
            var details = label +
                (preview ? '<br><small>' + preview + '</small>' : '') +
                (unread > 0 ? '<br><small><strong>' + unread + ' unread</strong></small>' : '');
            html += '<li><a href="' + url + '"><span class="details"><span class="label label-sm label-icon label-info md-skip"><i class="fa fa-bell"></i></span> ' + details + '</span>' +
                (time ? '<span class="time">' + time + '</span>' : '') +
                '</a></li>';
        });
        $list.html(html);
    }

    function renderSummary(count) {
        var $badge = $('.client-notif-badge');
        var $summary = $('#client-notification-summary');
        $badge.text(String(count));
        if ($summary.length) {
            if (count > 0) {
                $summary.html('<span class="bold">' + count + '</span> unread message(s)');
            } else {
                $summary.text('No unread messages');
            }
        }
    }

    function loadNotifications() {
        $.ajax({
            url: '/client/ajax/get_notifications.php',
            method: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                return;
            }
            renderSummary(parseInt(res.count || 0, 10));
            renderNotifications(res.items || []);
        });
    }

    $(function () {
        window.clientReloadNotifications = loadNotifications;
        loadNotifications();
        setInterval(loadNotifications, 60000);
    });
})();
