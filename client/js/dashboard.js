(function () {
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDateTime(value) {
        var raw = String(value || '').trim();
        if (!raw) return '';
        var iso = raw.replace(' ', 'T');
        var date = new Date(iso);
        if (isNaN(date.getTime())) return raw;
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function phaseBadgeClass(phaseKey) {
        var key = String(phaseKey || '').trim();
        if (key === 'appointment_review' || key === 'docs_requested') return '#fff3cd';
        if (key === 'appointment_scheduled') return '#dff7e8';
        if (key === 'closed') return '#eef2f6';
        return '#d9ecff';
    }

    function phaseTextColor(phaseKey) {
        var key = String(phaseKey || '').trim();
        if (key === 'appointment_review' || key === 'docs_requested') return '#8a5a00';
        if (key === 'appointment_scheduled') return '#236444';
        if (key === 'closed') return '#5f7183';
        return '#1b5e91';
    }

    function renderSecondaryActions(actions) {
        var html = '';
        (actions || []).forEach(function (action) {
            if (!action || !action.url || !action.label) return;
            html += '<a class="btn btn-default btn-lg" href="' + esc(action.url) + '">' + esc(action.label) + '</a>';
        });
        return html;
    }

    function renderAppointmentMeta(appointment) {
        if (!appointment || !appointment.start_at) return '';
        var parts = [formatDateTime(appointment.start_at)];
        if (appointment.end_at) {
            parts.push('to ' + formatDateTime(appointment.end_at));
        }
        if (appointment.mode_label) {
            parts.push(appointment.mode_label);
        }
        return parts.join(' · ');
    }

    function renderPrimaryRequest(request) {
        if (!request || !request.visible_phase) {
            $('#client-dashboard-primary').hide();
            $('#client-dashboard-empty').show();
            return;
        }

        var phase = request.visible_phase;
        $('#client-dashboard-empty').hide();
        $('#client-dashboard-primary').show();

        $('#client-journey-phase')
            .text(phase.label || 'Care journey')
            .css({
                backgroundColor: phaseBadgeClass(phase.key),
                color: phaseTextColor(phase.key)
            });

        $('#client-journey-title').text(phase.headline || 'We are coordinating your next step');
        $('#client-journey-description').text(phase.description || '');
        $('#client-journey-next-step').text(phase.next_step || '');

        var primary = phase.primary_cta || {};
        $('#client-journey-primary-cta')
            .attr('href', primary.url || '/client/my_requests.php')
            .text(primary.label || 'Open case');

        $('#client-journey-secondary-actions').html(renderSecondaryActions(phase.secondary_actions || []));

        if (request.appointment && request.appointment.start_at) {
            $('#client-journey-appointment-title').text(request.appointment.title || 'Appointment');
            $('#client-journey-appointment-meta').text(renderAppointmentMeta(request.appointment));
            $('#client-journey-appointment').show();
        } else {
            $('#client-journey-appointment').hide();
        }
    }

    function renderRequestCards(requests) {
        var $container = $('#client-dashboard-request-cards');
        var html = '';

        (requests || []).forEach(function (request) {
            if (!request || !request.visible_phase) return;
            var phase = request.visible_phase;
            var subtitle = [];
            subtitle.push('Request #' + parseInt(request.id || 0, 10));
            if (request.destination) subtitle.push(request.destination);
            if (request.last_update) subtitle.push('Updated ' + formatDateTime(request.last_update));

            html += '<div class="mt-case-card">' +
                '<span class="mt-patient-phase" style="background:' + esc(phaseBadgeClass(phase.key)) + '; color:' + esc(phaseTextColor(phase.key)) + ';">' + esc(phase.label || 'Care journey') + '</span>' +
                '<h4>' + esc(request.service_title || 'MedTravel care journey') + '</h4>' +
                '<div class="mt-case-subtitle">' + esc(subtitle.join(' · ')) + '</div>' +
                '<div class="mt-case-next">' + esc(phase.next_step || '') + '</div>';

            if (request.appointment && request.appointment.start_at) {
                html += '<div class="mt-case-subtitle"><strong>Appointment:</strong> ' + esc(renderAppointmentMeta(request.appointment)) + '</div>';
            }

            html += '<a class="btn btn-sm blue" href="' + esc((phase.primary_cta || {}).url || request.view_url || '/client/my_requests.php') + '">' +
                esc((phase.primary_cta || {}).label || 'Open case') +
                '</a>';

            (phase.secondary_actions || []).slice(0, 1).forEach(function (action) {
                if (!action || !action.url || !action.label) return;
                html += '<a class="btn btn-sm default" href="' + esc(action.url) + '">' + esc(action.label) + '</a>';
            });

            html += '</div>';
        });

        $container.html(html);
        $('#client-dashboard-cases-empty').toggle(!html);
    }

    function loadDashboard() {
        $.ajax({
            url: '/client/ajax/dashboard_overview.php',
            method: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.ok !== true || !res.summary) {
                $('#client-dashboard-empty').show();
                $('#client-dashboard-primary').hide();
                $('#client-dashboard-cases-empty').show();
                return;
            }

            var summary = res.summary || {};
            var requests = summary.requests || [];

            $('#client-dashboard-total').text(parseInt(summary.total_requests || 0, 10));
            $('#client-dashboard-notifications').text(parseInt(summary.unread_messages || 0, 10));
            $('#client-dashboard-action-required').text(parseInt(summary.action_required_count || 0, 10));

            renderPrimaryRequest(summary.primary_request || null);
            renderRequestCards(requests);
        }).fail(function () {
            $('#client-dashboard-empty').show();
            $('#client-dashboard-primary').hide();
            $('#client-dashboard-cases-empty').show();
        });
    }

    $(function () {
        loadDashboard();
    });
})();
