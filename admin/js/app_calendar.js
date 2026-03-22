(function () {
    var config = window.AdminCalendarConfig || {};
    var currentEvent = null;
    var focusState = resolveFocusFromQuery();
    var focusNoticeShown = false;
    var focusDetailOpened = false;
    var selectedItemId = focusState.itemId > 0 ? focusState.itemId : 0;
    var selectedRequestId = focusState.requestId > 0 ? focusState.requestId : 0;
    var knownItemOptions = {};
    var knownItemMeta = {};
    var knownRequestOptions = {};
    var pendingOpenEventId = 0;

    function parseQueryParams() {
        var out = {};
        var q = window.location && window.location.search ? window.location.search.replace(/^\?/, '') : '';
        if (!q) return out;
        q.split('&').forEach(function (part) {
            if (!part) return;
            var chunks = part.split('=');
            var key = decodeURIComponent((chunks[0] || '').replace(/\+/g, ' ')).trim();
            if (!key) return;
            var val = decodeURIComponent((chunks.slice(1).join('=') || '').replace(/\+/g, ' '));
            out[key] = val;
        });
        return out;
    }

    function resolveFocusFromQuery() {
        var params = parseQueryParams();
        var threadId = String(params.thread_id || '').trim();
        var threadType = String(params.thread_type || '').toUpperCase().trim();
        var itemId = parseInt(params.item_id || '0', 10) || 0;
        var requestId = parseInt(params.request_id || '0', 10) || 0;

        if (!threadId && itemId > 0) {
            threadId = 'ITEM:' + itemId;
        }
        if (threadId) {
            threadId = threadId.toUpperCase();
            if (threadId.indexOf('ITEM:') === 0 && itemId <= 0) {
                itemId = parseInt(threadId.substring(5), 10) || 0;
            }
            if (!threadType) {
                threadType = (threadId.indexOf('CARE:') === 0) ? 'CARE' : 'ITEM';
            }
        }
        return {
            threadId: threadId,
            threadType: threadType,
            itemId: itemId,
            requestId: requestId
        };
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getEventType(event) {
        if (!event) return 'ITEM';
        if (event.event_type) return String(event.event_type).toUpperCase();
        if (event.extendedProps && event.extendedProps.event_type) return String(event.extendedProps.event_type).toUpperCase();
        return 'ITEM';
    }

    function getEventStatus(event) {
        if (!event) return 'scheduled';
        if (event.status) return String(event.status).toLowerCase();
        if (event.extendedProps && event.extendedProps.status) return String(event.extendedProps.status).toLowerCase();
        return 'scheduled';
    }

    function getEventRequestId(event) {
        if (!event) return 0;
        if (event.request_id) return parseInt(event.request_id, 10) || 0;
        if (event.booking_request_id) return parseInt(event.booking_request_id, 10) || 0;
        if (event.extendedProps && event.extendedProps.request_id) return parseInt(event.extendedProps.request_id, 10) || 0;
        return 0;
    }

    function getEventItemId(event) {
        if (!event) return 0;
        if (event.item_id) return parseInt(event.item_id, 10) || 0;
        if (event.extendedProps && event.extendedProps.item_id) return parseInt(event.extendedProps.item_id, 10) || 0;
        return 0;
    }

    function getEventThreadId(event) {
        if (!event) return '';
        if (event.thread_id) return String(event.thread_id);
        if (event.extendedProps && event.extendedProps.thread_id) return String(event.extendedProps.thread_id);
        var type = getEventType(event);
        var requestId = getEventRequestId(event);
        var itemId = getEventItemId(event);
        if (type === 'ITEM' && itemId > 0) return 'ITEM:' + itemId;
        if (requestId > 0) return 'CARE:' + requestId;
        return '';
    }

    function statusColor(status) {
        var s = String(status || '').toLowerCase();
        if (s === 'confirmed') return '#36c6d3';
        if (s === 'proposed') return '#f1c40f';
        if (s === 'cancelled') return '#ed6b75';
        return '#5c9bd1';
    }

    function typeBorderColor(type) {
        return String(type || '').toUpperCase() === 'CARE' ? '#8e44ad' : '#1e88e5';
    }

    function toInputDateTime(value) {
        if (!value) return '';
        var m = moment(value);
        if (!m.isValid()) return '';
        return m.format('YYYY-MM-DDTHH:mm');
    }

    function toDisplayDateTime(value) {
        if (!value) return '';
        var m = moment(value);
        if (!m.isValid()) return String(value);
        return m.format('YYYY-MM-DD HH:mm');
    }

    function getFilterValue() {
        var v = $('#admin-calendar-filter').val();
        return String(v || 'ITEM').toUpperCase();
    }

    function isProviderView() {
        return !!config.isProvider;
    }

    function buildItemOptionLabel(itemId, event) {
        var ext = event && event.extendedProps ? event.extendedProps : {};
        var serviceName = String((ext.item_name || ext.service_name || event.item_name || event.service_name || '')).trim();
        if (serviceName) {
            return 'ITEM #' + itemId + ' - ' + serviceName;
        }
        return 'ITEM #' + itemId;
    }

    function registerItemOption(itemId, label, requestId, status) {
        itemId = parseInt(itemId, 10) || 0;
        requestId = parseInt(requestId, 10) || 0;
        if (itemId <= 0) return;
        var normalizedLabel = $.trim(String(label || ''));
        if (!normalizedLabel) {
            normalizedLabel = 'ITEM #' + itemId;
            if (requestId > 0) {
                normalizedLabel = 'Request #' + requestId + ' - ' + normalizedLabel;
            }
        }
        knownItemOptions[itemId] = normalizedLabel;
        knownItemMeta[itemId] = {
            itemId: itemId,
            requestId: requestId > 0 ? requestId : 0,
            status: $.trim(String(status || ''))
        };
        if (requestId > 0 && !knownRequestOptions[requestId]) {
            knownRequestOptions[requestId] = 'Request #' + requestId;
        }
    }

    function collectKnownItemOptions(events) {
        (events || []).forEach(function (e) {
            var itemId = getEventItemId(e);
            var requestId = getEventRequestId(e);
            if (requestId > 0 && !knownRequestOptions[requestId]) {
                knownRequestOptions[requestId] = 'Request #' + requestId;
            }
            if (itemId <= 0) return;
            registerItemOption(itemId, buildItemOptionLabel(itemId, e), requestId, getEventStatus(e));
        });
        if (selectedItemId > 0 && !knownItemOptions[selectedItemId]) {
            registerItemOption(selectedItemId, 'ITEM #' + selectedItemId, selectedRequestId, '');
        }
        if (selectedRequestId > 0 && !knownRequestOptions[selectedRequestId]) {
            knownRequestOptions[selectedRequestId] = 'Request #' + selectedRequestId;
        }
    }

    function renderItemSelector() {
        var $wrap = $('#admin-calendar-item-selector-wrap');
        var $select = $('#admin-calendar-item-select');
        if (!$wrap.length || !$select.length) return;

        var keys = Object.keys(knownItemOptions)
            .map(function (k) { return parseInt(k, 10) || 0; })
            .filter(function (n) { return n > 0; })
            .sort(function (a, b) { return a - b; });

        var shouldShow = isProviderView() || selectedItemId > 0 || keys.length > 0;
        $wrap.toggle(shouldShow);
        if (!shouldShow) return;

        var html = ['<option value="">Selecciona un ITEM...</option>'];
        keys.forEach(function (id) {
            var label = knownItemOptions[id] || ('ITEM #' + id);
            var selected = selectedItemId === id ? ' selected' : '';
            html.push('<option value="' + id + '"' + selected + '>' + esc(label) + '</option>');
        });
        if (selectedItemId > 0 && keys.indexOf(selectedItemId) === -1) {
            html.push('<option value="' + selectedItemId + '" selected>ITEM #' + selectedItemId + '</option>');
        }
        $select.html(html.join(''));
    }

    function renderCreateModalSelectors() {
        var $itemSelect = $('#admin-calendar-create-item-select');
        var $requestSelect = $('#admin-calendar-create-request-select');

        if ($itemSelect.length) {
            var itemKeys = Object.keys(knownItemOptions)
                .map(function (k) { return parseInt(k, 10) || 0; })
                .filter(function (n) { return n > 0; })
                .sort(function (a, b) { return a - b; });
            var itemHtml = ['<option value="">Select item (required)</option>'];
            itemKeys.forEach(function (id) {
                var selected = selectedItemId === id ? ' selected' : '';
                itemHtml.push('<option value="' + id + '"' + selected + '>' + esc(knownItemOptions[id] || ('ITEM #' + id)) + '</option>');
            });
            if (selectedItemId > 0 && itemKeys.indexOf(selectedItemId) === -1) {
                itemHtml.push('<option value="' + selectedItemId + '" selected>' + esc('ITEM #' + selectedItemId) + '</option>');
            }
            $itemSelect.html(itemHtml.join(''));
        }

        if ($requestSelect.length) {
            var requestKeys = Object.keys(knownRequestOptions)
                .map(function (k) { return parseInt(k, 10) || 0; })
                .filter(function (n) { return n > 0; })
                .sort(function (a, b) { return a - b; });
            var requestHtml = ['<option value="">Select booking request</option>'];
            requestKeys.forEach(function (id) {
                var selected = selectedRequestId === id ? ' selected' : '';
                requestHtml.push('<option value="' + id + '"' + selected + '>' + esc(knownRequestOptions[id]) + '</option>');
            });
            if (selectedRequestId > 0 && requestKeys.indexOf(selectedRequestId) === -1) {
                requestHtml.push('<option value="' + selectedRequestId + '" selected>' + esc('Request #' + selectedRequestId) + '</option>');
            }
            $requestSelect.html(requestHtml.join(''));
        }
    }

    function loadItemThreads(callback) {
        $.ajax({
            url: config.listUrl || 'ajax/calendar.php',
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list_threads',
                limit: 500
            }
        }).done(function (res) {
            if (!res || res.ok !== true) {
                if (callback) callback(false);
                return;
            }
            var list = Array.isArray(res.threads) ? res.threads : [];
            list.forEach(function (thread) {
                var itemId = parseInt(thread.item_id || '0', 10) || 0;
                if (itemId <= 0) return;
                var label = $.trim(String(thread.label || ''));
                var requestId = parseInt(thread.request_id || '0', 10) || 0;
                registerItemOption(itemId, label, requestId, thread.status || '');
            });
            renderItemSelector();
            renderCreateModalSelectors();
            if (callback) callback(true);
        }).fail(function () {
            if (callback) callback(false);
        });
    }

    function updateEmptyState(count) {
        var $empty = $('#admin-calendar-empty-state');
        if (!$empty.length) return;
        if (count > 0) {
            $empty.hide().text('');
            return;
        }
        if (isProviderView()) {
            if (selectedItemId > 0) {
                $empty.text('Aún no hay citas coordinadas para este item. Haz clic en una fecha para proponer un horario.');
            } else {
                $empty.text('Aún no hay citas coordinadas. Selecciona un hilo ITEM y luego elige fecha y hora para proponer un horario.');
            }
        } else {
            $empty.text('Aún no hay citas coordinadas.');
        }
        $empty.show();
    }

    function eventAllowedByFilter(event) {
        var f = getFilterValue();
        if (f === 'ALL') return true;
        return getEventType(event) === f;
    }

    function eventMatchesFocus(event) {
        if (selectedItemId > 0) {
            return getEventItemId(event) === selectedItemId;
        }
        if (!focusState) return true;
        if (focusState.itemId > 0) {
            return getEventItemId(event) === focusState.itemId;
        }
        if (focusState.threadId) {
            return getEventThreadId(event).toUpperCase() === focusState.threadId;
        }
        return true;
    }

    function mapListEvents(events) {
        var out = [];
        (events || []).forEach(function (e) {
            if (!eventAllowedByFilter(e)) return;
            if (!eventMatchesFocus(e)) return;
            e.backgroundColor = statusColor(getEventStatus(e));
            e.borderColor = typeBorderColor(getEventType(e));
            out.push(e);
        });
        return out;
    }

    function buildRequestUrl(requestId) {
        var base = config.requestBase || 'my_booking_requests.php';
        if (!requestId) return base;
        return base + '?request_id=' + encodeURIComponent(String(requestId));
    }

    function buildInboxUrl(threadId) {
        var base = config.inboxBase || 'app_inbox.php';
        if (!threadId) return base;
        return base + '?thread_id=' + encodeURIComponent(String(threadId));
    }

    function refreshCalendar() {
        $('#admin-calendar').fullCalendar('refetchEvents');
    }

    function applyInitialFilterFromFocus() {
        var $filter = $('#admin-calendar-filter');
        if (!focusState) return;

        if (focusState.requestId > 0) {
            selectedRequestId = focusState.requestId;
            if (!knownRequestOptions[selectedRequestId]) {
                knownRequestOptions[selectedRequestId] = 'Request #' + selectedRequestId;
            }
        }
        if (!$filter.length) return;

        if (focusState.itemId > 0 || focusState.threadType === 'ITEM') {
            if ($filter.find('option[value="ITEM"]').length) {
                $filter.val('ITEM');
            }
            return;
        }

        if (focusState.threadType === 'CARE' || (focusState.threadId && focusState.threadId.indexOf('CARE:') === 0)) {
            if ($filter.find('option[value="CARE"]').length) {
                $filter.val('CARE');
            } else if ($filter.find('option[value="ALL"]').length) {
                $filter.val('ALL');
            }
        }
    }

    function resetCreateFormErrors() {
        $('#admin-calendar-create-item-error').hide().text('');
        $('#admin-calendar-create-request-error').hide().text('');
        $('#admin-calendar-create-start-error').hide().text('');
        $('#admin-calendar-create-end-error').hide().text('');
    }

    function setCreateFieldError(id, message) {
        var $el = $('#' + id);
        if (!$el.length) return;
        $el.text(message || '').toggle(!!message);
    }

    function refreshCreateSummary() {
        var $summary = $('#admin-calendar-create-summary');
        if (!$summary.length) return;
        var eventType = String($('#admin-calendar-create-form [name="event_type"]').val() || 'ITEM').toUpperCase();
        var itemId = parseInt($('#admin-calendar-create-item-select').val() || '0', 10) || 0;
        var requestId = parseInt($('#admin-calendar-create-request-select').val() || '0', 10) || 0;

        if (eventType === 'ITEM' && itemId > 0) {
            var label = knownItemOptions[itemId] || ('ITEM #' + itemId);
            var itemMeta = knownItemMeta[itemId] || {};
            var resolvedRequestId = parseInt(itemMeta.requestId || requestId || '0', 10) || 0;
            var text = 'Hilo: ITEM #' + itemId;
            if (resolvedRequestId > 0) {
                text += ' — Solicitud #' + resolvedRequestId;
            }
            text += ' — ' + label;
            $summary.text(text).show();
            return;
        }

        if (eventType === 'CARE' && requestId > 0) {
            $summary.text('Hilo: CARE:' + requestId + ' — Solicitud #' + requestId).show();
            return;
        }

        $summary.hide().text('');
    }

    function updateCreateHeaderAndButtons() {
        var isProvider = isProviderView();
        var status = String($('#admin-calendar-create-form [name="status"]').val() || 'scheduled').toLowerCase();
        var submitLabel = isProvider ? 'Enviar propuesta' : 'Registrar cita coordinada';
        $('#admin-calendar-create-title').text(isProvider ? 'Proponer horario' : 'Registrar cita coordinada');
        $('#admin-calendar-create-submit').text(submitLabel).data('default-label', submitLabel);
        if (status === 'proposed') {
            $('#admin-calendar-create-subtitle').show();
        } else {
            $('#admin-calendar-create-subtitle').hide();
        }
    }

    function syncCreateModalByType() {
        var $form = $('#admin-calendar-create-form');
        var eventType = String($form.find('[name="event_type"]').val() || 'ITEM').toUpperCase();
        var isProvider = isProviderView();
        var isItem = eventType === 'ITEM';

        $('#admin-calendar-create-type-group').toggle(!isProvider);
        $('#admin-calendar-create-type-readonly-group').toggle(isProvider);
        $('#admin-calendar-create-status-group').toggle(!isProvider);
        $('#admin-calendar-create-status-readonly-group').toggle(isProvider);
        $('#admin-calendar-create-item-group').toggle(isItem);
        $('#admin-calendar-create-request-group').toggle(!isItem && !isProvider);
        $('#admin-calendar-create-type-readonly').text(isItem ? 'ITEM' : 'CARE');
        $('#admin-calendar-create-status-readonly').text('Propuesta enviada');

        if (isProvider) {
            $form.find('[name="event_type"]').val('ITEM').prop('disabled', true);
            $form.find('[name="status"]').val('proposed').prop('disabled', true);
        } else {
            $form.find('[name="event_type"]').prop('disabled', false);
            $form.find('[name="status"]').prop('disabled', false);
        }

        if (isItem) {
            var selectedModalItemId = parseInt($('#admin-calendar-create-item-select').val() || '0', 10) || 0;
            if (selectedModalItemId > 0) {
                selectedItemId = selectedModalItemId;
                var meta = knownItemMeta[selectedModalItemId] || {};
                if ((parseInt(meta.requestId || '0', 10) || 0) > 0) {
                    selectedRequestId = parseInt(meta.requestId, 10);
                }
            } else if (selectedItemId > 0) {
                $('#admin-calendar-create-item-select').val(String(selectedItemId));
            }
            $form.find('[name="request_id"]').val('');
        } else {
            var selectedModalRequestId = parseInt($('#admin-calendar-create-request-select').val() || '0', 10) || 0;
            if (selectedModalRequestId > 0) {
                selectedRequestId = selectedModalRequestId;
            } else if (selectedRequestId > 0) {
                $('#admin-calendar-create-request-select').val(String(selectedRequestId));
            }
            $form.find('[name="item_id"]').val('');
        }

        updateCreateHeaderAndButtons();
        refreshCreateSummary();
    }

    function validateCreateForm() {
        resetCreateFormErrors();
        var $form = $('#admin-calendar-create-form');
        var eventType = String($form.find('[name="event_type"]').val() || 'ITEM').toUpperCase();
        var itemId = parseInt($('#admin-calendar-create-item-select').val() || '0', 10) || 0;
        var requestId = parseInt($('#admin-calendar-create-request-select').val() || '0', 10) || 0;
        var startAt = $.trim(String($form.find('[name="start_at"]').val() || ''));
        var endAt = $.trim(String($form.find('[name="end_at"]').val() || ''));
        var hasError = false;

        if (eventType === 'ITEM' && itemId <= 0) {
            setCreateFieldError('admin-calendar-create-item-error', 'Debes seleccionar un item para coordinar esta cita.');
            hasError = true;
        }
        if (eventType === 'CARE' && !isProviderView() && requestId <= 0) {
            setCreateFieldError('admin-calendar-create-request-error', 'Debes seleccionar una solicitud para este evento CARE.');
            hasError = true;
        }
        if (startAt === '') {
            setCreateFieldError('admin-calendar-create-start-error', 'Debes indicar la fecha y hora de inicio.');
            hasError = true;
        }
        if (startAt !== '' && endAt !== '') {
            var mStart = moment(startAt);
            var mEnd = moment(endAt);
            if (mStart.isValid() && mEnd.isValid() && !mEnd.isAfter(mStart)) {
                setCreateFieldError('admin-calendar-create-end-error', 'La fecha y hora de fin debe ser posterior al inicio.');
                hasError = true;
            }
        }
        return !hasError;
    }

    function setCreateSubmittingState(isSubmitting) {
        var $btn = $('#admin-calendar-create-submit');
        if (!$btn.length) return;
        if (isSubmitting) {
            if (!$btn.data('default-label')) {
                $btn.data('default-label', $btn.text());
            }
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
            return;
        }
        var label = String($btn.data('default-label') || '');
        $btn.prop('disabled', false).text(label !== '' ? label : 'Registrar cita coordinada');
    }

    function openCreateModal(start, end, allDay, options) {
        if (!config.canCreate) return;
        options = options || {};
        var $form = $('#admin-calendar-create-form');
        $form[0].reset();
        resetCreateFormErrors();
        var startVal = start;
        var endVal = end;
        var allDayVal = !!allDay;
        if (options.forceThirtyMinutes && startVal) {
            var startMoment = moment(startVal);
            if (startMoment.isValid()) {
                startVal = startMoment;
                endVal = startMoment.clone().add(30, 'minutes');
                allDayVal = false;
            }
        }
        if (options.defaultTitle) {
            $form.find('[name="title"]').val(options.defaultTitle);
        }
        if (start) {
            $form.find('[name="start_at"]').val(toInputDateTime(startVal));
        }
        if (endVal) {
            $form.find('[name="end_at"]').val(toInputDateTime(endVal));
        }
        $form.find('[name="all_day"]').prop('checked', allDayVal);
        if (options.eventType) {
            $form.find('[name="event_type"]').val(options.eventType);
        }
        if (options.forceStatus) {
            $form.find('[name="status"]').val(options.forceStatus);
        }
        if (options.itemId > 0) {
            selectedItemId = parseInt(options.itemId, 10) || 0;
            $('#admin-calendar-create-item-select').val(String(selectedItemId));
            var meta = knownItemMeta[selectedItemId] || {};
            if ((parseInt(meta.requestId || '0', 10) || 0) > 0) {
                selectedRequestId = parseInt(meta.requestId, 10);
            }
        }
        if (options.requestId > 0) {
            selectedRequestId = parseInt(options.requestId, 10) || 0;
            $('#admin-calendar-create-request-select').val(String(selectedRequestId));
        }
        renderCreateModalSelectors();
        syncCreateModalByType();
        setCreateSubmittingState(false);
        $('#admin-calendar-create-modal').modal('show');
    }

    function openCreateFromSelection(start, end, allDay) {
        if (isProviderView() && selectedItemId <= 0) {
            toastr.warning('Please select an ITEM thread first.');
            return;
        }
        if (isProviderView()) {
            openCreateModal(start, end, allDay, {
                eventType: 'ITEM',
                itemId: selectedItemId,
                defaultTitle: 'Proposed schedule',
                forceStatus: 'proposed',
                forceThirtyMinutes: true
            });
            return;
        }
        if (selectedItemId > 0) {
            openCreateModal(start, end, allDay, {
                eventType: 'ITEM',
                itemId: selectedItemId
            });
            return;
        }
        openCreateModal(start, end, allDay, selectedRequestId > 0 ? {
            eventType: 'CARE',
            requestId: selectedRequestId
        } : {});
    }

    function loadEventInDetail(event) {
        currentEvent = event || null;
        if (!currentEvent) return;

        var requestId = getEventRequestId(currentEvent);
        var itemId = getEventItemId(currentEvent);
        var threadId = getEventThreadId(currentEvent);
        var canEdit = !!config.canUpdate;

        $('#admin-calendar-detail-id').val(currentEvent.id || '');
        $('#admin-calendar-detail-title').val(currentEvent.title || '');
        $('#admin-calendar-detail-start').val(toInputDateTime(currentEvent.start));
        $('#admin-calendar-detail-end').val(toInputDateTime(currentEvent.end));
        $('#admin-calendar-detail-type').val(getEventType(currentEvent));
        $('#admin-calendar-detail-request').val(requestId > 0 ? requestId : '');
        $('#admin-calendar-detail-item').val(itemId > 0 ? itemId : '');
        $('#admin-calendar-detail-status').val(getEventStatus(currentEvent));
        $('#admin-calendar-detail-description').val((currentEvent.description || (currentEvent.extendedProps && currentEvent.extendedProps.description) || ''));
        $('#admin-calendar-detail-allday').prop('checked', !!currentEvent.allDay);
        $('#admin-calendar-open-request').attr('href', buildRequestUrl(requestId));
        $('#admin-calendar-open-inbox').attr('href', buildInboxUrl(threadId));

        $('#admin-calendar-detail-form').find('input, textarea, select').prop('disabled', !canEdit);
        $('#admin-calendar-detail-id').prop('disabled', false);
        $('#admin-calendar-detail-type').prop('disabled', true);
        $('#admin-calendar-open-request, #admin-calendar-open-inbox').prop('disabled', false);
        $('#admin-calendar-detail-form button[type="submit"]').toggle(canEdit);
        $('#admin-calendar-delete-btn').toggle(!!config.canDelete);

        $('#admin-calendar-detail-modal').modal('show');
    }

    function postCalendar(data, onSuccess, onError) {
        $.ajax({
            url: config.listUrl || 'ajax/calendar.php',
            method: 'POST',
            dataType: 'json',
            data: data
        }).done(function (res) {
            if (!res || res.ok !== true) {
                var apiCode = String((res && res.code) ? res.code : '').toUpperCase();
                var apiError = (res && (res.error || res.message)) ? String(res.error || res.message) : 'Action failed';
                toastr.error(apiError);
                if (apiCode === 'CONFLICT') {
                    setCreateFieldError('admin-calendar-create-end-error', apiError);
                }
                if (onError) onError(res);
                return;
            }
            if (onSuccess) onSuccess(res);
        }).fail(function (xhr) {
            var payload = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            var apiCode = String((payload && payload.code) ? payload.code : '').toUpperCase();
            var apiError = (payload && (payload.error || payload.message)) ? String(payload.error || payload.message) : 'Request failed';
            toastr.error(apiError);
            if (apiCode === 'CONFLICT') {
                setCreateFieldError('admin-calendar-create-end-error', apiError);
            }
            if (onError) onError(payload || xhr);
        });
    }

    function initCalendar() {
        if (!$.fn.fullCalendar) {
            return;
        }

        $('#admin-calendar').fullCalendar({
            header: {
                left: 'title',
                center: '',
                right: 'prev,next today month,agendaWeek,agendaDay'
            },
            defaultView: 'month',
            selectable: !!config.canCreate,
            selectHelper: true,
            editable: !!config.canUpdate,
            eventStartEditable: !!config.canUpdate,
            eventDurationEditable: !!config.canUpdate,
            events: function (start, end, timezone, callback) {
                $.ajax({
                    url: config.listUrl || 'ajax/calendar.php',
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'list_events',
                        start: start && start.format ? start.format() : '',
                        end: end && end.format ? end.format() : ''
                    }
                }).done(function (res) {
                    if (!res || res.ok !== true) {
                        toastr.error((res && res.message) ? res.message : 'Could not load events');
                        callback([]);
                        updateEmptyState(0);
                        return;
                    }
                    collectKnownItemOptions(res.events || []);
                    renderItemSelector();
                    renderCreateModalSelectors();
                    var mapped = mapListEvents(res.events || []);
                    callback(mapped);
                    updateEmptyState(mapped.length);
                    if ((focusState.itemId > 0 || focusState.threadId) && mapped.length === 0 && !focusNoticeShown) {
                        focusNoticeShown = true;
                        toastr.warning('Not allowed or no events found for selected item.');
                    }
                    if (pendingOpenEventId > 0 && mapped.length > 0) {
                        var matchedPending = null;
                        mapped.some(function (evt) {
                            if (parseInt(evt.id, 10) === pendingOpenEventId) {
                                matchedPending = evt;
                                return true;
                            }
                            return false;
                        });
                        if (matchedPending) {
                            pendingOpenEventId = 0;
                            if (matchedPending.start) {
                                $('#admin-calendar').fullCalendar('gotoDate', matchedPending.start);
                            }
                            setTimeout(function () {
                                loadEventInDetail(matchedPending);
                            }, 120);
                        }
                    }
                    if ((focusState.itemId > 0 || focusState.threadId) && mapped.length > 0 && !focusDetailOpened) {
                        focusDetailOpened = true;
                        var first = mapped[0];
                        if (first && first.start) {
                            $('#admin-calendar').fullCalendar('gotoDate', first.start);
                        }
                        setTimeout(function () {
                            loadEventInDetail(first);
                        }, 120);
                    }
                }).fail(function () {
                    toastr.error('Could not load events');
                    callback([]);
                    updateEmptyState(0);
                });
            },
            select: function (start, end, allDay) {
                openCreateFromSelection(start, end, allDay);
                $('#admin-calendar').fullCalendar('unselect');
            },
            dayClick: function (date, jsEvent, view) {
                if (!config.canCreate) {
                    return;
                }
                var start = moment(date);
                var end = start.clone().add(30, 'minutes');
                var isAllDayView = view && (view.name === 'month' || view.name === 'basicWeek' || view.name === 'basicDay');
                if (isAllDayView) {
                    start.hour(9).minute(0).second(0).millisecond(0);
                    end = start.clone().add(30, 'minutes');
                }
                openCreateFromSelection(start, end, false);
            },
            eventClick: function (event) {
                loadEventInDetail(event);
            },
            eventDrop: function (event, delta, revertFunc) {
                if (!config.canUpdate) {
                    revertFunc();
                    return;
                }
                postCalendar({
                    action: 'update_event',
                    id: event.id,
                    start_at: toDisplayDateTime(event.start),
                    end_at: toDisplayDateTime(event.end),
                    all_day: event.allDay ? 1 : 0
                }, function () {
                    refreshCalendar();
                }, function () {
                    revertFunc();
                });
            },
            eventResize: function (event, delta, revertFunc) {
                if (!config.canUpdate) {
                    revertFunc();
                    return;
                }
                postCalendar({
                    action: 'update_event',
                    id: event.id,
                    start_at: toDisplayDateTime(event.start),
                    end_at: toDisplayDateTime(event.end),
                    all_day: event.allDay ? 1 : 0
                }, function () {
                    refreshCalendar();
                }, function () {
                    revertFunc();
                });
            }
        });
    }

    $(function () {
        applyInitialFilterFromFocus();
        if (isProviderView()) {
            $('#admin-calendar-provider-guide').show();
        }
        renderItemSelector();
        renderCreateModalSelectors();
        loadItemThreads();
        initCalendar();

        $('#admin-calendar-filter').on('change', function () {
            if (focusState.itemId > 0 || focusState.threadId) {
                focusState.itemId = 0;
                focusState.threadId = '';
                focusState.threadType = '';
            }
            if (getFilterValue() !== 'ITEM') {
                selectedItemId = 0;
                selectedRequestId = selectedRequestId > 0 ? selectedRequestId : 0;
                renderItemSelector();
                renderCreateModalSelectors();
            }
            refreshCalendar();
        });

        $('#admin-calendar-item-select').on('change', function () {
            selectedItemId = parseInt($(this).val() || '0', 10) || 0;
            if (selectedItemId > 0) {
                var meta = knownItemMeta[selectedItemId] || {};
                if ((parseInt(meta.requestId || '0', 10) || 0) > 0) {
                    selectedRequestId = parseInt(meta.requestId, 10);
                }
                focusState.itemId = selectedItemId;
                focusState.threadId = 'ITEM:' + selectedItemId;
                focusState.threadType = 'ITEM';
                if ($('#admin-calendar-filter').find('option[value="ITEM"]').length) {
                    $('#admin-calendar-filter').val('ITEM');
                }
            } else if (focusState.itemId > 0 || (focusState.threadId || '').indexOf('ITEM:') === 0) {
                focusState.itemId = 0;
                focusState.threadId = '';
                focusState.threadType = '';
            }
            renderCreateModalSelectors();
            refreshCalendar();
        });

        $('#admin-calendar-create-type').on('change', function () {
            syncCreateModalByType();
        });

        $('#admin-calendar-create-status').on('change', function () {
            updateCreateHeaderAndButtons();
        });

        $('#admin-calendar-create-item-select').on('change', function () {
            selectedItemId = parseInt($(this).val() || '0', 10) || 0;
            if (selectedItemId > 0) {
                var meta = knownItemMeta[selectedItemId] || {};
                if ((parseInt(meta.requestId || '0', 10) || 0) > 0) {
                    selectedRequestId = parseInt(meta.requestId, 10);
                    $('#admin-calendar-create-request-select').val(String(selectedRequestId));
                }
            }
            syncCreateModalByType();
        });

        $('#admin-calendar-create-request-select').on('change', function () {
            selectedRequestId = parseInt($(this).val() || '0', 10) || 0;
            syncCreateModalByType();
        });

        $('#admin-calendar-create-form [name="start_at"], #admin-calendar-create-form [name="end_at"]').on('change', function () {
            setCreateFieldError('admin-calendar-create-start-error', '');
            setCreateFieldError('admin-calendar-create-end-error', '');
        });

        $('#admin-calendar-create-form').on('shown.bs.modal', function () {
            renderCreateModalSelectors();
            syncCreateModalByType();
        });

        $('#admin-calendar-create-form').on('submit', function (e) {
            e.preventDefault();
            var $f = $(this);
            if (!validateCreateForm()) {
                return;
            }
            var submittedItemId = parseInt($.trim($('#admin-calendar-create-item-select').val() || '0'), 10) || 0;
            var submittedRequestId = parseInt($.trim($('#admin-calendar-create-request-select').val() || '0'), 10) || 0;
            var eventType = $.trim($f.find('[name="event_type"]').val() || 'ITEM').toUpperCase();
            setCreateSubmittingState(true);
            postCalendar({
                action: 'create_event',
                title: $.trim($f.find('[name="title"]').val() || ''),
                description: $.trim($f.find('[name="description"]').val() || ''),
                event_type: eventType,
                request_id: (eventType === 'CARE' && submittedRequestId > 0) ? submittedRequestId : '',
                item_id: (eventType === 'ITEM' && submittedItemId > 0) ? submittedItemId : '',
                start_at: $.trim($f.find('[name="start_at"]').val() || ''),
                end_at: $.trim($f.find('[name="end_at"]').val() || ''),
                status: $.trim($f.find('[name="status"]').val() || 'scheduled'),
                all_day: $f.find('[name="all_day"]').is(':checked') ? 1 : 0
            }, function (res) {
                setCreateSubmittingState(false);
                $('#admin-calendar-create-modal').modal('hide');
                toastr.success(isProviderView() ? 'Propuesta registrada' : 'Cita coordinada registrada');
                var createdEventId = parseInt((res && res.event && res.event.id) ? res.event.id : '0', 10) || 0;
                if (createdEventId > 0) {
                    pendingOpenEventId = createdEventId;
                }
                if (submittedItemId > 0) {
                    selectedItemId = submittedItemId;
                    var meta = knownItemMeta[submittedItemId] || {};
                    if ((parseInt(meta.requestId || '0', 10) || 0) > 0) {
                        selectedRequestId = parseInt(meta.requestId, 10);
                    }
                    focusState.itemId = submittedItemId;
                    focusState.threadId = 'ITEM:' + submittedItemId;
                    focusState.threadType = 'ITEM';
                    registerItemOption(submittedItemId, knownItemOptions[submittedItemId] || ('ITEM #' + submittedItemId), selectedRequestId, '');
                    renderItemSelector();
                    renderCreateModalSelectors();
                } else if (submittedRequestId > 0) {
                    selectedRequestId = submittedRequestId;
                }
                refreshCalendar();
            }, function () {
                setCreateSubmittingState(false);
            });
        });

        $('#admin-calendar-detail-form').on('submit', function (e) {
            e.preventDefault();
            var $f = $(this);
            postCalendar({
                action: 'update_event',
                id: $.trim($f.find('[name="id"]').val() || ''),
                title: $.trim($f.find('[name="title"]').val() || ''),
                description: $.trim($f.find('[name="description"]').val() || ''),
                request_id: $.trim($f.find('[name="request_id"]').val() || ''),
                item_id: $.trim($f.find('[name="item_id"]').val() || ''),
                start_at: $.trim($f.find('[name="start_at"]').val() || ''),
                end_at: $.trim($f.find('[name="end_at"]').val() || ''),
                status: $.trim($f.find('[name="status"]').val() || 'scheduled'),
                all_day: $f.find('[name="all_day"]').is(':checked') ? 1 : 0
            }, function () {
                $('#admin-calendar-detail-modal').modal('hide');
                toastr.success('Event updated');
                refreshCalendar();
            });
        });

        $('#admin-calendar-delete-btn').on('click', function () {
            var eventId = $.trim($('#admin-calendar-detail-id').val() || '');
            if (!eventId) return;
            postCalendar({
                action: 'delete_event',
                id: eventId
            }, function () {
                $('#admin-calendar-detail-modal').modal('hide');
                toastr.success('Event deleted');
                refreshCalendar();
            });
        });
    });
})();
