(function () {
    var currentThread = null;
    var preferredThread = null;

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
        if (s === 'client') return 'info';
        if (s === 'provider') return 'success';
        return 'default';
    }

    function renderThreads(threads) {
        var $list = $('#client-inbox-thread-list');
        if (!$list.length) return;

        if (!threads || !threads.length) {
            $list.html('<li><a href="javascript:;">No threads available</a></li>');
            $('#client-inbox-content').hide();
            $('#client-inbox-empty').show();
            return;
        }

        var desired = preferredThread;
        var matchedKey = '';
        if (desired && desired.requestId > 0) {
            for (var i = 0; i < threads.length; i++) {
                var t = threads[i];
                var tBooking = parseInt(t.booking_id || 0, 10);
                var tItem = parseInt(t.item_id || 0, 10);
                var tType = String(t.thread_type || '').toUpperCase();
                if (tBooking !== desired.requestId) {
                    continue;
                }
                if (desired.threadType === 'ITEM') {
                    if (tType === 'ITEM' && desired.itemId > 0 && tItem === desired.itemId) {
                        matchedKey = String(t.thread_key || '');
                        break;
                    }
                } else if (tType === 'CARE') {
                    matchedKey = String(t.thread_key || '');
                    break;
                }
            }
        }

        var html = '';
        threads.forEach(function (thread, idx) {
            var active = false;
            if (matchedKey) {
                active = String(thread.thread_key || '') === matchedKey;
            } else {
                active = (currentThread && currentThread.thread_key === thread.thread_key) || (!currentThread && idx === 0);
            }
            html += '<li class="' + (active ? 'active' : '') + '">' +
                '<a href="javascript:;" class="client-thread-link"' +
                    ' data-thread-key="' + esc(thread.thread_key) + '"' +
                    ' data-thread-type="' + esc(thread.thread_type) + '"' +
                    ' data-booking-id="' + esc(thread.booking_id) + '"' +
                    ' data-item-id="' + esc(thread.item_id || 0) + '">' +
                    esc(thread.title || 'Thread') +
                    (thread.subtitle ? '<small style="display:block;margin-top:4px;opacity:.8;">' + esc(thread.subtitle) + '</small>' : '') +
                '</a>' +
            '</li>';
        });
        $list.html(html);

        if (!currentThread || matchedKey) {
            var first = threads[0];
            if (matchedKey) {
                for (var j = 0; j < threads.length; j++) {
                    if (String(threads[j].thread_key || '') === matchedKey) {
                        first = threads[j];
                        break;
                    }
                }
            }
            currentThread = {
                thread_key: first.thread_key,
                thread_type: first.thread_type,
                booking_id: parseInt(first.booking_id || 0, 10),
                item_id: parseInt(first.item_id || 0, 10)
            };
            preferredThread = null;
            loadMessages();
        }
    }

    function renderMessages(messages) {
        var $box = $('#client-inbox-messages');
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

    function loadThreads() {
        $.ajax({
            url: '/client/ajax/list_messages.php',
            method: 'GET',
            dataType: 'json',
            data: { mode: 'threads' }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not load threads');
                return;
            }
            currentThread = null;
            renderThreads(res.threads || []);
        }).fail(function () {
            toastr.error('Could not load threads');
        });
    }

    function loadMessages() {
        if (!currentThread) return;

        $('#client-inbox-title').text('Loading...');
        $('#client-inbox-empty').hide();
        $('#client-inbox-content').show();

        $.ajax({
            url: '/client/ajax/list_messages.php',
            method: 'GET',
            dataType: 'json',
            data: {
                booking_id: currentThread.booking_id,
                thread_type: currentThread.thread_type,
                item_id: currentThread.item_id
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not load messages');
                return;
            }

            var title = 'General - Request #' + currentThread.booking_id;
            if (currentThread.thread_type === 'ITEM') {
                title = 'Item #' + currentThread.item_id + ' - Request #' + currentThread.booking_id;
            }
            $('#client-inbox-title').text(title);
            renderMessages(res.messages || []);
        }).fail(function () {
            toastr.error('Could not load messages');
        });
    }

    function sendMessage() {
        var text = $.trim($('#client-inbox-message').val() || '');
        if (!currentThread) return;
        if (!text) {
            toastr.warning('Write a message before sending');
            return;
        }

        $.ajax({
            url: '/client/ajax/send_message.php',
            method: 'POST',
            dataType: 'json',
            data: {
                booking_id: currentThread.booking_id,
                thread_type: currentThread.thread_type,
                item_id: currentThread.item_id,
                message: text
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not send message');
                return;
            }
            $('#client-inbox-message').val('');
            toastr.success('Message sent');
            loadMessages();
        }).fail(function () {
            toastr.error('Could not send message');
        });
    }

    $(function () {
        var params = new URLSearchParams(window.location.search);
        var requestId = parseInt(params.get('request_id') || '0', 10);
        var threadType = String(params.get('thread_type') || 'CARE').toUpperCase();
        var itemId = parseInt(params.get('item_id') || '0', 10);
        if (requestId > 0 && (threadType === 'CARE' || threadType === 'ITEM')) {
            preferredThread = {
                requestId: requestId,
                threadType: threadType,
                itemId: itemId
            };
        }

        $('#client-inbox-refresh').on('click', function () {
            loadThreads();
        });

        $('#client-inbox-thread-list').on('click', '.client-thread-link', function () {
            var $a = $(this);
            currentThread = {
                thread_key: String($a.data('thread-key') || ''),
                thread_type: String($a.data('thread-type') || 'CARE'),
                booking_id: parseInt($a.data('booking-id') || 0, 10),
                item_id: parseInt($a.data('item-id') || 0, 10)
            };
            $('#client-inbox-thread-list li').removeClass('active');
            $a.closest('li').addClass('active');
            loadMessages();
        });

        $('#client-inbox-send-form').on('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });

        loadThreads();
    });
})();
