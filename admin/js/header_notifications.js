(function () {
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderSummary(totalCount, unreadCount, pendingCount) {
        var $badge = $('.admin-notif-badge');
        var $summary = $('#admin-notification-summary');
        $badge.text(String(totalCount));
        if ($summary.length) {
            if (totalCount > 0) {
                var parts = [];
                if (unreadCount > 0) {
                    parts.push(unreadCount + ' unread message(s)');
                }
                if (pendingCount > 0) {
                    parts.push(pendingCount + ' pending service(s)');
                }
                $summary.html('<span class="bold">' + totalCount + '</span> notification(s)' + (parts.length ? ' · ' + esc(parts.join(' · ')) : ''));
            } else {
                $summary.text('No notifications');
            }
        }
    }

    function renderItems(items, pendingServices) {
        var $list = $('#admin-notification-list');
        if (!$list.length) return;

        var inboxItems = items || [];
        var pendingItems = pendingServices || [];

        if (!inboxItems.length && !pendingItems.length) {
            $list.html(
                '<li><a href="app_inbox.php"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No notifications</span></a></li>'
            );
            return;
        }

        var html = '';
        if (pendingItems.length) {
            html += '<li><span class="details"><strong>Pending services</strong></span></li>';
            pendingItems.forEach(function (item) {
                var requestId = parseInt(item.request_id || 0, 10);
                var serviceName = esc(item.service_name || ('Item #' + (item.item_id || '')));
                var destination = esc(item.destination || '');
                var timeline = esc(item.timeline || '');
                var createdAt = esc(item.created_at || '');
                var url = esc(item.url_target || 'my_booking_requests.php');
                var details = 'Request #' + requestId + ' - ' + serviceName;
                if (timeline) {
                    details += '<br><small>Timeline: ' + timeline + '</small>';
                } else if (destination) {
                    details += '<br><small>Destination: ' + destination + '</small>';
                }
                html += '<li><a href="' + url + '"><span class="details"><span class="label label-sm label-icon label-warning md-skip"><i class="fa fa-calendar"></i></span> ' + details + '</span>' +
                    (createdAt ? '<span class="time">' + createdAt + '</span>' : '') +
                    '</a></li>';
            });
        }

        if (inboxItems.length) {
            html += '<li><span class="details"><strong>Inbox unread</strong></span></li>';
        }

        inboxItems.forEach(function (item) {
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
            var unreadCount = parseInt(res.unread_count || 0, 10);
            var pendingCount = parseInt(res.pending_services_count || 0, 10);
            var totalCount = parseInt(res.count || (unreadCount + pendingCount), 10);
            renderSummary(totalCount, unreadCount, pendingCount);
            renderItems(res.items || [], res.pending_services || []);
        });
    }

    $(function () {
        window.adminReloadNotifications = loadNotifications;
        loadNotifications();
        setInterval(loadNotifications, 60000);
    });
})();
