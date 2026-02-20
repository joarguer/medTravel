(function () {
    var currentThread = null;
    var preferredThread = null;
    var feeGateActive = false;
    var quickReplies = {
        DATES_AVAILABLE: 'Dates available',
        DATES_NOT_AVAILABLE: 'Dates not available',
        REQUEST_MEDICAL_HISTORY: 'Please share your medical history.',
        REQUEST_LABS: 'Please share recent lab results.',
        REQUEST_PHOTOS: 'Please share the requested photos.'
    };

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function senderClass(sender) {
        var s = String(sender || 'system').toLowerCase();
        if (s === 'provider') return 'success';
        if (s === 'client') return 'info';
        if (s === 'admin' || s === 'patientcare') return 'warning';
        return 'default';
    }

    function matchesPreferred(thread, preferred) {
        if (!thread || !preferred) return false;
        if (preferred.threadId && String(thread.thread_id || '') === String(preferred.threadId)) {
            return true;
        }
        var tRequest = parseInt(thread.booking_request_id || thread.request_id || 0, 10);
        var tItem = parseInt(thread.item_id || 0, 10);
        var tType = String(thread.thread_type || '').toUpperCase();
        if (preferred.requestId > 0 && tRequest !== preferred.requestId) {
            return false;
        }
        if (preferred.threadType === 'ITEM') {
            return tType === 'ITEM' && preferred.itemId > 0 && tItem === preferred.itemId;
        }
        return tType === 'CARE';
    }

    function renderThreads(threads) {
        var $list = $('#admin-inbox-thread-list');
        if (!$list.length) return;

        if (!threads || !threads.length) {
            $list.html('<li><a href="javascript:;">No threads available</a></li>');
            $('#admin-inbox-content').hide();
            $('#admin-inbox-empty').show();
            currentThread = null;
            return;
        }

        var selectedKey = '';
        if (preferredThread) {
            for (var i = 0; i < threads.length; i++) {
                if (matchesPreferred(threads[i], preferredThread)) {
                    selectedKey = String(threads[i].thread_id || '');
                    break;
                }
            }
        }
        if (!selectedKey && currentThread && currentThread.thread_id) {
            selectedKey = String(currentThread.thread_id);
        }
        if (!selectedKey) {
            selectedKey = String(threads[0].thread_id || '');
        }

        var html = '';
        threads.forEach(function (thread) {
            var threadId = String(thread.thread_id || '');
            var unread = parseInt(thread.unread_count || 0, 10);
            var active = threadId === selectedKey;
            var badge = unread > 0 ? '<span class="badge badge-danger" style="margin-left:6px;">' + unread + '</span>' : '';
            html += '<li class="' + (active ? 'active' : '') + '">' +
                '<a href="javascript:;" class="admin-thread-link"' +
                ' data-thread-id="' + esc(threadId) + '"' +
                ' data-thread-type="' + esc(thread.thread_type) + '"' +
                ' data-booking-id="' + esc(thread.booking_request_id || thread.request_id || 0) + '"' +
                ' data-item-id="' + esc(thread.item_id || 0) + '">' +
                esc(thread.title || 'Thread') + badge +
                (thread.subtitle ? '<small style="display:block;margin-top:4px;opacity:.8;">' + esc(thread.subtitle) + '</small>' : '') +
                '</a>' +
                '</li>';
        });
        $list.html(html);

        var selected = null;
        for (var j = 0; j < threads.length; j++) {
            if (String(threads[j].thread_id || '') === selectedKey) {
                selected = threads[j];
                break;
            }
        }
        if (!selected) {
            selected = threads[0];
        }

        var changed = !currentThread || String(currentThread.thread_id || '') !== String(selected.thread_id || '');
        currentThread = {
            thread_id: String(selected.thread_id || ''),
            thread_type: String(selected.thread_type || 'ITEM'),
            booking_request_id: parseInt(selected.booking_request_id || selected.request_id || 0, 10),
            item_id: parseInt(selected.item_id || 0, 10)
        };
        preferredThread = null;
        if (changed) {
            loadMessages();
        }
    }

    function renderMessages(messages) {
        var $box = $('#admin-inbox-messages');
        if (!$box.length) return;
        if (!messages || !messages.length) {
            $box.html('<p class="text-muted" style="margin:0;">No messages in this thread yet.</p>');
            return;
        }

        var html = '';
        messages.forEach(function (m) {
            html += '<div class="well well-sm" style="margin-bottom:10px;">' +
                '<div><span class="label label-' + senderClass(m.sender) + '">' + esc(m.sender || 'system') + '</span>' +
                (m.time ? '<small style="margin-left:8px;">' + esc(m.time) + '</small>' : '') +
                '</div>' +
                '<div style="margin-top:6px;white-space:pre-wrap;">' + esc(m.body || '') + '</div>' +
                '</div>';
        });
        $box.html(html);
        $box.scrollTop($box[0].scrollHeight);
    }

    function refreshHeaderNotifications() {
        if (typeof window.adminReloadNotifications === 'function') {
            window.adminReloadNotifications();
        }
    }

    function setFeeGateState(enabled) {
        feeGateActive = !!enabled;
        var $alert = $('#admin-inbox-fee-alert');
        var $quick = $('#admin-inbox-quick-replies');
        var $msg = $('#admin-inbox-message');
        var $send = $('#admin-inbox-send-form button[type="submit"]');

        if ($alert.length) {
            if (feeGateActive) {
                $alert.show();
            } else {
                $alert.hide();
            }
        }
        if ($quick.length) {
            if (feeGateActive) {
                $quick.show();
            } else {
                $quick.hide();
            }
        }
        if ($msg.length) {
            $msg.prop('disabled', feeGateActive);
        }
        if ($send.length) {
            $send.prop('disabled', feeGateActive);
        }
    }

    function loadThreads() {
        $.ajax({
            url: 'ajax/inbox.php',
            method: 'GET',
            dataType: 'json',
            data: { action: 'list_threads' }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not load threads');
                return;
            }
            renderThreads(res.threads || []);
            refreshHeaderNotifications();
        }).fail(function () {
            toastr.error('Could not load threads');
        });
    }

    function markCurrentRead() {
        if (!currentThread || !currentThread.thread_id) return;
        $.ajax({
            url: 'ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'mark_read',
                thread_id: currentThread.thread_id
            }
        }).done(function (res) {
            if (!res || res.ok !== true) return;
            refreshHeaderNotifications();
            loadThreads();
        });
    }

    function loadMessages() {
        if (!currentThread || !currentThread.thread_id) return;

        $('#admin-inbox-title').text('Loading...');
        $('#admin-inbox-empty').hide();
        $('#admin-inbox-content').show();

        $.ajax({
            url: 'ajax/inbox.php',
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list_messages',
                thread_id: currentThread.thread_id
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not load messages');
                return;
            }

            setFeeGateState(!!res.fee_locked);

            var title = 'Request #' + currentThread.booking_request_id;
            if (currentThread.thread_type === 'ITEM') {
                title = 'Item #' + currentThread.item_id + ' - Request #' + currentThread.booking_request_id;
            } else {
                title = 'General - Request #' + currentThread.booking_request_id;
            }
            $('#admin-inbox-title').text(title);
            renderMessages(res.messages || []);
            markCurrentRead();
        }).fail(function () {
            toastr.error('Could not load messages');
        });
    }

    function sendMessage() {
        var text = $.trim($('#admin-inbox-message').val() || '');
        if (!currentThread || !currentThread.thread_id) return;
        if (feeGateActive) {
            toastr.warning('Coordination Fee required');
            return;
        }
        if (!text) {
            toastr.warning('Write a message before sending');
            return;
        }

        $.ajax({
            url: 'ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_message',
                thread_id: currentThread.thread_id,
                message: text
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                if (res && res.code === 'FEE_REQUIRED') {
                    setFeeGateState(true);
                    toastr.warning('Coordination Fee required');
                    return;
                }
                toastr.error((res && res.message) ? res.message : 'Could not send message');
                return;
            }
            $('#admin-inbox-message').val('');
            toastr.success('Message sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not send message');
        });
    }

    function sendQuickReply(replyKey) {
        if (!currentThread || !currentThread.thread_id) return;
        var key = (replyKey || '').toString().toUpperCase();
        if (!quickReplies[key]) {
            toastr.error('Invalid quick reply');
            return;
        }

        $.ajax({
            url: 'ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_quick_reply',
                thread_id: currentThread.thread_id,
                thread_type: currentThread.thread_type,
                reply_key: key
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not send quick reply');
                return;
            }
            toastr.success('Quick reply sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not send quick reply');
        });
    }

    $(function () {
        var params = new URLSearchParams(window.location.search);
        var threadId = String(params.get('thread_id') || '');
        var requestId = parseInt(params.get('request_id') || '0', 10);
        var threadType = String(params.get('thread_type') || 'CARE').toUpperCase();
        var itemId = parseInt(params.get('item_id') || '0', 10);
        if (threadId) {
            preferredThread = { threadId: threadId };
        } else if (requestId > 0 && (threadType === 'CARE' || threadType === 'ITEM')) {
            preferredThread = {
                requestId: requestId,
                threadType: threadType,
                itemId: itemId
            };
        }

        $('#admin-inbox-refresh').on('click', function () {
            loadThreads();
        });

        $('#admin-inbox-thread-list').on('click', '.admin-thread-link', function () {
            var $a = $(this);
            currentThread = {
                thread_id: String($a.data('thread-id') || ''),
                thread_type: String($a.data('thread-type') || 'ITEM'),
                booking_request_id: parseInt($a.data('booking-id') || 0, 10),
                item_id: parseInt($a.data('item-id') || 0, 10)
            };
            $('#admin-inbox-thread-list li').removeClass('active');
            $a.closest('li').addClass('active');
            loadMessages();
        });

        $('#admin-inbox-send-form').on('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });

        $('#admin-inbox-quick-replies').on('click', '.admin-quick-reply', function () {
            var key = $(this).data('reply') || '';
            sendQuickReply(key);
        });

        loadThreads();
    });
})();
