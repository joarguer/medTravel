(function () {
    var config = window.ClientInboxConfig || {};
    var currentThread = null;
    var preferredThread = null;
    var selectedFiles = [];
    var currentDocuments = [];
    var feeGateActive = !!config.feeGateActive;
    var freeMessageAllowed = true;
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

    function formatFileSize(bytes) {
        var n = parseInt(bytes || 0, 10);
        if (!isFinite(n) || n <= 0) return '0 B';
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function buildClientDocumentUrl(filePath) {
        var relative = String(filePath || '').replace(/^\/+/, '');
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

    function renderThreadDocuments() {
        if (!currentDocuments || !currentDocuments.length) {
            return '';
        }

        var html = '<div class="well well-sm" style="margin-bottom:10px;">' +
            '<strong>Medical documents</strong>' +
            '<ul style="margin:8px 0 0 18px;padding:0;">';

        currentDocuments.forEach(function (doc) {
            var docType = String(doc.document_type || '').replace(/_/g, ' ');
            var title = String(doc.title || '').trim();
            var description = String(doc.description || '').trim();
            var originalName = String(doc.original_filename || doc.filename || ('Document #' + (doc.id || '')));
            var href = String(doc.download_url || '').trim();
            if (!href) {
                href = buildClientDocumentUrl(doc.file_path || '');
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

        if (normalized.length > 110) {
            normalized = normalized.slice(0, 110).trim() + '…';
        }

        return normalized;
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
            var threadTypeRaw = String(thread.thread_type || 'CARE').toUpperCase();
            var threadTypeSub = (threadTypeRaw === 'CARE') ? 'GENERAL' : threadTypeRaw;
            var requestId = parseInt(thread.booking_id || thread.request_id || 0, 10);
            var location = String(thread.subtitle || '').trim();
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
            var docsOnlyHtml = renderThreadDocuments();
            $box.html(docsOnlyHtml + '<p class="text-muted" style="margin:0;">No messages in this thread yet.</p>');
            return;
        }

        var html = renderThreadDocuments();
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
        if ($actions.length) {
            if (feeGateActive) {
                $actions.show();
            } else {
                $actions.hide();
            }
        }
    }

    function setComposeGateState(canSendFreeMessage, noticeMessage) {
        freeMessageAllowed = !!canSendFreeMessage;
        var composeBlocked = feeGateActive || !freeMessageAllowed;

        var $msg = $('#client-inbox-message');
        var $send = $('#client-inbox-send-btn');
        if ($msg.length) {
            $msg.prop('disabled', composeBlocked);
        }
        if ($send.length) {
            $send.prop('disabled', composeBlocked);
        }

        var $note = $('#client-inbox-compose-note');
        if ($note.length) {
            if (!freeMessageAllowed) {
                $note.text(noticeMessage || 'Messaging will be available after the initial review. Please use the options above.');
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
            var canSendFreeMessage = (typeof res.can_send_free_message === 'boolean') ? res.can_send_free_message : !feeLocked;
            setComposeGateState(canSendFreeMessage, res.free_message_notice || '');
            var isCareThread = String(currentThread.thread_type || '').toUpperCase() === 'CARE';
            var hasStructuredItemActions = !!res.has_structured_item_actions;
            setStructuredCareAlert(
                isCareThread && hasStructuredItemActions,
                res.request_id || currentThread.booking_id || 0,
                res.structured_item_id || 0
            );
            currentDocuments = $.isArray(res.documents) ? res.documents : [];

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
                setComposeGateState(true, '');
                $('#client-inbox-title').text('Coordination fee required');
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
        var text = $.trim($('#client-inbox-message').val() || '');
        if (!currentThread || !currentThread.thread_id) return;
        if (!freeMessageAllowed) {
            toastr.warning('Messaging will be available after the initial review. Please use the options above.');
            return;
        }
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
                setComposeGateState(true, '');
                toastr.warning('Unlock after Coordination Fee');
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                setComposeGateState(false, 'Messaging will be available after the initial review. Please use the options above.');
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
        setStructuredCareAlert(false, 0, 0);
        if (feeGateActive) {
            setFeeGateState(true, 'Unlock after Coordination Fee.');
        }
        setComposeGateState(true, '');

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

        loadThreads();
    });
})();
