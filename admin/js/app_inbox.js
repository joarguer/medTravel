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
    var urlFilterThreadId = '';
    var feeGateActive = false;
    var commissionGateActive = false;
    var freeMessageAllowed = true;
    var lastComposeNotice = '';
    var cancelledMeetingKeys = {};
    var currentDocuments = [];
    var currentDocumentsThreadId = '';
    var attachModalBusy = false;
    var composeBusy = false;
    var composeBusyMessage = '';
    var quickReplies = {
        DATES_AVAILABLE: 'Fechas disponibles',
        DATES_NOT_AVAILABLE: 'Fechas no disponibles',
        REQUEST_MEDICAL_HISTORY: 'Solicitar historia clínica',
        REQUEST_LABS: 'Solicitar laboratorios',
        REQUEST_IMAGING: 'Solicitar imágenes',
        REQUEST_PHOTOS: 'Solicitar fotografías',
        FINAL_APPROVED: 'Aprobación final',
        FINAL_NOT_ELIGIBLE: 'No elegible'
    };
    var quickReplyPreviewMessages = {
        DATES_AVAILABLE: 'We have availability to continue with your case. Please confirm whether these dates still work for you.',
        REQUEST_MEDICAL_HISTORY: 'Please share your medical history so we can continue evaluating your case.',
        REQUEST_LABS: 'Please share your recent lab results so we can continue evaluating your case.',
        REQUEST_IMAGING: 'Please share your diagnostic images so we can continue evaluating your case.',
        REQUEST_PHOTOS: 'Please share clinical photos so we can continue evaluating your case.',
        FINAL_APPROVED: 'We reviewed your case and it is ready to move to the next step.',
        FINAL_NOT_ELIGIBLE: 'We reviewed your case and at this time it is not eligible for this service.'
    };
    var quickReplySingleFootprintKeys = {
        REQUEST_MEDICAL_HISTORY: true,
        REQUEST_LABS: true,
        REQUEST_IMAGING: true,
        REQUEST_PHOTOS: true,
        FINAL_APPROVED: true,
        FINAL_NOT_ELIGIBLE: true
    };

    function adminVisibleQuickReplyLabel(rawValue) {
        var normalized = String(rawValue || '').trim().split(/\r?\n/, 1)[0].toUpperCase().replace(/^\[(ACTION|REPLY)\]\s*/i, '');
        var map = {
            DATES_AVAILABLE: 'FECHAS DISPONIBLES',
            DATES_NOT_AVAILABLE: 'FECHAS NO DISPONIBLES',
            REQUEST_MEDICAL_HISTORY: 'SOLICITAR HISTORIA CLÍNICA',
            REQUEST_HISTORY: 'SOLICITAR HISTORIA CLÍNICA',
            REQUEST_LABS: 'SOLICITAR LABORATORIOS',
            REQUEST_IMAGING: 'SOLICITAR IMÁGENES',
            REQUEST_PHOTOS: 'SOLICITAR FOTOGRAFÍAS',
            FINAL_APPROVED: 'APROBACIÓN FINAL',
            FINAL_NOT_ELIGIBLE: 'NO ELEGIBLE',
            NOT_ELIGIBLE: 'NO ELEGIBLE'
        };
        return map[normalized] || '';
    }

    function adminVisibleActionLabel(rawValue) {
        var normalized = String(rawValue || '').trim().split(/\r?\n/, 1)[0].toUpperCase().replace(/^\[(ACTION|REPLY)\]\s*/i, '');
        var map = {
            FINAL_ACCEPT_AND_PAY: 'ACEPTÓ Y CONTINÚA CON EL SIGUIENTE PASO',
            FINAL_DECLINE: 'DECLINÓ CONTINUAR',
            PROPOSE_NEW_DATES: 'SOLICITÓ NUEVAS FECHAS'
        };
        return map[normalized] || '';
    }

    function adminProposalResponseMeta(actionType) {
        var normalized = String(actionType || '').trim().toUpperCase();
        var map = {
            ACCEPT_PROPOSAL: { cls: 'success', label: 'Aceptó la propuesta' },
            REQUEST_CHANGES: { cls: 'warning', label: 'Solicitó cambios' },
            REJECT_PROPOSAL: { cls: 'danger', label: 'Rechazó la propuesta' },
            DOCS_NOT_AVAILABLE: { cls: 'default', label: 'Indicó que no tiene los documentos' }
        };
        return map[normalized] || { cls: 'default', label: normalized || 'Respuesta' };
    }

    function parseReplyTokenAndNote(text) {
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

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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
                hint: 'Se confirma solo dentro de MedTravel. No crea Google Calendar ni Google Meet.',
                badge: 'MedTravel',
                badgeClass: 'label-default'
            },
            calendar_only: {
                label: 'Reunión con Google Calendar',
                hint: 'Al aceptar se crea un evento en Google Calendar, sin enlace Meet.',
                badge: 'Calendar',
                badgeClass: 'label-info'
            },
            calendar_plus_meet: {
                label: 'Reunión con Google Meet',
                hint: 'Al aceptar se crea un evento en Google Calendar con enlace de Google Meet.',
                badge: 'Calendar + Meet',
                badgeClass: 'label-success'
            }
        };
        return map[normalized] || map.calendar_plus_meet;
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
        if (String(fullText || '').indexOf('[MEETING_PROPOSAL]') === 0) {
            var payload = parseStructuredJson('[MEETING_PROPOSAL]', fullText);
            if (payload) {
                return {
                    startAt: String(payload.start_at || '').trim(),
                    endAt: String(payload.end_at || '').trim(),
                    note: String(payload.note || '').trim(),
                    integrationMode: String(payload.integration_mode || 'calendar_plus_meet').trim().toLowerCase()
                };
            }
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

    function renderMeetingProposalCard(text) {
        var proposal = parseMeetingProposalPayload(text);
        if (!proposal) {
            return '<span style="white-space:pre-wrap;">' + esc(text) + '</span>';
        }
        var integration = meetingIntegrationMeta(proposal.integrationMode);

        return '<div class="panel panel-warning" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>' + esc(integration.label) + '</strong> <span class="label ' + esc(integration.badgeClass) + '" style="margin-left:6px;">' + esc(integration.badge) + '</span></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                '<div><strong>Inicio:</strong> ' + esc(formatInboxDateTime(proposal.startAt)) + '</div>' +
                '<div style="margin-top:6px;"><strong>Fin:</strong> ' + esc(formatInboxDateTime(proposal.endAt)) + '</div>' +
                (proposal.note ? '<div style="margin-top:6px;"><strong>Nota:</strong> ' + esc(proposal.note) + '</div>' : '') +
                '<div class="text-muted" style="margin-top:8px;">' + esc(integration.hint) + '</div>' +
                '<div class="text-muted" style="margin-top:4px;">Pendiente de respuesta del paciente.</div>' +
            '</div>' +
        '</div>';
    }

    function renderMeetingConfirmedCard(fullText) {
        var payload = parseStructuredJson('[MEETING_CONFIRMED]', fullText);
        if (!payload) {
            return renderStructuredParseFallback('[MEETING_CONFIRMED]');
        }
        var integration = meetingIntegrationMeta(payload.integration_mode || (payload.meet_url ? 'calendar_plus_meet' : (payload.html_link ? 'calendar_only' : 'internal_only')));
        var isCancelled = isMeetingCancelledPayload(payload);
        var canCancel = !isCancelled && currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;

        var actionsHtml = '<div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;">';
        if (!isCancelled && payload.meet_url) {
            actionsHtml += '<a class="btn btn-success btn-xs" href="' + esc(payload.meet_url) + '" target="_blank" rel="noopener">ABRIR MEET</a>';
        }
        if (!isCancelled && payload.html_link) {
            actionsHtml += '<a class="btn btn-default btn-xs" href="' + esc(payload.html_link) + '" target="_blank" rel="noopener">ABRIR EVENTO</a>';
        }
        if (canCancel) {
            actionsHtml += '<button type="button" class="btn btn-danger btn-xs admin-meeting-cancel">CANCELAR REUNIÓN</button>';
        }
        actionsHtml += '</div>';

        return '<div class="panel ' + (isCancelled ? 'panel-warning' : 'panel-success') + '" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>' + esc(isCancelled ? 'Reunión cancelada' : 'Reunión confirmada') + '</strong> <span class="label ' + esc(integration.badgeClass) + '" style="margin-left:6px;">' + esc(integration.badge) + '</span></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                (payload.start_at ? '<div><strong>Inicio:</strong> ' + esc(formatInboxDateTime(payload.start_at)) + '</div>' : '') +
                (payload.end_at ? '<div style="margin-top:6px;"><strong>Fin:</strong> ' + esc(formatInboxDateTime(payload.end_at)) + '</div>' : '') +
                '<div style="margin-top:6px;"><strong>Tipo:</strong> ' + esc(integration.label) + '</div>' +
                (payload.organizer_email ? '<div style="margin-top:6px;"><strong>Organizador:</strong> ' + esc(payload.organizer_email) + '</div>' : '') +
                '<div class="text-muted" style="margin-top:8px;">' + esc(isCancelled ? 'La reunión fue cancelada. El caso sigue activo y puede reagendarse.' : integration.hint) + '</div>' +
                actionsHtml +
            '</div>' +
        '</div>';
    }

    function renderMeetingCancelledCard(fullText) {
        var payload = parseStructuredJson('[MEETING_CANCELLED]', fullText);
        if (!payload) {
            return renderStructuredParseFallback('[MEETING_CANCELLED]');
        }

        var integration = meetingIntegrationMeta(payload.integration_mode || 'calendar_plus_meet');
        var cancelledByRole = String(payload.cancelled_by_role || '').trim().toUpperCase();
        var byLabel = 'el equipo';
        if (cancelledByRole === 'CLIENT') {
            byLabel = 'el paciente';
        } else if (cancelledByRole === 'PROVIDER') {
            byLabel = 'el prestador';
        } else if (cancelledByRole === 'ADMIN') {
            byLabel = 'coordinación';
        }

        return '<div class="panel panel-warning" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>Reunión cancelada</strong> <span class="label ' + esc(integration.badgeClass) + '" style="margin-left:6px;">' + esc(integration.badge) + '</span></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                (payload.start_at ? '<div><strong>Inicio:</strong> ' + esc(formatInboxDateTime(payload.start_at)) + '</div>' : '') +
                (payload.end_at ? '<div style="margin-top:6px;"><strong>Fin:</strong> ' + esc(formatInboxDateTime(payload.end_at)) + '</div>' : '') +
                '<div style="margin-top:6px;"><strong>Cancelada por:</strong> ' + esc(byLabel) + '</div>' +
                '<div class="text-muted" style="margin-top:8px;">El caso sigue activo y puede reagendarse.</div>' +
            '</div>' +
        '</div>';
    }

    function renderMeetingChangeRequestedCard() {
        return '<div class="panel panel-info" style="margin:0;">' +
            '<div class="panel-heading" style="padding:8px 10px;"><strong>Cambio solicitado</strong></div>' +
            '<div class="panel-body" style="padding:10px;">' +
                '<div>El paciente pidió ajustar la propuesta de reunión desde Inbox ITEM.</div>' +
            '</div>' +
        '</div>';
    }

    function resetAttachDocumentModal() {
        var $form = $('#admin-attach-document-form');
        if ($form.length && $form[0]) {
            $form[0].reset();
        }
        $('#admin-attach-thread-id').val('');
        $('#admin-attach-thread-type').val('');
        $('#admin-attach-request-id').val('');
        $('#admin-attach-item-id').val('');
        $('#admin-attach-context').text('Contexto sin definir.');
        setAttachModalBusy(false);
        setAttachStatus('');
    }

    function setAttachStatus(message, tone) {
        var $status = $('#admin-chat-attach-status');
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
        $('#admin-attach-submit-btn').prop('disabled', attachModalBusy);
        $('#admin-attach-document-form').find('input, select, textarea').prop('disabled', attachModalBusy);
    }

    function describeUploadError(res) {
        var code = '';
        if (res && res.results && res.results[0] && res.results[0].message) {
            code = String(res.results[0].message);
        } else if (res && res.message) {
            code = String(res.message);
        }
        var map = {
            FEE_REQUIRED: 'La condición de coordinación sigue pendiente.',
            FREE_MESSAGE_BLOCKED: 'El envío de mensajes está limitado temporalmente para este hilo.',
            file_required: 'Debes seleccionar un archivo.',
            upload_error: 'No se pudo procesar el archivo cargado.',
            file_too_large: 'El archivo supera el tamaño máximo permitido.',
            invalid_tmp_file: 'No se pudo leer el archivo temporal.',
            file_extension_not_allowed: 'La extensión del archivo no está permitida.',
            file_type_not_allowed: 'El tipo de archivo no está permitido.',
            file_save_failed: 'No se pudo guardar el archivo.',
            insert_failed: 'No se pudieron guardar los metadatos del documento.',
            upload_failed: 'No se pudo adjuntar el documento. Intenta de nuevo.',
            client_not_resolved: 'No se pudo resolver el paciente asociado a esta solicitud.'
        };
        if (map[code]) {
            return map[code];
        }
        if (code.indexOf('insert_failed:') === 0) {
            return map.insert_failed;
        }
        return code || 'No se pudo adjuntar el documento. Intenta de nuevo.';
    }

    function cleanDocumentTitleFallback(filename) {
        var raw = String(filename || '').trim();
        if (!raw) return 'Documento';
        var base = raw.replace(/\.[a-z0-9]{2,8}$/i, '');
        base = base.replace(/[_\-]+/g, ' ').replace(/\s+/g, ' ').trim();
        return base || raw;
    }

    function normalizeDocumentTypeKey(type) {
        var key = String(type || 'other').toLowerCase().trim();
        var aliasMap = {
            history: 'medical_history',
            medical_history: 'medical_history',
            labs: 'lab_results',
            lab_results: 'lab_results',
            imaging: 'diagnostic_imaging',
            diagnostic_imaging: 'diagnostic_imaging',
            photos: 'photos',
            quote: 'quote',
            consent_form: 'consent_form',
            medical_order: 'medical_order',
            prescription: 'prescription',
            administrative_document: 'administrative_document',
            invoice: 'administrative_document',
            contract: 'administrative_document',
            insurance: 'administrative_document',
            passport: 'administrative_document',
            id_card: 'administrative_document',
            other: 'other'
        };
        return aliasMap[key] || 'other';
    }

    function populateAttachDocumentContext() {
        var threadId = currentThread && currentThread.thread_id ? String(currentThread.thread_id) : '';
        var threadType = currentThread && currentThread.thread_type ? String(currentThread.thread_type) : 'ITEM';
        var requestId = currentThread ? parseInt(currentThread.booking_request_id || 0, 10) : 0;
        var itemId = currentThread ? parseInt(currentThread.item_id || 0, 10) : 0;
        $('#admin-attach-thread-id').val(threadId);
        $('#admin-attach-thread-type').val(threadType);
        $('#admin-attach-request-id').val(requestId > 0 ? String(requestId) : '');
        $('#admin-attach-item-id').val(itemId > 0 ? String(itemId) : '');
        var parts = ['Hilo: ' + (threadId || 'Sin definir')];
        if (requestId > 0) parts.push('Solicitud #' + requestId);
        if (itemId > 0) parts.push('Item #' + itemId);
        $('#admin-attach-context').text(parts.join(' · '));
    }

    function openAttachDocumentModal() {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Selecciona primero un hilo del inbox');
            return;
        }
        resetAttachDocumentModal();
        populateAttachDocumentContext();
        $('#adminAttachDocumentModal').modal('show');
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

    function buildSharedDocumentMessage(results) {
        var docs = $.isArray(results) ? results.filter(function (item) {
            return item && item.ok === true;
        }) : [];
        if (!docs.length) {
            return 'Documento compartido';
        }
        return docs.map(function (item) {
            var title = String(item.title || cleanDocumentTitleFallback(item.original_filename || item.filename || 'Documento')).trim();
            var typeLabel = docTypeLabel(item.document_type || 'other');
            var fileLabel = String(item.original_filename || item.filename || '').trim();
            var note = String(item.description || item.document_note || '').trim();
            var lines = ['Documento compartido: ' + title, 'Tipo: ' + typeLabel];
            if (fileLabel) {
                lines.push('Archivo: ' + fileLabel);
            }
            if (note) {
                lines.push('Observación: ' + note);
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
            var singleMatch = trimmedLine.match(/^(Documento compartido|Shared document):\s*(.+)$/i);
            if (singleMatch) {
                var entry = {
                    lookup_name: singleMatch[2].trim(),
                    title: singleMatch[2].trim(),
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
                    var typeMatch = detailLine.match(/^(Tipo|Type):\s*(.+)$/i);
                    if (typeMatch) {
                        entry.document_type = normalizeDocumentTypeKey(typeMatch[2]);
                        i++;
                        continue;
                    }
                    var fileMatch = detailLine.match(/^(Archivo|File):\s*(.+)$/i);
                    if (fileMatch) {
                        entry.file_name = fileMatch[2].trim();
                        i++;
                        continue;
                    }
                    var noteMatch = detailLine.match(/^(Observación|Observation):\s*(.+)$/i);
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
            if (doc.id) {
                return '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(doc.id));
            }
            var filePath = String(doc.file_path || '').trim();
            if (filePath) {
                return '/uploads/medical_docs/' + String(filePath).replace(/^\/+/, '');
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
            var title = doc
                ? String(doc.title || entry.title || cleanDocumentTitleFallback(doc.original_filename || doc.filename || 'Documento'))
                : String(entry.title || cleanDocumentTitleFallback(entry.file_name || entry.lookup_name || 'Documento'));
            var originalName = doc
                ? String(doc.original_filename || doc.filename || entry.file_name || title || ('Documento #' + (doc.id || '')))
                : String(entry.file_name || entry.lookup_name || title || '');
            var typeKey = normalizeDocumentTypeKey((doc && doc.document_type) || entry.document_type || 'other');
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
                    '<a class="mt-shared-doc-link" href="' + encodedHref + '" data-doc-id="' + docIdAttr + '" data-url="' + encodedHref + '" target="_blank" rel="noopener">Abrir documento</a>' +
                '</div>')
                : '';
            return '<div class="mt-shared-doc-card">' +
                '<div class="mt-shared-doc-label"><i class="fa fa-paperclip" aria-hidden="true"></i> Documento compartido</div>' +
                titleHtml +
                '<div class="mt-shared-doc-meta">Tipo: ' + esc(docTypeLabel(typeKey)) + '</div>' +
                (originalName ? '<div class="mt-shared-doc-file">Archivo: ' + esc(originalName) + '</div>' : '') +
                (note ? '<div class="mt-shared-doc-note">Observación: ' + esc(note) + '</div>' : '') +
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
            medical_history: 'Historia clínica',
            lab_results: 'Examen / laboratorio',
            diagnostic_imaging: 'Imagen diagnóstica',
            photos: 'Imagen clínica',
            quote: 'Cotización',
            consent_form: 'Consentimiento',
            medical_order: 'Orden médica',
            prescription: 'Fórmula / indicación',
            administrative_document: 'Documento administrativo',
            other: 'Otro'
        };
        var key = normalizeDocumentTypeKey(type || 'other');
        return labels[key] || 'Otro';
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
        var isItemThread = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;
        if (!isItemThread) {
            return '';
        }
        var hasDocs = docs && docs.length > 0;
        var countHtml = hasDocs
            ? ' <span class="badge" style="background:#7f8c9d;">' + docs.length + '</span>'
            : '';
        var innerHtml;
        if (!hasDocs) {
            innerHtml = '<p class="mt-docs-empty text-muted">Aún no hay documentos compartidos.</p>';
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
            docs.forEach(function (doc) {
                var typeKey = normalizeDocumentTypeKey(doc.document_type || 'other');
                var typeLabel = docTypeLabel(typeKey);
                var cls = typeCls[typeKey] || 'label-default';
                var originalName = String(doc.original_filename || doc.filename || ('Documento #' + (doc.id || '')));
                var title = String(doc.title || cleanDocumentTitleFallback(originalName)).trim();
                var note = String(doc.description || '').trim();
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
                        '<div class="mt-doc-main">' +
                            '<a href="' + esc(href) + '" class="mt-doc-title mt-doc-open" data-doc-id="' + esc(String(doc.id || '')) + '" data-url="' + esc(encodedHref) + '" title="Ver ' + esc(title) + '">' + esc(title) + '</a>' +
                            '<span class="mt-doc-name">Archivo: ' + esc(originalName) + '</span>' +
                            (note ? '<span class="mt-doc-note">Observación: ' + esc(note) + '</span>' : '') +
                        '</div>' +
                        (dateText ? '<small class="mt-doc-date text-muted"><i class="fa fa-clock-o" aria-hidden="true"></i> ' + esc(dateText) + '</small>' : '') +
                        '<button type="button" class="btn btn-xs btn-info mt-doc-view"' +
                            ' data-doc-id="' + esc(String(doc.id || '')) + '"' +
                            ' data-url="' + esc(encodedHref) + '"' +
                            ' title="Ver ' + esc(title) + '">' +
                            '<i class="fa fa-eye" aria-hidden="true"></i> Ver' +
                        '</button>' +
                        '<a class="btn btn-xs btn-default mt-doc-download" href="' + esc(href) + '" target="_blank" rel="noopener" title="Descargar ' + esc(title) + '">' +
                            '<i class="fa fa-download" aria-hidden="true"></i> Descargar' +
                        '</a>' +
                    '</div>';
            });
            innerHtml += '</div>';
        }
        return '<div class="mt-docs-section">' +
            '<div class="mt-docs-header">' +
                '<i class="fa fa-paperclip mt-docs-icon" aria-hidden="true"></i> ' +
                '<strong>Documentos compartidos' + countHtml + '</strong>' +
            '</div>' +
            innerHtml +
        '</div>';
    }

    function syncThreadDocumentsPanel(docs) {
        var $panel = $('#admin-inbox-docs-panel');
        var $content = $('#admin-inbox-docs-content');
        var $count = $('#admin-inbox-docs-count');
        var $collapse = $('#admin-inbox-docs-collapse');
        if (!$panel.length || !$content.length || !$count.length || !$collapse.length) {
            return;
        }
        var html = renderThreadDocuments(docs || []);
        if (!html) {
            $content.html('');
            $count.text('0');
            $panel.hide();
            $collapse.removeClass('in').css('height', '');
            return;
        }
        $content.html(html);
        $count.text(String(($.isArray(docs) ? docs.length : 0)));
        $panel.show();
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
        return 'Enviando acción estructurada…';
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
            : '<div class="text-muted">No se especificaron tipos de documento.</div>';

        return '<div class="admin-structured-card admin-structured-request">' +
            '<div class="admin-structured-header">' +
                '<i class="fa fa-file-medical-o admin-structured-icon" aria-hidden="true"></i>' +
                '<span class="admin-structured-title">Información adicional solicitada</span>' +
                '<span class="label label-warning admin-structured-badge">En espera del cliente</span>' +
            '</div>' +
            '<div class="admin-structured-body">' +
                '<div><strong>Tipos solicitados</strong></div>' +
                listHtml +
                (note ? '<div class="admin-structured-note"><strong>Nota:</strong> ' + esc(note) + '</div>' : '') +
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
                '<span class="admin-structured-title">Propuesta de ajuste de cotización</span>' +
                '<span class="label label-warning admin-structured-badge">En espera de respuesta del cliente</span>' +
            '</div>' +
            '<div class="admin-structured-body">' +
                '<div><strong>Monto:</strong> ' + esc(amount) + '</div>' +
                (notes ? '<div class="admin-structured-note"><strong>Justificación:</strong> ' + esc(notes) + '</div>' : '') +
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
        var badge = adminProposalResponseMeta(action);

        return '<div class="admin-structured-card admin-structured-response">' +
            '<div class="admin-structured-header">' +
                '<i class="fa fa-check-circle admin-structured-icon" aria-hidden="true"></i>' +
                '<span class="admin-structured-title">Respuesta a la propuesta</span>' +
                '<span class="label label-' + esc(badge.cls) + ' admin-structured-badge">' + esc(badge.label) + '</span>' +
            '</div>' +
            '<div class="admin-structured-body">' +
                (notes ? '<div class="admin-structured-note"><strong>Nota:</strong> ' + esc(notes) + '</div>' : '<div class="text-muted">Sin notas adicionales.</div>') +
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
        if (trimmed.indexOf('[MEETING_PROPOSAL]') === 0) {
            return renderMeetingProposalCard(trimmed);
        }
        if (trimmed.indexOf('[MEETING_CONFIRMED]') === 0) {
            return renderMeetingConfirmedCard(trimmed);
        }
        if (trimmed.indexOf('[MEETING_CANCELLED]') === 0) {
            return renderMeetingCancelledCard(trimmed);
        }
        if (/^\[ACTION\]\s*Client rejected proposed dates$/i.test(trimmed)) {
            return renderMeetingChangeRequestedCard();
        }

        var label = '';
        var isReply = false;
        if (trimmed.indexOf('[ACTION]') === 0) {
            label = 'Acción';
            trimmed = trimmed.replace(/^\[ACTION\]\s*/i, '');
        } else if (trimmed.indexOf('[REPLY]') === 0) {
            label = 'Respuesta';
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
            return '<span style="white-space:pre-wrap;">' + esc(text) + '</span>';
        }

        if (isReply && trimmed.toUpperCase().indexOf('PROPOSED_DATES') === 0) {
            return renderMeetingProposalCard(trimmed);
        }

        var replyMeta = isReply ? parseReplyTokenAndNote(trimmed) : { token: trimmed, note: '' };
        var replyToken = String(replyMeta.token || '').trim();
        var replyNote = String(replyMeta.note || '').trim();
        var actionToken = !isReply ? replyToken : '';
        var actionNote = !isReply ? replyNote : '';
        var visibleBody = isReply
            ? (adminVisibleQuickReplyLabel(replyToken) || replyToken || trimmed)
            : (adminVisibleActionLabel(actionToken) || actionToken || trimmed);
        var messageHtml = '<span class="label label-primary" style="margin-right:6px;">' + esc(label) + '</span>' + esc(visibleBody);
        if (isReply && replyNote) {
            messageHtml += '<div style="margin-top:8px;white-space:pre-wrap;">' + esc(replyNote) + '</div>';
        }
        if (!isReply && actionNote) {
            messageHtml += '<div style="margin-top:8px;white-space:pre-wrap;">' + esc(actionNote) + '</div>';
        }
        var structuredReplyUpper = replyToken.toUpperCase();
        var structuredActionUpper = actionToken.toUpperCase();
        if (isReply) {
            if (structuredReplyUpper.indexOf('REQUEST LABS') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="labs">SUBIR LABORATORIOS</button>' +
                    '</div>';
            }
            if (structuredReplyUpper.indexOf('REQUEST IMAGING') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="imaging">SUBIR IMÁGENES</button>' +
                    '</div>';
            }
            if (structuredReplyUpper.indexOf('REQUEST PHOTOS') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="photos">SUBIR FOTOGRAFÍAS</button>' +
                    '</div>';
            }
            if (structuredReplyUpper.indexOf('REQUEST HISTORY') !== -1) {
                messageHtml += '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-default btn-xs client-structured-upload" data-upload-type="history">SUBIR HISTORIA CLÍNICA</button>' +
                    '</div>';
            }
        }

        if (isReply && structuredReplyUpper.indexOf('FINAL_APPROVED') !== -1 && feeGateActive) {
            messageHtml += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' +
                '<button type="button" class="btn btn-default btn-xs client-final-action" data-action="final_accept_and_pay">ACEPTAR Y PAGAR</button>' +
                '<button type="button" class="btn btn-default btn-xs client-final-action" data-action="final_decline">DECLINAR</button>' +
                '</div>';
        }

        if (!isReply && structuredActionUpper.indexOf('FINAL_ACCEPT_AND_PAY') === 0) {
            var bookingId = currentThread && currentThread.booking_id ? currentThread.booking_id : 0;
            var payUrl = '/booking.php';
            if (bookingId) {
                payUrl += '?request_id=' + encodeURIComponent(String(bookingId));
            }
            messageHtml += '<div style="margin-top:8px;">' +
                '<a class="btn btn-xs btn-success" href="' + esc(payUrl) + '">Continuar con pago de coordinación</a>' +
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
            return 'solicitó información adicional';
        }
        if (normalized.indexOf('[PROPOSE_QUOTE]') === 0) {
            return 'envió ajuste de propuesta';
        }
        if (normalized.indexOf('[PROPOSAL_RESPONSE]') === 0) {
            var proposalPayload = parseStructuredJson('[PROPOSAL_RESPONSE]', normalized);
            var proposalAction = String(proposalPayload && proposalPayload.action_type || '').toUpperCase();
            if (proposalAction === 'ACCEPT_PROPOSAL') return 'aceptó la propuesta';
            if (proposalAction === 'REQUEST_CHANGES') return 'solicitó cambios';
            if (proposalAction === 'REJECT_PROPOSAL') return 'rechazó la propuesta';
            if (proposalAction === 'DOCS_NOT_AVAILABLE') return 'indicó documentos no disponibles';
            return 'envió respuesta a la propuesta';
        }

        var rawText = String(raw || '').trim();
        var isReplyPreview = rawText.indexOf('[REPLY]') === 0;
        normalized = normalized.replace(/^\[(ACTION|REPLY)\]\s*/i, '').trim();
        var previewMeta = parseReplyTokenAndNote(normalized);
        var previewToken = String(previewMeta.token || '').toUpperCase().replace(/\s+/g, '_');
        var previewNote = String(previewMeta.note || '').replace(/\s+/g, ' ').trim();
        if (previewNote) {
            return previewNote.length > 110 ? previewNote.slice(0, 110).trim() + '…' : previewNote;
        }

        var quickReplyPreviewMap = {
            DATES_AVAILABLE: 'fechas disponibles',
            DATES_NOT_AVAILABLE: 'fechas no disponibles',
            REQUEST_MEDICAL_HISTORY: 'provider solicitó historia clínica',
            REQUEST_LABS: 'provider solicitó laboratorios',
            REQUEST_IMAGING: 'provider solicitó imágenes diagnósticas',
            REQUEST_PHOTOS: 'provider solicitó fotografías clínicas',
            FINAL_APPROVED: 'provider indicó caso viable',
            FINAL_NOT_ELIGIBLE: 'provider indicó caso no viable'
        };
        if (isReplyPreview && quickReplyPreviewMap[previewToken]) {
            return quickReplyPreviewMap[previewToken];
        }

        var actionPreviewMap = {
            FINAL_ACCEPT_AND_PAY: 'confirmó que desea continuar',
            FINAL_DECLINE: 'declinó continuar',
            PROPOSE_NEW_DATES: 'solicitó nuevas fechas'
        };
        if (!isReplyPreview && actionPreviewMap[previewToken]) {
            return actionPreviewMap[previewToken];
        }

        var previewText = String(raw || '').replace(/\s+/g, ' ').trim();
        if (previewText.length > 110) {
            previewText = previewText.slice(0, 110).trim() + '…';
        }

        return previewText;
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
            syncThreadDocumentsPanel([]);
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

    function findDocumentById(docId) {
        var target = String(docId || '').trim();
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
        var safeDoc = doc || {};
        var originalName = String(safeDoc.original_filename || safeDoc.filename || 'Documento');
        var displayTitle = String(safeDoc.title || cleanDocumentTitleFallback(originalName));
        var typeKey = normalizeDocumentTypeKey(safeDoc.document_type || 'other');
        var typeLabel = docTypeLabel(typeKey);
        var mimeType = String(safeDoc.mime_type || '').toLowerCase().trim();
        var href = String(safeDoc.download_url || fallbackUrl || '').trim();
        if (!href && safeDoc.id) {
            href = '/admin/ajax/download_medical_document.php?doc_id=' + encodeURIComponent(String(safeDoc.id));
        }
        var previewUrl = href;
        if (safeDoc.id) {
            previewUrl = '/admin/ajax/preview_medical_document.php?doc_id=' + encodeURIComponent(String(safeDoc.id));
        }
        var previewType = resolvePreviewType({ mime: mimeType, name: originalName, url: href });
        // Header
        $('#adminDocViewerName').text(displayTitle);
        $('#adminDocViewerType').text(typeLabel);
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
        $('#adminDocViewerType').attr('class', 'label ' + (typeCls[typeKey] || 'label-default') + ' mt-dv-type-badge');
        // Meta: size + date
        var metaParts = [];
        metaParts.push('Archivo: ' + originalName);
        var uploadedRaw = String(safeDoc.uploaded_at || safeDoc.created_at || '').trim();
        if (uploadedRaw) {
            var d = new Date(uploadedRaw.replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                var dd = (d.getDate() < 10 ? '0' : '') + d.getDate();
                var mo = ((d.getMonth() + 1) < 10 ? '0' : '') + (d.getMonth() + 1);
                metaParts.push('Cargado: ' + dd + '/' + mo + '/' + d.getFullYear());
            }
        }
        if (safeDoc.file_size > 0) {
            var kb = (safeDoc.file_size / 1024).toFixed(1);
            metaParts.push(kb + ' KB');
        }
        if (mimeType) { metaParts.push(mimeType); }
        $('#adminDocViewerMeta').text(metaParts.join(' · '));
        // Download button
        $('#adminDocViewerDownload').attr('href', href || '#');
        // Preview
        var $preview = $('#adminDocViewerPreview');
        if (previewType === 'image' && previewUrl) {
            $preview.html('<img src="' + esc(previewUrl) + '" alt="' + esc(originalName) + '">');
        } else if (previewType === 'pdf' && previewUrl) {
            $preview.html('<iframe src="' + esc(previewUrl) + '" title="' + esc(originalName) + '"></iframe>');
        } else {
            $preview.html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<div>Vista previa no disponible para este tipo de archivo.</div>' +
                    '<div style="margin-top:8px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">' +
                        '<a href="' + esc(href || '#') + '" target="_blank" rel="noopener" class="btn btn-default btn-sm">' +
                            '<i class="fa fa-external-link" aria-hidden="true"></i> Abrir en otra pestaña</a>' +
                        '<a href="' + esc(href || '#') + '" target="_blank" rel="noopener" class="btn btn-primary btn-sm">' +
                            '<i class="fa fa-download" aria-hidden="true"></i> Descargar</a>' +
                    '</div>' +
                '</div>'
            );
        }
        $('#adminDocViewerModal').modal('show');
    }

    function renderMessages(messages) {
        var $box = $('#admin-inbox-messages');
        if (!$box.length) return;
        var divider = '<div class="mt-section-divider">Mensajes</div>';
        if (!messages || !messages.length) {
            cancelledMeetingKeys = {};
            $box.html(divider + '<p class="text-muted" style="margin:0;">No messages in this thread yet.</p>');
            return;
        }

        annotateGrouping(messages, null);
        cancelledMeetingKeys = collectCancelledMeetingKeys(messages);
        var html = divider;
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
        var hasMeetingCancellation = filtered.some(function (m) {
            return String(m && m.body ? m.body : '').trim().indexOf('[MEETING_CANCELLED]') === 0;
        });
        if (hasMeetingCancellation) {
            loadMessages();
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
        if (normalized === 'client') return 'El paciente está escribiendo…';
        if (normalized === 'provider') return 'El prestador está escribiendo…';
        if (normalized === 'patientcare' || normalized === 'admin') return 'Soporte está escribiendo…';
        return 'Alguien está escribiendo…';
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

        if ($alert.length) {
            if (feeGateActive) {
                $alert.show();
            } else {
                $alert.hide();
            }
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
        var isItemThread = currentThread && String(currentThread.thread_type || '').toUpperCase() === 'ITEM' && parseInt(currentThread.item_id || 0, 10) > 0;

        var $quick = $('#admin-inbox-quick-replies');
        var $msg = $('#admin-inbox-message');
        var $send = $('#admin-inbox-send-form button[type="submit"]');
        var $attach = $('#admin-chat-attach-btn');
        var $composerGroup = $('#admin-inbox-send-form .form-group');
        var $typing = $('#admin-typing-indicator');
        var $note = $('#admin-inbox-compose-note');

        if ($quick.length) {
            if (isItemThread) {
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
                var noteText = '';
                if (feeGateActive) {
                    noteText = 'La mensajería libre está bloqueada por la condición comercial de coordinación. Puedes seguir usando las acciones formales.';
                } else if (typeof noticeMessage === 'string' && noticeMessage.trim() !== '') {
                    noteText = noticeMessage;
                    lastComposeNotice = noticeMessage;
                } else if (lastComposeNotice) {
                    noteText = lastComposeNotice;
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

        toggleStructuredActionButtons(isItemThread);
    }

    function toggleStructuredActionButtons(isItemThread) {
        var $box = $('#admin-inbox-structured-actions');
        if (!$box.length) return;
        if (isItemThread) {
            $box.show();
        } else {
            $box.hide();
        }
    }

    function loadThreads() {
        var listData = { action: 'list_threads' };
        if (urlFilterThreadId) {
            listData.filter_thread_id = urlFilterThreadId;
        }
        $.ajax({
            url: 'ajax/inbox.php',
            method: 'GET',
            dataType: 'json',
            data: listData
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

        $('#admin-inbox-title').text('Cargando...');
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
                toastr.error((res && res.message) ? res.message : 'No se pudieron cargar los mensajes');
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
            syncThreadDocumentsPanel(currentDocuments);

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
                toastr.warning('La condición de coordinación sigue pendiente');
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                var notice = res.notice || res.free_message_notice || lastComposeNotice || '';
                setComposeGateState(false, notice);
                return;
            }
            toastr.error('No se pudieron cargar los mensajes');
        });
    }

    function uploadDocumentsBatch(documents) {
        var deferred = $.Deferred();
        if (!currentThread || !currentThread.thread_id) {
            deferred.reject({ message: 'Selecciona primero un hilo del inbox' });
            return deferred.promise();
        }
        if (!documents || !documents.length) {
            deferred.resolve({ ok: true, uploaded_count: 0, results: [] });
            return deferred.promise();
        }

        var formData = new FormData();
        documents.forEach(function (doc) {
            formData.append('chat_files[]', doc.file);
            formData.append('document_title[]', doc.title || '');
            formData.append('document_type[]', normalizeDocumentTypeKey(doc.document_type || 'other'));
            formData.append('document_note[]', doc.note || '');
        });
        formData.append('action', 'upload_documents');
        formData.append('thread_id', currentThread.thread_id);
        formData.append('thread_type', currentThread.thread_type || 'ITEM');
        formData.append('request_id', currentThread.booking_request_id || 0);
        formData.append('item_id', currentThread.item_id || 0);

        $.ajax({
            url: 'ajax/inbox.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true) {
                deferred.reject(res || { message: 'No se pudo adjuntar el documento. Intenta de nuevo.' });
                return;
            }
            deferred.resolve(res);
        }).fail(function (xhr) {
            deferred.reject((xhr && xhr.responseJSON) ? xhr.responseJSON : { message: 'No se pudo adjuntar el documento. Intenta de nuevo.' });
        });

        return deferred.promise();
    }

    function sendMessageText(text, options) {
        options = options || {};
        if (!currentThread || !currentThread.thread_id) return;
        if (composeBusy) return;
        if (!freeMessageAllowed) {
            return;
        }
        if (feeGateActive) {
            toastr.warning('La condición de coordinación sigue pendiente');
            return;
        }

        var pendingId = addPendingMessage(text);
        emitTyping('stop');
        setComposeBusy(true, options.busyMessage || 'Enviando mensaje...');

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
                    toastr.warning('La condición de coordinación sigue pendiente');
                    updatePendingStatus(pendingId, 'Falló');
                    return;
                }
                if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                    var notice = res.notice || res.free_message_notice || lastComposeNotice || '';
                    setComposeGateState(false, notice);
                    updatePendingStatus(pendingId, 'Falló');
                    return;
                }
                toastr.error((res && res.message) ? res.message : 'No se pudo enviar el mensaje');
                updatePendingStatus(pendingId, 'Falló');
                return;
            }
            markSentFromResponse(res);
            removePendingMessage(pendingId);
            realtimeEmitCommitted(currentThread.thread_id, res, 'ADMIN');
            if (options.clearComposer !== false) {
                $('#admin-inbox-message').val('');
            }
            if (options.suppressSuccessToast) {
                // no-op
            } else if (options.successToast) {
                toastr.success(options.successToast);
            } else {
                toastr.success('Mensaje enviado');
            }
            setComposeBusy(false, '');
            loadMessages();
            loadThreads();
        }).fail(function (xhr) {
            setComposeBusy(false, '');
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            updatePendingStatus(pendingId, 'Falló');
            if (res && res.code === 'FEE_REQUIRED') {
                setFeeGateState(true);
                setComposeGateState(true, '');
                toastr.warning('La condición de coordinación sigue pendiente');
                return;
            }
            if (res && res.code === 'FREE_MESSAGE_BLOCKED') {
                var notice = res.notice || res.free_message_notice || lastComposeNotice || '';
                setComposeGateState(false, notice);
                return;
            }
            toastr.error('No se pudo enviar el mensaje');
        });
    }

    function sendMessage() {
        if (composeBusy) {
            return;
        }
        var text = $.trim($('#admin-inbox-message').val() || '');
        if (!text) {
            toastr.warning('Escribe un mensaje antes de enviar');
            return;
        }
        sendMessageText(text);
    }

    function submitAttachDocument() {
        if (attachModalBusy || composeBusy) {
            return;
        }
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Selecciona primero un hilo del inbox');
            return;
        }
        var fileInput = document.getElementById('admin-attach-file');
        var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        var title = $.trim($('#admin-attach-title').val() || '');
        var type = normalizeDocumentTypeKey($('#admin-attach-type').val() || 'other');
        var note = $.trim($('#admin-attach-note').val() || '');
        if (!file) {
            toastr.warning('Selecciona un archivo');
            return;
        }
        if (!title) {
            toastr.warning('Ingresa el título del documento');
            return;
        }

        var docs = [{
            file: file,
            title: title,
            document_type: type,
            note: note
        }];
        setAttachModalBusy(true);
        setComposeBusy(true, 'Adjuntando documento...');
        uploadDocumentsBatch(docs).done(function (uploadRes) {
            mergeUploadedDocuments(uploadRes || null);
            syncThreadDocumentsPanel(currentDocuments);
            var messageText = buildSharedDocumentMessage(uploadRes && uploadRes.results ? uploadRes.results : []);
            setAttachStatus('Documento listo para publicarse en el chat.', 'success');
            $('#adminAttachDocumentModal').modal('hide');
            setComposeBusy(false, '');
            setAttachModalBusy(false);
            sendMessageText(messageText, {
                busyMessage: 'Publicando documento...',
                successToast: 'Documento adjuntado al chat',
                clearComposer: false
            });
        }).fail(function (res) {
            setComposeBusy(false, '');
            setAttachModalBusy(false);
            var errorMessage = describeUploadError(res);
            setAttachStatus(errorMessage, 'danger');
            toastr.error(errorMessage);
        });
    }

    function openQuickReplyPreview(replyKey) {
        if (!currentThread || !currentThread.thread_id) return;
        var key = (replyKey || '').toString().toUpperCase();
        if (!quickReplies[key]) {
            toastr.error('Acción rápida no válida');
            return;
        }
        if (key === 'DATES_NOT_AVAILABLE') {
            toastr.info('Define una nueva propuesta concreta para el paciente.');
            openMeetingProposalModal('The previous dates are not available. We are proposing a new meeting so we can continue with your case.');
            return;
        }

        var singleFootprint = !!quickReplySingleFootprintKeys[key];
        $('#admin-quick-reply-preview-key').val(key);
        $('#admin-quick-reply-preview-title').text('Revisar antes de enviar: ' + (quickReplies[key] || key));
        $('#admin-quick-reply-preview-text').val(String(quickReplyPreviewMessages[key] || ''));
        $('#admin-quick-reply-preview-text').prop('readonly', singleFootprint);
        $('#admin-quick-reply-preview-hint').text(
            singleFootprint
                ? 'Esta acción formal ya comunica suficiente por sí sola. Se registrará una única huella visible en el chat.'
                : 'Si confirmas con texto, además de la acción formal se enviará un mensaje adicional de contexto.'
        );
        $('#adminQuickReplyPreviewModal').modal('show');
    }

    function performQuickReply(replyKey, options) {
        options = options || {};
        if (!currentThread || !currentThread.thread_id) {
            return $.Deferred().reject({ message: 'thread_required' }).promise();
        }
        var key = (replyKey || '').toString().toUpperCase();
        if (!quickReplies[key]) {
            return $.Deferred().reject({ message: 'invalid_quick_reply' }).promise();
        }

        var pendingId = addPendingMessage(quickReplies[key]);
        var deferred = $.Deferred();
        var messageNote = $.trim(String(options.messageNote || ''));

        $.ajax({
            url: 'ajax/inbox.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'send_quick_reply',
                thread_id: currentThread.thread_id,
                thread_type: currentThread.thread_type,
                reply_key: key,
                message_note: messageNote
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                updatePendingStatus(pendingId, 'Failed');
                deferred.reject(res || { message: 'quick_reply_failed' });
                return;
            }
            markSentFromResponse(res);
            removePendingMessage(pendingId);
            realtimeEmitCommitted(currentThread.thread_id, res, 'ADMIN');
            loadMessages();
            loadThreads();
            if (!options.suppressSuccessToast) {
                toastr.success(options.successToast || 'Acción formal enviada');
            }
            deferred.resolve(res);
        }).fail(function () {
            updatePendingStatus(pendingId, 'Failed');
            deferred.reject({ message: 'quick_reply_failed' });
        });

        return deferred.promise();
    }

    function openMeetingProposalModal(defaultNote) {
        if (!currentThread || String(currentThread.thread_type || '').toUpperCase() !== 'ITEM' || parseInt(currentThread.item_id || 0, 10) <= 0) {
            toastr.warning('Open a service thread first');
            return false;
        }
        $('#admin-meeting-start-at').val('');
        $('#admin-meeting-end-at').val('');
        $('#admin-meeting-note').val(String(defaultNote || ''));
        $('#admin-meeting-enable-calendar').prop('checked', true).prop('disabled', false);
        $('#admin-meeting-enable-meet').prop('checked', true);
        updateMeetingIntegrationUi();
        $('#adminProposeMeetingModal').modal('show');
        return true;
    }

    function sendStructuredAction(actionType, payload) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Selecciona primero un hilo antes de enviar');
            return;
        }
        if (String(currentThread.thread_type || '').toUpperCase() !== 'ITEM') {
            toastr.warning('Las acciones estructuradas solo están disponibles en hilos de servicio');
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

    function describeMeetingProposalError(res) {
        var code = String((res && res.message) || '').trim();
        var map = {
            no_google_admin_connected: 'No hay un administrador conectado a Google Calendar para organizar la reunión.',
            meeting_schedule_required: 'Debes indicar inicio y fin de la reunión.',
            invalid_meeting_range: 'La fecha final debe ser posterior a la fecha inicial.',
            transition_not_allowed_from_provider_proposed_change: 'El caso ya está en reprogramación. Puedes enviar una nueva propuesta.',
            transition_not_allowed_from_awaiting_client: 'La reunión ya fue confirmada o cambió de estado operativo.',
            transition_not_allowed_from_client_accepted: 'La reunión ya fue aceptada por el paciente.'
        };
        return map[code] || code || 'No se pudo enviar la propuesta de reunión';
    }

    function updateMeetingIntegrationUi() {
        var calendarChecked = $('#admin-meeting-enable-calendar').is(':checked');
        var meetChecked = $('#admin-meeting-enable-meet').is(':checked');

        if (meetChecked && !calendarChecked) {
            $('#admin-meeting-enable-calendar').prop('checked', true);
            calendarChecked = true;
        }

        $('#admin-meeting-enable-calendar').prop('disabled', meetChecked);

        var helpText = 'Si no marcas ninguna opción, la propuesta quedará solo en MedTravel.';
        if (meetChecked) {
            helpText = 'Google Meet requiere Google Calendar. Calendar se marcó automáticamente.';
        } else if (calendarChecked) {
            helpText = 'Al aceptar, se creará un evento en Google Calendar sin enlace Meet.';
        }
        $('#admin-meeting-integration-help').text(helpText);
    }

    function resolveMeetingIntegrationMode() {
        if ($('#admin-meeting-enable-meet').is(':checked')) {
            return 'calendar_plus_meet';
        }
        if ($('#admin-meeting-enable-calendar').is(':checked')) {
            return 'calendar_only';
        }
        return 'internal_only';
    }

    function sendMeetingProposal(startAt, endAt, note, integrationMode) {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Selecciona primero un hilo antes de enviar');
            return false;
        }
        if (String(currentThread.thread_type || '').toUpperCase() !== 'ITEM' || parseInt(currentThread.item_id || 0, 10) <= 0) {
            toastr.warning('La propuesta de reunión solo aplica a hilos ITEM');
            return false;
        }

        var startValue = String(startAt || '').trim();
        var endValue = String(endAt || '').trim();
        if (!startValue || !endValue) {
            toastr.warning('Debes indicar inicio y fin de la reunión');
            return false;
        }

        var startDate = new Date(startValue);
        var endDate = new Date(endValue);
        if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate.getTime() <= startDate.getTime()) {
            toastr.warning('La fecha final debe ser posterior a la fecha inicial');
            return false;
        }

        var pendingId = addPendingMessage('[MEETING_PROPOSAL] ' + JSON.stringify({
            start_at: startValue.replace('T', ' '),
            end_at: endValue.replace('T', ' '),
            note: String(note || ''),
            integration_mode: String(integrationMode || 'calendar_plus_meet')
        }));
        setComposeBusy(true, 'Enviando propuesta de reunión...');

        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'provider_propose_change',
                item_id: parseInt(currentThread.item_id || 0, 10),
                proposed_start_at: startValue,
                proposed_end_at: endValue,
                proposed_date_from: startValue.substring(0, 10),
                proposed_date_to: endValue.substring(0, 10),
                provider_notes: String(note || ''),
                integration_mode: String(integrationMode || 'calendar_plus_meet')
            }
        }).done(function (res) {
            setComposeBusy(false, '');
            if (!res || res.ok !== true) {
                updatePendingStatus(pendingId, 'Falló');
                toastr.error(describeMeetingProposalError(res));
                return;
            }
            removePendingMessage(pendingId);
            toastr.success('Propuesta de reunión enviada');
            loadMessages();
            loadThreads();
        }).fail(function (xhr) {
            setComposeBusy(false, '');
            updatePendingStatus(pendingId, 'Falló');
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            toastr.error(describeMeetingProposalError(res));
        });

        return true;
    }

    function sendMeetingCancellation() {
        if (!currentThread || !currentThread.thread_id) {
            toastr.warning('Selecciona primero un hilo antes de enviar');
            return false;
        }
        if (String(currentThread.thread_type || '').toUpperCase() !== 'ITEM' || parseInt(currentThread.item_id || 0, 10) <= 0) {
            toastr.warning('La cancelación de reunión solo aplica a hilos ITEM');
            return false;
        }
        if (!window.confirm('¿Cancelar esta reunión? El caso seguirá activo para poder reagendar.')) {
            return false;
        }

        setComposeBusy(true, 'Cancelando reunión...');
        $.ajax({
            url: 'ajax/my_booking_requests.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'cancel_meeting',
                item_id: parseInt(currentThread.item_id || 0, 10)
            }
        }).done(function (res) {
            setComposeBusy(false, '');
            if (!res || res.ok !== true) {
                toastr.error((res && res.message) ? res.message : 'No se pudo cancelar la reunión');
                return;
            }
            markSentFromResponse(res);
            realtimeEmitCommitted(currentThread.thread_id, res, 'ADMIN');
            toastr.success('Reunión cancelada');
            loadMessages();
            loadThreads();
        }).fail(function (xhr) {
            setComposeBusy(false, '');
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            toastr.error((res && res.message) ? res.message : 'No se pudo cancelar la reunión');
        });
        return true;
    }

    $(function () {
        var params = new URLSearchParams(window.location.search);
        var threadId = String(params.get('thread_id') || '');
        var requestId = parseInt(params.get('request_id') || '0', 10);
        var threadType = String(params.get('thread_type') || 'CARE').toUpperCase();
        var itemId = parseInt(params.get('item_id') || '0', 10);
        if (threadId) {
            preferredThread = { threadId: threadId };
            urlFilterThreadId = threadId;
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

        // Doc viewer: open modal when View button or document name is clicked
        $('#admin-inbox-docs-content').on('click', '.mt-doc-view, .mt-doc-open', function (evt) {
            evt.preventDefault();
            evt.stopPropagation();
            var docId = String($(this).data('doc-id') || '').trim();
            var href = String(decodeURIComponent($(this).attr('data-url') || '') || $(this).attr('href') || '').trim();
            var doc = findDocumentById(docId);
            openDocViewer(doc || null, href);
        });
        $('#admin-inbox-messages').on('click', '.mt-shared-doc-link', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var docId = String($(this).data('doc-id') || '').trim();
            var href = String(decodeURIComponent($(this).attr('data-url') || '') || $(this).attr('href') || '').trim();
            var doc = findDocumentById(docId);
            openDocViewer(doc || null, href);
        });
        $('#admin-inbox-messages').on('click', '.admin-meeting-cancel', function () {
            sendMeetingCancellation();
        });

        // Clean up preview iframe/img on modal close to stop loading
        $('#adminDocViewerModal').on('hidden.bs.modal', function () {
            $('#adminDocViewerPreview').html(
                '<div class="mt-dv-no-preview">' +
                    '<i class="fa fa-file-o" aria-hidden="true"></i>' +
                    '<span>Vista previa no disponible.</span>' +
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
            setAttachStatus('');
            $('#admin-inbox-thread-list li').removeClass('active');
            $a.closest('li').addClass('active');
            loadMessages();
        });

        $('#admin-chat-attach-btn').on('click', function () {
            if (this.disabled) {
                return;
            }
            openAttachDocumentModal();
        });

        $('#admin-attach-file').on('change', function () {
            var file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                return;
            }
            var currentTitle = $.trim($('#admin-attach-title').val() || '');
            if (!currentTitle) {
                $('#admin-attach-title').val(cleanDocumentTitleFallback(file.name || ''));
            }
        });

        $('#admin-attach-document-form').on('submit', function (e) {
            e.preventDefault();
            submitAttachDocument();
        });

        $('#adminAttachDocumentModal').on('hidden.bs.modal', function () {
            resetAttachDocumentModal();
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
            openQuickReplyPreview(key);
        });

        $('#adminQuickReplyPreviewModal').on('hidden.bs.modal', function () {
            $('#admin-quick-reply-preview-key').val('');
            $('#admin-quick-reply-preview-title').text('Revisar antes de enviar');
            $('#admin-quick-reply-preview-text').val('');
            $('#admin-quick-reply-preview-text').prop('readonly', false);
            $('#admin-quick-reply-preview-hint').text('');
            $('#admin-submit-quick-reply-preview').prop('disabled', false).text('Confirmar y enviar');
        });

        $('#admin-submit-quick-reply-preview').on('click', function () {
            var key = String($('#admin-quick-reply-preview-key').val() || '').toUpperCase();
            var noteText = $.trim($('#admin-quick-reply-preview-text').val() || '');
            var $btn = $(this);
            if (!key) {
                toastr.warning('Selecciona una acción primero');
                return;
            }
            if (noteText.length > 2000) {
                toastr.warning('El mensaje es demasiado largo');
                return;
            }
            $btn.prop('disabled', true).text('Enviando...');
            var singleFootprint = !!quickReplySingleFootprintKeys[key];
            performQuickReply(key, {
                suppressSuccessToast: true,
                messageNote: singleFootprint ? noteText : ''
            }).done(function () {
                $('#adminQuickReplyPreviewModal').modal('hide');
                if (!singleFootprint && noteText) {
                    sendMessageText(noteText, {
                        successToast: 'Acción formal y mensaje enviados'
                    });
                } else {
                    toastr.success('Acción formal enviada');
                }
            }).fail(function (res) {
                $btn.prop('disabled', false).text('Confirmar y enviar');
                toastr.error((res && res.message) ? res.message : 'No se pudo enviar la acción formal');
            });
        });

        $('#admin-open-request-info').on('click', function () {
            if (!currentThread || String(currentThread.thread_type || '').toUpperCase() !== 'ITEM') {
                toastr.warning('Open a service thread first');
                return;
            }
            $('#admin-request-info-types input[type="checkbox"]').prop('checked', false);
            $('#admin-request-info-note').val('Please share the requested information so we can continue evaluating your case.');
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
            $('#admin-propose-notes').val('We are sharing an updated quote for you to review the next step of your case.');
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

        $('#admin-open-propose-meeting').on('click', function () {
            openMeetingProposalModal('We are proposing this meeting to review the next step of your case.');
        });

        $('#admin-open-reprogramming-proposal').on('click', function () {
            toastr.info('Define una nueva propuesta concreta para el paciente.');
            openMeetingProposalModal('The previous dates are not available. We are proposing a new meeting so we can continue with your case.');
        });

        $('#admin-meeting-enable-calendar, #admin-meeting-enable-meet').on('change', function () {
            updateMeetingIntegrationUi();
        });

        $('#admin-submit-propose-meeting').on('click', function () {
            var startAt = $.trim($('#admin-meeting-start-at').val() || '');
            var endAt = $.trim($('#admin-meeting-end-at').val() || '');
            var note = $.trim($('#admin-meeting-note').val() || '');
            var integrationMode = resolveMeetingIntegrationMode();
            if (note.length > 500) {
                toastr.warning('La nota es demasiado larga');
                return;
            }
            if (sendMeetingProposal(startAt, endAt, note, integrationMode)) {
                $('#adminProposeMeetingModal').modal('hide');
            }
        });

        bindInboxHelpPanel();

        initRealtime();
        loadThreads();
    });
})();
