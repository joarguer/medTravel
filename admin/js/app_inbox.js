(function () {
    var helpConfig = window.AdminInboxHelpConfig || {};
    var currentThread = null;
    var preferredThread = null;
    var feeGateActive = false;
    var freeMessageAllowed = true;
    var currentDocuments = [];
    var quickReplies = {
        DATES_AVAILABLE: 'Dates available',
        DATES_NOT_AVAILABLE: 'Dates not available',
        REQUEST_MEDICAL_HISTORY: 'REQUEST HISTORY',
        REQUEST_LABS: 'REQUEST LABS',
        REQUEST_IMAGING: 'REQUEST IMAGING',
        REQUEST_PHOTOS: 'REQUEST PHOTOS',
        FINAL_APPROVED: 'FINAL_APPROVED',
        FINAL_NOT_ELIGIBLE: 'FINAL_NOT_ELIGIBLE'
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

    function parseStructuredJson(prefix, text) {
        var raw = String(text || '').trim();
        if (raw.indexOf(prefix) !== 0) {
            return null;
        }
        var jsonText = raw.slice(prefix.length).trim();
        if (!jsonText) {
            return null;
        }
        try {
            var payload = JSON.parse(jsonText);
            return payload && typeof payload === 'object' ? payload : null;
        } catch (e) {
            return null;
        }
    }

    function renderStructuredParseFallback(prefix) {
        return '<div class="admin-structured-card">' +
            '<div class="admin-structured-header">' +
                '<span class="admin-structured-title">Structured message</span>' +
                '<span class="label label-default admin-structured-badge">' + esc(prefix) + '</span>' +
            '</div>' +
            '<div class="admin-structured-body">Unable to parse this message.</div>' +
        '</div>';
    }

    function docTypeLabel(type) {
        var labels = {
            labs: 'Labs',
            imaging: 'Imaging',
            photos: 'Photos',
            medical_history: 'Medical history',
            other: 'Other'
        };
        var key = String(type || '').toLowerCase();
        return labels[key] || key || 'Other';
    }

    function renderThreadDocuments(docs) {
        if (!docs || !docs.length) {
            return '';
        }
        var html = '<div class="well well-sm" style="margin-bottom:10px;">' +
            '<strong>Medical documents</strong>' +
            '<ul style="margin:8px 0 0 18px;padding:0;">';
        docs.forEach(function (doc) {
            var docType = String(doc.document_type || '').replace(/_/g, ' ');
            var title = String(doc.title || '').trim();
            var description = String(doc.description || '').trim();
            var originalName = String(doc.original_filename || doc.filename || ('Document #' + (doc.id || '')));
            var href = String(doc.download_url || '').trim();
            if (!href && doc.id) {
                href = '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(doc.id));
            }
            html += '<li style="margin-bottom:8px;">' +
                '<a href="' + esc(href) + '" target="_blank" rel="noopener">' + esc(originalName) + '</a>';
            if (docType) {
                html += ' <span class="label label-default" style="margin-left:6px;">' + esc(docType) + '</span>';
            }
            if (title) {
                html += '<div><strong>' + esc(title) + '</strong></div>';
            }
            if (description) {
                html += '<div class="text-muted">' + esc(description) + '</div>';
            }
            html += '</li>';
        });
        html += '</ul></div>';
        return html;
    }

    function formatCurrencyAmount(amount, currency) {
        var value = parseFloat(String(amount || '0'));
        if (!isFinite(value)) {
            value = 0;
        }
        var cur = String(currency || 'USD').toUpperCase() || 'USD';
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: cur,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        } catch (e) {
            return value.toFixed(2) + ' ' + cur;
        }
    }

    function renderStructuredRequestInfo(text) {
        var payload = parseStructuredJson('[REQUEST_INFO]', text);
        if (!payload) {
            return renderStructuredParseFallback('[REQUEST_INFO]');
        }

        var requiredTypes = Array.isArray(payload.required_types) ? payload.required_types : [];
        var note = String(payload.note || '').trim();
        var listHtml = requiredTypes.length
            ? ('<ul class="admin-structured-list">' + requiredTypes.map(function (t) {
                return '<li>' + esc(docTypeLabel(t)) + '</li>';
            }).join('') + '</ul>')
            : '<div class="text-muted">No document types specified.</div>';

        return '<div class="admin-structured-card admin-structured-request">' +
            '<div class="admin-structured-header">' +
                '<i class="fa fa-file-medical-o admin-structured-icon" aria-hidden="true"></i>' +
                '<span class="admin-structured-title">Additional Information Requested</span>' +
                '<span class="label label-warning admin-structured-badge">Awaiting Client</span>' +
            '</div>' +
            '<div class="admin-structured-body">' +
                '<div><strong>Requested types</strong></div>' +
                listHtml +
                (note ? '<div class="admin-structured-note"><strong>Note:</strong> ' + esc(note) + '</div>' : '') +
            '</div>' +
        '</div>';
    }

    function renderStructuredProposeQuote(text) {
        var payload = parseStructuredJson('[PROPOSE_QUOTE]', text);
        if (!payload) {
            return renderStructuredParseFallback('[PROPOSE_QUOTE]');
        }

        var amount = formatCurrencyAmount(payload.amount, payload.currency || 'USD');
        var notes = String(payload.notes || '').trim();

        return '<div class="admin-structured-card admin-structured-proposal">' +
            '<div class="admin-structured-header">' +
                '<i class="fa fa-money admin-structured-icon" aria-hidden="true"></i>' +
                '<span class="admin-structured-title">Quote Adjustment Proposal</span>' +
                '<span class="label label-warning admin-structured-badge">Awaiting Client Response</span>' +
            '</div>' +
            '<div class="admin-structured-body">' +
                '<div><strong>Amount:</strong> ' + esc(amount) + '</div>' +
                (notes ? '<div class="admin-structured-note"><strong>Justification:</strong> ' + esc(notes) + '</div>' : '') +
            '</div>' +
        '</div>';
    }

    function renderStructuredProposalResponse(text) {
        var payload = parseStructuredJson('[PROPOSAL_RESPONSE]', text);
        if (!payload) {
            return renderStructuredParseFallback('[PROPOSAL_RESPONSE]');
        }

        var action = String(payload.action_type || '').toUpperCase();
        var notes = String(payload.notes || '').trim();
        var map = {
            ACCEPT_PROPOSAL: { cls: 'success', label: 'Accepted' },
            REQUEST_CHANGES: { cls: 'warning', label: 'Changes Requested' },
            REJECT_PROPOSAL: { cls: 'danger', label: 'Rejected' },
            DOCS_NOT_AVAILABLE: { cls: 'default', label: 'Documents Not Available' }
        };
        var badge = map[action] || { cls: 'default', label: action || 'Response' };

        return '<div class="admin-structured-card admin-structured-response">' +
            '<div class="admin-structured-header">' +
                '<i class="fa fa-check-circle admin-structured-icon" aria-hidden="true"></i>' +
                '<span class="admin-structured-title">Proposal Response</span>' +
                '<span class="label label-' + esc(badge.cls) + ' admin-structured-badge">' + esc(badge.label) + '</span>' +
            '</div>' +
            '<div class="admin-structured-body">' +
                (notes ? '<div class="admin-structured-note"><strong>Note:</strong> ' + esc(notes) + '</div>' : '<div class="text-muted">No additional notes.</div>') +
            '</div>' +
        '</div>';
    }

    function formatAdminMessageBody(body) {
        var text = String(body || '').trim();
        if (text.indexOf('[REQUEST_INFO]') === 0) {
            return renderStructuredRequestInfo(text);
        }
        if (text.indexOf('[PROPOSE_QUOTE]') === 0) {
            return renderStructuredProposeQuote(text);
        }
        if (text.indexOf('[PROPOSAL_RESPONSE]') === 0) {
            return renderStructuredProposalResponse(text);
        }
        return '<span style="white-space:pre-wrap;">' + esc(body || '') + '</span>';
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

    function formatThreadTime(value) {
        var raw = String(value || '').trim();
        if (!raw) return '';
        var date = new Date(raw.replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return '';
        }

        var now = new Date();
        var sameDay = now.getFullYear() === date.getFullYear() &&
            now.getMonth() === date.getMonth() &&
            now.getDate() === date.getDate();

        var hh = date.getHours();
        var mm = date.getMinutes();
        var hhText = (hh < 10 ? '0' : '') + hh;
        var mmText = (mm < 10 ? '0' : '') + mm;
        if (sameDay) {
            return hhText + ':' + mmText;
        }

        var dd = date.getDate();
        var mon = date.getMonth() + 1;
        var ddText = (dd < 10 ? '0' : '') + dd;
        var monText = (mon < 10 ? '0' : '') + mon;
        return ddText + '/' + monText;
    }

    function getThreadPreviewText(thread) {
        if (!thread || typeof thread !== 'object') {
            return '';
        }

        var raw = '';
        if (typeof thread.last_message_preview !== 'undefined' && thread.last_message_preview !== null && thread.last_message_preview !== '') {
            raw = thread.last_message_preview;
        } else if (typeof thread.last_message !== 'undefined' && thread.last_message !== null && thread.last_message !== '') {
            if (typeof thread.last_message === 'object') {
                if (typeof thread.last_message.body !== 'undefined' && thread.last_message.body !== null && thread.last_message.body !== '') {
                    raw = thread.last_message.body;
                } else if (typeof thread.last_message.content !== 'undefined' && thread.last_message.content !== null && thread.last_message.content !== '') {
                    raw = thread.last_message.content;
                }
            } else {
                raw = thread.last_message;
            }
        } else if (typeof thread.last_activity_text !== 'undefined' && thread.last_activity_text !== null && thread.last_activity_text !== '') {
            raw = thread.last_activity_text;
        } else if (typeof thread.last_message_body !== 'undefined' && thread.last_message_body !== null && thread.last_message_body !== '') {
            raw = thread.last_message_body;
        } else if (typeof thread.preview !== 'undefined' && thread.preview !== null && thread.preview !== '') {
            raw = thread.preview;
        }

        var normalized = String(raw || '').replace(/\s+/g, ' ').trim();
        if (!normalized) {
            return '';
        }

        if (normalized.length > 110) {
            normalized = normalized.slice(0, 110).trim() + '…';
        }

        return normalized;
    }

    function cleanServiceTitle(rawTitle) {
        var title = String(rawTitle || '').trim();
        if (!title) {
            return 'Servicio';
        }
        title = title.replace(/\s*-\s*Request\s*#\d+\s*$/i, '').trim();
        title = title.replace(/\s*-\s*Solicitud\s*#\d+\s*$/i, '').trim();
        return title || 'Servicio';
    }

    function renderInboxHeader($target, headingText, requestId) {
        if (!$target || !$target.length) return;
        var safeHeading = esc(headingText || 'Inbox');
        var requestLabel = parseInt(requestId || 0, 10);
        var safeRequest = requestLabel > 0 ? String(requestLabel) : '-';
        $target.html('<h2 style="margin:0;">' + safeHeading + '</h2><small class="text-muted">Solicitud #' + esc(safeRequest) + '</small>');
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
            var threadTypeRaw = String(thread.thread_type || 'CARE').toUpperCase();
            var threadTypeSub = (threadTypeRaw === 'CARE') ? 'GENERAL' : threadTypeRaw;
            var requestId = parseInt(thread.booking_request_id || thread.request_id || 0, 10);
            var location = String(thread.subtitle || '').trim();
            var timeLabel = formatThreadTime(thread.updated_at || '');
            var previewText = getThreadPreviewText(thread);
            var unreadMeta = unread > 0 ? '<span class="badge badge-danger mt-unread">' + unread + '</span>' : '';
            var timeHtml = timeLabel ? '<div class="mt-time">' + esc(timeLabel) + '</div>' : '';
            var previewHtml = previewText ? '<div class="mt-thread-preview text-muted">Last: ' + esc(previewText) + '</div>' : '';
            var liClasses = 'mt-thread-item' + (active ? ' active' : '') + (unread > 0 ? ' unread' : '');

            html += '<li class="' + liClasses + '">' +
                '<a href="javascript:;" class="admin-thread-link mt-thread-link"' +
                ' data-thread-id="' + esc(threadId) + '"' +
                ' data-thread-type="' + esc(thread.thread_type) + '"' +
                ' data-booking-id="' + esc(thread.booking_request_id || thread.request_id || 0) + '"' +
                ' data-item-id="' + esc(thread.item_id || 0) + '">' +
                '<div class="mt-thread-row">' +
                    '<div class="mt-thread-main">' +
                        '<div class="mt-thread-title">' + esc(thread.title || 'Thread') + '</div>' +
                        '<div class="mt-thread-sub">' +
                            '<span class="mt-thread-request">Request #' + esc(requestId > 0 ? String(requestId) : '-') + '</span>' +
                            '<span class="mt-dot">•</span>' +
                            '<span class="mt-thread-type">' + esc(threadTypeSub) + '</span>' +
                            (location ? '<span class="mt-dot">•</span><span class="mt-thread-location">' + esc(location) + '</span>' : '') +
                        '</div>' +
                        previewHtml +
                    '</div>' +
                    '<div class="mt-thread-meta">' +
                        '<span class="badge badge-info mt-badge">' + esc(threadTypeRaw) + '</span>' +
                        unreadMeta +
                        timeHtml +
                    '</div>' +
                '</div>' +
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
            item_id: parseInt(selected.item_id || 0, 10),
            thread_title: String(selected.title || '')
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
            var docsHtml = renderThreadDocuments(currentDocuments);
            $box.html(docsHtml + '<p class="text-muted" style="margin:0;">No messages in this thread yet.</p>');
            return;
        }

        var html = renderThreadDocuments(currentDocuments);
        messages.forEach(function (m) {
            var bodyHtml = formatAdminMessageBody(m.body || '');
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

    function inboxHelpStorageKey() {
        var userId = parseInt(helpConfig.userId || 0, 10);
        var role = String(helpConfig.role || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        var suffix = '';
        if (userId > 0) {
            suffix += '_u' + userId;
        }
        if (role) {
            suffix += '_r' + role;
        }
        return 'mt_admin_inbox_help_collapsed' + suffix;
    }

    function readInboxHelpCollapsed() {
        var key = inboxHelpStorageKey();
        var value = null;
        try {
            value = localStorage.getItem(key);
        } catch (e) {
            value = null;
        }
        if (value !== '0' && value !== '1') {
            return true;
        }
        return value === '1';
    }

    function writeInboxHelpCollapsed(collapsed) {
        var key = inboxHelpStorageKey();
        try {
            localStorage.setItem(key, collapsed ? '1' : '0');
        } catch (e) {
        }
    }

    function applyInboxHelpState(collapsed) {
        var $panel = $('#admin-inbox-help-collapse');
        var $btn = $('#admin-inbox-help-toggle');
        if (!$panel.length) {
            return;
        }
        $panel.collapse(collapsed ? 'hide' : 'show');
        if ($btn.length) {
            $btn.attr('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    function bindInboxHelpPanel() {
        var $panel = $('#admin-inbox-help-collapse');
        var $btn = $('#admin-inbox-help-toggle');
        var $header = $('#admin-inbox-help-header');
        if (!$panel.length || !$btn.length) {
            return;
        }

        var collapsed = readInboxHelpCollapsed();
        applyInboxHelpState(collapsed);

        var toggle = function (evt) {
            if (evt) {
                evt.preventDefault();
            }
            var isOpen = $panel.hasClass('in');
            var nextCollapsed = isOpen;
            applyInboxHelpState(nextCollapsed);
            writeInboxHelpCollapsed(nextCollapsed);
        };

        $btn.on('click', toggle);
        $header.on('click', toggle);

        $panel.on('shown.bs.collapse', function () {
            $btn.attr('aria-expanded', 'true');
            writeInboxHelpCollapsed(false);
        });
        $panel.on('hidden.bs.collapse', function () {
            $btn.attr('aria-expanded', 'false');
            writeInboxHelpCollapsed(true);
        });
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

        if ($alert.length) {
            if (feeGateActive) {
                $alert.show();
            } else {
                $alert.hide();
            }
        }
        if ($quick.length && feeGateActive) {
            $quick.show();
        }
    }

    function setComposeGateState(canSendFreeMessage, noticeMessage) {
        freeMessageAllowed = !!canSendFreeMessage;
        var composeBlocked = feeGateActive || !freeMessageAllowed;

        var $quick = $('#admin-inbox-quick-replies');
        var $msg = $('#admin-inbox-message');
        var $send = $('#admin-inbox-send-form button[type="submit"]');
        var $note = $('#admin-inbox-compose-note');

        if ($quick.length) {
            if (composeBlocked) {
                $quick.show();
            } else {
                $quick.hide();
            }
        }
        if ($msg.length) {
            $msg.prop('disabled', composeBlocked);
        }
        if ($send.length) {
            $send.prop('disabled', composeBlocked);
        }

        if ($note.length) {
            if (!freeMessageAllowed) {
                $note.text(noticeMessage || 'Messaging will be available after the initial review. Please use the options above.');
                $note.show();
            } else {
                $note.hide();
            }
        }

        toggleStructuredActionButtons(composeBlocked);
    }

    function toggleStructuredActionButtons(composeBlocked) {
        var $box = $('#admin-inbox-structured-actions');
        if (!$box.length) return;
        var isItemThread = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;
        if (composeBlocked && isItemThread) {
            $box.show();
        } else {
            $box.hide();
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

            var feeLocked = !!res.fee_locked;
            setFeeGateState(feeLocked);
            var canSendFreeMessage = (typeof res.can_send_free_message === 'boolean') ? res.can_send_free_message : !feeLocked;
            setComposeGateState(canSendFreeMessage, res.free_message_notice || '');
            currentDocuments = $.isArray(res.documents) ? res.documents : [];

            var isItemThread = String(currentThread.thread_type || '').toUpperCase() === 'ITEM';
            var headingText = isItemThread ? cleanServiceTitle(currentThread.thread_title || '') : 'MedTravel Coordination';
            renderInboxHeader($('#admin-inbox-title'), headingText, currentThread.booking_request_id);
            renderMessages(res.messages || []);
            markCurrentRead();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true);
                setComposeGateState(true, '');
                toastr.warning('Coordination Fee required');
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                setComposeGateState(false, 'Messaging will be available after the initial review. Please use the options above.');
                return;
            }
            toastr.error('Could not load messages');
        });
    }

    function sendMessage() {
        var text = $.trim($('#admin-inbox-message').val() || '');
        if (!currentThread || !currentThread.thread_id) return;
        if (!freeMessageAllowed) {
            return;
        }
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
                    setComposeGateState(true, '');
                    toastr.warning('Coordination Fee required');
                    return;
                }
                if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                    setComposeGateState(false, 'Messaging will be available after the initial review. Please use the options above.');
                    return;
                }
                toastr.error((res && res.message) ? res.message : 'Could not send message');
                return;
            }
            $('#admin-inbox-message').val('');
            toastr.success('Message sent');
            loadMessages();
            loadThreads();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true);
                setComposeGateState(true, '');
                toastr.warning('Coordination Fee required');
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                setComposeGateState(false, 'Messaging will be available after the initial review. Please use the options above.');
                return;
            }
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

    function sendStructuredAction(actionType, payload) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before sending');
            return;
        }
        if (String(currentThread.thread_type || '').toUpperCase() !== 'ITEM') {
            toastr.warning('Structured proposals are only available in service threads');
            return;
        }

        $.ajax({
            url: 'ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_structured_action',
                thread_id: currentThread.thread_id,
                action_type: String(actionType || ''),
                payload_json: JSON.stringify(payload || {})
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not send structured action');
                return;
            }
            toastr.success('Structured action sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not send structured action');
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
                item_id: parseInt($a.data('item-id') || 0, 10),
                thread_title: $.trim($a.find('.mt-thread-title').text() || '')
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

        $('#admin-open-request-info').on('click', function () {
            if (!currentThread || String(currentThread.thread_type || '').toUpperCase() !== 'ITEM') {
                toastr.warning('Open a service thread first');
                return;
            }
            $('#admin-request-info-types input[type="checkbox"]').prop('checked', false);
            $('#admin-request-info-note').val('');
            $('#adminRequestInfoModal').modal('show');
        });

        $('#admin-submit-request-info').on('click', function () {
            var selected = [];
            $('#admin-request-info-types input[type="checkbox"]:checked').each(function () {
                selected.push(String($(this).val() || ''));
            });
            var note = $.trim($('#admin-request-info-note').val() || '');
            if (!selected.length) {
                toastr.warning('Select at least one required document type');
                return;
            }
            if (note.length > 500) {
                toastr.warning('Note is too long');
                return;
            }
            sendStructuredAction('REQUEST_ADDITIONAL_INFO', {
                required_types: selected,
                note: note
            });
            $('#adminRequestInfoModal').modal('hide');
        });

        $('#admin-open-propose-quote').on('click', function () {
            if (!currentThread || String(currentThread.thread_type || '').toUpperCase() !== 'ITEM') {
                toastr.warning('Open a service thread first');
                return;
            }
            $('#admin-propose-amount').val('');
            $('#admin-propose-currency').val('USD');
            $('#admin-propose-notes').val('');
            $('#adminProposeQuoteModal').modal('show');
        });

        $('#admin-submit-propose-quote').on('click', function () {
            var amountRaw = $.trim($('#admin-propose-amount').val() || '');
            var amount = parseFloat(amountRaw);
            var currency = $.trim($('#admin-propose-currency').val() || 'USD').toUpperCase();
            var notes = $.trim($('#admin-propose-notes').val() || '');

            if (!isFinite(amount) || amount <= 0) {
                toastr.warning('Enter a valid amount');
                return;
            }
            if (!currency) {
                currency = 'USD';
            }
            if (currency.length > 10) {
                toastr.warning('Invalid currency');
                return;
            }
            if (notes.length > 500) {
                toastr.warning('Notes are too long');
                return;
            }

            sendStructuredAction('PROPOSE_QUOTE_ADJUSTMENT', {
                amount: amount.toFixed(2),
                currency: currency,
                notes: notes
            });
            $('#adminProposeQuoteModal').modal('hide');
        });

        bindInboxHelpPanel();

        loadThreads();
    });
})();
