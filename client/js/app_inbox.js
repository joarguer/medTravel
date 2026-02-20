(function () {
    var config = window.ClientInboxConfig || {};
    var currentThread = null;
    var preferredThread = null;
    var feeGateActive = !!config.feeGateActive;
    var quickActions = {
        REQUEST_AVAILABILITY: 'Please confirm availability for my dates.',
        DATES_FLEXIBLE: 'My dates are flexible.',
        DOCS_UPLOADED: 'I have uploaded medical documents.'
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
        if (s === 'client') return 'info';
        if (s === 'provider') return 'success';
        if (s === 'admin' || s === 'patientcare') return 'warning';
        return 'default';
    }

    function threadMatchesPreference(thread, preferred) {
        if (!thread || !preferred) return false;
        if (preferred.threadId && String(thread.thread_id || '') === String(preferred.threadId)) {
            return true;
        }
        var tBooking = parseInt(thread.booking_id || thread.request_id || 0, 10);
        var tItem = parseInt(thread.item_id || 0, 10);
        var tType = String(thread.thread_type || '').toUpperCase();
        if (preferred.requestId > 0 && tBooking !== preferred.requestId) {
            return false;
        }
        if (preferred.threadType === 'ITEM') {
            return tType === 'ITEM' && preferred.itemId > 0 && tItem === preferred.itemId;
        }
        return tType === 'CARE';
    }

    function renderThreads(threads) {
        var $list = $('#client-inbox-thread-list');
        if (!$list.length) return;

        if (!threads || !threads.length) {
            $list.html('<li><a href="javascript:;">No threads available</a></li>');
            $('#client-inbox-content').hide();
            $('#client-inbox-empty').show();
            currentThread = null;
            return;
        }

        var selectedKey = '';
        if (preferredThread) {
            for (var i = 0; i < threads.length; i++) {
                if (threadMatchesPreference(threads[i], preferredThread)) {
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
            var active = (threadId === selectedKey);
            var badge = unread > 0 ? '<span class="badge badge-danger" style="margin-left:6px;">' + unread + '</span>' : '';
            html += '<li class="' + (active ? 'active' : '') + '">' +
                '<a href="javascript:;" class="client-thread-link"' +
                ' data-thread-id="' + esc(threadId) + '"' +
                ' data-thread-type="' + esc(thread.thread_type) + '"' +
                ' data-booking-id="' + esc(thread.booking_id || thread.request_id || 0) + '"' +
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
            thread_type: String(selected.thread_type || 'CARE'),
            booking_id: parseInt(selected.booking_id || selected.request_id || 0, 10),
            item_id: parseInt(selected.item_id || 0, 10)
        };
        preferredThread = null;
        if (changed) {
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
            var bodyHtml = formatMessageBody(m.body || '');
            html += '<div class="well well-sm" style="margin-bottom:10px;">' +
                '<div><span class="label label-' + senderClass(m.sender) + '">' + esc(m.sender || 'system') + '</span>' +
                (m.time ? '<small style="margin-left:8px;">' + esc(m.time) + '</small>' : '') +
                '</div>' +
                '<div style="margin-top:6px;">' + bodyHtml + '</div>' +
                '</div>';
        });

        $box.html(html);
        $box.scrollTop($box[0].scrollHeight);
    }

    function refreshHeaderNotifications() {
        if (typeof window.clientReloadNotifications === 'function') {
            window.clientReloadNotifications();
        }
    }

    function setFeeGateState(enabled, message) {
        feeGateActive = !!enabled;
        var $alert = $('#client-inbox-fee-alert');
        var $msg = $('#client-inbox-message');
        var $send = $('#client-inbox-send-btn');
        var $actions = $('#client-inbox-fee-actions');
        if ($alert.length) {
            if (feeGateActive) {
                var text = message || 'Coordination Fee required. Unlock after Coordination Fee.';
                $alert.html('<strong>Coordination Fee required.</strong> ' + esc(text));
                $alert.show();
            } else {
                $alert.hide();
            }
        }
        if ($msg.length) {
            $msg.prop('disabled', feeGateActive);
        }
        if ($send.length) {
            $send.prop('disabled', feeGateActive);
        }
        if ($actions.length) {
            if (feeGateActive) {
                $actions.show();
            } else {
                $actions.hide();
            }
        }
    }

    function setStructuredCareAlert(visible, requestId, itemId) {
        var $alert = $('#client-inbox-structured-alert');
        var $link = $('#client-go-service-thread');
        if (!$alert.length || !$link.length) {
            return;
        }
        if (!visible) {
            $alert.hide();
            $link.attr('href', '#');
            return;
        }
        var reqId = parseInt(requestId || 0, 10);
        var itmId = parseInt(itemId || 0, 10);
        if (reqId <= 0 || itmId <= 0) {
            $alert.hide();
            $link.attr('href', '#');
            return;
        }
        var url = '/client/app_inbox.php?request_id=' + encodeURIComponent(String(reqId)) +
            '&thread_type=ITEM&item_id=' + encodeURIComponent(String(itmId));
        $link.attr('href', url);
        $alert.show();
    }

    function formatMessageBody(body) {
        var text = String(body || '');
        var trimmed = text.trim();
        var label = '';
        var isReply = false;
        if (trimmed.indexOf('[ACTION]') === 0) {
            label = 'Action';
            trimmed = trimmed.replace(/^\[ACTION\]\s*/i, '');
        } else if (trimmed.indexOf('[REPLY]') === 0) {
            label = 'Reply';
            trimmed = trimmed.replace(/^\[REPLY\]\s*/i, '');
            isReply = true;
        }

        if (!label) {
            return '<span style="white-space:pre-wrap;">' + esc(text) + '</span>';
        }

        var messageHtml = '<span class="label label-primary" style="margin-right:6px;">' + esc(label) + '</span>' + esc(trimmed);
        var structuredReplyUpper = trimmed.toUpperCase();
        if (isReply) {
            if (structuredReplyUpper.indexOf('REQUEST LABS') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="labs">UPLOAD LABS</button>' +
                    '</div>';
            }
            if (structuredReplyUpper.indexOf('REQUEST IMAGING') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="imaging">UPLOAD IMAGING</button>' +
                    '</div>';
            }
            if (structuredReplyUpper.indexOf('REQUEST PHOTOS') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="photos">UPLOAD PHOTOS</button>' +
                    '</div>';
            }
            if (structuredReplyUpper.indexOf('REQUEST HISTORY') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="history">UPLOAD HISTORY</button>' +
                    '</div>';
            }

            var isItemThread = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;
            if (structuredReplyUpper.indexOf('DATES AVAILABLE') !== -1 && isItemThread) {
                messageHtml += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-date-action" data-action="accept_dates">ACCEPT THESE DATES</button>' +
                    '</div>';
            }
            if (structuredReplyUpper.indexOf('DATES NOT AVAILABLE') !== -1 && isItemThread) {
                messageHtml += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-propose-new-dates">PROPOSE NEW DATES</button>' +
                    '</div>';
            }
        }

        if (isReply && structuredReplyUpper.indexOf('PROPOSED_DATES') !== -1) {
            messageHtml += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' +
                '<button type="button" class="btn btn-default btn-xs client-date-action" data-action="accept_dates">ACCEPT DATES</button>' +
                '<button type="button" class="btn btn-default btn-xs client-date-action" data-action="reject_dates">REJECT DATES</button>' +
                '</div>';
        }

        if (isReply && structuredReplyUpper.indexOf('FINAL_APPROVED') !== -1 && feeGateActive) {
            messageHtml += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' +
                '<button type="button" class="btn btn-default btn-xs client-final-action" data-action="final_accept_and_pay">ACCEPT & PAY</button>' +
                '<button type="button" class="btn btn-default btn-xs client-final-action" data-action="final_decline">DECLINE</button>' +
                '</div>';
        }

        if (label === 'Action' && trimmed.toUpperCase().indexOf('FINAL_ACCEPT_AND_PAY') === 0) {
            var bookingId = currentThread && currentThread.booking_id ? currentThread.booking_id : 0;
            var payUrl = '/booking.php';
            if (bookingId) {
                payUrl += '?request_id=' + encodeURIComponent(String(bookingId));
            }
            messageHtml += '<div style="margin-top:8px;">' +
                '<a class="btn btn-xs btn-success" href="' + esc(payUrl) + '">Proceed to pay Coordination Fee</a>' +
                '</div>';
        }

        return messageHtml;
    }

    $('#client-inbox-messages').on('click', '.client-upload-cta', function () {
        var target = $('#client-doc-file');
        if (!target.length) {
            return;
        }

        var feeActions = $('#client-inbox-fee-actions');
        if (feeActions.length) {
            $('html, body').animate({ scrollTop: feeActions.offset().top - 20 }, 200);
        }

        target.trigger('click');
    });

    $('#client-inbox-messages').on('click', '.client-structured-upload', function () {
        var feeActions = $('#client-inbox-fee-actions');
        if (!feeActions.length) {
            return;
        }
        $('html, body').animate({ scrollTop: feeActions.offset().top - 20 }, 200);
    });

    $('#client-inbox-messages').on('click', '.client-date-action', function () {
        var action = ($(this).data('action') || '').toString();
        if (!action) {
            return;
        }
        sendDateDecision(action);
    });

    $('#client-inbox-messages').on('click', '.client-final-action', function () {
        var action = ($(this).data('action') || '').toString();
        if (!action) {
            return;
        }
        sendFinalDecision(action);
    });

    $('#client-inbox-messages').on('click', '.client-propose-new-dates', function () {
        if (!currentThread) {
            return;
        }
        var requestId = parseInt(currentThread.booking_id || 0, 10);
        var itemId = parseInt(currentThread.item_id || 0, 10);
        if (requestId <= 0 || itemId <= 0) {
            toastr.warning('Open a service thread to continue');
            return;
        }
        window.location.href = '/client/app_inbox.php?request_id=' + encodeURIComponent(String(requestId)) +
            '&thread_type=ITEM&item_id=' + encodeURIComponent(String(itemId)) + '#client-inbox-fee-actions';
    });
    function loadThreads() {
        $.ajax({
            url: '/client/ajax/inbox.php',
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

    function markCurrentThreadRead() {
        if (!currentThread || !currentThread.thread_id) {
            return;
        }
        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'mark_read',
                thread_id: currentThread.thread_id
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                return;
            }
            refreshHeaderNotifications();
            loadThreads();
        });
    }

    function loadMessages() {
        if (!currentThread || !currentThread.thread_id) return;

        $('#client-inbox-title').text('Loading...');
        $('#client-inbox-empty').hide();
        $('#client-inbox-content').show();

        $.ajax({
            url: '/client/ajax/inbox.php',
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
            var feeRequired = parseInt(res.fee_required || 0, 10) === 1;
            var feeStatus = String(res.fee_status || '').toLowerCase();
            var computedFeeLocked = (feeRequired && feeStatus !== 'paid');
            var feeLocked = !!res.fee_locked || computedFeeLocked;
            setFeeGateState(feeLocked, res.fee_message || 'Unlock after Coordination Fee.');
            var isCareThread = String(currentThread.thread_type || '').toUpperCase() === 'CARE';
            var hasStructuredItemActions = !!res.has_structured_item_actions;
            setStructuredCareAlert(
                isCareThread && hasStructuredItemActions,
                res.request_id || currentThread.booking_id || 0,
                res.structured_item_id || 0
            );

            var title = 'General - Request #' + currentThread.booking_id;
            if (currentThread.thread_type === 'ITEM') {
                title = 'Item #' + currentThread.item_id + ' - Request #' + currentThread.booking_id;
            }
            $('#client-inbox-title').text(title);
            renderMessages(res.messages || []);
            markCurrentThreadRead();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            setStructuredCareAlert(false, 0, 0);
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true, 'Unlock after Coordination Fee.');
                $('#client-inbox-title').text('Coordination fee required');
                return;
            }
            toastr.error('Could not load messages');
        });
    }

    function sendMessage() {
        var text = $.trim($('#client-inbox-message').val() || '');
        if (!currentThread || !currentThread.thread_id) return;
        if (feeGateActive) {
            toastr.warning('Unlock after Coordination Fee');
            return;
        }
        if (!text) {
            toastr.warning('Write a message before sending');
            return;
        }

        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_message',
                thread_id: currentThread.thread_id,
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
            loadThreads();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true, 'Unlock after Coordination Fee.');
                toastr.warning('Unlock after Coordination Fee');
                return;
            }
            toastr.error('Could not send message');
        });
    }

    function sendQuickAction(actionKey) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before sending');
            return;
        }
        var key = (actionKey || '').toString().toUpperCase();
        if (!quickActions[key]) {
            toastr.error('Invalid quick action');
            return;
        }

        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_quick_action',
                thread_id: currentThread.thread_id,
                action_key: key
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not send quick action');
                return;
            }
            toastr.success('Quick action sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not send quick action');
        });
    }

    function sendDateDecision(actionKey) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before responding');
            return;
        }
        var action = (actionKey || '').toString();
        if (action !== 'accept_dates' && action !== 'reject_dates') {
            toastr.error('Invalid action');
            return;
        }

        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: action,
                thread_id: currentThread.thread_id,
                thread_type: 'ITEM',
                item_id: parseInt(currentThread.item_id || 0, 10)
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not update dates');
                return;
            }
            toastr.success('Response sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not update dates');
        });
    }

    function sendFinalDecision(actionKey) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before responding');
            return;
        }
        var action = (actionKey || '').toString();
        if (action !== 'final_accept_and_pay' && action !== 'final_decline') {
            toastr.error('Invalid action');
            return;
        }

        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: action,
                thread_id: currentThread.thread_id
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not update final decision');
                return;
            }
            toastr.success('Response sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not update final decision');
        });
    }

    function uploadMedicalDocument() {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before uploading');
            return;
        }

        var fileInput = document.getElementById('client-doc-file');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            toastr.warning('Choose a file to upload');
            return;
        }

        var formData = new FormData();
        formData.append('document', fileInput.files[0]);
        formData.append('document_type', ($('#client-doc-type').val() || 'other').toString());
        formData.append('title', ($('#client-doc-title').val() || '').toString());
        formData.append('description', ($('#client-doc-description').val() || '').toString());
        formData.append('request_id', currentThread.booking_id || 0);
        formData.append('item_id', currentThread.item_id || 0);

        var $btn = $('#client-doc-upload-btn');
        $btn.prop('disabled', true).text('Uploading...');

        $.ajax({
            url: '/client/ajax/upload_medical_document.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Upload failed');
                return;
            }
            $('#client-doc-file').val('');
            $('#client-doc-title').val('');
            $('#client-doc-description').val('');
            toastr.success('Document uploaded');
        }).fail(function () {
            toastr.error('Upload failed');
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Upload document');
        });
    }

    $(function () {
        setStructuredCareAlert(false, 0, 0);
        if (feeGateActive) {
            setFeeGateState(true, 'Unlock after Coordination Fee.');
        }

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

        $('#client-inbox-refresh').on('click', function () {
            loadThreads();
        });

        $('#client-inbox-thread-list').on('click', '.client-thread-link', function () {
            var $a = $(this);
            currentThread = {
                thread_id: String($a.data('thread-id') || ''),
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

        $('#client-inbox-fee-actions').on('click', '.client-quick-action', function () {
            var actionKey = $(this).data('action') || '';
            sendQuickAction(actionKey);
        });

        $('#client-inbox-doc-form').on('submit', function (e) {
            e.preventDefault();
            uploadMedicalDocument();
        });

        loadThreads();
    });
})();
