(function () {
    var config = window.AdminCalendarConfig || {};
    var currentEvent = null;
    var focusState = resolveFocusFromQuery();
    var focusNoticeShown = false;
    var focusDetailOpened = false;

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
            itemId: itemId
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

    function eventAllowedByFilter(event) {
        var f = getFilterValue();
        if (f === 'ALL') return true;
        return getEventType(event) === f;
    }

    function eventMatchesFocus(event) {
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
        if (!$filter.length || !focusState) return;

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

    function openCreateModal(start, end, allDay) {
        if (!config.canCreate) return;
        var $form = $('#admin-calendar-create-form');
        $form[0].reset();
        if (start) {
            $form.find('[name="start_at"]').val(toInputDateTime(start));
        }
        if (end) {
            $form.find('[name="end_at"]').val(toInputDateTime(end));
        }
        $form.find('[name="all_day"]').prop('checked', !!allDay);
        if (!config.canAdmin) {
            $form.find('[name="event_type"]').val('ITEM').prop('disabled', true);
        } else {
            $form.find('[name="event_type"]').prop('disabled', false);
        }
        $('#admin-calendar-create-modal').modal('show');
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
                toastr.error((res && res.message) ? res.message : 'Action failed');
                if (onError) onError(res);
                return;
            }
            if (onSuccess) onSuccess(res);
        }).fail(function () {
            toastr.error('Request failed');
            if (onError) onError();
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
                        return;
                    }
                    var mapped = mapListEvents(res.events || []);
                    callback(mapped);
                    if ((focusState.itemId > 0 || focusState.threadId) && mapped.length === 0 && !focusNoticeShown) {
                        focusNoticeShown = true;
                        toastr.warning('Not allowed or no events found for selected item.');
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
                });
            },
            select: function (start, end, allDay) {
                openCreateModal(start, end, allDay);
                $('#admin-calendar').fullCalendar('unselect');
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
        initCalendar();

        $('#admin-calendar-filter').on('change', function () {
            if (focusState.itemId > 0 || focusState.threadId) {
                focusState.itemId = 0;
                focusState.threadId = '';
                focusState.threadType = '';
            }
            refreshCalendar();
        });

        $('#admin-calendar-create-form').on('submit', function (e) {
            e.preventDefault();
            var $f = $(this);
            postCalendar({
                action: 'create_event',
                title: $.trim($f.find('[name="title"]').val() || ''),
                description: $.trim($f.find('[name="description"]').val() || ''),
                event_type: $.trim($f.find('[name="event_type"]').val() || 'ITEM'),
                request_id: $.trim($f.find('[name="request_id"]').val() || ''),
                item_id: $.trim($f.find('[name="item_id"]').val() || ''),
                start_at: $.trim($f.find('[name="start_at"]').val() || ''),
                end_at: $.trim($f.find('[name="end_at"]').val() || ''),
                status: $.trim($f.find('[name="status"]').val() || 'scheduled'),
                all_day: $f.find('[name="all_day"]').is(':checked') ? 1 : 0
            }, function () {
                $('#admin-calendar-create-modal').modal('hide');
                toastr.success('Event created');
                refreshCalendar();
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
