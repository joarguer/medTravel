(function () {
    var realtimeState = {
        socket: null,
        joining: false,
        joined: false
    };

    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function notifDebug(message) {
        if (window.MT_DEBUG_NOTIF === true) {
            console.log('[notif]', message);
        }
    }

    function realtimeConfig() {
        return window.MT_REALTIME || {};
    }

    function realtimeEnabled() {
        var cfg = realtimeConfig();
        return !!(cfg.baseUrl && cfg.socketPath && typeof window.io === 'function');
    }

    function renderSummary(totalCount, unreadCount, pendingCount) {
        var badge = document.querySelector('.admin-notif-badge');
        var $summary = $('#admin-notification-summary');
        var unread = parseInt(unreadCount || 0, 10);
        if (!isFinite(unread) || unread < 0) {
            unread = 0;
        }
        if (badge) {
            if (unread > 0) {
                badge.textContent = String(unread);
                badge.style.display = 'inline-block';
            } else {
                badge.textContent = '';
                badge.style.display = 'none';
            }
        }
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
        if (!document.querySelector('#admin-notification-list')) {
            return;
        }
        notifDebug('fetch start');
        fetch('/admin/ajax/get_notifications.php', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                notifDebug('response ' + JSON.stringify(data || {}));
                if (!data || data.ok !== true) {
                    return;
                }
                var unreadCount = parseInt(data.unread_count || 0, 10);
                var pendingCount = parseInt(data.pending_services_count || 0, 10);
                var totalCount = parseInt(data.count || (unreadCount + pendingCount), 10);
                notifDebug('unread_count ' + unreadCount);
                renderSummary(totalCount, unreadCount, pendingCount);
                renderItems(data.items || [], data.pending_services || []);
            })
            .catch(function () {
                // noop: keep fallback polling next tick
            });
    }

    function fetchAdminToken(callback) {
        var cfg = realtimeConfig();
        if (!cfg.adminTokenUrl) {
            callback('');
            return;
        }
        $.ajax({
            url: cfg.adminTokenUrl,
            method: 'POST',
            dataType: 'json'
        }).done(function (res) {
            if (res && res.ok === true && res.token) {
                callback(res.token);
                return;
            }
            callback('');
        }).fail(function () {
            callback('');
        });
    }

    function joinAdminRoom() {
        if (!realtimeEnabled() || !realtimeState.socket || !realtimeState.socket.connected) {
            return;
        }
        if (realtimeState.joining) {
            return;
        }
        realtimeState.joining = true;
        var joinTimeout = setTimeout(function () {
            realtimeState.joining = false;
        }, 3000);
        fetchAdminToken(function (token) {
            if (!token) {
                clearTimeout(joinTimeout);
                realtimeState.joining = false;
                return;
            }
            realtimeState.socket.emit('join_admin', { token: token }, function (ack) {
                clearTimeout(joinTimeout);
                realtimeState.joining = false;
                if (ack && ack.ok && ack.joined) {
                    realtimeState.joined = true;
                    notifDebug('joined admin room ' + (ack.room || ''));
                } else {
                    realtimeState.joined = false;
                }
            });
        });
    }

    function initRealtime() {
        if (!realtimeEnabled() || realtimeState.socket) {
            return;
        }
        var cfg = realtimeConfig();
        realtimeState.socket = window.io(String(cfg.baseUrl || ''), {
            path: String(cfg.socketPath || ''),
            transports: ['websocket', 'polling']
        });

        realtimeState.socket.on('connect', function () {
            notifDebug('socket connected');
            joinAdminRoom();
        });

        realtimeState.socket.on('disconnect', function () {
            realtimeState.joined = false;
        });

        realtimeState.socket.on('admin.unread_changed', function () {
            if (typeof window.adminReloadNotificationsDebounced === 'function') {
                window.adminReloadNotificationsDebounced();
            } else if (typeof window.adminReloadNotifications === 'function') {
                window.adminReloadNotifications();
            } else {
                loadNotifications();
            }
        });

        realtimeState.socket.on('auth_error', function () {
            notifDebug('auth_error');
        });
    }

    function createDebouncedRefresh(fn, waitMs) {
        var timer = null;
        var lastRun = 0;
        var wait = parseInt(waitMs || 1500, 10);
        if (!isFinite(wait) || wait < 250) {
            wait = 1500;
        }
        return function () {
            var now = Date.now();
            var elapsed = now - lastRun;
            if (elapsed >= wait) {
                lastRun = now;
                fn();
                return;
            }
            if (timer) {
                return;
            }
            timer = setTimeout(function () {
                timer = null;
                lastRun = Date.now();
                fn();
            }, wait - elapsed);
        };
    }

    $(function () {
        window.adminReloadNotifications = loadNotifications;
        window.adminReloadNotificationsDebounced = createDebouncedRefresh(loadNotifications, 1500);
        window.adminReloadNotificationsNow = function () {
            loadNotifications();
        };
        loadNotifications();
        setInterval(loadNotifications, 60000);
        initRealtime();
    });
})();
