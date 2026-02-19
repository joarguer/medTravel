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
                '<li><a href="/client/my_requests.php"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No tienes notificaciones</span></a></li>'
            );
            return;
        }

        var html = '';
        items.forEach(function (item) {
            var title = escapeHtml(item.title || 'Notification');
            var subtitle = escapeHtml(item.subtitle || '');
            var time = escapeHtml(item.time || '');
            var url = escapeHtml(item.url || '/client/my_requests.php');
            var details = title + (subtitle ? '<br><small>' + subtitle + '</small>' : '');
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
                $summary.html('<span class="bold">' + count + '</span> notification(s)');
            } else {
                $summary.text('No notifications');
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
        loadNotifications();
        setInterval(loadNotifications, 60000);
    });
})();

