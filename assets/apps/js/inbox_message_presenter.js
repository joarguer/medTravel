(function (global) {
    'use strict';

    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function escapeRegExp(text) {
        return String(text || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function hasStructuredPrefix(fullText, prefix) {
        var source = String(fullText || '').trim();
        if (!source) {
            return false;
        }
        var prefixPattern = escapeRegExp(prefix);
        return new RegExp('^(?:\\[(?:ACTION|REPLY)\\]\\s*)?' + prefixPattern + '(?:\\s|$)', 'i').test(source);
    }

    function stripStructuredPrefix(fullText, prefix) {
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

    function parseStructuredJson(prefix, fullText) {
        var jsonText = stripStructuredPrefix(fullText, prefix);
        if (!jsonText) {
            return null;
        }
        return parseStructuredPayload(jsonText);
    }

    function parseReplyTokenAndNote(text) {
        var source = String(text || '').trim();
        if (!source) {
            return { token: '', note: '' };
        }
        var parts = source.split(/\r?\n+/);
        var token = String(parts.shift() || '').trim();
        var note = normalizeText(parts.join('\n'));
        return {
            token: token,
            note: note
        };
    }

    function prettifySystemToken(token) {
        var cleaned = String(token || '').trim().replace(/^\[|\]$/g, '');
        if (!cleaned) {
            return '';
        }
        return cleaned
            .toLowerCase()
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (chr) { return chr.toUpperCase(); });
    }

    function buildAudienceMaps(audience) {
        if (audience === 'admin') {
            return {
                requestInfo: 'solicitó información adicional',
                proposeQuote: 'envió ajuste de propuesta',
                proposalResponse: {
                    ACCEPT_PROPOSAL: 'aceptó la propuesta',
                    REQUEST_CHANGES: 'solicitó cambios',
                    REJECT_PROPOSAL: 'rechazó la propuesta',
                    DOCS_NOT_AVAILABLE: 'indicó documentos no disponibles',
                    defaultLabel: 'envió respuesta a la propuesta'
                },
                replyLabels: {
                    DATES_AVAILABLE: 'fechas disponibles',
                    DATES_NOT_AVAILABLE: 'fechas no disponibles',
                    REQUEST_MEDICAL_HISTORY: 'provider solicitó historia clínica',
                    REQUEST_HISTORY: 'provider solicitó historia clínica',
                    REQUEST_LABS: 'provider solicitó laboratorios',
                    REQUEST_IMAGING: 'provider solicitó imágenes diagnósticas',
                    REQUEST_PHOTOS: 'provider solicitó fotografías clínicas',
                    FINAL_APPROVED: 'provider indicó caso viable',
                    FINAL_NOT_ELIGIBLE: 'provider indicó caso no viable'
                },
                actionLabels: {
                    FINAL_ACCEPT_AND_PAY: 'confirmó que desea continuar',
                    FINAL_DECLINE: 'declinó continuar',
                    PROPOSE_NEW_DATES: 'solicitó nuevas fechas'
                },
                structuredLabels: {
                    MEETING_PROPOSAL: 'Reunión propuesta',
                    MEETING_CREATED: 'Reunión creada',
                    MEETING_CONFIRMED: 'Reunión confirmada',
                    MEETING_CANCELLED: 'Reunión cancelada',
                    MEETING_RESCHEDULED: 'Reunión reprogramada',
                    MEETING_UPDATED: 'Reunión actualizada'
                },
                genericSystem: 'Actualización del sistema'
            };
        }

        return {
            requestInfo: 'Provider requested additional information',
            proposeQuote: 'Provider sent an updated quote',
            proposalResponse: {
                ACCEPT_PROPOSAL: 'You accepted the proposal',
                REQUEST_CHANGES: 'You requested changes',
                REJECT_PROPOSAL: 'You rejected the proposal',
                DOCS_NOT_AVAILABLE: 'You indicated the documents are not available',
                defaultLabel: 'You sent a proposal response'
            },
            replyLabels: {
                DATES_AVAILABLE: 'Provider shared available dates',
                DATES_NOT_AVAILABLE: 'Provider is not available on those dates',
                REQUEST_MEDICAL_HISTORY: 'Provider requested medical history',
                REQUEST_HISTORY: 'Provider requested medical history',
                REQUEST_LABS: 'Provider requested lab results',
                REQUEST_IMAGING: 'Provider requested diagnostic imaging',
                REQUEST_PHOTOS: 'Provider requested clinical photos',
                FINAL_APPROVED: 'Provider marked the case as approved',
                FINAL_NOT_ELIGIBLE: 'Provider marked the case as not eligible'
            },
            actionLabels: {
                FINAL_ACCEPT_AND_PAY: 'You accepted the next step',
                FINAL_DECLINE: 'You declined to continue',
                PROPOSE_NEW_DATES: 'You requested new dates'
            },
            structuredLabels: {
                MEETING_PROPOSAL: 'Meeting proposed',
                MEETING_CREATED: 'Meeting created',
                MEETING_CONFIRMED: 'Meeting confirmed',
                MEETING_CANCELLED: 'Meeting cancelled',
                MEETING_RESCHEDULED: 'Meeting rescheduled',
                MEETING_UPDATED: 'Meeting updated'
            },
            genericSystem: 'System update'
        };
    }

    function extractSystemTag(rawText) {
        var source = String(rawText || '').trim();
        if (!source) {
            return '';
        }
        var match = source.match(/^(?:\[(?:ACTION|REPLY)\]\s*)?\[([A-Z0-9_]+)\]/i);
        return match ? String(match[1] || '').toUpperCase() : '';
    }

    function summarizeStructuredMessage(rawText, options) {
        var audience = options && options.audience === 'admin' ? 'admin' : 'client';
        var maxLength = options && options.maxLength ? parseInt(options.maxLength, 10) : 0;
        var maps = buildAudienceMaps(audience);
        var raw = String(rawText || '');
        var normalized = normalizeText(raw);

        if (!normalized) {
            return '';
        }

        if (normalized.indexOf('[REQUEST_INFO]') === 0) {
            return maps.requestInfo;
        }
        if (normalized.indexOf('[PROPOSE_QUOTE]') === 0) {
            return maps.proposeQuote;
        }
        if (normalized.indexOf('[PROPOSAL_RESPONSE]') === 0) {
            var proposalPayload = parseStructuredJson('[PROPOSAL_RESPONSE]', normalized);
            var proposalAction = String(proposalPayload && proposalPayload.action_type || '').toUpperCase();
            return maps.proposalResponse[proposalAction] || maps.proposalResponse.defaultLabel;
        }

        var rawTrimmed = String(raw || '').trim();
        var isReply = rawTrimmed.indexOf('[REPLY]') === 0;
        var rawWithoutActionReply = rawTrimmed.replace(/^\[(ACTION|REPLY)\]\s*/i, '').trim();
        var replyMeta = parseReplyTokenAndNote(rawWithoutActionReply);
        var normalizedToken = String(replyMeta.token || '').toUpperCase().replace(/\s+/g, '_');
        if (replyMeta.note) {
            if (maxLength > 0 && replyMeta.note.length > maxLength) {
                return replyMeta.note.slice(0, maxLength).trim() + '…';
            }
            return replyMeta.note;
        }

        if (isReply && maps.replyLabels[normalizedToken]) {
            return maps.replyLabels[normalizedToken];
        }
        if (!isReply && maps.actionLabels[normalizedToken]) {
            return maps.actionLabels[normalizedToken];
        }

        var systemTag = extractSystemTag(rawTrimmed);
        if (systemTag) {
            var structuredLabel = maps.structuredLabels[systemTag];
            if (structuredLabel) {
                return structuredLabel;
            }

            var structuredPayload = parseStructuredJson('[' + systemTag + ']', rawTrimmed);
            if (structuredPayload || /^\s*[\[{]/.test(stripStructuredPrefix(rawTrimmed, '[' + systemTag + ']'))) {
                return prettifySystemToken(systemTag) || maps.genericSystem;
            }

            var cleanedAfterTag = stripStructuredPrefix(rawTrimmed, '[' + systemTag + ']');
            if (cleanedAfterTag) {
                return maxLength > 0 && cleanedAfterTag.length > maxLength
                    ? cleanedAfterTag.slice(0, maxLength).trim() + '…'
                    : cleanedAfterTag;
            }

            return prettifySystemToken(systemTag) || maps.genericSystem;
        }

        var previewText = normalized;
        if (maxLength > 0 && previewText.length > maxLength) {
            previewText = previewText.slice(0, maxLength).trim() + '…';
        }
        return previewText;
    }

    global.MedTravelInboxPresenter = {
        parseStructuredJson: parseStructuredJson,
        parseReplyTokenAndNote: parseReplyTokenAndNote,
        hasStructuredPrefix: hasStructuredPrefix,
        stripStructuredPrefix: stripStructuredPrefix,
        summarizeStructuredMessage: summarizeStructuredMessage
    };
}(window));
