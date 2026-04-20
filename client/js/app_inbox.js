(function () {
    var config = window.ClientInboxConfig || {};
    var realtimeConfig = {
        baseUrl: String(config.realtimeBaseUrl || '').trim(),
        socketPath: String(config.realtimeSocketPath || '').trim(),
        tokenUrl: String(config.realtimeTokenUrl || '/client/ajax/realtime_token.php').trim()
    };
    var realtimeState = {
        socket: null,
        pendingThreadId: '',
        lastThreadId: '',
        joining: false,
        lastMessageIdByThread: {}
    };
    var recentSentMessageIds = {};
    var RECENT_SENT_TTL_MS = 30000;
    var messageStatusById = {};
    var MESSAGE_STATUS_TTL_MS = 60000;
    var typingState = {
        lastEmitAt: 0,
        stopTimer: null,
        remoteTimer: null
    };
    var currentThread = null;
    var preferredThread = null;
    var threadListFilter = null;
    var autoSelectItemRequestId = 0;
    var selectedFiles = [];
    var currentDocuments = [];
    var currentDocumentsThreadId = '';
    var attachModalBusy = false;
    var composeBusy = false;
    var composeBusyMessage = '';
    var feeGateActive = !!config.feeGateActive;
    var commissionGateActive = !!config.commissionGateActive;
    var commissionPaid = !!config.commissionPaid;
    var commissionGateMessage = String(config.commissionMessage || '');
    var freeMessageAllowed = true;
    var lastComposeNotice = '';
    var cancelledMeetingKeys = {};
    var inboxPresenter = window.MedTravelInboxPresenter || null;
    var quickActions = {
        REQUEST_AVAILABILITY: 'Please confirm availability for my dates.',
        DATES_FLEXIBLE: 'My dates are flexible.',
        DOCS_UPLOADED: 'I have uploaded medical documents.',
        DOCS_NOT_AVAILABLE: "I don't have the requested documents yet."
    };

    function clientVisibleReplyLabel(rawValue) {
        var normalized = String(rawValue || '').trim().split(/\r?\n/, 1)[0].toUpperCase().replace(/^\[(ACTION|REPLY)\]\s*/i, '');
        var map = {
            DATES_AVAILABLE: 'Dates available',
            DATES_NOT_AVAILABLE: 'Dates not available',
            REQUEST_MEDICAL_HISTORY: 'Medical history requested',
            REQUEST_HISTORY: 'Medical history requested',
            REQUEST_LABS: 'Lab results requested',
            REQUEST_IMAGING: 'Diagnostic imaging requested',
            REQUEST_PHOTOS: 'Clinical photos requested',
            FINAL_APPROVED: 'Final approval',
            FINAL_NOT_ELIGIBLE: 'Not eligible',
            NOT_ELIGIBLE: 'Not eligible'
        };
        return map[normalized] || '';
    }

    function clientVisibleActionLabel(rawValue) {
        var normalized = String(rawValue || '').trim().split(/\r?\n/, 1)[0].toUpperCase().replace(/^\[(ACTION|REPLY)\]\s*/i, '');
        var map = {
            FINAL_ACCEPT_AND_PAY: 'Accepted the next step with the provider',
            FINAL_DECLINE: 'Declined to continue',
            PROPOSE_NEW_DATES: 'Requested new dates'
        };
        return map[normalized] || '';
    }

    function clientProposalResponseMeta(actionType) {
        var normalized = String(actionType || '').trim().toUpperCase();
        var map = {
            ACCEPT_PROPOSAL: { title: 'Proposal response', label: 'Accepted the proposal' },
            REQUEST_CHANGES: { title: 'Proposal response', label: 'Requested changes' },
            REJECT_PROPOSAL: { title: 'Proposal response', label: 'Rejected the proposal' },
            DOCS_NOT_AVAILABLE: { title: 'Document response', label: "I don't have the requested documents" }
        };
        return map[normalized] || { title: 'Proposal response', label: normalized || 'Response' };
    }

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function resetAttachDocumentModal() {
        var $form = $('#client-attach-document-form');
        if ($form.length && $form[0]) {
            $form[0].reset();
        }
        $('#client-attach-thread-id').val('');
        $('#client-attach-thread-type').val('');
        $('#client-attach-request-id').val('');
        $('#client-attach-item-id').val('');
        $('#client-attach-context').text('Thread context not available.');
        setAttachModalBusy(false);
        setAttachStatus('');
    }

    function setAttachStatus(message, tone) {
        var $status = $('#client-chat-attach-status');
        if (!$status.length) return;
        var text = String(message || '').trim();
        if (!text) {
            $status.hide().removeClass('text-danger text-success text-warning').text('');
            return;
        }
        $status
            .removeClass('text-danger text-success text-warning')
            .addClass(tone ? ('text-' + tone) : '')
            .text(text)
            .show();
    }

    function setAttachModalBusy(enabled) {
        attachModalBusy = !!enabled;
        $('#client-attach-submit-btn').prop('disabled', attachModalBusy);
        $('#client-attach-document-form').find('input, select, textarea').prop('disabled', attachModalBusy);
    }

    function describeUploadError(res) {
        var code = '';
        if (res && res.results && res.results[0] && res.results[0].message) {
            code = String(res.results[0].message);
        } else if (res && res.message) {
            code = String(res.message);
        }
        var map = {
            FEE_REQUIRED: 'This thread is locked until the coordination step is completed.',
            FREE_MESSAGE_BLOCKED: 'Messaging is temporarily limited for this thread.',
            file_required: 'Please select a file.',
            title_required: 'Please enter a document title.',
            upload_error: 'The uploaded file could not be processed.',
            file_too_large: 'The file is larger than the allowed size.',
            invalid_tmp_file: 'The uploaded temporary file could not be read.',
            file_extension_not_allowed: 'This file extension is not allowed.',
            file_type_not_allowed: 'This file type is not allowed.',
            file_save_failed: 'The file could not be saved.',
            insert_failed: 'The document metadata could not be saved.',
            upload_failed: 'The document could not be attached. Please try again.',
            invalid_booking_id: 'The current request could not be resolved.',
            client_documents_scope_missing: 'The current thread context is incomplete.'
        };
        if (map[code]) {
            return map[code];
        }
        if (code.indexOf('insert_failed:') === 0) {
            return map.insert_failed;
        }
        return code || 'The document could not be attached. Please try again.';
    }

    function cleanDocumentTitleFallback(filename) {
        var raw = String(filename || '').trim();
        if (!raw) return 'Document';
        var base = raw.replace(/\.[a-z0-9]{2,8}$/i, '');
        base = base.replace(/[_\-]+/g, ' ').replace(/\s+/g, ' ').trim();
        return base || raw;
    }

    function normalizeDocumentTypeKey(type) {
        var key = String(type || 'other').toLowerCase().trim();
        var aliasMap = {
            history: 'medical_history',
            medical_history: 'medical_history',
            'medical history': 'medical_history',
            labs: 'lab_results',
            lab_results: 'lab_results',
            'lab results': 'lab_results',
            'exam / lab result': 'lab_results',
            imaging: 'diagnostic_imaging',
            diagnostic_imaging: 'diagnostic_imaging',
            'diagnostic image': 'diagnostic_imaging',
            photos: 'photos',
            'clinical image': 'photos',
            quote: 'quote',
            'quote / estimate': 'quote',
            consent_form: 'consent_form',
            'consent form': 'consent_form',
            medical_order: 'medical_order',
            'medical order': 'medical_order',
            prescription: 'prescription',
            'prescription / indication': 'prescription',
            administrative_document: 'administrative_document',
            'administrative document': 'administrative_document',
            invoice: 'administrative_document',
            contract: 'administrative_document',
            insurance: 'administrative_document',
            passport: 'administrative_document',
            id_card: 'administrative_document',
            other: 'other'
        };
        return aliasMap[key] || 'other';
    }

    function resolveCurrentRequestId() {
        var requestIdFromThread = currentThread ? parseInt(currentThread.booking_id || 0, 10) : 0;
        if (requestIdFromThread > 0) {
            return requestIdFromThread;
        }
        var urlParams = new URLSearchParams(window.location.search || '');
        return parseInt(urlParams.get('request_id') || '0', 10);
    }

    function populateAttachDocumentContext() {
        var threadId = currentThread && currentThread.thread_id ? String(currentThread.thread_id) : '';
        var threadType = currentThread && currentThread.thread_type ? String(currentThread.thread_type) : 'CARE';
        var requestId = resolveCurrentRequestId();
        var itemId = currentThread ? parseInt(currentThread.item_id || 0, 10) : 0;
        $('#client-attach-thread-id').val(threadId);
        $('#client-attach-thread-type').val(threadType);
        $('#client-attach-request-id').val(requestId > 0 ? String(requestId) : '');
        $('#client-attach-item-id').val(itemId > 0 ? String(itemId) : '');
        var parts = ['Thread: ' + (threadId || 'Unavailable')];
        if (requestId > 0) parts.push('Request #' + requestId);
        if (itemId > 0) parts.push('Item #' + itemId);
        $('#client-attach-context').text(parts.join(' · '));
    }

    function openAttachDocumentModal() {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread first');
            return;
        }
        resetAttachDocumentModal();
        populateAttachDocumentContext();
        $('#clientAttachDocumentModal').modal('show');
    }

    function senderClass(sender) {
        var s = String(sender || 'system').toLowerCase();
        if (s === 'client') return 'info';
        if (s === 'provider') return 'success';
        if (s === 'admin' || s === 'patientcare') return 'warning';
        return 'default';
    }

    function normalizeRole(sender) {
        return String(sender || 'system').toLowerCase().trim();
    }

    function isOwnClientMessage(sender) {
        return normalizeRole(sender) === 'client';
    }

    function getClientDisplayName(m, own) {
        if (own) return 'Me';
        // Use API name field if present (future-proof)
        var apiName = String(m.sender_name || m.user_name || m.display_name || '').trim();
        if (apiName) return apiName;
        var s = normalizeRole(m.sender || '');
        if (s === 'admin' || s === 'patientcare') return 'Support';
        if (s === 'provider') return 'Provider';
        if (s === 'system') return 'System';
        return 'Support';
    }

    function buildClientMsgHtml(m, bodyHtml) {
        var own = isOwnClientMessage(m.sender || '');
        var rowCls = own ? 'mt-msg-row--own' : 'mt-msg-row--other';
        var role = normalizeRole(m.sender || '');
        if (role === 'system') {
            rowCls = 'mt-msg-row--system';
        }
        var displayName = getClientDisplayName(m, own);
        var statusText = getMessageStatusText(m, own);
        var headHtml = m._showHeader !== false
            ? ('<div class="mt-bubble-head">' +
                '<span class="mt-bubble-name">' + esc(displayName) + '</span>' +
                (m.time ? '<span class="mt-bubble-time">' + esc(m.time) + '</span>' : '') +
              '</div>')
            : '';
        var statusHtml = statusText ? '<div class="mt-bubble-status">' + esc(statusText) + '</div>' : '';
        var groupedCls = m._grouped ? ' mt-msg-row--grouped' : '';
        var tempAttr = m._tempId ? ' data-temp-id="' + esc(m._tempId) + '"' : '';
        return '<div class="mt-msg-row ' + rowCls + groupedCls + '"' + tempAttr + '>' +
            '<div class="mt-msg-bubble">' +
                headHtml +
                '<div class="mt-bubble-body">' + bodyHtml + '</div>' +
                statusHtml +
            '</div>' +
        '</div>';
    }

    function shouldAutoScroll(el) {
        if (!el) return true;
        return (el.scrollHeight - el.scrollTop - el.clientHeight) <= 120;
    }

    function realtimeEnabled() {
        return !!(realtimeConfig.baseUrl && realtimeConfig.socketPath && typeof window.io === 'function');
    }

    function realtimeDebug(message) {
        if (window.MT_DEBUG_REALTIME === true) {
            console.log(message);
        }
    }

    function setMessageStatus(messageId, statusText) {
        var id = parseInt(messageId || 0, 10);
        if (!isFinite(id) || id <= 0) return;
        messageStatusById[id] = {
            text: String(statusText || ''),
            ts: Date.now()
        };
        setTimeout(function () {
            delete messageStatusById[id];
        }, MESSAGE_STATUS_TTL_MS);
    }

    function markSentFromResponse(res) {
        if (!res) return;
        var sentId = res && res.message ? res.message.id : 0;
        if (!sentId && res && res.message_id) {
            sentId = res.message_id;
        }
        if (!sentId) {
            sentId = extractMaxMessageId(res.messages || []);
        }
        if (sentId) {
            setMessageStatus(sentId, 'Sent');
        }
    }

    function getMessageStatusText(m, own) {
        if (!own) return '';
        if (m && m._status) return String(m._status);
        var id = parseInt(m.id || 0, 10);
        if (!isFinite(id) || id <= 0) return '';
        var entry = messageStatusById[id];
        if (!entry) return '';
        if (entry.text === '') return '';
        if ((Date.now() - entry.ts) > MESSAGE_STATUS_TTL_MS) {
            delete messageStatusById[id];
            return '';
        }
        return entry.text;
    }

    function parseMessageTime(value) {
        var raw = String(value || '').trim();
        if (!raw) return 0;
        var d = new Date(raw.replace(' ', 'T'));
        if (isNaN(d.getTime())) {
            return 0;
        }
        return d.getTime();
    }

    function shouldGroupMessages(prevMsg, msg) {
        if (!prevMsg || !msg) return false;
        if (normalizeRole(prevMsg.sender || '') === 'system') return false;
        if (normalizeRole(msg.sender || '') === 'system') return false;
        if (normalizeRole(prevMsg.sender || '') !== normalizeRole(msg.sender || '')) return false;
        var prevTime = parseMessageTime(prevMsg.time);
        var currTime = parseMessageTime(msg.time);
        if (!prevTime || !currTime) return false;
        return Math.abs(currTime - prevTime) <= 120000;
    }

    function annotateGrouping(messages, previousMeta) {
        var prevMsg = previousMeta && previousMeta.msg ? previousMeta.msg : null;
        messages.forEach(function (m) {
            var grouped = shouldGroupMessages(prevMsg, m);
            m._grouped = grouped;
            m._showHeader = !grouped;
            prevMsg = m;
        });
        return {
            msg: messages.length ? messages[messages.length - 1] : prevMsg
        };
    }

    function rememberLastRenderedMeta(threadId, lastMsg) {
        if (!threadId) return;
        if (!lastMsg) {
            realtimeState.lastRenderedMeta = null;
            return;
        }
        realtimeState.lastRenderedMeta = {
            threadId: threadId,
            msg: lastMsg
        };
    }

    function getLastRenderedMeta(threadId) {
        if (!realtimeState.lastRenderedMeta) return null;
        if (realtimeState.lastRenderedMeta.threadId !== threadId) return null;
        return realtimeState.lastRenderedMeta;
    }

    function trackRecentSentMessage(messageId) {
        var id = parseInt(messageId || 0, 10);
        if (!isFinite(id) || id <= 0) return;
        recentSentMessageIds[id] = Date.now();
        setTimeout(function () {
            delete recentSentMessageIds[id];
        }, RECENT_SENT_TTL_MS);
    }

    function shouldDedupeMessage(messageId) {
        var id = parseInt(messageId || 0, 10);
        if (!isFinite(id) || id <= 0) return false;
        var ts = recentSentMessageIds[id];
        if (!ts) return false;
        if ((Date.now() - ts) > RECENT_SENT_TTL_MS) {
            delete recentSentMessageIds[id];
            return false;
        }
        delete recentSentMessageIds[id];
        return true;
    }

    function initRealtime() {
        if (!realtimeEnabled() || realtimeState.socket) {
            return;
        }
        realtimeState.socket = window.io(realtimeConfig.baseUrl, {
            path: realtimeConfig.socketPath,
            transports: ['websocket', 'polling']
        });

        realtimeState.socket.on('connect', function () {
            if (realtimeState.pendingThreadId) {
                realtimeJoinThread(realtimeState.pendingThreadId);
            }
        });

        realtimeState.socket.on('message.created', function (payload) {
            var threadId = payload && payload.thread_id ? String(payload.thread_id) : '';
            var messageId = payload && payload.message_id ? parseInt(payload.message_id || 0, 10) : 0;
            if (!threadId) return;
            if (messageId && shouldDedupeMessage(messageId)) {
                realtimeDebug('[realtime] dedupe message.created id=' + messageId + ' thread=' + threadId);
                return;
            }
            if (currentThread && String(currentThread.thread_id || '') === threadId) {
                var sinceId = realtimeState.lastMessageIdByThread[threadId] || 0;
                fetchNewMessages(threadId, sinceId);
                return;
            }
            loadThreads();
        });

        realtimeState.socket.on('typing', function (payload) {
            var threadId = payload && payload.thread_id ? String(payload.thread_id) : '';
            if (!currentThread || String(currentThread.thread_id || '') !== threadId) {
                return;
            }
            var role = String(payload.role || '').toLowerCase();
            if (!role || role === 'client') {
                return;
            }
            var state = String(payload.state || '').toLowerCase();
            if (state === 'stop') {
                hideTypingIndicator();
                return;
            }
            showTypingIndicator(typingLabelForRole(role));
        });

        realtimeState.socket.on('connect_error', function () {
            // noop: fallback to polling/manual refresh
        });

        realtimeState.socket.on('auth_error', function () {
            // noop: server rejected token; user can refresh/join again
        });
    }

    function realtimeJoinThread(threadId) {
        var thread = String(threadId || '').trim();
        if (!thread || !realtimeEnabled()) {
            return;
        }
        initRealtime();
        if (!realtimeState.socket) {
            return;
        }
        realtimeState.pendingThreadId = thread;
        if (!realtimeState.socket.connected || realtimeState.joining) {
            return;
        }
        realtimeState.joining = true;
        $.ajax({
            url: realtimeConfig.tokenUrl,
            method: 'POST',
            dataType: 'json',
            data: { thread_id: thread }
        }).done(function (res) {
            if (!res || res.ok !== true || !res.token) {
                return;
            }
            realtimeState.lastThreadId = thread;
            realtimeState.socket.emit('join_room', {
                thread_id: thread,
                token: res.token
            });
        }).always(function () {
            realtimeState.joining = false;
        });
    }

    function extractMaxMessageId(messages) {
        var maxId = 0;
        if (!messages || !messages.length) return maxId;
        messages.forEach(function (m) {
            var id = parseInt(m.id || 0, 10);
            if (isFinite(id) && id > maxId) {
                maxId = id;
            }
        });
        return maxId;
    }

    function rememberLastMessageId(threadId, messages) {
        var id = extractMaxMessageId(messages);
        if (!threadId || !id) return;
        realtimeState.lastMessageIdByThread[threadId] = id;
    }

    function formatFileSize(bytes) {
        var n = parseInt(bytes || 0, 10);
        if (!isFinite(n) || n <= 0) return '0 B';
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function docTypeLabel(type) {
        return normalizeDocTypeLabel(type);
    }

    function resolvePreviewType(meta) {
        var mime = String(meta && meta.mime || '').toLowerCase().trim();
        if (mime === 'application/pdf' || mime === 'application/x-pdf') {
            return 'pdf';
        }
        if (mime === 'image/jpeg' || mime === 'image/jpg' || mime === 'image/png' || mime === 'image/webp') {
            return 'image';
        }

        var source = String((meta && (meta.name || meta.url)) || '').trim();
        if (!source) {
            return '';
        }
        var clean = source.split('?')[0].split('#')[0].toLowerCase();
        var dotIndex = clean.lastIndexOf('.');
        if (dotIndex === -1) {
            return '';
        }
        var ext = clean.slice(dotIndex + 1);
        if (ext === 'pdf') {
            return 'pdf';
        }
        if (ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'webp') {
            return 'image';
        }
        return '';
    }

    function buildClientDocumentUrl(filePath) {
        var relative = String(filePath || '').trim().replace(/\\+/g, '/').replace(/^\/+/, '');
        relative = relative.replace(/^uploads\/medical_docs\//i, '');
        relative = relative.replace(/^medical_docs\//i, '');
        if (!relative) return '#';
        return '/uploads/medical_docs/' + encodeURI(relative);
    }

    function renderSelectedFilesBatch() {
        var $batch = $('#client-doc-batch');
        if (!$batch.length) return;

        if (!selectedFiles.length) {
            $batch.html('');
            return;
        }

        var typeOptionsHtml = $('#client-doc-type').html() || '';
        var html = '';
        selectedFiles.forEach(function (item, index) {
            var file = item.file || null;
            var filename = file ? (file.name || ('File ' + (index + 1))) : ('File ' + (index + 1));
            var filesize = file ? formatFileSize(file.size || 0) : '0 B';
            html += '' +
                '<div class="well well-sm" style="margin-bottom:8px;">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">' +
                        '<strong>' + esc(filename) + '</strong>' +
                        '<button type="button" class="btn btn-xs btn-default client-doc-remove" data-index="' + index + '">Remove</button>' +
                    '</div>' +
                    '<div class="text-muted" style="margin:4px 0 8px 0;">' + esc(filesize) + '</div>' +
                    '<div class="form-group" style="margin-bottom:8px;">' +
                        '<label style="margin-bottom:4px;">Document type</label>' +
                        '<select class="form-control input-sm client-doc-item-type" data-index="' + index + '">' + typeOptionsHtml + '</select>' +
                    '</div>' +
                    '<div class="form-group" style="margin-bottom:8px;">' +
                        '<label style="margin-bottom:4px;">Title</label>' +
                        '<input type="text" class="form-control input-sm client-doc-item-title" data-index="' + index + '" maxlength="255" value="' + esc(item.title || '') + '">' +
                    '</div>' +
                    '<div class="form-group" style="margin-bottom:0;">' +
                        '<label style="margin-bottom:4px;">Description</label>' +
                        '<textarea class="form-control input-sm client-doc-item-description" data-index="' + index + '" rows="2" maxlength="500">' + esc(item.description || '') + '</textarea>' +
                    '</div>' +
                '</div>';
        });

        $batch.html(html);
        selectedFiles.forEach(function (item, index) {
            $batch.find('.client-doc-item-type[data-index="' + index + '"]').val(item.doc_type || 'other');
        });
    }

    function appendSelectedFiles(fileList) {
        var defaults = {
            doc_type: ($('#client-doc-type').val() || 'other').toString(),
            title: ($('#client-doc-title').val() || '').toString(),
            description: ($('#client-doc-description').val() || '').toString()
        };
        if (!fileList || !fileList.length) {
            renderSelectedFilesBatch();
            return;
        }
        for (var i = 0; i < fileList.length; i++) {
            selectedFiles.push({
                file: fileList[i],
                doc_type: defaults.doc_type,
                title: defaults.title,
                description: defaults.description
            });
        }
        renderSelectedFilesBatch();
    }

    function mergeUploadedDocuments(uploadRes) {
        var results = uploadRes && $.isArray(uploadRes.results) ? uploadRes.results : [];
        if (!results.length) {
            return;
        }
        results.forEach(function (item) {
            if (!item || item.ok !== true) {
                return;
            }
            var docId = parseInt(item.document_id || 0, 10);
            if (docId > 0) {
                currentDocuments = (currentDocuments || []).filter(function (doc) {
                    return parseInt(doc.id || 0, 10) !== docId;
                });
            }
            var filePath = String(item.file_path || '').trim();
            currentDocuments.unshift({
                id: docId,
                file_path: filePath,
                document_type: normalizeDocumentTypeKey(item.document_type || 'other'),
                original_filename: String(item.original_filename || ''),
                filename: String(item.filename || ''),
                title: String(item.title || ''),
                description: String(item.description || item.document_note || ''),
                file_size: parseInt(item.file_size || 0, 10) || 0,
                mime_type: String(item.mime_type || ''),
                uploaded_at: String(item.uploaded_at || ''),
                download_url: String(item.download_url || (filePath ? buildClientDocumentUrl(filePath) : ''))
            });
        });
    }

    function setComposeBusy(enabled, message) {
        composeBusy = !!enabled;
        composeBusyMessage = composeBusy ? String(message || 'Working...') : '';
        setComposeGateState(freeMessageAllowed, composeBusyMessage);
    }

    function buildSharedDocumentMessage(results) {
        var docs = $.isArray(results) ? results.filter(function (item) {
            return item && item.ok === true;
        }) : [];
        if (!docs.length) {
            return 'Shared document';
        }
        return docs.map(function (item) {
            var title = String(item.title || cleanDocumentTitleFallback(item.original_filename || item.filename || 'Document')).trim();
            var typeLabel = docTypeLabel(item.document_type || 'other');
            var fileLabel = String(item.original_filename || item.filename || '').trim();
            var note = String(item.description || item.document_note || '').trim();
            var lines = ['Shared document: ' + title, 'Type: ' + typeLabel];
            if (fileLabel) {
                lines.push('File: ' + fileLabel);
            }
            if (note) {
                lines.push('Observation: ' + note);
            }
            return lines.join('\n');
        }).join('\n\n');
    }

    function parseSharedDocumentMessage(text) {
        var raw = String(text || '');
        var lines = raw.split(/\r?\n/);
        var entries = [];
        var kept = [];
        var i = 0;
        while (i < lines.length) {
            var line = lines[i];
            var trimmedLine = String(line || '').trim();
            var singleMatch = trimmedLine.match(/^Shared document:\s*(.+)$/i);
            if (singleMatch) {
                var entry = {
                    lookup_name: singleMatch[1].trim(),
                    title: singleMatch[1].trim(),
                    document_type: '',
                    file_name: '',
                    note: ''
                };
                i++;
                while (i < lines.length) {
                    var detailLine = String(lines[i] || '').trim();
                    if (!detailLine) {
                        i++;
                        break;
                    }
                    var typeMatch = detailLine.match(/^Type:\s*(.+)$/i);
                    if (typeMatch) {
                        entry.document_type = normalizeDocumentTypeKey(typeMatch[1]);
                        i++;
                        continue;
                    }
                    var fileMatch = detailLine.match(/^File:\s*(.+)$/i);
                    if (fileMatch) {
                        entry.file_name = fileMatch[1].trim();
                        i++;
                        continue;
                    }
                    var noteMatch = detailLine.match(/^(Observation|Note):\s*(.+)$/i);
                    if (noteMatch) {
                        entry.note = noteMatch[2].trim();
                        i++;
                        continue;
                    }
                    break;
                }
                if (!entry.lookup_name && entry.file_name) {
                    entry.lookup_name = entry.file_name;
                }
                entries.push(entry);
                continue;
            }
            var multiMatch = trimmedLine.match(/^Shared\s+\d+\s+documents:\s*(.+)$/i);
            if (multiMatch) {
                multiMatch[1].split(/\s*,\s*/).forEach(function (name) {
                    var docName = String(name || '').trim();
                    if (docName !== '') {
                        entries.push({ lookup_name: docName, title: '', document_type: '', file_name: '', note: '' });
                    }
                });
                i++;
                continue;
            }
            kept.push(line);
            i++;
        }
        return {
            body: kept.join('\n').trim(),
            entries: entries
        };
    }

    function normalizeSharedDocumentName(name) {
        var value = String(name || '').trim().toLowerCase();
        if (!value) {
            return '';
        }
        if (typeof value.normalize === 'function') {
            value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        value = value
            .replace(/[\u2010-\u2015]/g, '-')
            .replace(/[\u00a0\s]+/g, ' ')
            .replace(/\s*([._-])\s*/g, '$1')
            .replace(/^["'`]+|["'`]+$/g, '')
            .trim();
        return value;
    }

    function sharedDocumentNameWithoutExtension(name) {
        var value = normalizeSharedDocumentName(name);
        return value.replace(/\.[a-z0-9]{2,8}$/i, '');
    }

    function sharedDocumentExtension(name) {
        var match = normalizeSharedDocumentName(name).match(/(\.[a-z0-9]{2,8})$/i);
        return match ? match[1] : '';
    }

    function resolveSharedMessageDocument(ref) {
        var reference = ref && typeof ref === 'object'
            ? [ref.lookup_name, ref.file_name, ref.title].filter(function (value) { return String(value || '').trim() !== ''; })
            : [ref];
        var bestDoc = null;
        var bestScore = -1;
        if (!reference.length || !currentDocuments || !currentDocuments.length) {
            return null;
        }

        currentDocuments.forEach(function (doc) {
            var candidates = [
                String(doc.original_filename || '').trim(),
                String(doc.filename || '').trim(),
                String(doc.title || '').trim()
            ].filter(function (value) {
                return value !== '';
            });
            var docBestScore = -1;

            reference.forEach(function (targetValue) {
                var target = normalizeSharedDocumentName(targetValue);
                var targetWithoutExt = sharedDocumentNameWithoutExtension(targetValue);
                var targetExt = sharedDocumentExtension(targetValue);
                if (!target) {
                    return;
                }
                candidates.forEach(function (candidate) {
                    var candidateKey = normalizeSharedDocumentName(candidate);
                    var candidateWithoutExt = sharedDocumentNameWithoutExtension(candidate);
                    var candidateExt = sharedDocumentExtension(candidate);
                    if (!candidateKey) {
                        return;
                    }
                    if (candidateKey === target) {
                        docBestScore = Math.max(docBestScore, 100);
                        return;
                    }
                    if (candidateWithoutExt && candidateWithoutExt === targetWithoutExt) {
                        docBestScore = Math.max(docBestScore, 95);
                    }
                    if (targetExt && candidateExt && targetExt === candidateExt && (
                        candidateKey.indexOf(target) !== -1 || target.indexOf(candidateKey) !== -1
                    )) {
                        docBestScore = Math.max(docBestScore, 85);
                    }
                    var targetTokens = targetWithoutExt.split(/[^a-z0-9]+/).filter(function (token) { return token.length >= 3; });
                    var candidateTokens = candidateWithoutExt.split(/[^a-z0-9]+/).filter(function (token) { return token.length >= 3; });
                    var overlap = 0;
                    targetTokens.forEach(function (token) {
                        if (candidateTokens.indexOf(token) !== -1) {
                            overlap++;
                        }
                    });
                    if (overlap >= 3 && (!targetExt || !candidateExt || targetExt === candidateExt)) {
                        docBestScore = Math.max(docBestScore, 70 + overlap);
                    }
                });
            });

            if (docBestScore > bestScore) {
                bestScore = docBestScore;
                bestDoc = doc;
            }
        });

        if (bestScore >= 70) {
            return bestDoc;
        }
        if (currentDocuments.length === 1) {
            return currentDocuments[0];
        }
        return null;
    }

    function renderSharedDocumentsBlock(entries) {
        if (!entries || !entries.length) {
            return '';
        }
        function buildSharedDocumentHref(doc) {
            if (!doc) {
                return '';
            }
            var href = String(doc.download_url || '').trim();
            if (href) {
                return href;
            }
            var filePath = String(doc.file_path || '').trim();
            if (filePath) {
                return buildClientDocumentUrl(filePath);
            }
            return '';
        }
        var itemsHtml = entries.map(function (entry) {
            var doc = resolveSharedMessageDocument(entry);
            if (!doc && entries.length === 1 && currentDocuments && currentDocuments.length) {
                doc = currentDocuments[0];
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('[inbox] shared document fallback to latest thread document', {
                        requested_name: entry,
                        fallback_document: {
                            id: doc.id || null,
                            original_filename: doc.original_filename || '',
                            filename: doc.filename || '',
                            title: doc.title || '',
                            download_url: doc.download_url || '',
                            file_path: doc.file_path || ''
                        }
                    });
                }
            }
            var originalName = doc
                ? String(doc.original_filename || doc.filename || entry.file_name || entry.lookup_name || ('Document #' + (doc.id || '')))
                : String(entry.file_name || entry.lookup_name || '');
            var title = doc
                ? String(doc.title || cleanDocumentTitleFallback(originalName))
                : String(entry.title || cleanDocumentTitleFallback(originalName));
            var typeKey = doc
                ? normalizeDocumentTypeKey(doc.document_type || entry.document_type || 'other')
                : normalizeDocumentTypeKey(entry.document_type || 'other');
            var typeLabel = docTypeLabel(typeKey);
            var note = doc
                ? String(doc.description || entry.note || '').trim()
                : String(entry.note || '').trim();
            var href = buildSharedDocumentHref(doc);
            var docIdAttr = esc(String(doc && doc.id ? doc.id : ''));
            var encodedHref = esc(href);
            var titleHtml = href
                ? ('<a class="mt-shared-doc-link mt-shared-doc-name" href="' + encodedHref + '" data-doc-id="' + docIdAttr + '" data-url="' + encodedHref + '" target="_blank" rel="noopener">' + esc(title) + '</a>')
                : ('<div class="mt-shared-doc-name">' + esc(title) + '</div>');
            if (!doc && window.console && typeof window.console.warn === 'function') {
                window.console.warn('[inbox] shared document unresolved', {
                    requested_name: entry,
                    current_documents: (currentDocuments || []).map(function (item) {
                        return {
                            id: item.id || null,
                            original_filename: item.original_filename || '',
                            filename: item.filename || '',
                            title: item.title || '',
                            download_url: item.download_url || '',
                            file_path: item.file_path || ''
                        };
                    })
                });
            } else if (doc && !href && window.console && typeof window.console.warn === 'function') {
                window.console.warn('[inbox] shared document resolved without href', {
                    requested_name: entry,
                    document: {
                        id: doc.id || null,
                        original_filename: doc.original_filename || '',
                        filename: doc.filename || '',
                        title: doc.title || '',
                        download_url: doc.download_url || '',
                        file_path: doc.file_path || ''
                    }
                });
            }
            var actionsHtml = href
                ? ('<div class="mt-shared-doc-actions">' +
                    '<a class="mt-shared-doc-link" href="' + encodedHref + '" data-doc-id="' + docIdAttr + '" data-url="' + encodedHref + '" target="_blank" rel="noopener">Open document</a>' +
                '</div>')
                : '';
            return '<div class="mt-shared-doc-card">' +
                '<div class="mt-shared-doc-label"><i class="fa fa-paperclip" aria-hidden="true"></i> Shared document</div>' +
                titleHtml +
                '<span class="mt-shared-doc-meta">Type: ' + esc(typeLabel) + '</span>' +
                (originalName ? '<span class="mt-shared-doc-file">File: ' + esc(originalName) + '</span>' : '') +
                (note ? '<span class="mt-shared-doc-note">Note: ' + esc(note) + '</span>' : '') +
                actionsHtml +
            '</div>';
        }).join('');
        return '<div class="mt-shared-docs">' + itemsHtml + '</div>';
    }

    function renderThreadDocuments() {
        var isItemThread = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;
        if (!isItemThread) {
            return '';
        }
        if (!currentDocuments || !currentDocuments.length) {
            return '';
        }
        var hasDocs = currentDocuments.length > 0;
        var countHtml = hasDocs
            ? ' <span class="badge" style="background:#7f8c9d;">' + currentDocuments.length + '</span>'
            : '';
        var innerHtml;
        if (!hasDocs) {
            innerHtml = '<p class="mt-docs-empty text-muted">No medical documents uploaded yet.</p>';
        } else {
            var typeCls = {
                lab_results: 'label-info',
                diagnostic_imaging: 'label-primary',
                photos: 'label-success',
                medical_history: 'label-warning',
                quote: 'label-primary',
                consent_form: 'label-warning',
                medical_order: 'label-info',
                prescription: 'label-success',
                administrative_document: 'label-default',
                other: 'label-default'
            };
            innerHtml = '<div class="mt-docs-list">';
            currentDocuments.forEach(function (doc) {
                var typeKey = normalizeDocumentTypeKey(doc.document_type || 'other');
                var typeLabel = docTypeLabel(typeKey);
                var cls = typeCls[typeKey] || 'label-default';
                var originalName = String(doc.original_filename || doc.filename || ('Document #' + (doc.id || '')));
                var title = String(doc.title || cleanDocumentTitleFallback(originalName)).trim();
                var note = String(doc.description || '').trim();
                var uploadedRaw = String(doc.uploaded_at || doc.created_at || '').trim();
                var href = String(doc.download_url || '').trim();
                if (!href) {
                    href = buildClientDocumentUrl(doc.file_path || '');
                }
                var encodedHref = href ? encodeURIComponent(href) : '';
                var dateText = '';
                if (uploadedRaw) {
                    var d = new Date(uploadedRaw.replace(' ', 'T'));
                    if (!isNaN(d.getTime())) {
                        var dd = (d.getDate() < 10 ? '0' : '') + d.getDate();
                        var mo = ((d.getMonth() + 1) < 10 ? '0' : '') + (d.getMonth() + 1);
                        dateText = dd + '/' + mo + '/' + d.getFullYear();
                    }
                }
                innerHtml +=
                    '<div class="mt-doc-row">' +
                        '<span class="label ' + cls + ' mt-doc-type">' + esc(typeLabel) + '</span>' +
                        '<div class="mt-doc-main">' +
                            '<a href="' + esc(href) + '" class="mt-doc-title mt-doc-open" data-doc-id="' + esc(String(doc.id || '')) + '" data-url="' + esc(encodedHref) + '" title="View ' + esc(title) + '">' + esc(title) + '</a>' +
                            '<span class="mt-doc-name">' + esc(originalName) + '</span>' +
                            (note ? '<span class="mt-doc-note">Note: ' + esc(note) + '</span>' : '') +
                        '</div>' +
                        (dateText ? '<small class="mt-doc-date text-muted"><i class="fa fa-clock-o" aria-hidden="true"></i> ' + esc(dateText) + '</small>' : '') +
                        '<button type="button" class="btn btn-xs btn-info mt-doc-view"' +
                            ' data-doc-id="' + esc(String(doc.id || '')) + '"' +
                            ' data-url="' + esc(encodedHref) + '"' +
                            ' title="View ' + esc(title) + '">' +
                            '<i class="fa fa-eye" aria-hidden="true"></i> View' +
                        '</button>' +
                        '<a class="btn btn-xs btn-default mt-doc-download" href="' + esc(href) + '" target="_blank" rel="noopener" title="Download ' + esc(originalName) + '">' +
                            '<i class="fa fa-download" aria-hidden="true"></i> Download' +
                        '</a>' +
                    '</div>';
            });
            innerHtml += '</div>';
        }
        return '<div class="mt-docs-section">' +
            '<div class="mt-docs-header">' +
                '<i class="fa fa-paperclip mt-docs-icon" aria-hidden="true"></i> ' +
                '<strong>Medical Documents' + countHtml + '</strong>' +
            '</div>' +
            innerHtml +
        '</div>';
    }

    function findDocumentById(docId) {
        var target = String(docId || '');
        if (!target) {
            return null;
        }
        for (var i = 0; i < currentDocuments.length; i++) {
            if (String(currentDocuments[i].id || '') === target) {
                return currentDocuments[i];
            }
        }
        return null;
    }

    function openDocViewer(doc, fallbackUrl) {
        var originalName = String(doc && (doc.original_filename || doc.filename) || 'Document');
        var title = String(doc && doc.title || cleanDocumentTitleFallback(originalName));
        var note = String(doc && doc.description || '').trim();
        var typeKey = normalizeDocumentTypeKey(doc && doc.document_type || 'other');
        var typeLabel = docTypeLabel(typeKey);
        var mimeType = String(doc && doc.mime_type || '').toLowerCase().trim();
        var href = String(doc && doc.download_url || fallbackUrl || '').trim();
        if (!href && doc) {
            href = buildClientDocumentUrl(doc.file_path || '');
        }
        var previewType = resolvePreviewType({
            mime: mimeType,
            name: originalName,
            url: href
        });

        $('#clientDocViewerName').text(title);
        $('#clientDocViewerType').text(typeLabel);
        var typeCls = {
            lab_results: 'label-info',
            diagnostic_imaging: 'label-primary',
            photos: 'label-success',
            medical_history: 'label-warning',
            quote: 'label-primary',
            consent_form: 'label-warning',
            medical_order: 'label-info',
            prescription: 'label-success',
            administrative_document: 'label-default',
            other: 'label-default'
        };
        $('#clientDocViewerType').attr('class', 'label ' + (typeCls[typeKey] || 'label-default') + ' mt-dv-type-badge');

        var metaParts = [];
        var uploadedRaw = String(doc && (doc.uploaded_at || doc.created_at) || '').trim();
        if (uploadedRaw) {
            var d = new Date(uploadedRaw.replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                var dd = (d.getDate() < 10 ? '0' : '') + d.getDate();
                var mo = ((d.getMonth() + 1) < 10 ? '0' : '') + (d.getMonth() + 1);
                metaParts.push('Uploaded: ' + dd + '/' + mo + '/' + d.getFullYear());
            }
        }
        if (originalName) {
            metaParts.push('File: ' + originalName);
        }
        if (doc && doc.file_size > 0) {
            var kb = (doc.file_size / 1024).toFixed(1);
            metaParts.push(kb + ' KB');
        }
        if (mimeType) {
            metaParts.push(mimeType);
        }
        if (note) {
            metaParts.push('Note: ' + note);
        }
        $('#clientDocViewerMeta').text(metaParts.join(' · '));
        $('#clientDocViewerDownload').attr('href', href || '#');
        $('#clientDocViewerOpen').attr('href', href || '#');

        var $preview = $('#clientDocViewerPreview');
        if (previewType === 'image' && href) {
            $preview.html('<img src="' + esc(href) + '" alt="' + esc(originalName) + '">');
        } else if (previewType === 'pdf' && href) {
            $preview.html('<iframe src="' + esc(href) + '" title="' + esc(originalName) + '"></iframe>');
        } else {
            $preview.html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<div>Preview not available for this file type.</div>' +
                    '<div style="margin-top:8px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">' +
                        '<a href="' + esc(href || '#') + '" target="_blank" rel="noopener" class="btn btn-default btn-sm">' +
                            '<i class="fa fa-external-link" aria-hidden="true"></i> Open in new tab</a>' +
                        '<a href="' + esc(href || '#') + '" target="_blank" rel="noopener" class="btn btn-primary btn-sm">' +
                            '<i class="fa fa-download" aria-hidden="true"></i> Download</a>' +
                    '</div>' +
                '</div>'
            );
        }
        $('#clientDocViewerModal').modal('show');
    }

    function syncThreadDocumentsPanel() {
        var $panel = $('#client-inbox-docs-panel');
        var $content = $('#client-inbox-docs-content');
        var $count = $('#client-inbox-docs-count');
        var $collapse = $('#client-inbox-docs-collapse');
        if (!$panel.length || !$content.length || !$count.length || !$collapse.length) {
            return;
        }
        var html = renderThreadDocuments();
        if (!html) {
            $content.html('');
            $count.text('0');
            $panel.hide();
            $collapse.removeClass('in').css('height', '');
            return;
        }
        $content.html(html);
        $count.text(String(currentDocuments && currentDocuments.length ? currentDocuments.length : 0));
        $panel.show();
    }

    function setUploadStatusAlert(level, message) {
        var $status = $('#client-doc-upload-status');
        if (!$status.length) return;
        var allowed = { success: true, warning: true, danger: true, info: true };
        var alertLevel = allowed[level] ? level : 'info';
        var text = String(message || '').trim();
        if (!text) {
            $status.html('');
            return;
        }
        $status.html('<div class="alert alert-' + alertLevel + ' small" role="alert" style="margin-bottom:0;">' + esc(text) + '</div>');
    }

    function clearLegacyUploadStatus() {
        var $status = $('#client-doc-upload-status');
        if ($status.length) {
            $status.html('');
        }
    }

    function mapStructuredUploadType(uploadType) {
        var key = String(uploadType || '').toLowerCase();
        if (key === 'history') return 'medical_history';
        if (key === 'labs') return 'lab_results';
        if (key === 'photos') return 'photos';
        if (key === 'imaging') return 'other';
        return 'other';
    }

    function openStructuredFilePicker(uploadType) {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf,.jpg,.jpeg,.png';
        input.style.position = 'fixed';
        input.style.left = '-9999px';
        input.style.top = '0';
        document.body.appendChild(input);
        input.addEventListener('change', function () {
            var file = (input.files && input.files.length) ? input.files[0] : null;
            if (input.parentNode) {
                input.parentNode.removeChild(input);
            }
            if (!file) {
                return;
            }
            uploadStructuredDocument(uploadType, file);
        }, { once: true });
        input.click();
    }

    function resolveResultOriginalName(item) {
        if (!item) return '';
        return String(
            item.original_name ||
            item.original_filename ||
            item.filename ||
            item.name ||
            ''
        ).trim();
    }

    function renderUploadStatusFromResponse(res, fallbackErrorMessage) {
        var hasUploadedCount = !!(res && typeof res.uploaded_count !== 'undefined' && res.uploaded_count !== null);
        var hasResults = !!(res && $.isArray(res.results));

        if (!hasUploadedCount && !hasResults) {
            if (fallbackErrorMessage) {
                setUploadStatusAlert('danger', fallbackErrorMessage);
            } else {
                setUploadStatusAlert('success', 'Upload completed.');
            }
            return;
        }

        var uploadedCount = hasUploadedCount ? parseInt(res.uploaded_count || 0, 10) : 0;
        if (!isFinite(uploadedCount) || uploadedCount < 0) {
            uploadedCount = 0;
        }

        var results = hasResults ? res.results : [];
        var failedItems = [];
        if (results.length) {
            failedItems = results.filter(function (item) {
                return !item || item.ok !== true;
            });
        }

        var hasFailedCount = !!(res && typeof res.failed_count !== 'undefined' && res.failed_count !== null);
        var failedCount = hasFailedCount ? parseInt(res.failed_count || 0, 10) : failedItems.length;
        if (!isFinite(failedCount) || failedCount < 0) {
            failedCount = 0;
        }

        if (uploadedCount > 0 && failedCount === 0) {
            setUploadStatusAlert('success', 'Documents uploaded: ' + uploadedCount);
            return;
        }

        if (uploadedCount > 0 && failedCount > 0) {
            var failedNames = failedItems.map(resolveResultOriginalName).filter(function (name) {
                return name !== '';
            });
            var previewNames = failedNames.slice(0, 3);
            var moreCount = failedCount - previewNames.length;
            var summary = 'Uploaded: ' + uploadedCount + ' · Failed: ' + failedCount;
            if (previewNames.length) {
                summary += ' (' + previewNames.join(', ');
                if (moreCount > 0) {
                    summary += ' (+' + moreCount + ' more)';
                }
                summary += ')';
            } else if (moreCount > 0) {
                summary += ' (+' + moreCount + ' more)';
            }
            setUploadStatusAlert('warning', summary);
            return;
        }

        var fallback = String(fallbackErrorMessage || (res && res.message) || '').trim();
        if (fallback) {
            setUploadStatusAlert('danger', fallback);
        } else {
            setUploadStatusAlert('danger', 'Upload failed. Please try again.');
        }
    }

    function uploadStructuredDocument(uploadType, file) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before uploading');
            return;
        }
        if (!file) {
            toastr.warning('Choose a file to upload');
            return;
        }

        var normalizedType = mapStructuredUploadType(uploadType);
        var requestIdFromThread = parseInt(currentThread.booking_id || 0, 10);
        var urlParams = new URLSearchParams(window.location.search || '');
        var requestIdFromUrl = parseInt(urlParams.get('request_id') || '0', 10);
        var safeRequestId = requestIdFromThread > 0 ? requestIdFromThread : requestIdFromUrl;
        if (safeRequestId <= 0) {
            toastr.error('Could not determine request id for upload');
            return;
        }

        setUploadStatusAlert('info', 'Uploading document...');
        toastr.info('Uploading ' + String(uploadType || 'document').toUpperCase() + '...');

        var formData = new FormData();
        formData.append('client_doc_files', file);
        formData.append('meta_json', JSON.stringify([{
            doc_type: normalizedType,
            title: '',
            description: '',
            original_name: String(file.name || '')
        }]));
        formData.append('document_type', normalizedType);
        formData.append('title', '');
        formData.append('description', '');
        formData.append('booking_request_id', safeRequestId);
        formData.append('request_id', safeRequestId);
        formData.append('item_id', currentThread.item_id || 0);
        formData.append('thread_type', currentThread.thread_type || 'CARE');

        $.ajax({
            url: '/client/ajax/upload_medical_document.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                var errorMessage = (res && res.message) ? String(res.message) : 'Upload failed. Please try again.';
                renderUploadStatusFromResponse(res || null, errorMessage);
                toastr.error(errorMessage);
                return;
            }
            renderUploadStatusFromResponse(res || null, 'Upload failed. Please try again.');
            toastr.success('Document uploaded');
            loadMessages();
            loadThreads();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            var errorMessage = (res && res.message) ? String(res.message) : 'Upload failed. Please try again.';
            renderUploadStatusFromResponse(res || null, errorMessage);
            toastr.error(errorMessage);
        });
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
        if (preferred.threadType === 'ALL') {
            return true;
        }
        if (preferred.threadType === 'ITEM') {
            if (tType !== 'ITEM') {
                return false;
            }
            if (preferred.itemId > 0) {
                return tItem === preferred.itemId;
            }
            if (preferred.itemGroup) {
                return normalizeItemGroupKey(thread.item_type) === preferred.itemGroup;
            }
            return true;
        }
        return tType === 'CARE';
    }

    function normalizeItemGroupKey(rawType) {
        var type = String(rawType || '').toLowerCase().trim();
        if (type === 'medical_offer') return 'medical';
        if (type === 'complementary_service') return 'complementary';
        return '';
    }

    function threadMatchesListFilter(thread, filter) {
        if (!thread || !filter) return true;
        var threadId = String(thread.thread_id || '');
        if (filter.threadId) {
            return threadId === String(filter.threadId);
        }
        var requestId = parseInt(thread.booking_id || thread.request_id || 0, 10);
        if (filter.requestId > 0 && requestId !== filter.requestId) {
            return false;
        }
        var type = String(thread.thread_type || '').toUpperCase();
        if (filter.mode === 'CARE') {
            return type === 'CARE';
        }
        if (filter.mode === 'ITEM') {
            return type === 'ITEM';
        }
        if (filter.mode === 'ITEM_GROUP') {
            return type === 'ITEM' && normalizeItemGroupKey(thread.item_type) === filter.itemGroup;
        }
        return true;
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

        // Campos reales priorizados desde el JSON del thread (sin inventar nuevos campos).
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
        return summarizeInboxText(raw, 110);
    }

    function cleanServiceTitle(rawTitle) {
        var title = String(rawTitle || '').trim();
        if (!title) {
            return 'Service';
        }
        title = title.replace(/\s*-\s*Request\s*#\d+\s*$/i, '').trim();
        title = title.replace(/\s*-\s*Solicitud\s*#\d+\s*$/i, '').trim();
        return title || 'Service';
    }

    function clientThreadKindLabel(threadType) {
        var type = String(threadType || 'CARE').toUpperCase();
        return type === 'ITEM' ? 'MEDICAL PROVIDER' : 'MEDTRAVEL';
    }

    function clientThreadAudienceText(threadType) {
        var type = String(threadType || 'CARE').toUpperCase();
        if (type === 'ITEM') {
            return 'Medical provider communication for this requested service.';
        }
        return 'Questions about coordination, documents, travel support, and case guidance.';
    }

    function clientThreadHeading(thread) {
        var type = String(thread && thread.thread_type || 'CARE').toUpperCase();
        if (type === 'ITEM') {
            var serviceTitle = cleanServiceTitle(thread && thread.thread_title || thread && thread.title || '');
            return serviceTitle || 'Talk to your Medical Provider';
        }
        return careDisplayTitle();
    }

    function clientThreadSubtitle(thread) {
        var type = String(thread && thread.thread_type || 'CARE').toUpperCase();
        var parts = [clientThreadAudienceText(type)];
        var location = String(thread && (thread.thread_subtitle || thread.subtitle) || '').trim();
        if (location) {
            parts.push(location);
        }
        return parts.join(' • ');
    }

    function isSpanishClientUi() {
        var lang = String((document.documentElement && document.documentElement.lang) || '').toLowerCase();
        return lang.indexOf('es') === 0;
    }

    function careDisplayTitle() {
        return isSpanishClientUi() ? 'Coordinación MedTravel' : 'MedTravel Coordination';
    }

    function renderInboxHeader($target, headingText, requestId, subtitle) {
        if (!$target || !$target.length) return;
        var safeHeading = esc(headingText || 'Inbox');
        var requestLabel = parseInt(requestId || 0, 10);
        var safeRequest = requestLabel > 0 ? String(requestLabel) : '-';
        var subtitleText = String(subtitle || '').trim();
        var smallText = 'Request #' + esc(safeRequest);
        if (subtitleText !== '') {
            smallText += ' • ' + esc(subtitleText);
        }
        $target.html('<h2 style="margin:0;">' + safeHeading + '</h2><small class="text-muted">' + smallText + '</small>');
    }

    function renderThreads(threads) {
        var $list = $('#client-inbox-thread-list');
        if (!$list.length) return;

        var visibleThreads = $.isArray(threads) ? threads : [];
        if (threadListFilter) {
            visibleThreads = visibleThreads.filter(function (thread) {
                return threadMatchesListFilter(thread, threadListFilter);
            });
        }

        if (!visibleThreads.length) {
            $list.html('<li><a href="javascript:;">No threads available for this filter</a></li>');
            $('#client-inbox-content').hide();
            $('#client-inbox-empty').show();
            currentThread = null;
            syncThreadDocumentsPanel();
            return;
        }

        var selectedKey = '';
        if (preferredThread) {
            for (var i = 0; i < visibleThreads.length; i++) {
                if (threadMatchesPreference(visibleThreads[i], preferredThread)) {
                    selectedKey = String(visibleThreads[i].thread_id || '');
                    break;
                }
            }
        }
        if (!selectedKey && currentThread && currentThread.thread_id) {
            selectedKey = String(currentThread.thread_id);
        }
        if (!selectedKey && autoSelectItemRequestId > 0) {
            for (var k = 0; k < visibleThreads.length; k++) {
                var autoThread = visibleThreads[k] || {};
                var autoReqId = parseInt(autoThread.booking_id || autoThread.request_id || 0, 10);
                var autoType = String(autoThread.thread_type || '').toUpperCase();
                if (autoReqId === autoSelectItemRequestId && autoType === 'ITEM') {
                    selectedKey = String(autoThread.thread_id || '');
                    break;
                }
            }
            if (!selectedKey) {
                for (var m = 0; m < visibleThreads.length; m++) {
                    var careThread = visibleThreads[m] || {};
                    var careReqId = parseInt(careThread.booking_id || careThread.request_id || 0, 10);
                    var careType = String(careThread.thread_type || '').toUpperCase();
                    if (careReqId === autoSelectItemRequestId && careType === 'CARE') {
                        selectedKey = String(careThread.thread_id || '');
                        break;
                    }
                }
            }
            autoSelectItemRequestId = 0;
        }
        if (!selectedKey) {
            selectedKey = String(visibleThreads[0].thread_id || '');
        }

        var html = '';
        visibleThreads.forEach(function (thread) {
            var threadId = String(thread.thread_id || '');
            var unread = parseInt(thread.unread_count || 0, 10);
            var active = (threadId === selectedKey);
            var threadTypeRaw = String(thread.thread_type || 'CARE').toUpperCase();
            var threadTypeBadge = clientThreadKindLabel(threadTypeRaw);
            var requestId = parseInt(thread.booking_id || thread.request_id || 0, 10);
            var displayTitle = threadTypeRaw === 'CARE'
                ? 'MedTravel Coordination'
                : cleanServiceTitle(thread.title || '');
            if (!displayTitle) {
                displayTitle = threadTypeRaw === 'CARE' ? 'MedTravel Coordination' : 'Talk to your Medical Provider';
            }
            var supportingText = clientThreadAudienceText(threadTypeRaw);
            var timeLabel = formatThreadTime(thread.updated_at || '');
            var previewText = getThreadPreviewText(thread);
            var unreadMeta = unread > 0 ? '<span class="badge badge-danger mt-unread">' + unread + '</span>' : '';
            var timeHtml = timeLabel ? '<div class="mt-time">' + esc(timeLabel) + '</div>' : '';
            var previewHtml = previewText ? '<div class="mt-thread-preview text-muted">Last: ' + esc(previewText) + '</div>' : '';
            var liClasses = 'mt-thread-item' + (active ? ' active' : '') + (unread > 0 ? ' unread' : '');

            html += '<li class="' + liClasses + '">' +
                '<a href="javascript:;" class="client-thread-link mt-thread-link"' +
                ' data-thread-id="' + esc(threadId) + '"' +
                ' data-thread-type="' + esc(thread.thread_type) + '"' +
                ' data-booking-id="' + esc(thread.booking_id || thread.request_id || 0) + '"' +
                ' data-item-id="' + esc(thread.item_id || 0) + '"' +
                ' data-item-type="' + esc(thread.item_type || '') + '">' +
                '<div class="mt-thread-row">' +
                    '<div class="mt-thread-main">' +
                        '<div class="mt-thread-title">' + esc(displayTitle) + '</div>' +
                        '<div class="mt-thread-sub">' +
                            '<span class="mt-thread-request">Request #' + esc(requestId > 0 ? String(requestId) : '-') + '</span>' +
                            '<span class="mt-dot">•</span>' +
                            '<span class="mt-thread-type">' + esc(supportingText) + '</span>' +
                        '</div>' +
                        previewHtml +
                    '</div>' +
                    '<div class="mt-thread-meta">' +
                        '<span class="badge badge-info mt-badge">' + esc(threadTypeBadge) + '</span>' +
                        unreadMeta +
                        timeHtml +
                    '</div>' +
                '</div>' +
                '</a>' +
                '</li>';
        });
        $list.html(html);

        var selected = null;
        for (var j = 0; j < visibleThreads.length; j++) {
            if (String(visibleThreads[j].thread_id || '') === selectedKey) {
                selected = visibleThreads[j];
                break;
            }
        }
        if (!selected) {
            selected = visibleThreads[0];
        }

        var changed = !currentThread || String(currentThread.thread_id || '') !== String(selected.thread_id || '');
        currentThread = {
            thread_id: String(selected.thread_id || ''),
            thread_type: String(selected.thread_type || 'CARE'),
            booking_id: parseInt(selected.booking_id || selected.request_id || 0, 10),
            item_id: parseInt(selected.item_id || 0, 10),
            item_type: String(selected.item_type || ''),
            thread_title: String(selected.title || ''),
            thread_subtitle: String(selected.subtitle || '')
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
            cancelledMeetingKeys = {};
            $box.html('<p class="text-muted" style="margin:0;">No messages in this thread yet.</p>');
            return;
        }

        annotateGrouping(messages, null);
        cancelledMeetingKeys = collectCancelledMeetingKeys(messages);
        var html = '';
        messages.forEach(function (m) {
            var bodyHtml = formatMessageBody(m.body || '');
            html += buildClientMsgHtml(m, bodyHtml);
        });

        $box.html(html);
        $box.scrollTop($box[0].scrollHeight);
        if (currentThread && currentThread.thread_id) {
            rememberLastMessageId(String(currentThread.thread_id), messages);
            rememberLastRenderedMeta(String(currentThread.thread_id), messages[messages.length - 1]);
        }
    }

    function appendMessages(messages, minId) {
        var $box = $('#client-inbox-messages');
        if (!$box.length) return;
        if (!messages || !messages.length) return;
        var threadId = currentThread && currentThread.thread_id ? String(currentThread.thread_id) : '';
        var html = '';
        var appended = false;
        var floorId = parseInt(minId || 0, 10);
        var nearBottom = shouldAutoScroll($box[0]);
        var lastMeta = getLastRenderedMeta(threadId);
        var filtered = [];
        messages.forEach(function (m) {
            var msgId = parseInt(m.id || 0, 10);
            if (isFinite(msgId) && floorId > 0 && msgId <= floorId) {
                return;
            }
            filtered.push(m);
        });
        if (!filtered.length) {
            return;
        }
        var hasMeetingCancellation = filtered.some(function (m) {
            return String(m && m.body ? m.body : '').trim().indexOf('[MEETING_CANCELLED]') === 0;
        });
        if (hasMeetingCancellation) {
            loadMessages();
            return;
        }
        annotateGrouping(filtered, lastMeta);
        filtered.forEach(function (m) {
            var bodyHtml = formatMessageBody(m.body || '');
            html += buildClientMsgHtml(m, bodyHtml);
            appended = true;
        });
        $box.find('p.text-muted').filter(function () {
            return String($(this).text() || '').indexOf('No messages in this thread yet.') !== -1;
        }).remove();
        $box.append(html);
        if (nearBottom) {
            $box.scrollTop($box[0].scrollHeight);
        }
        if (threadId) {
            rememberLastMessageId(threadId, messages);
            rememberLastRenderedMeta(threadId, filtered[filtered.length - 1]);
        }
    }

    function fetchNewMessages(threadId, sinceId) {
        var thread = String(threadId || '').trim();
        var lastId = parseInt(sinceId || 0, 10);
        if (!thread) return;
        if (!lastId || lastId <= 0) {
            loadMessages();
            return;
        }
        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list_messages',
                thread_id: thread,
                since_id: lastId
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                return;
            }
            var isCurrentCareThread = !!(
                currentThread &&
                String(currentThread.thread_id || '') === thread &&
                String(currentThread.thread_type || '').toUpperCase() === 'CARE'
            );
            if (isCurrentCareThread) {
                setStructuredCareAlert(
                    !!res.has_structured_item_actions,
                    res.request_id || currentThread.booking_id || 0,
                    res.structured_item_id || 0
                );
            }
            var newMessages = $.isArray(res.messages) ? res.messages : [];
            if (!newMessages.length) {
                return;
            }
            appendMessages(newMessages, lastId);
        });
    }

    function addPendingMessage(text) {
        if (!currentThread || !currentThread.thread_id) return '';
        var tempId = 'temp-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
        var msg = {
            id: tempId,
            _tempId: tempId,
            _status: 'Sending…',
            sender: 'client',
            body: text,
            time: new Date().toISOString()
        };
        appendMessages([msg], 0);
        return tempId;
    }

    function removePendingMessage(tempId) {
        if (!tempId) return;
        $('#client-inbox-messages').find('[data-temp-id="' + tempId + '"]').remove();
    }

    function updatePendingStatus(tempId, statusText) {
        if (!tempId) return;
        var $row = $('#client-inbox-messages').find('[data-temp-id="' + tempId + '"]');
        if (!$row.length) return;
        var $status = $row.find('.mt-bubble-status');
        if (!$status.length) {
            $row.find('.mt-msg-bubble').append('<div class="mt-bubble-status"></div>');
            $status = $row.find('.mt-bubble-status');
        }
        $status.text(statusText || '');
    }

    function emitTyping(state) {
        if (!currentThread || !currentThread.thread_id) return;
        if (!realtimeCanEmit(String(currentThread.thread_id))) return;
        realtimeState.socket.emit('typing', {
            thread_id: String(currentThread.thread_id),
            role: 'CLIENT',
            user_id: parseInt(config.userId || 0, 10) || 0,
            state: state,
            ts: Date.now()
        });
    }

    function handleLocalTyping() {
        var now = Date.now();
        if (now - typingState.lastEmitAt >= 2000) {
            emitTyping('start');
            typingState.lastEmitAt = now;
        }
        if (typingState.stopTimer) {
            clearTimeout(typingState.stopTimer);
        }
        typingState.stopTimer = setTimeout(function () {
            emitTyping('stop');
        }, 1500);
    }

    function showTypingIndicator(label) {
        if (!freeMessageAllowed) {
            return;
        }
        var $el = $('#client-typing-indicator');
        if (!$el.length) return;
        $el.text(label).show();
        if (typingState.remoteTimer) {
            clearTimeout(typingState.remoteTimer);
        }
        typingState.remoteTimer = setTimeout(function () {
            $el.hide();
        }, 2000);
    }

    function hideTypingIndicator() {
        var $el = $('#client-typing-indicator');
        if (!$el.length) return;
        $el.hide();
    }

    function typingLabelForRole(role) {
        var r = String(role || '').toLowerCase();
        if (r === 'provider') return 'Provider is typing…';
        if (r === 'admin' || r === 'patientcare') return 'Support is typing…';
        return 'Support is typing…';
    }

    function realtimeCanEmit(threadId) {
        return !!(
            realtimeEnabled() &&
            realtimeState.socket &&
            realtimeState.socket.connected &&
            realtimeState.lastThreadId === threadId
        );
    }

    function realtimeEmitCommitted(threadId, res, defaultRole) {
        var thread = String(threadId || '').trim();
        if (!thread || !realtimeCanEmit(thread)) {
            return;
        }
        var msg = res && res.message ? res.message : null;
        var msgId = msg ? parseInt(msg.id || 0, 10) : 0;
        if (!isFinite(msgId) || msgId <= 0) {
            return;
        }
        trackRecentSentMessage(msgId);
        var senderRole = String(defaultRole || 'CLIENT').toUpperCase();
        if (msg && msg.sender) {
            senderRole = String(msg.sender || senderRole).toUpperCase();
        }
        var createdAt = msg && msg.time ? String(msg.time) : new Date().toISOString();
        realtimeState.socket.emit('client_message_committed', {
            thread_id: thread,
            message_id: msgId,
            sender_role: senderRole,
            created_at: createdAt
        });
    }

    function refreshHeaderNotifications() {
        if (typeof window.clientReloadNotifications === 'function') {
            window.clientReloadNotifications();
        }
    }

    function setFeeGateState(enabled, message) {
        feeGateActive = !!enabled;
        var $alert = $('#client-inbox-fee-alert');
        var $actions = $('#client-inbox-fee-actions');
        if ($alert.length) {
            $alert.hide();
        }
        if ($actions.length) {
            if (feeGateActive) {
                $actions.show();
            } else {
                $actions.hide();
            }
        }
    }

    function setCommissionGateState(enabled, paid, message) {
        commissionGateActive = !!enabled && !paid;
        commissionPaid = !!paid;
        commissionGateMessage = String(message || commissionGateMessage || '');
        var $alert = $('#client-inbox-commission-alert');
        if ($alert.length) {
            $alert.hide();
        }
    }

    function setComposeGateState(canSendFreeMessage, noticeMessage) {
        freeMessageAllowed = !!canSendFreeMessage;
        var permissionBlocked = !freeMessageAllowed;
        var composeBlocked = permissionBlocked || composeBusy;
        if (typeof noticeMessage === 'string' && noticeMessage !== '') {
            lastComposeNotice = noticeMessage;
        }

        var $msg = $('#client-inbox-message');
        var $send = $('#client-inbox-send-btn');
        var $attach = $('#client-chat-attach-btn');
        var $composerGroup = $('#client-inbox-send-form .form-group');
        var $typing = $('#client-typing-indicator');
        if ($msg.length) {
            $msg.prop('disabled', composeBlocked);
        }
        if ($send.length) {
            $send.prop('disabled', composeBlocked);
        }
        if ($attach.length) {
            $attach.prop('disabled', composeBlocked);
        }
        if (permissionBlocked) {
            if ($composerGroup.length) $composerGroup.hide();
            if ($send.length) $send.hide();
            if ($typing.length) $typing.hide();
        } else {
            if ($composerGroup.length) $composerGroup.show();
            if ($send.length) $send.show();
            if ($typing.length) $typing.show();
        }

        var $note = $('#client-inbox-compose-note');
        if ($note.length) {
            if (composeBusy) {
                $note.text(composeBusyMessage || 'Uploading document...');
                $note.show();
            } else if (!freeMessageAllowed) {
                $note.text(noticeMessage || lastComposeNotice || 'Free-form chat is blocked by a commercial condition. Formal actions remain available where applicable.');
                $note.show();
            } else {
                $note.hide();
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

        if (trimmed.indexOf('[REQUEST_INFO]') === 0) {
            return renderRequestInfoCard(trimmed);
        }
        if (trimmed.indexOf('[PROPOSE_QUOTE]') === 0) {
            return renderProposeQuoteCard(trimmed);
        }
        if (trimmed.indexOf('[PROPOSAL_RESPONSE]') === 0) {
            return renderProposalResponseCard(trimmed);
        }
        if (hasStructuredPrefix(trimmed, '[MEETING_PROPOSAL]')) {
            return renderMeetingProposalCard(trimmed, currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0);
        }
        if (hasStructuredPrefix(trimmed, '[MEETING_CONFIRMED]')) {
            return renderMeetingConfirmedCard(trimmed);
        }
        if (hasStructuredPrefix(trimmed, '[MEETING_CANCELLED]')) {
            return renderMeetingCancelledCard(trimmed);
        }
        if (/^\[ACTION\]\s*Client rejected proposed dates$/i.test(trimmed)) {
            return renderMeetingChangeRequestedCard();
        }

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
            var parsedShared = parseSharedDocumentMessage(text);
            if (parsedShared.entries.length) {
                var bodyHtml = parsedShared.body
                    ? '<div style="white-space:pre-wrap;">' + esc(parsedShared.body) + '</div>'
                    : '';
                return bodyHtml + renderSharedDocumentsBlock(parsedShared.entries);
            }
            if (isStructuredSystemText(text)) {
                return renderStructuredSummaryFallback(text);
            }
            return '<span style="white-space:pre-wrap;">' + esc(text) + '</span>';
        }

        var isItemThread = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;
        if (hasStructuredPrefix(trimmed, '[MEETING_PROPOSAL]')) {
            return renderMeetingProposalCard(trimmed, isItemThread);
        }
        if (hasStructuredPrefix(trimmed, '[MEETING_CONFIRMED]')) {
            return renderMeetingConfirmedCard(trimmed);
        }
        if (hasStructuredPrefix(trimmed, '[MEETING_CANCELLED]')) {
            return renderMeetingCancelledCard(trimmed);
        }
        if (isReply && trimmed.toUpperCase().indexOf('PROPOSED_DATES') === 0) {
            return renderMeetingProposalCard(trimmed, isItemThread);
        }

        var entryMeta = parseReplyTokenAndNote(trimmed);
        var replyToken = String(entryMeta.token || '').trim();
        var replyNote = String(entryMeta.note || '').trim();
        var visibleBody = isReply
            ? (clientVisibleReplyLabel(replyToken) || replyToken || trimmed)
            : (clientVisibleActionLabel(replyToken) || replyToken || trimmed);
        var messageHtml = '<span class="label label-primary" style="margin-right:6px;">' + esc(label) + '</span>' + esc(visibleBody);
        if (replyNote) {
            messageHtml += '<div style="margin-top:8px;white-space:pre-wrap;">' + esc(replyNote) + '</div>';
        }
        var structuredReplyUpper = replyToken.toUpperCase();
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
            if (structuredReplyUpper.indexOf('DATES NOT AVAILABLE') !== -1 && isItemThread) {
                messageHtml += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-propose-new-dates" title="Propose new dates">' +
                        'PROPOSE NEW DATES' +
                    '</button>' +
                    '</div>';
            }
        }

        if (isReply && structuredReplyUpper.indexOf('FINAL_APPROVED') !== -1 && feeGateActive) {
            messageHtml += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' +
                '<button type="button" class="btn btn-default btn-xs client-final-action" data-action="final_accept_and_pay">ACCEPT NEXT STEP</button>' +
                '<button type="button" class="btn btn-default btn-xs client-final-action" data-action="final_decline">DECLINE</button>' +
                '</div>';
        }

        return messageHtml;
    }

    function summarizeInboxText(rawText, maxLength) {
        if (inboxPresenter && typeof inboxPresenter.summarizeStructuredMessage === 'function') {
            return inboxPresenter.summarizeStructuredMessage(rawText, {
                audience: 'client',
                maxLength: maxLength || 0
            });
        }
        var text = String(rawText || '').replace(/\s+/g, ' ').trim();
        if (!text) {
            return '';
        }
        if (maxLength && text.length > maxLength) {
            return text.slice(0, maxLength).trim() + '…';
        }
        return text;
    }

    function isStructuredSystemText(rawText) {
        return /^\s*(?:\[(?:ACTION|REPLY)\]\s*)?\[[A-Z0-9_]+\]/i.test(String(rawText || ''));
    }

    function renderStructuredSummaryFallback(rawText) {
        var summary = summarizeInboxText(rawText, 0) || 'System update';
        return '<span style="white-space:pre-wrap;">' + esc(summary) + '</span>';
    }

    function parseStructuredJson(prefix, fullText) {
        if (inboxPresenter && typeof inboxPresenter.parseStructuredJson === 'function') {
            return inboxPresenter.parseStructuredJson(prefix, fullText);
        }
        var jsonText = stripStructuredPrefix(fullText, prefix);
        if (!jsonText) {
            return null;
        }
        return parseStructuredPayload(jsonText);
    }

    function parseReplyTokenAndNote(text) {
        if (inboxPresenter && typeof inboxPresenter.parseReplyTokenAndNote === 'function') {
            return inboxPresenter.parseReplyTokenAndNote(text);
        }
        var source = String(text || '').trim();
        if (!source) {
            return { token: '', note: '' };
        }
        var parts = source.split(/\r?\n+/);
        var token = String(parts.shift() || '').trim();
        var note = $.trim(parts.join('\n'));
        return {
            token: token,
            note: note
        };
    }

    function escapeRegExp(text) {
        return String(text || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function hasStructuredPrefix(fullText, prefix) {
        if (inboxPresenter && typeof inboxPresenter.hasStructuredPrefix === 'function') {
            return inboxPresenter.hasStructuredPrefix(fullText, prefix);
        }
        var source = String(fullText || '').trim();
        if (!source) {
            return false;
        }
        var prefixPattern = escapeRegExp(prefix);
        return new RegExp('^(?:\\[(?:ACTION|REPLY)\\]\\s*)?' + prefixPattern + '(?:\\s|$)', 'i').test(source);
    }

    function stripStructuredPrefix(fullText, prefix) {
        if (inboxPresenter && typeof inboxPresenter.stripStructuredPrefix === 'function') {
            return inboxPresenter.stripStructuredPrefix(fullText, prefix);
        }
        var source = String(fullText || '').trim();
        if (!hasStructuredPrefix(source, prefix)) {
            return '';
        }
        var prefixPattern = escapeRegExp(prefix);
        return source.replace(new RegExp('^(?:\\[(?:ACTION|REPLY)\\]\\s*)?' + prefixPattern + '\\s*', 'i'), '').trim();
    }

    function parseStructuredPayload(text) {
        var candidate = String(text || '').trim();
        var depth = 0;

        while (candidate && depth < 3) {
            try {
                var parsed = JSON.parse(candidate);
                if (parsed && typeof parsed === 'object') {
                    return parsed;
                }
                if (typeof parsed === 'string') {
                    candidate = parsed.trim();
                    depth += 1;
                    continue;
                }
                return null;
            } catch (e) {
                break;
            }
        }

        var firstBrace = candidate.indexOf('{');
        var lastBrace = candidate.lastIndexOf('}');
        if (firstBrace === -1 || lastBrace <= firstBrace) {
            return null;
        }

        try {
            var sliced = JSON.parse(candidate.slice(firstBrace, lastBrace + 1));
            return sliced && typeof sliced === 'object' ? sliced : null;
        } catch (e2) {
            return null;
        }
    }

    function formatInboxDateTime(value) {
        var raw = String(value || '').trim();
        if (!raw) {
            return '';
        }
        var normalized = raw.replace(' ', 'T');
        var parsed = new Date(normalized);
        if (isNaN(parsed.getTime())) {
            return raw;
        }
        return parsed.toLocaleString('es-CO', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function meetingIntegrationMeta(mode) {
        var normalized = String(mode || 'calendar_plus_meet').trim().toLowerCase();
        var map = {
            internal_only: {
                label: 'Reunión interna MedTravel',
                hint: 'No crea Google Calendar ni Google Meet.',
                badge: 'MedTravel',
                badgeClass: 'label-default'
            },
            calendar_only: {
                label: 'Reunión con Google Calendar',
                hint: 'Al aceptar se creará un evento en Google Calendar, sin Meet.',
                badge: 'Calendar',
                badgeClass: 'label-info'
            },
            calendar_plus_meet: {
                label: 'Reunión con Google Meet',
                hint: 'Al aceptar se creará un evento en Google Calendar con Google Meet.',
                badge: 'Calendar + Meet',
                badgeClass: 'label-success'
            }
        };
        return map[normalized] || map.calendar_plus_meet;
    }

    function meetingEventKeyFromPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return '';
        }
        var eventId = String(payload.event_id || '').trim();
        if (eventId) {
            return 'g:' + eventId;
        }
        var calendarEventId = parseInt(payload.calendar_event_id || 0, 10) || 0;
        if (calendarEventId > 0) {
            return 'c:' + String(calendarEventId);
        }
        return '';
    }

    function collectCancelledMeetingKeys(messages) {
        var map = {};
        (messages || []).forEach(function (message) {
            var payload = parseStructuredJson('[MEETING_CANCELLED]', message && message.body ? message.body : '');
            var key = meetingEventKeyFromPayload(payload);
            if (key) {
                map[key] = true;
            }
        });
        return map;
    }

    function isMeetingCancelledPayload(payload) {
        var key = meetingEventKeyFromPayload(payload);
        return !!(key && cancelledMeetingKeys[key]);
    }

    function parseMeetingProposalText(text) {
        var source = String(text || '').trim();
        var match = source.match(/^PROPOSED_DATES\s+(.+?)\s+to\s+(.+)$/i);
        if (!match) {
            return null;
        }
        return {
            startAt: String(match[1] || '').trim(),
            endAt: String(match[2] || '').trim()
        };
    }

    function parseMeetingProposalPayload(fullText) {
        var payload = parseStructuredJson('[MEETING_PROPOSAL]', fullText);
        if (payload) {
            return {
                startAt: String(payload.start_at || payload.startAt || '').trim(),
                endAt: String(payload.end_at || payload.endAt || '').trim(),
                note: String(payload.note || payload.notes || '').trim(),
                integrationMode: String(payload.integration_mode || payload.integrationMode || 'calendar_plus_meet').trim().toLowerCase()
            };
        }

        var proposal = parseMeetingProposalText(String(fullText || '').replace(/^\[REPLY\]\s*/i, ''));
        if (!proposal) {
            return null;
        }
        return {
            startAt: proposal.startAt,
            endAt: proposal.endAt,
            note: '',
            integrationMode: 'calendar_plus_meet'
        };
    }

    function renderMeetingProposalCard(fullText, actionable) {
        var proposal = parseMeetingProposalPayload(fullText);
        if (!proposal) {
            return renderStructuredSummaryFallback(fullText);
        }
        var integration = meetingIntegrationMeta(proposal.integrationMode);

        var actionsHtml = '';
        if (actionable) {
            actionsHtml = '<div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;">' +
                '<button type="button" class="btn btn-success btn-xs client-date-action" data-action="accept_dates">Accept meeting</button>' +
                '<button type="button" class="btn btn-default btn-xs client-date-action" data-action="reject_dates">Request change</button>' +
                '</div>';
        }

        return '<div class="panel panel-warning" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>' + esc(integration.label) + '</strong> <span class="label ' + esc(integration.badgeClass) + '" style="margin-left:6px;">' + esc(integration.badge) + '</span></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                '<div><strong>Inicio:</strong> ' + esc(formatInboxDateTime(proposal.startAt)) + '</div>' +
                '<div style="margin-top:6px;"><strong>Fin:</strong> ' + esc(formatInboxDateTime(proposal.endAt)) + '</div>' +
                '<div style="margin-top:6px;"><strong>Tipo:</strong> ' + esc(integration.label) + '</div>' +
                (proposal.note ? '<div style="margin-top:6px;"><strong>Nota:</strong> ' + esc(proposal.note) + '</div>' : '') +
                '<div class="text-muted" style="margin-top:8px;">' + esc(integration.hint) + '</div>' +
                actionsHtml +
            '</div>' +
        '</div>';
    }

    function renderMeetingChangeRequestedCard() {
        return '<div class="panel panel-info" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>Cambio solicitado</strong></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                '<div>Has pedido ajustar la propuesta de reunión. El proveedor podrá enviarte una nueva fecha desde Inbox ITEM.</div>' +
            '</div>' +
        '</div>';
    }

    function normalizeDocTypeLabel(t) {
        var map = {
            lab_results: 'Exam / lab result',
            labs: 'Exam / lab result',
            diagnostic_imaging: 'Diagnostic image',
            imaging: 'Diagnostic image',
            photos: 'Clinical image',
            medical_history: 'Medical history',
            quote: 'Quote / estimate',
            consent_form: 'Consent form',
            medical_order: 'Medical order',
            prescription: 'Prescription / indication',
            administrative_document: 'Administrative document',
            other: 'Other'
        };
        var key = normalizeDocumentTypeKey(t || 'other');
        return map[key] || key || 'Other';
    }

    function renderRequestInfoCard(fullText) {
        var payload = parseStructuredJson('[REQUEST_INFO]', fullText);
        if (!payload) {
            return renderStructuredSummaryFallback(fullText);
        }
        var types = $.isArray(payload.required_types) ? payload.required_types : [];
        var note = String(payload.note || '').trim();
        var listHtml = types.length
            ? ('<ul style="margin:6px 0 0 18px;padding:0;">' + types.map(function (t) {
                return '<li>' + esc(normalizeDocTypeLabel(t)) + '</li>';
            }).join('') + '</ul>')
            : '<div class="text-muted">No specific document type provided.</div>';

        var actionButtons = '<div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;">' +
            '<button type="button" class="btn btn-default btn-xs client-request-info-response" data-response="DOCS_UPLOADED">I\'ve uploaded the documents</button>' +
            '<button type="button" class="btn btn-default btn-xs client-request-info-response" data-response="DOCS_NOT_AVAILABLE">I don\'t have them</button>' +
            '</div>';

        return '<div class="panel panel-default" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>Provider requested additional information</strong></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                '<div><strong>Requested types:</strong></div>' +
                listHtml +
                (note ? '<div style="margin-top:8px;"><strong>Note:</strong> ' + esc(note) + '</div>' : '') +
                actionButtons +
            '</div>' +
        '</div>';
    }

    function renderProposeQuoteCard(fullText) {
        var payload = parseStructuredJson('[PROPOSE_QUOTE]', fullText);
        if (!payload) {
            return renderStructuredSummaryFallback(fullText);
        }
        var amount = String(payload.amount || '').trim();
        var currency = String(payload.currency || 'USD').trim().toUpperCase() || 'USD';
        var notes = String(payload.notes || '').trim();

        var actionsHtml = '<div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;">' +
            '<button type="button" class="btn btn-default btn-xs client-proposal-response" data-action-type="ACCEPT_PROPOSAL">ACCEPT_PROPOSAL</button>' +
            '<button type="button" class="btn btn-default btn-xs client-proposal-response" data-action-type="REQUEST_CHANGES">REQUEST_CHANGES</button>' +
            '<button type="button" class="btn btn-default btn-xs client-proposal-response" data-action-type="REJECT_PROPOSAL">REJECT_PROPOSAL</button>' +
            '</div>';

        return '<div class="panel panel-default" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>Provider quote adjustment</strong></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                '<div><strong>Amount:</strong> ' + esc(amount || '0.00') + ' ' + esc(currency) + '</div>' +
                (notes ? '<div style="margin-top:8px;"><strong>Notes:</strong> ' + esc(notes) + '</div>' : '') +
                actionsHtml +
            '</div>' +
        '</div>';
    }

    function renderProposalResponseCard(fullText) {
        var payload = parseStructuredJson('[PROPOSAL_RESPONSE]', fullText);
        if (!payload) {
            return renderStructuredSummaryFallback(fullText);
        }
        var actionType = String(payload.action_type || '').toUpperCase();
        var notes = String(payload.notes || '').trim();
        var meta = clientProposalResponseMeta(actionType);
        return '<div class="panel panel-default" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>' + esc(meta.title) + '</strong></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                '<div><strong>Action:</strong> ' + esc(meta.label) + '</div>' +
                (notes ? '<div style="margin-top:8px;"><strong>Notes:</strong> ' + esc(notes) + '</div>' : '') +
            '</div>' +
        '</div>';
    }

    function renderMeetingConfirmedCard(fullText) {
        var payload = parseStructuredJson('[MEETING_CONFIRMED]', fullText);
        if (!payload) {
            return renderStructuredSummaryFallback(fullText);
        }
        var integration = meetingIntegrationMeta(payload.integration_mode || (payload.meet_url ? 'calendar_plus_meet' : (payload.html_link ? 'calendar_only' : 'internal_only')));
        var isCancelled = isMeetingCancelledPayload(payload);
        var canCancel = !isCancelled && currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;

        var actionsHtml = '<div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;">';
        if (!isCancelled && payload.meet_url) {
            actionsHtml += '<a class="btn btn-success btn-xs" href="' + esc(payload.meet_url) + '" target="_blank" rel="noopener">OPEN MEET</a>';
        }
        if (!isCancelled && payload.html_link) {
            actionsHtml += '<a class="btn btn-default btn-xs" href="' + esc(payload.html_link) + '" target="_blank" rel="noopener">OPEN EVENT</a>';
        }
        if (canCancel) {
            actionsHtml += '<button type="button" class="btn btn-danger btn-xs client-meeting-cancel">Cancel meeting</button>';
        }
        actionsHtml += '</div>';

        return '<div class="panel ' + (isCancelled ? 'panel-warning' : 'panel-success') + '" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>' + esc(isCancelled ? 'Meeting cancelled' : 'Meeting confirmed') + '</strong> <span class="label ' + esc(integration.badgeClass) + '" style="margin-left:6px;">' + esc(integration.badge) + '</span></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                (payload.start_at ? '<div><strong>Start:</strong> ' + esc(formatInboxDateTime(payload.start_at)) + '</div>' : '') +
                (payload.end_at ? '<div style="margin-top:6px;"><strong>End:</strong> ' + esc(formatInboxDateTime(payload.end_at)) + '</div>' : '') +
                '<div style="margin-top:6px;"><strong>Type:</strong> ' + esc(integration.label) + '</div>' +
                (payload.organizer_email ? '<div style="margin-top:6px;"><strong>Organizer:</strong> ' + esc(payload.organizer_email) + '</div>' : '') +
                '<div class="text-muted" style="margin-top:8px;">' + esc(isCancelled ? 'This meeting was cancelled. The case remains active and can be rescheduled later.' : integration.hint) + '</div>' +
                actionsHtml +
            '</div>' +
        '</div>';
    }

    function renderMeetingCancelledCard(fullText) {
        var payload = parseStructuredJson('[MEETING_CANCELLED]', fullText);
        if (!payload) {
            return renderStructuredSummaryFallback(fullText);
        }
        var integration = meetingIntegrationMeta(payload.integration_mode || 'calendar_plus_meet');
        var cancelledByRole = String(payload.cancelled_by_role || '').trim().toUpperCase();
        var byLabel = 'the team';
        if (cancelledByRole === 'CLIENT') {
            byLabel = 'the patient';
        } else if (cancelledByRole === 'PROVIDER') {
            byLabel = 'the provider';
        } else if (cancelledByRole === 'ADMIN') {
            byLabel = 'coordination';
        }

        return '<div class="panel panel-warning" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>Meeting cancelled</strong> <span class="label ' + esc(integration.badgeClass) + '" style="margin-left:6px;">' + esc(integration.badge) + '</span></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                (payload.start_at ? '<div><strong>Start:</strong> ' + esc(formatInboxDateTime(payload.start_at)) + '</div>' : '') +
                (payload.end_at ? '<div style="margin-top:6px;"><strong>End:</strong> ' + esc(formatInboxDateTime(payload.end_at)) + '</div>' : '') +
                '<div style="margin-top:6px;"><strong>Status:</strong> Cancelled by ' + esc(byLabel) + '</div>' +
                '<div class="text-muted" style="margin-top:8px;">The case remains active. A new meeting can be proposed later.</div>' +
            '</div>' +
        '</div>';
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

    $('#client-inbox-messages').on('click', '.client-date-action', function () {
        var action = ($(this).data('action') || '').toString();
        if (!action) {
            return;
        }
        sendDateDecision(action);
    });

    $('#client-inbox-messages').on('click', '.client-meeting-cancel', function () {
        sendMeetingCancellation();
    });

    $('#client-inbox-messages').on('click', '.client-final-action', function () {
        var action = ($(this).data('action') || '').toString();
        if (!action) {
            return;
        }
        sendFinalDecision(action);
    });

    $('#client-inbox-messages').on('click', '.client-propose-new-dates', function () {
        openProposeDatesModal();
    });

    $('#client-submit-propose-dates').on('click', function () {
        submitProposeDates();
    });

    $('#client-inbox-messages').on('click', '.client-request-info-response', function () {
        var response = String($(this).data('response') || '').toUpperCase();
        if (!response) {
            return;
        }
        if (response === 'DOCS_NOT_AVAILABLE') {
            sendProposalResponse(response);
            return;
        }
        sendQuickAction(response);
        if (response === 'DOCS_UPLOADED') {
            var feeActions = $('#client-inbox-fee-actions');
            if (feeActions.length) {
                $('html, body').animate({ scrollTop: feeActions.offset().top - 20 }, 200);
            }
        }
    });

    $('#client-inbox-messages').on('click', '.client-proposal-response', function () {
        var actionType = String($(this).data('action-type') || '').toUpperCase();
        if (!actionType) {
            return;
        }
        sendProposalResponse(actionType);
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

        var activeThreadId = String(currentThread.thread_id || '');
        if (currentDocumentsThreadId !== activeThreadId) {
            currentDocuments = [];
            currentDocumentsThreadId = activeThreadId;
        }

        realtimeJoinThread(currentThread.thread_id);
        hideTypingIndicator();

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
            var isCareThread = String(currentThread.thread_type || '').toUpperCase() === 'CARE';
            setFeeGateState(
                isCareThread ? false : feeLocked,
                res.fee_message || 'Messaging remains available here. The coordination fee may still unlock additional downstream steps.'
            );
            var commissionGateEnabled = parseInt(res.commission_gate_enabled || 0, 10) === 1;
            var commissionPaidFlag = parseInt(res.commission_paid || 0, 10) === 1;
            setCommissionGateState(commissionGateEnabled, commissionPaidFlag, res.commission_message || '');
            var canSendFreeMessage = (typeof res.can_send_free_message === 'boolean') ? res.can_send_free_message : !feeLocked;
            var effectiveCanSendFreeMessage = isCareThread ? true : canSendFreeMessage;
            var composeNotice = isCareThread ? '' : (res.free_message_notice || '');
            setComposeGateState(effectiveCanSendFreeMessage, composeNotice);
            var hasStructuredItemActions = !!res.has_structured_item_actions;
            setStructuredCareAlert(
                isCareThread && hasStructuredItemActions,
                res.request_id || currentThread.booking_id || 0,
                res.structured_item_id || 0
            );
            var freshDocs = $.isArray(res.documents) ? res.documents : [];
            var freshIds = freshDocs.map(function (d) { return parseInt(d.id || 0, 10); });
            var localOnly = currentDocuments.filter(function (d) {
                var id = parseInt(d.id || 0, 10);
                return id > 0 && freshIds.indexOf(id) === -1;
            });
            currentDocuments = freshDocs.concat(localOnly);
            syncThreadDocumentsPanel();

            renderInboxHeader(
                $('#client-inbox-title'),
                clientThreadHeading(currentThread),
                currentThread.booking_id,
                clientThreadSubtitle(currentThread)
            );
            renderMessages(res.messages || []);
            markCurrentThreadRead();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            setStructuredCareAlert(false, 0, 0);
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true, 'Messaging remains available here. The coordination fee may still unlock additional downstream steps.');
                setComposeGateState(true, '');
                renderInboxHeader(
                    $('#client-inbox-title'),
                    careDisplayTitle(),
                    currentThread && currentThread.booking_id ? currentThread.booking_id : 0,
                    clientThreadAudienceText('CARE')
                );
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                var isCareBlocked = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'CARE';
                if (isCareBlocked) {
                    setComposeGateState(true, '');
                } else {
                    setComposeGateState(false, lastComposeNotice || 'Free-form messaging is locked right now. Please use the structured actions above.');
                }
                return;
            }
            toastr.error('Could not load messages');
        });
    }

    function uploadChatDocuments(docs) {
        var deferred = $.Deferred();
        if (!currentThread || !currentThread.thread_id) {
            deferred.reject({ message: 'Select a thread before uploading' });
            return deferred.promise();
        }
        var payloadDocs = $.isArray(docs) ? docs.filter(function (doc) {
            return doc && doc.file;
        }) : [];
        if (!payloadDocs.length) {
            deferred.resolve({ ok: true, uploaded_count: 0, results: [] });
            return deferred.promise();
        }

        var safeRequestId = resolveCurrentRequestId();
        if (safeRequestId <= 0) {
            deferred.reject({ message: 'Could not determine request id for upload' });
            return deferred.promise();
        }

        var formData = new FormData();
        var metaArray = [];
        payloadDocs.forEach(function (doc) {
            formData.append('client_doc_files[]', doc.file);
            metaArray.push({
                doc_type: normalizeDocumentTypeKey(doc.document_type || 'other'),
                title: String(doc.title || '').trim(),
                description: String(doc.note || '').trim(),
                original_name: String((doc.file && doc.file.name) || '')
            });
        });
        formData.append('meta_json', JSON.stringify(metaArray));
        formData.append('document_type', (metaArray[0].doc_type || 'other').toString());
        formData.append('title', (metaArray[0].title || '').toString());
        formData.append('description', (metaArray[0].description || '').toString());
        formData.append('booking_request_id', safeRequestId);
        formData.append('request_id', safeRequestId);
        formData.append('item_id', currentThread.item_id || 0);
        formData.append('thread_type', currentThread.thread_type || 'CARE');

        $.ajax({
            url: '/client/ajax/upload_medical_document.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                deferred.reject(res || { message: 'Upload failed. Please try again.' });
                return;
            }
            deferred.resolve(res);
        }).fail(function (xhr) {
            deferred.reject((xhr && xhr.responseJSON) ? xhr.responseJSON : { message: 'Upload failed. Please try again.' });
        });

        return deferred.promise();
    }

    function sendMessageText(text, opts) {
        var deferred = $.Deferred();
        if (!currentThread || !currentThread.thread_id) {
            deferred.reject({ message: 'Select a thread first' });
            return deferred.promise();
        }
        if (composeBusy) {
            deferred.reject({ message: 'The message composer is busy' });
            return deferred.promise();
        }
        var options = opts && typeof opts === 'object' ? opts : {};
        clearLegacyUploadStatus();
        if (!freeMessageAllowed) {
            toastr.warning(lastComposeNotice || 'Free-form messaging is locked right now. Please use the structured actions above.');
            deferred.reject({ code: 'FREE_MESSAGE_BLOCKED', message: lastComposeNotice || 'Free-form messaging is locked right now.' });
            return deferred.promise();
        }
        if (commissionGateActive) {
            toastr.warning('Commission payment required');
            deferred.reject({ code: 'COMMISSION_REQUIRED', message: 'Commission payment required' });
            return deferred.promise();
        }

        var pendingId = addPendingMessage(text);
        emitTyping('stop');
        setComposeBusy(true, options.busyMessage || 'Sending message...');

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
                setComposeBusy(false, '');
                toastr.error((res && res.message) ? res.message : 'Could not send message');
                updatePendingStatus(pendingId, 'Failed');
                deferred.reject(res || { message: 'Could not send message' });
                return;
            }
            var sentId = res && res.message ? res.message.id : 0;
            if (!sentId && res && res.message_id) {
                sentId = res.message_id;
            }
            if (!sentId) {
                sentId = extractMaxMessageId(res.messages || []);
            }
            if (sentId) {
                setMessageStatus(sentId, 'Sent');
            }
            removePendingMessage(pendingId);
            realtimeEmitCommitted(currentThread.thread_id, res, 'CLIENT');
            if (options.clearComposer !== false) {
                $('#client-inbox-message').val('');
            }
            toastr.success(options.successToast || 'Message sent');
            setComposeBusy(false, '');
            loadMessages();
            loadThreads();
            deferred.resolve(res);
        }).fail(function (xhr) {
            setComposeBusy(false, '');
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            updatePendingStatus(pendingId, 'Failed');
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true, 'Messaging remains available here. The coordination fee may still unlock additional downstream steps.');
                setComposeGateState(true, '');
                toastr.warning('Coordination fee required');
                deferred.reject(res);
                return;
            }
            if (res && res.code === 'COMMISSION_REQUIRED') {
                setCommissionGateState(true, false, res.message || 'Messaging remains available in Inbox. The commission payment may still unlock provider details or other downstream steps.');
                setComposeGateState(true, '');
                toastr.warning('Commission payment required');
                deferred.reject(res);
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                var isCareBlocked = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'CARE';
                if (isCareBlocked) {
                    setComposeGateState(true, '');
                } else {
                    setComposeGateState(false, lastComposeNotice || 'Free-form messaging is locked right now. Please use the structured actions above.');
                }
                deferred.reject(res);
                return;
            }
            toastr.error('Could not send message');
            deferred.reject(res || { message: 'Could not send message' });
        });
        return deferred.promise();
    }

    function sendMessage() {
        if (composeBusy) {
            return;
        }
        var text = $.trim($('#client-inbox-message').val() || '');
        if (!text) {
            toastr.warning('Write a message before sending');
            return;
        }
        sendMessageText(text);
    }

    function submitAttachDocument() {
        if (attachModalBusy || composeBusy) {
            return;
        }
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread first');
            return;
        }
        var fileInput = document.getElementById('client-attach-file');
        var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        var title = $.trim($('#client-attach-title').val() || '');
        var type = normalizeDocumentTypeKey($('#client-attach-type').val() || 'other');
        var note = $.trim($('#client-attach-note').val() || '');
        if (!file) {
            toastr.warning('Select a file');
            return;
        }
        if (!title) {
            toastr.warning('Enter the document title');
            return;
        }

        var docs = [{
            file: file,
            title: title,
            document_type: type,
            note: note
        }];
        setAttachModalBusy(true);
        setAttachStatus('');
        setComposeBusy(true, 'Attaching document...');
        uploadChatDocuments(docs).done(function (uploadRes) {
            var messageText = buildSharedDocumentMessage(uploadRes && uploadRes.results ? uploadRes.results : []);
            setComposeBusy(false, '');
            sendMessageText(messageText, {
                busyMessage: 'Posting document...',
                successToast: 'Document attached to the chat',
                clearComposer: false
            }).done(function () {
                mergeUploadedDocuments(uploadRes || null);
                syncThreadDocumentsPanel();
                setAttachStatus('Document attached to the chat.', 'success');
                setAttachModalBusy(false);
                $('#clientAttachDocumentModal').modal('hide');
            }).fail(function (res) {
                setAttachModalBusy(false);
                var failureMessage = (res && res.message) ? String(res.message) : 'The file was uploaded, but the chat message could not be published.';
                setAttachStatus(failureMessage, 'danger');
            });
        }).fail(function (res) {
            setComposeBusy(false, '');
            setAttachModalBusy(false);
            var errorMessage = describeUploadError(res);
            setAttachStatus(errorMessage, 'danger');
            toastr.error(errorMessage);
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
            markSentFromResponse(res);
            realtimeEmitCommitted(currentThread.thread_id, res, 'CLIENT');
            toastr.success('Quick action sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not send quick action');
        });
    }

    function sendProposalResponse(actionType) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before responding');
            return;
        }
        if (String(currentThread.thread_type || '').toUpperCase() !== 'ITEM') {
            toastr.warning('Open a service thread to respond');
            return;
        }
        var allowed = ['ACCEPT_PROPOSAL', 'REQUEST_CHANGES', 'REJECT_PROPOSAL', 'DOCS_NOT_AVAILABLE'];
        var normalized = String(actionType || '').toUpperCase();
        if (allowed.indexOf(normalized) === -1) {
            toastr.error('Invalid proposal response');
            return;
        }
        var successMessage = normalized === 'DOCS_NOT_AVAILABLE'
            ? 'Document response sent'
            : 'Response sent';
        var errorMessage = normalized === 'DOCS_NOT_AVAILABLE'
            ? 'Could not send document response'
            : 'Could not send proposal response';

        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_structured_action',
                thread_id: currentThread.thread_id,
                action_type: normalized,
                notes: ''
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : errorMessage);
                return;
            }
            markSentFromResponse(res);
            realtimeEmitCommitted(currentThread.thread_id, res, 'CLIENT');
            toastr.success(successMessage);
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error(errorMessage);
        });
    }

    function openProposeDatesModal() {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before responding');
            return;
        }
        var isItemThread = String(currentThread.thread_type || '').toUpperCase() === 'ITEM' &&
            parseInt(currentThread.item_id || 0, 10) > 0;
        if (!isItemThread) {
            toastr.warning('Open a service thread to continue');
            return;
        }
        var $modal = $('#clientProposeDatesModal');
        if (!$modal.length) {
            toastr.error('Date proposal form is unavailable');
            return;
        }
        $('#client-proposed-date-from').val('');
        $('#client-proposed-date-to').val('');
        $('#client-proposed-notes').val('');
        toastr.info('Opening date proposal...');
        $modal.modal('show');
    }

    function submitProposeDates() {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before responding');
            return;
        }
        var isItemThread = String(currentThread.thread_type || '').toUpperCase() === 'ITEM' &&
            parseInt(currentThread.item_id || 0, 10) > 0;
        if (!isItemThread) {
            toastr.warning('Open a service thread to continue');
            return;
        }
        var dateFrom = $.trim($('#client-proposed-date-from').val() || '');
        var dateTo = $.trim($('#client-proposed-date-to').val() || '');
        var notes = $.trim($('#client-proposed-notes').val() || '');
        if (!dateFrom && !dateTo && !notes) {
            toastr.warning('Add at least a date or note');
            return;
        }
        if (dateFrom && dateTo && dateFrom > dateTo) {
            toastr.warning('Date range is invalid');
            return;
        }
        if (notes.length > 500) {
            toastr.warning('Notes are too long');
            return;
        }
        var message = '[ACTION] PROPOSE_NEW_DATES';
        if (dateFrom || dateTo) {
            message += ' ' + (dateFrom || '-') + ' to ' + (dateTo || '-');
        }
        if (notes) {
            message += ' | Notes: ' + notes;
        }
        if (message.length > 2000) {
            toastr.warning('Notes are too long');
            return;
        }

        var $btn = $('#client-submit-propose-dates');
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).text('Sending...');

        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_message',
                thread_id: currentThread.thread_id,
                message: message
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not send proposal');
                return;
            }
            markSentFromResponse(res);
            realtimeEmitCommitted(currentThread.thread_id, res, 'CLIENT');
            $('#clientProposeDatesModal').modal('hide');
            toastr.success('Proposal sent');
            loadMessages();
            loadThreads();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true, 'Messaging remains available here. The coordination fee may still unlock additional downstream steps.');
                setComposeGateState(true, '');
                toastr.warning('Coordination fee required');
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                setComposeGateState(false, lastComposeNotice || 'Free-form messaging is locked right now. Please use the structured actions above.');
                return;
            }
            toastr.error((res && res.message) ? res.message : 'Could not send proposal');
        }).always(function () {
            $btn.prop('disabled', false).html(originalHtml);
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
            markSentFromResponse(res);
            realtimeEmitCommitted(currentThread.thread_id, res, 'CLIENT');
            toastr.success('Response sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not update dates');
        });
    }

    function sendMeetingCancellation() {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Select a thread before responding');
            return;
        }
        if (!window.confirm('Cancel this meeting? The case will remain active so it can be rescheduled later.')) {
            return;
        }

        $.ajax({
            url: '/client/ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'cancel_meeting',
                thread_id: currentThread.thread_id,
                thread_type: 'ITEM',
                item_id: parseInt(currentThread.item_id || 0, 10)
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'Could not cancel the meeting');
                return;
            }
            markSentFromResponse(res);
            realtimeEmitCommitted(currentThread.thread_id, res, 'CLIENT');
            toastr.success('Meeting cancelled');
            loadMessages();
            loadThreads();
        }).fail(function () {
            toastr.error('Could not cancel the meeting');
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
            markSentFromResponse(res);
            realtimeEmitCommitted(currentThread.thread_id, res, 'CLIENT');
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

        if (!selectedFiles.length) {
            toastr.warning('Choose at least one file to upload');
            return;
        }

        setUploadStatusAlert('info', 'Uploading documents...');

        var formData = new FormData();
        var metaArray = [];
        selectedFiles.forEach(function (item) {
            if (item && item.file) {
                formData.append('client_doc_files[]', item.file);
                metaArray.push({
                    doc_type: (item.doc_type || 'other').toString(),
                    title: (item.title || '').toString(),
                    description: (item.description || '').toString(),
                    original_name: (item.file && item.file.name) ? String(item.file.name) : ''
                });
            }
        });
        if (!metaArray.length) {
            toastr.warning('Choose at least one valid file to upload');
            return;
        }

        formData.append('meta_json', JSON.stringify(metaArray));
        formData.append('document_type', (metaArray[0].doc_type || 'other').toString());
        formData.append('title', (metaArray[0].title || '').toString());
        formData.append('description', (metaArray[0].description || '').toString());
        var requestIdFromThread = parseInt(currentThread.booking_id || 0, 10);
        var urlParams = new URLSearchParams(window.location.search || '');
        var requestIdFromUrl = parseInt(urlParams.get('request_id') || '0', 10);
        var safeRequestId = requestIdFromThread > 0 ? requestIdFromThread : requestIdFromUrl;
        formData.append('booking_request_id', safeRequestId);
        formData.append('request_id', safeRequestId);
        formData.append('item_id', currentThread.item_id || 0);
        formData.append('thread_type', currentThread.thread_type || 'CARE');

        var $btn = $('#client-doc-upload-btn');
        $btn.prop('disabled', true).text('Uploading...');

        var uploadUrl = '/client/ajax/upload_medical_document.php';

        $.ajax({
            url: uploadUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                var errorMessage = (res && res.message) ? String(res.message) : 'Upload failed. Please try again.';
                renderUploadStatusFromResponse(res || null, errorMessage);
                return;
            }
            $('#client-doc-file').val('');
            selectedFiles = [];
            renderSelectedFilesBatch();
            renderUploadStatusFromResponse(res || null, 'Upload failed. Please try again.');
            loadMessages();
        }).fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            var errorMessage = (res && res.message) ? String(res.message) : 'Upload failed. Please try again.';
            renderUploadStatusFromResponse(res || null, errorMessage);
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Upload document');
        });
    }

    $(function () {
        document.addEventListener('click', function (event) {
            var target = event && event.target ? event.target : null;
            if (!target || !target.closest) {
                return;
            }
            var btn = target.closest('.client-structured-upload');
            if (!btn) {
                return;
            }
            var messagesBox = document.getElementById('client-inbox-messages');
            if (!messagesBox || !messagesBox.contains(btn)) {
                return;
            }
            event.preventDefault();
            var uploadType = (btn.getAttribute('data-upload-type') || '').toLowerCase();
            if (!uploadType) {
                toastr.error('Invalid upload type');
                return;
            }
            openStructuredFilePicker(uploadType);
        });

        setStructuredCareAlert(false, 0, 0);
        if (feeGateActive) {
            setFeeGateState(true, 'Messaging remains available here. The coordination fee may still unlock additional downstream steps.');
        }
        setComposeGateState(true, '');

        var params = new URLSearchParams(window.location.search);
        var threadId = String(params.get('thread_id') || '');
        var requestId = parseInt(params.get('request_id') || params.get('booking_id') || '0', 10);
        var hasThreadTypeParam = params.has('thread_type');
        var hasItemIdParam = params.has('item_id');
        var threadType = String(params.get('thread_type') || 'CARE').toUpperCase();
        var itemId = parseInt(params.get('item_id') || '0', 10);
        var itemGroup = String(params.get('item_group') || '').toLowerCase().trim();
        if (itemGroup !== 'medical' && itemGroup !== 'complementary') {
            itemGroup = '';
        }
        if (threadId) {
            preferredThread = { threadId: threadId };
            threadListFilter = {
                threadId: threadId,
                requestId: 0,
                mode: 'THREAD'
            };
        } else if (requestId > 0) {
            if (hasItemIdParam && itemId > 0) {
                preferredThread = {
                    requestId: requestId,
                    threadType: 'ITEM',
                    itemId: itemId,
                    itemGroup: itemGroup
                };
                threadListFilter = {
                    threadId: '',
                    requestId: requestId,
                    mode: 'ITEM'
                };
            } else if (hasThreadTypeParam && threadType === 'ALL') {
                preferredThread = {
                    requestId: requestId,
                    threadType: 'ALL',
                    itemId: 0
                };
                threadListFilter = {
                    threadId: '',
                    requestId: requestId,
                    mode: 'ALL_REQUEST'
                };
            } else if (hasThreadTypeParam && threadType === 'ITEM' && itemGroup) {
                preferredThread = {
                    requestId: requestId,
                    threadType: 'ITEM',
                    itemId: 0,
                    itemGroup: itemGroup
                };
                threadListFilter = {
                    threadId: '',
                    requestId: requestId,
                    mode: 'ITEM_GROUP',
                    itemGroup: itemGroup
                };
            } else if (hasThreadTypeParam && threadType === 'CARE') {
                preferredThread = {
                    requestId: requestId,
                    threadType: 'CARE',
                    itemId: 0
                };
                threadListFilter = {
                    threadId: '',
                    requestId: requestId,
                    mode: 'CARE'
                };
            } else if (hasThreadTypeParam && threadType === 'ITEM') {
                autoSelectItemRequestId = requestId;
                threadListFilter = {
                    threadId: '',
                    requestId: requestId,
                    mode: 'ITEM'
                };
            } else {
                autoSelectItemRequestId = requestId;
                threadListFilter = {
                    threadId: '',
                    requestId: requestId,
                    mode: 'ALL_REQUEST'
                };
            }
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
                item_id: parseInt($a.data('item-id') || 0, 10),
                item_type: String($a.data('item-type') || ''),
                thread_title: $.trim($a.find('.mt-thread-title').text() || ''),
                thread_subtitle: $.trim($a.find('.mt-thread-location').text() || '')
            };
            setAttachStatus('');
            $('#client-inbox-thread-list li').removeClass('active');
            $a.closest('li').addClass('active');
            loadMessages();
        });

        $('#client-chat-attach-btn').on('click', function () {
            if (this.disabled) {
                return;
            }
            openAttachDocumentModal();
        });

        $('#client-attach-file').on('change', function () {
            var file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                return;
            }
            var currentTitle = $.trim($('#client-attach-title').val() || '');
            if (!currentTitle) {
                $('#client-attach-title').val(cleanDocumentTitleFallback(file.name || ''));
            }
        });

        $('#client-attach-document-form').on('submit', function (e) {
            e.preventDefault();
            submitAttachDocument();
        });

        $('#clientAttachDocumentModal').on('hidden.bs.modal', function () {
            resetAttachDocumentModal();
        });

        $('#client-inbox-send-form').on('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });
        $('#client-inbox-docs-content').on('click', '.mt-doc-view, .mt-doc-open', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var docId = String($(this).data('doc-id') || '').trim();
            var fallbackHref = String(decodeURIComponent($(this).attr('data-url') || '') || $(this).attr('href') || '').trim();
            var doc = findDocumentById(docId);
            openDocViewer(doc || null, fallbackHref);
        });
        $('#client-inbox-messages').on('click', '.mt-shared-doc-link', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var docId = String($(this).data('doc-id') || '').trim();
            var href = String(decodeURIComponent($(this).attr('data-url') || '') || $(this).attr('href') || '').trim();
            var doc = findDocumentById(docId);
            openDocViewer(doc || null, href);
        });
        $('#clientDocViewerModal').on('hidden.bs.modal', function () {
            $('#clientDocViewerPreview').html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<span>Preview not available.</span>' +
                '</div>'
            );
        });
        $('#client-inbox-message').on('input', function () {
            handleLocalTyping();
        });
        $('#client-inbox-message').on('blur', function () {
            emitTyping('stop');
        });

        $('#client-inbox-fee-actions').on('click', '.client-quick-action', function () {
            var actionKey = $(this).data('action') || '';
            sendQuickAction(actionKey);
        });

        $('#client-inbox-doc-form').on('submit', function (e) {
            e.preventDefault();
            uploadMedicalDocument();
        });

        $('#client-doc-file').on('change', function () {
            appendSelectedFiles(this.files || []);
            $(this).val('');
        });

        $('#client-doc-batch').on('click', '.client-doc-remove', function () {
            var index = parseInt($(this).data('index') || -1, 10);
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }
            selectedFiles.splice(index, 1);
            renderSelectedFilesBatch();
        });

        $('#client-doc-batch').on('change', '.client-doc-item-type', function () {
            var index = parseInt($(this).data('index') || -1, 10);
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }
            selectedFiles[index].doc_type = ($(this).val() || 'other').toString();
        });

        $('#client-doc-batch').on('input', '.client-doc-item-title', function () {
            var index = parseInt($(this).data('index') || -1, 10);
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }
            selectedFiles[index].title = ($(this).val() || '').toString();
        });

        $('#client-doc-batch').on('input', '.client-doc-item-description', function () {
            var index = parseInt($(this).data('index') || -1, 10);
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }
            selectedFiles[index].description = ($(this).val() || '').toString();
        });

        initRealtime();
        loadThreads();
    });
})();
