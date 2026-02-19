(function () {
    var config = window.ClientCalendarConfig || {};
    var currentEvent = null;

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

    function toDisplayDateTime(value) {
        if (!value) return '';
        var m = moment(value);
        if (!m.isValid()) return String(value);
        return m.format('YYYY-MM-DD HH:mm');
    }

    function buildRequestUrl(requestId) {
        var base = config.requestBase || '/client/request_detail.php';
        if (!requestId) return base;
        return base + '?id=' + encodeURIComponent(String(requestId));
    }

    function buildInboxUrl(threadId) {
        var base = config.inboxBase || '/client/app_inbox.php';
        if (!threadId) return base;
        return base + '?thread_id=' + encodeURIComponent(String(threadId));
    }

    function openDetail(event) {
        currentEvent = event || null;
        if (!currentEvent) return;

        var title = currentEvent.title || 'Event detail';
        var status = getEventStatus(currentEvent);
        var start = toDisplayDateTime(currentEvent.start);
        var end = toDisplayDateTime(currentEvent.end);
        var type = getEventType(currentEvent);
        var description = currentEvent.description || (currentEvent.extendedProps && currentEvent.extendedProps.description) || '';
        var requestId = getEventRequestId(currentEvent);
        var threadId = getEventThreadId(currentEvent);

        $('#client-calendar-detail-title').text(title);
        $('#client-calendar-detail-status').text(status);
        $('#client-calendar-detail-start').text(start);
        $('#client-calendar-detail-end').text(end || 'N/A');
        $('#client-calendar-detail-type').text(type);
        $('#client-calendar-detail-description').text(description || 'N/A');
        $('#client-calendar-open-request').attr('href', buildRequestUrl(requestId));
        $('#client-calendar-open-inbox').attr('href', buildInboxUrl(threadId));
        $('#client-calendar-detail-modal').modal('show');
    }

    function initCalendar() {
        if (!$.fn.fullCalendar) {
            return;
        }

        $('#client-calendar').fullCalendar({
            header: {
                left: 'title',
                center: '',
                right: 'prev,next today month,agendaWeek,agendaDay'
            },
            defaultView: 'month',
            editable: false,
            selectable: false,
            events: function (start, end, timezone, callback) {
                $.ajax({
                    url: config.listUrl || '/client/ajax/calendar.php',
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'list_events',
                        start: start && start.format ? start.format() : '',
                        end: end && end.format ? end.format() : ''
                    }
                }).done(function (res) {
                    if (!res || res.ok !== true) {
                        toastr.error((res && res.message) ? res.message : 'Could not load calendar');
                        callback([]);
                        return;
                    }
                    var events = [];
                    (res.events || []).forEach(function (e) {
                        e.backgroundColor = statusColor(getEventStatus(e));
                        e.borderColor = typeBorderColor(getEventType(e));
                        events.push(e);
                    });
                    callback(events);
                }).fail(function () {
                    toastr.error('Could not load calendar');
                    callback([]);
                });
            },
            eventClick: function (event) {
                openDetail(event);
            }
        });
    }

    $(function () {
        initCalendar();
    });
})();
