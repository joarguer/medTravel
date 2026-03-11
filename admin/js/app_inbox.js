(function () {
    var helpConfig = window.AdminInboxHelpConfig || {};
    var realtimeConfig = {
        baseUrl: String(helpConfig.realtimeBaseUrl || '').trim(),
        socketPath: String(helpConfig.realtimeSocketPath || '').trim(),
        tokenUrl: String(helpConfig.realtimeTokenUrl || 'ajax/realtime_token.php').trim()
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
    var feeGateActive = false;
    var commissionGateActive = false;
    var freeMessageAllowed = true;
    var lastComposeNotice = '';
    var currentDocuments = [];
    var currentDocumentsThreadId = '';
    var composeFiles = [];
    var composeBusy = false;
    var composeBusyMessage = '';
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

    function renderComposeFilesBatch() {
        var $list = $('#admin-chat-attach-list');
        if (!$list.length) return;
        if (!composeFiles.length) {
            $list.hide().html('');
            return;
        }
        var html = composeFiles.map(function (file, index) {
            var name = String((file && file.name) || ('Document ' + (index + 1)));
            return '<span class="label label-default" style="display:inline-block;margin:0 6px 6px 0;">' +
                esc(name) +
                ' <a href="#" class="admin-chat-attach-remove" data-index="' + index + '" style="color:inherit;text-decoration:none;">&times;</a>' +
            '</span>';
        }).join('');
        $list.html('<div><strong>Attached:</strong></div><div style="margin-top:6px;">' + html + '</div>').show();
    }

    function appendComposeFiles(fileList) {
        if (!fileList || !fileList.length) {
            renderComposeFilesBatch();
            return;
        }
        for (var i = 0; i < fileList.length; i++) {
            if (fileList[i]) {
                composeFiles.push(fileList[i]);
            }
        }
        renderComposeFilesBatch();
    }

    function resetComposeFiles() {
        composeFiles = [];
        var input = document.getElementById('admin-chat-attach-input');
        if (input) {
            input.value = '';
        }
        renderComposeFilesBatch();
    }

    function mergeComposeUploadDocuments(uploadRes) {
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
                original_filename: String(item.original_filename || ''),
                filename: String(item.filename || ''),
                title: String(item.title || ''),
                download_url: docId > 0
                    ? '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(docId))
                    : (filePath ? '/uploads/medical_docs/' + filePath.replace(/^\/+/, '') : '')
            });
        });
    }

    function setComposeBusy(enabled, message) {
        composeBusy = !!enabled;
        composeBusyMessage = composeBusy ? String(message || 'Working...') : '';
        setComposeGateState(freeMessageAllowed, composeBusyMessage);
    }

    function buildComposeAttachmentSummary(files) {
        var names = (files || []).map(function (file) {
            return String((file && file.name) || '').trim();
        }).filter(function (name) {
            return name !== '';
        });
        if (!names.length) {
            return 'Shared a document.';
        }
        if (names.length === 1) {
            return 'Shared document: ' + names[0];
        }
        return 'Shared ' + names.length + ' documents: ' + names.slice(0, 3).join(', ');
    }

    function parseSharedDocumentMessage(text) {
        var raw = String(text || '');
        var lines = raw.split(/\r?\n/);
        var docNames = [];
        var kept = [];
        lines.forEach(function (line) {
            var trimmedLine = String(line || '').trim();
            var singleMatch = trimmedLine.match(/^Shared document:\s*(.+)$/i);
            if (singleMatch) {
                docNames.push(singleMatch[1].trim());
                return;
            }
            var multiMatch = trimmedLine.match(/^Shared\s+\d+\s+documents:\s*(.+)$/i);
            if (multiMatch) {
                multiMatch[1].split(/\s*,\s*/).forEach(function (name) {
                    var docName = String(name || '').trim();
                    if (docName !== '') {
                        docNames.push(docName);
                    }
                });
                return;
            }
            kept.push(line);
        });
        return {
            body: kept.join('\n').trim(),
            names: docNames
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

    function resolveSharedMessageDocument(name) {
        var target = normalizeSharedDocumentName(name);
        var targetWithoutExt = sharedDocumentNameWithoutExtension(name);
        var targetExt = sharedDocumentExtension(name);
        var bestDoc = null;
        var bestScore = -1;
        if (!target || !currentDocuments || !currentDocuments.length) {
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

    function renderSharedDocumentsBlock(names) {
        if (!names || !names.length) {
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
            if (doc.id) {
                return '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(doc.id));
            }
            var filePath = String(doc.file_path || '').trim();
            if (filePath) {
                return '/uploads/medical_docs/' + String(filePath).replace(/^\/+/, '');
            }
            return '';
        }
        var itemsHtml = names.map(function (name) {
            var doc = resolveSharedMessageDocument(name);
            if (!doc && names.length === 1 && currentDocuments && currentDocuments.length) {
                doc = currentDocuments[0];
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('[inbox] shared document fallback to latest thread document', {
                        requested_name: name,
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
                ? String(doc.original_filename || doc.filename || name || ('Document #' + (doc.id || '')))
                : String(name || '');
            var href = buildSharedDocumentHref(doc);
            if (!doc && window.console && typeof window.console.warn === 'function') {
                window.console.warn('[inbox] shared document unresolved', {
                    requested_name: name,
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
                    requested_name: name,
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
                    '<a class="mt-shared-doc-link" href="' + esc(href) + '" data-url="' + esc(href) + '" target="_blank" rel="noopener">Open document</a>' +
                '</div>')
                : '';
            return '<div class="mt-shared-doc-card">' +
                '<div class="mt-shared-doc-label"><i class="fa fa-paperclip" aria-hidden="true"></i> Shared document</div>' +
                '<div class="mt-shared-doc-name">' + esc(originalName) + '</div>' +
                actionsHtml +
            '</div>';
        }).join('');
        return '<div class="mt-shared-docs">' + itemsHtml + '</div>';
    }

    function senderClass(sender) {
        var s = String(sender || 'system').toLowerCase();
        if (s === 'provider') return 'success';
        if (s === 'client') return 'info';
        if (s === 'admin' || s === 'patientcare') return 'warning';
        return 'default';
    }

    function normalizeRole(sender) {
        return String(sender || 'system').toLowerCase().trim();
    }

    function getCurrentUserId() {
        var direct = parseInt(helpConfig.userId || 0, 10);
        if (isFinite(direct) && direct > 0) return direct;
        var session = window.MT_SESSION || window.mtSession || {};
        var fallback = parseInt(session.user_id || session.id_usuario || session.id || 0, 10);
        return (isFinite(fallback) && fallback > 0) ? fallback : 0;
    }

    function getMessageActorId(m) {
        if (!m || typeof m !== 'object') return 0;
        var raw = m.actor_user_id;
        if (raw === undefined || raw === null || raw === '') raw = m.sender_user_id;
        if (raw === undefined || raw === null || raw === '') raw = m.sender_id;
        if (raw === undefined || raw === null || raw === '') raw = m.user_id;
        var id = parseInt(raw || 0, 10);
        return (isFinite(id) && id > 0) ? id : 0;
    }

    function isOwnMessage(m) {
        var myId = getCurrentUserId();
        var actorId = getMessageActorId(m);
        if (myId > 0 && actorId > 0) {
            return myId === actorId;
        }
        return isOwnAdminMessage(m && m.sender ? m.sender : '');
    }

    function isOwnAdminMessage(sender) {
        var s = normalizeRole(sender);
        if (!s || s === 'system') return false;
        var myRole = String(helpConfig.role || '').toLowerCase().trim();
        if (!myRole) return false;
        return s === myRole;
    }

    function getAdminDisplayName(m, own) {
        if (own) return 'Me';
        // Use API name field if present (future-proof)
        var apiName = String(m.sender_name || m.user_name || m.display_name || '').trim();
        if (apiName) return apiName;
        var s = normalizeRole(m.sender || '');
        if (s === 'client') return 'Patient';
        if (s === 'provider') return 'Provider';
        if (s === 'system') return 'System';
        return 'Support';
    }

    function buildAdminMsgHtml(m, bodyHtml, sysMsg) {
        var own = isOwnMessage(m);
        var rowCls = sysMsg ? 'mt-msg-row--system' : (own ? 'mt-msg-row--own' : 'mt-msg-row--other');
        var msgCls = sysMsg ? 'mt-msg-system' : 'mt-msg-human';
        var displayName = sysMsg ? 'System' : getAdminDisplayName(m, own);
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
            '<div class="mt-msg ' + msgCls + '">' +
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

    function updateUnreadBadge(total) {
        var $badge = $('.admin-notif-badge');
        if (!$badge.length) return;
        var count = parseInt(total || 0, 10);
        if (!isFinite(count) || count < 0) {
            count = 0;
        }
        $badge.text(String(count));
        $badge.show();
    }

    function realtimeEnabled() {
        return !!(realtimeConfig.baseUrl && realtimeConfig.socketPath && typeof window.io === 'function');
    }

    function realtimeDebug() {
        return !!window.MT_DEBUG_REALTIME;
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
        if ((Date.now() - entry.ts) > MESSAGE_STATUS_TTL_MS) {
            delete messageStatusById[id];
            return '';
        }
        return entry.text || '';
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

    function shouldGroupMessages(prevMsg, msg, sysMsg) {
        if (!prevMsg || !msg) return false;
        if (sysMsg) return false;
        if (isSystemActionMessage(prevMsg.body || '')) return false;
        if (normalizeRole(prevMsg.sender || '') !== normalizeRole(msg.sender || '')) return false;
        var prevTime = parseMessageTime(prevMsg.time);
        var currTime = parseMessageTime(msg.time);
        if (!prevTime || !currTime) return false;
        return Math.abs(currTime - prevTime) <= 120000;
    }

    function annotateGrouping(messages, previousMeta) {
        var prevMsg = previousMeta && previousMeta.msg ? previousMeta.msg : null;
        messages.forEach(function (m) {
            var sysMsg = isSystemActionMessage(m.body || '');
            var grouped = shouldGroupMessages(prevMsg, m, sysMsg);
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
                if (realtimeDebug()) {
                    console.log('[realtime] dedupe message.created id=' + messageId + ' thread=' + threadId);
                }
                return;
            }
            if (currentThread && String(currentThread.thread_id || '') === threadId) {
                var nearBottom = shouldAutoScroll($('#admin-inbox-messages')[0]);
                var sinceId = realtimeState.lastMessageIdByThread[threadId] || 0;
                fetchNewMessages(threadId, sinceId, nearBottom);
                if (!nearBottom && typeof window.adminReloadNotificationsDebounced === 'function') {
                    window.adminReloadNotificationsDebounced();
                }
                return;
            }
            if (typeof window.adminReloadNotificationsDebounced === 'function') {
                window.adminReloadNotificationsDebounced();
            } else if (typeof window.adminReloadNotifications === 'function') {
                window.adminReloadNotifications();
            }
            loadThreads();
        });

        realtimeState.socket.on('typing', function (payload) {
            var threadId = payload && payload.thread_id ? String(payload.thread_id) : '';
            if (!currentThread || String(currentThread.thread_id || '') !== threadId) {
                return;
            }
            var role = String(payload.role || '').toLowerCase();
            if (!role || role === String(helpConfig.role || '').toLowerCase()) {
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

    function extractFileExtension(value) {
        var raw = String(value || '').trim();
        if (!raw) return '';
        var clean = raw.split('?')[0].split('#')[0];
        var lastDot = clean.lastIndexOf('.');
        if (lastDot === -1) return '';
        return clean.slice(lastDot + 1).toLowerCase();
    }

    function isPdfMime(mime) {
        var m = String(mime || '').toLowerCase();
        return m === 'application/pdf' || m === 'application/x-pdf';
    }

    function isPreviewImageMime(mime) {
        var m = String(mime || '').toLowerCase();
        return m === 'image/jpeg' || m === 'image/jpg' || m === 'image/png' || m === 'image/webp';
    }

    function isPreviewImageExt(ext) {
        return ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'webp';
    }

    function resolvePreviewType(meta) {
        var mime = String(meta.mime || '').toLowerCase();
        if (isPdfMime(mime)) return 'pdf';
        if (isPreviewImageMime(mime)) return 'image';
        var ext = extractFileExtension(meta.name || '') || extractFileExtension(meta.url || '');
        if (ext === 'pdf') return 'pdf';
        if (isPreviewImageExt(ext)) return 'image';
        return '';
    }

    function renderThreadDocuments(docs) {
        var hasDocs = docs && docs.length > 0;
        var countHtml = hasDocs
            ? ' <span class="badge" style="background:#7f8c9d;">' + docs.length + '</span>'
            : '';
        var innerHtml;
        if (!hasDocs) {
            innerHtml = '<p class="mt-docs-empty text-muted">No medical documents uploaded yet.</p>';
        } else {
            var typeCls = { labs: 'label-info', imaging: 'label-primary', photos: 'label-success', medical_history: 'label-warning', other: 'label-default' };
            innerHtml = '<div class="mt-docs-list">';
            docs.forEach(function (doc) {
                var typeKey = String(doc.document_type || 'other').toLowerCase();
                var typeLabel = docTypeLabel(typeKey);
                var cls = typeCls[typeKey] || 'label-default';
                var originalName = String(doc.original_filename || doc.filename || ('Document #' + (doc.id || '')));
                var uploadedRaw = String(doc.uploaded_at || doc.created_at || '').trim();
                var href = String(doc.download_url || '').trim();
                if (!href && doc.id) {
                    href = '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(doc.id));
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
                        '<span class="mt-doc-name">' + esc(originalName) + '</span>' +
                        (dateText ? '<small class="mt-doc-date text-muted"><i class="fa fa-clock-o" aria-hidden="true"></i> ' + esc(dateText) + '</small>' : '') +
                        '<button type="button" class="btn btn-xs btn-info mt-doc-view"' +
                            ' data-doc-id="' + esc(String(doc.id || '')) + '"' +
                            ' data-url="' + esc(encodedHref) + '"' +
                            ' title="Ver ' + esc(originalName) + '">' +
                            '<i class="fa fa-eye" aria-hidden="true"></i> Ver' +
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

    function isSystemActionMessage(body) {
        var text = String(body || '').trim();
        if (text.indexOf('[REQUEST_INFO]') === 0) return true;
        if (text.indexOf('[PROPOSE_QUOTE]') === 0) return true;
        if (text.indexOf('[PROPOSAL_RESPONSE]') === 0) return true;
        var keys = Object.keys(quickReplies);
        for (var i = 0; i < keys.length; i++) {
            if (text === keys[i] || text === quickReplies[keys[i]]) return true;
        }
        return false;
    }

    function buildStructuredPendingBody(actionType, payload) {
        var normalized = String(actionType || '').toUpperCase();
        if (normalized === 'REQUEST_ADDITIONAL_INFO') {
            return '[REQUEST_INFO] ' + JSON.stringify(payload || {});
        }
        if (normalized === 'PROPOSE_QUOTE_ADJUSTMENT') {
            return '[PROPOSE_QUOTE] ' + JSON.stringify(payload || {});
        }
        return 'Sending structured action…';
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
        var text = String(body || '');
        var trimmed = text.trim();
        if (trimmed.indexOf('[REQUEST_INFO]') === 0) {
            return renderStructuredRequestInfo(trimmed);
        }
        if (trimmed.indexOf('[PROPOSE_QUOTE]') === 0) {
            return renderStructuredProposeQuote(trimmed);
        }
        if (trimmed.indexOf('[PROPOSAL_RESPONSE]') === 0) {
            return renderStructuredProposalResponse(trimmed);
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
            if (parsedShared.names.length) {
                var bodyHtml = parsedShared.body
                    ? '<div style="white-space:pre-wrap;">' + esc(parsedShared.body) + '</div>'
                    : '';
                return bodyHtml + renderSharedDocumentsBlock(parsedShared.names);
            }
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
                    '<button type="button" class="btn btn-default btn-xs client-propose-new-dates" title="Propose new dates">' +
                        'PROPOSE NEW DATES' +
                    '</button>' +
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

        if (normalized.indexOf('[REQUEST_INFO]') === 0) {
            return 'requested additional information';
        }
        if (normalized.indexOf('[PROPOSE_QUOTE]') === 0) {
            return 'sent quote adjustment';
        }
        if (normalized.indexOf('[PROPOSAL_RESPONSE]') === 0) {
            var proposalPayload = parseStructuredJson('[PROPOSAL_RESPONSE]', normalized);
            var proposalAction = String(proposalPayload && proposalPayload.action_type || '').toUpperCase();
            if (proposalAction === 'ACCEPT_PROPOSAL') return 'accepted proposal';
            if (proposalAction === 'REQUEST_CHANGES') return 'requested changes';
            if (proposalAction === 'REJECT_PROPOSAL') return 'rejected proposal';
            if (proposalAction === 'DOCS_NOT_AVAILABLE') return 'documents unavailable';
            return 'sent proposal response';
        }

        normalized = normalized.replace(/^\[(ACTION|REPLY)\]\s*/i, '').trim();

        var quickReplyPreviewMap = {
            DATES_AVAILABLE: 'dates available',
            DATES_NOT_AVAILABLE: 'dates unavailable',
            REQUEST_MEDICAL_HISTORY: 'requested medical history',
            REQUEST_LABS: 'requested labs',
            REQUEST_IMAGING: 'requested imaging',
            REQUEST_PHOTOS: 'requested photos',
            FINAL_APPROVED: 'case approved',
            FINAL_NOT_ELIGIBLE: 'case not eligible'
        };
        var quickReplyKey = normalized.toUpperCase().replace(/\s+/g, '_');
        if (quickReplyPreviewMap[quickReplyKey]) {
            return quickReplyPreviewMap[quickReplyKey];
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

    function getThreadPatientName(thread) {
        if (!thread || typeof thread !== 'object') {
            return 'Patient';
        }
        var requestId = parseInt(thread.booking_request_id || thread.request_id || 0, 10);
        var patientName = String(thread.patient_name || thread.client_name || '').trim();
        if (patientName) {
            return patientName;
        }
        return requestId > 0 ? ('Patient Request #' + requestId) : 'Patient';
    }

    function getThreadCaseLabel(thread) {
        if (!thread || typeof thread !== 'object') {
            return 'Request';
        }
        var requestId = parseInt(thread.booking_request_id || thread.request_id || 0, 10);
        var threadType = String(thread.thread_type || 'CARE').toUpperCase();
        var serviceLabel = threadType === 'ITEM'
            ? cleanServiceTitle(thread.title || '')
            : 'Care Coordination';
        var parts = [];
        if (serviceLabel) {
            parts.push(serviceLabel);
        }
        if (requestId > 0) {
            parts.push('Request #' + requestId);
        }
        return parts.join(' • ') || 'Request';
    }

    function getThreadStatusMeta(status) {
        var key = String(status || '').trim().toLowerCase();
        if (!key) return null;
        var map = {
            pending_provider: { cls: 'warning', label: 'Pending' },
            pending: { cls: 'warning', label: 'Pending' },
            provider_confirmed: { cls: 'success', label: 'Confirmed' },
            client_accepted: { cls: 'success', label: 'Accepted' },
            provider_rejected: { cls: 'danger', label: 'Rejected' },
            client_rejected: { cls: 'danger', label: 'Rejected' },
            provider_proposed_change: { cls: 'info', label: 'Changes' },
            awaiting_client: { cls: 'info', label: 'Awaiting' },
            cancelled: { cls: 'default', label: 'Cancelled' }
        };
        return map[key] || { cls: 'default', label: key.replace(/_/g, ' ') };
    }

    function renderThreadStatusBadge(status) {
        var meta = getThreadStatusMeta(status);
        if (!meta) return '';
        return '<span class="label label-' + esc(meta.cls) + ' mt-thread-status-badge">' + esc(meta.label) + '</span>';
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
        var totalUnread = 0;
        threads.forEach(function (thread) {
            var threadId = String(thread.thread_id || '');
            var unread = parseInt(thread.unread_count || 0, 10);
            totalUnread += (isFinite(unread) ? unread : 0);
            var active = threadId === selectedKey;
            var patientName = getThreadPatientName(thread);
            var caseLabel = getThreadCaseLabel(thread);
            var timeLabel = formatThreadTime(thread.updated_at || '');
            var previewText = getThreadPreviewText(thread);
            var statusHtml = renderThreadStatusBadge(thread.status_label || '');
            var unreadMeta = unread > 0 ? '<span class="badge badge-danger mt-unread">' + unread + '</span>' : '';
            var timeHtml = timeLabel ? '<div class="mt-time">' + esc(timeLabel) + '</div>' : '';
            var previewHtml = previewText ? '<div class="mt-thread-preview text-muted">Last: ' + esc(previewText) + '</div>' : '';
            var liClasses = 'mt-thread-item' + (active ? ' active' : '') + (unread > 0 ? ' unread' : '');

            html += '<li class="' + liClasses + '">' +
                '<a href="javascript:;" class="admin-thread-link mt-thread-link"' +
                ' data-thread-id="' + esc(threadId) + '"' +
                ' data-thread-type="' + esc(thread.thread_type) + '"' +
                ' data-booking-id="' + esc(thread.booking_request_id || thread.request_id || 0) + '"' +
                ' data-item-id="' + esc(thread.item_id || 0) + '"' +
                ' data-thread-title="' + esc(thread.title || '') + '">' +
                '<div class="mt-thread-row">' +
                    '<div class="mt-thread-main">' +
                        '<div class="mt-thread-title">' + esc(patientName) + '</div>' +
                        '<div class="mt-thread-sub">' + esc(caseLabel) + '</div>' +
                        previewHtml +
                    '</div>' +
                    '<div class="mt-thread-meta">' +
                        statusHtml +
                        unreadMeta +
                        timeHtml +
                    '</div>' +
                '</div>' +
                '</a>' +
                '</li>';
        });
        $list.html(html);
        updateUnreadBadge(totalUnread);

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

    function openDocViewer(doc) {
        var originalName = String(doc.original_filename || doc.filename || ('Document #' + (doc.id || '')));
        var typeKey = String(doc.document_type || 'other').toLowerCase();
        var typeLabel = docTypeLabel(typeKey);
        var mimeType = String(doc.mime_type || '').toLowerCase().trim();
        var href = String(doc.download_url || '').trim();
        if (!href && doc.id) {
            href = '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(doc.id));
        }
        var previewUrl = href;
        if (doc.id) {
            previewUrl = '/admin/ajax/preview_medical_document.php?doc_id=' + encodeURIComponent(String(doc.id));
        }
        // Header
        $('#adminDocViewerName').text(originalName);
        $('#adminDocViewerType').text(typeLabel);
        var typeCls = { labs: 'label-info', imaging: 'label-primary', photos: 'label-success', medical_history: 'label-warning', other: 'label-default' };
        $('#adminDocViewerType').attr('class', 'label ' + (typeCls[typeKey] || 'label-default') + ' mt-dv-type-badge');
        // Meta: size + date
        var metaParts = [];
        var uploadedRaw = String(doc.uploaded_at || doc.created_at || '').trim();
        if (uploadedRaw) {
            var d = new Date(uploadedRaw.replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                var dd = (d.getDate() < 10 ? '0' : '') + d.getDate();
                var mo = ((d.getMonth() + 1) < 10 ? '0' : '') + (d.getMonth() + 1);
                metaParts.push('Uploaded: ' + dd + '/' + mo + '/' + d.getFullYear());
            }
        }
        if (doc.file_size > 0) {
            var kb = (doc.file_size / 1024).toFixed(1);
            metaParts.push(kb + ' KB');
        }
        if (mimeType) { metaParts.push(mimeType); }
        $('#adminDocViewerMeta').text(metaParts.join(' · '));
        // Download button
        $('#adminDocViewerDownload').attr('href', href);
        // Preview
        var $preview = $('#adminDocViewerPreview');
        if (mimeType.indexOf('image/') === 0) {
            $preview.html('<img src="' + esc(previewUrl) + '" alt="' + esc(originalName) + '">');
        } else if (mimeType === 'application/pdf') {
            $preview.html('<iframe src="' + esc(previewUrl) + '" title="' + esc(originalName) + '"></iframe>');
        } else {
            $preview.html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<div>Preview not available for this file type.</div>' +
                    '<div style="margin-top:8px;"><a href="' + esc(href) + '" target="_blank" rel="noopener" class="btn btn-default btn-sm">' +
                        '<i class="fa fa-download" aria-hidden="true"></i> Download to view</a></div>' +
                '</div>'
            );
        }
        $('#adminDocViewerModal').modal('show');
    }

    function renderMessages(messages) {
        var $box = $('#admin-inbox-messages');
        if (!$box.length) return;
        var docsHtml = renderThreadDocuments(currentDocuments);
        var divider = '<div class="mt-section-divider">Messages</div>';
        if (!messages || !messages.length) {
            $box.html(docsHtml + divider + '<p class="text-muted" style="margin:0;">No messages in this thread yet.</p>');
            return;
        }

        annotateGrouping(messages, null);
        var html = docsHtml + divider;
        messages.forEach(function (m) {
            var bodyHtml = formatAdminMessageBody(m.body || '');
            var sysMsg = isSystemActionMessage(m.body || '');
            html += buildAdminMsgHtml(m, bodyHtml, sysMsg);
        });
        $box.html(html);
        $box.scrollTop($box[0].scrollHeight);
        if (currentThread && currentThread.thread_id) {
            rememberLastMessageId(String(currentThread.thread_id), messages);
            rememberLastRenderedMeta(String(currentThread.thread_id), messages[messages.length - 1]);
        }
    }

    function appendMessages(messages, minId) {
        var $box = $('#admin-inbox-messages');
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
        annotateGrouping(filtered, lastMeta);
        filtered.forEach(function (m) {
            var bodyHtml = formatAdminMessageBody(m.body || '');
            var sysMsg = isSystemActionMessage(m.body || '');
            html += buildAdminMsgHtml(m, bodyHtml, sysMsg);
            appended = true;
        });
        if (!appended) {
            return;
        }
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

    function fetchNewMessages(threadId, sinceId, nearBottom) {
        var thread = String(threadId || '').trim();
        var lastId = parseInt(sinceId || 0, 10);
        if (!thread) return;
        if (!lastId || lastId <= 0) {
            loadMessages();
            return;
        }
        $.ajax({
            url: 'ajax/inbox.php',
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
            var newMessages = $.isArray(res.messages) ? res.messages : [];
            if (!newMessages.length) {
                return;
            }
            appendMessages(newMessages, lastId);
            if (nearBottom) {
                markCurrentRead();
            } else {
                loadThreads();
            }
        });
    }

    function addPendingMessage(text) {
        if (!currentThread || !currentThread.thread_id) return '';
        var currentUserId = getCurrentUserId();
        var tempId = 'temp-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
        var msg = {
            id: tempId,
            _tempId: tempId,
            _status: 'Sending…',
            sender: String(helpConfig.role || 'admin').toLowerCase() || 'admin',
            sender_user_id: currentUserId,
            actor_user_id: currentUserId,
            body: text,
            time: new Date().toISOString()
        };
        appendMessages([msg], 0);
        return tempId;
    }

    function removePendingMessage(tempId) {
        if (!tempId) return;
        $('#admin-inbox-messages').find('[data-temp-id="' + tempId + '"]').remove();
    }

    function updatePendingStatus(tempId, statusText) {
        if (!tempId) return;
        var $row = $('#admin-inbox-messages').find('[data-temp-id="' + tempId + '"]');
        if (!$row.length) return;
        var $status = $row.find('.mt-bubble-status');
        if (!$status.length) {
            $row.find('.mt-msg').append('<div class="mt-bubble-status"></div>');
            $status = $row.find('.mt-bubble-status');
        }
        $status.text(statusText || '');
    }

    function emitTyping(state) {
        if (!currentThread || !currentThread.thread_id) return;
        if (!realtimeCanEmit(String(currentThread.thread_id))) return;
        realtimeState.socket.emit('typing', {
            thread_id: String(currentThread.thread_id),
            role: String(helpConfig.role || '').toUpperCase() || 'ADMIN',
            user_id: parseInt(helpConfig.userId || 0, 10) || 0,
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
        var $el = $('#admin-typing-indicator');
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
        var $el = $('#admin-typing-indicator');
        if ($el.length) {
            $el.hide();
        }
    }

    function typingLabelForRole(role) {
        var normalized = String(role || '').toLowerCase();
        if (normalized === 'client') return 'Patient is typing…';
        if (normalized === 'provider') return 'Provider is typing…';
        if (normalized === 'patientcare' || normalized === 'admin') return 'Support is typing…';
        return 'Someone is typing…';
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
        var senderRole = String(defaultRole || 'ADMIN').toUpperCase();
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

    function setCommissionGateState(enabled, paid) {
        commissionGateActive = !!enabled;
        var $alert = $('#admin-inbox-commission-alert');
        if (!$alert.length) return;
        if (!commissionGateActive) {
            $alert.hide();
            return;
        }
        if (paid) {
            $alert.removeClass('note-warning').addClass('note-success');
            $alert.html('<strong>Commission paid.</strong> Provider details are unlocked for the client.');
        } else {
            $alert.removeClass('note-success').addClass('note-warning');
            $alert.html('<strong>Commission pending.</strong> Provider details remain locked for the client.');
        }
        $alert.show();
    }

    function setComposeGateState(canSendFreeMessage, noticeMessage) {
        freeMessageAllowed = !!canSendFreeMessage;
        var permissionBlocked = feeGateActive || !freeMessageAllowed;
        var composeBlocked = permissionBlocked || composeBusy;

        var $quick = $('#admin-inbox-quick-replies');
        var $msg = $('#admin-inbox-message');
        var $send = $('#admin-inbox-send-form button[type="submit"]');
        var $attach = $('#admin-chat-attach-btn');
        var $attachInput = $('#admin-chat-attach-input');
        var $composerGroup = $('#admin-inbox-send-form .form-group');
        var $typing = $('#admin-typing-indicator');
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
        if ($attach.length) {
            $attach.prop('disabled', composeBlocked);
        }
        if ($attachInput.length) {
            $attachInput.prop('disabled', composeBlocked);
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

        if ($note.length) {
            if (composeBusy) {
                $note.text(composeBusyMessage || 'Uploading document...');
                $note.show();
            } else if (!freeMessageAllowed) {
                var isItemThread = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;
                var noteText = '';
                if (feeGateActive) {
                    noteText = 'Coordination Fee required';
                } else if (typeof noticeMessage === 'string' && noticeMessage.trim() !== '') {
                    noteText = noticeMessage;
                    lastComposeNotice = noticeMessage;
                } else if (lastComposeNotice) {
                    noteText = lastComposeNotice;
                } else if (!isItemThread) {
                    noteText = 'Messaging will be available after the initial review. Please use the options above.';
                }
                if (noteText) {
                    $note.text(noteText);
                    $note.show();
                } else {
                    $note.hide();
                }
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
            if (typeof window.adminReloadNotificationsDebounced === 'function') {
                window.adminReloadNotificationsDebounced();
            } else if (typeof window.adminReloadNotifications === 'function') {
                window.adminReloadNotifications();
            }
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
            var commissionGateEnabled = parseInt(res.commission_gate_enabled || 0, 10) === 1;
            var commissionPaid = parseInt(res.commission_paid || 0, 10) === 1;
            setCommissionGateState(commissionGateEnabled, commissionPaid);
            var canSendFreeMessage = (typeof res.can_send_free_message === 'boolean') ? res.can_send_free_message : !feeLocked;
            if (res.free_message_notice) {
                lastComposeNotice = res.free_message_notice;
            }
            setComposeGateState(canSendFreeMessage, lastComposeNotice);
            var freshDocs = $.isArray(res.documents) ? res.documents : [];
            var freshIds = freshDocs.map(function (d) { return parseInt(d.id || 0, 10); });
            var localOnly = currentDocuments.filter(function (d) {
                var id = parseInt(d.id || 0, 10);
                return id > 0 && freshIds.indexOf(id) === -1;
            });
            currentDocuments = freshDocs.concat(localOnly);

            var isItemThread = String(currentThread.thread_type || '').toUpperCase() === 'ITEM';
            var headingText = isItemThread ? cleanServiceTitle(currentThread.thread_title || '') : 'MedTravel Coordination';
            renderInboxHeader($('#admin-inbox-title'), headingText, currentThread.booking_request_id);
            renderMessages(res.messages || []);
            hideTypingIndicator();
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
                var notice = res.notice || res.free_message_notice || lastComposeNotice || '';
                setComposeGateState(false, notice);
                return;
            }
            toastr.error('Could not load messages');
        });
    }

    function uploadComposeDocuments() {
        var deferred = $.Deferred();
        if (!currentThread || !currentThread.thread_id) {
            deferred.reject({ message: 'Select a thread before uploading' });
            return deferred.promise();
        }
        if (!composeFiles.length) {
            deferred.resolve({ ok: true, uploaded_count: 0, results: [] });
            return deferred.promise();
        }

        var formData = new FormData();
        composeFiles.forEach(function (file) {
            formData.append('chat_files[]', file);
        });
        formData.append('action', 'upload_documents');
        formData.append('thread_id', currentThread.thread_id);
        formData.append('thread_type', currentThread.thread_type || 'ITEM');
        formData.append('request_id', currentThread.booking_request_id || 0);
        formData.append('item_id', currentThread.item_id || 0);
        formData.append('document_type', 'other');

        $.ajax({
            url: 'ajax/inbox.php',
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

    function sendMessageText(text) {
        if (!currentThread || !currentThread.thread_id) return;
        if (composeBusy) return;
        if (!freeMessageAllowed) {
            return;
        }
        if (feeGateActive) {
            toastr.warning('Coordination Fee required');
            return;
        }

        var pendingId = addPendingMessage(text);
        emitTyping('stop');
        setComposeBusy(true, 'Sending message...');

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
                setComposeBusy(false, '');
                if (res && res.code === 'FEE_REQUIRED') {
                    setFeeGateState(true);
                    setComposeGateState(true, '');
                    toastr.warning('Coordination Fee required');
                    updatePendingStatus(pendingId, 'Failed');
                    return;
                }
                if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                    var notice = res.notice || res.free_message_notice || lastComposeNotice || '';
                    setComposeGateState(false, notice);
                    updatePendingStatus(pendingId, 'Failed');
                    return;
                }
                toastr.error((res && res.message) ? res.message : 'Could not send message');
                updatePendingStatus(pendingId, 'Failed');
                return;
            }
            markSentFromResponse(res);
            removePendingMessage(pendingId);
            realtimeEmitCommitted(currentThread.thread_id, res, 'ADMIN');
            $('#admin-inbox-message').val('');
            resetComposeFiles();
            toastr.success('Message sent');
            setComposeBusy(false, '');
            loadMessages();
            loadThreads();
        }).fail(function (xhr) {
            setComposeBusy(false, '');
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            updatePendingStatus(pendingId, 'Failed');
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true);
                setComposeGateState(true, '');
                toastr.warning('Coordination Fee required');
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                var notice = res.notice || res.free_message_notice || lastComposeNotice || '';
                setComposeGateState(false, notice);
                return;
            }
            toastr.error('Could not send message');
        });
    }

    function sendMessage() {
        if (composeBusy) {
            return;
        }
        var text = $.trim($('#admin-inbox-message').val() || '');
        var hasAttachments = composeFiles.length > 0;
        if (!text && !hasAttachments) {
            toastr.warning('Write a message or attach a document before sending');
            return;
        }
        if (!hasAttachments) {
            sendMessageText(text);
            return;
        }

        var composeSnapshot = composeFiles.slice(0);
        setComposeBusy(true, 'Uploading document...');
        uploadComposeDocuments().done(function (uploadRes) {
            mergeComposeUploadDocuments(uploadRes || null);
            resetComposeFiles();
            setComposeBusy(false, '');
            var attachmentSummary = buildComposeAttachmentSummary(composeSnapshot);
            var messageText = text ? (text + '\n\n' + attachmentSummary) : attachmentSummary;
            sendMessageText(messageText);
        }).fail(function (res) {
            setComposeBusy(false, '');
            var errorMessage = (res && res.message) ? String(res.message) : 'Upload failed. Please try again.';
            toastr.error(errorMessage);
        });
    }

    function sendQuickReply(replyKey) {
        if (!currentThread || !currentThread.thread_id) return;
        var key = (replyKey || '').toString().toUpperCase();
        if (!quickReplies[key]) {
            toastr.error('Invalid quick reply');
            return;
        }

        var pendingId = addPendingMessage(quickReplies[key]);

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
                updatePendingStatus(pendingId, 'Failed');
                return;
            }
            markSentFromResponse(res);
            removePendingMessage(pendingId);
            realtimeEmitCommitted(currentThread.thread_id, res, 'ADMIN');
            toastr.success('Quick reply sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            updatePendingStatus(pendingId, 'Failed');
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

        var pendingId = addPendingMessage(buildStructuredPendingBody(actionType, payload));

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
                updatePendingStatus(pendingId, 'Failed');
                return;
            }
            markSentFromResponse(res);
            removePendingMessage(pendingId);
            realtimeEmitCommitted(currentThread.thread_id, res, 'ADMIN');
            toastr.success('Structured action sent');
            loadMessages();
            loadThreads();
        }).fail(function () {
            updatePendingStatus(pendingId, 'Failed');
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

        // Doc viewer: open modal when View button is clicked
        $('#admin-inbox-messages').on('click', '.mt-doc-view', function () {
            var docId = String($(this).data('doc-id') || '');
            var doc = null;
            for (var i = 0; i < currentDocuments.length; i++) {
                if (String(currentDocuments[i].id || '') === docId) {
                    doc = currentDocuments[i];
                    break;
                }
            }
            if (doc) {
                openDocViewer(doc);
            }
        });
        $('#admin-inbox-messages').on('click', '.mt-shared-doc-link', function (e) {
            var href = String($(this).attr('data-url') || $(this).attr('href') || '').trim();
            if (!href) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            window.open(href, '_blank', 'noopener');
        });

        // Clean up preview iframe/img on modal close to stop loading
        $('#adminDocViewerModal').on('hidden.bs.modal', function () {
            $('#adminDocViewerPreview').html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<span>Preview not available.</span>' +
                '</div>'
            );
        });

        $('#admin-inbox-thread-list').on('click', '.admin-thread-link', function () {
            var $a = $(this);
            currentThread = {
                thread_id: String($a.data('thread-id') || ''),
                thread_type: String($a.data('thread-type') || 'ITEM'),
                booking_request_id: parseInt($a.data('booking-id') || 0, 10),
                item_id: parseInt($a.data('item-id') || 0, 10),
                thread_title: String($a.data('thread-title') || '')
            };
            resetComposeFiles();
            $('#admin-inbox-thread-list li').removeClass('active');
            $a.closest('li').addClass('active');
            loadMessages();
        });

        $('#admin-chat-attach-btn').on('click', function () {
            if (this.disabled) {
                return;
            }
            var input = document.getElementById('admin-chat-attach-input');
            if (input) {
                input.click();
            }
        });

        $('#admin-chat-attach-input').on('change', function () {
            appendComposeFiles(this.files || []);
            $(this).val('');
        });

        $('#admin-chat-attach-list').on('click', '.admin-chat-attach-remove', function (e) {
            e.preventDefault();
            var index = parseInt($(this).data('index') || -1, 10);
            if (index < 0 || index >= composeFiles.length) {
                return;
            }
            composeFiles.splice(index, 1);
            renderComposeFilesBatch();
        });

        $('#admin-inbox-send-form').on('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });

        $('#admin-inbox-message').on('input', function () {
            handleLocalTyping();
        });

        $('#admin-inbox-message').on('blur', function () {
            emitTyping('stop');
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

        initRealtime();
        loadThreads();
    });
})();
