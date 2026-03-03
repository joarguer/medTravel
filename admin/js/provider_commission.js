/**
 * admin/js/provider_commission.js
 * Commission Settings UI for the provider edit page (providers_edit.php).
 *
 * Responsibilities:
 *  - Load commission settings when the Commission tab is first activated
 *  - Populate form fields
 *  - Submit via AJAX on form submit
 *  - Show a toast / inline notification on save
 *  - Render the header badge (Commission Active / No Commission Gate)
 *
 * Requires: jQuery (loaded by Metronic), PROVIDER_ID global.
 */
(function ($) {
    'use strict';

    var PROVIDER_ID = (typeof window.PROVIDER_ID !== 'undefined') ? window.PROVIDER_ID : 0;
    var ENDPOINT    = 'ajax/provider_commission_settings.php';
    var loaded      = false;

    // ── Badge ─────────────────────────────────────────────────────────────────
    function renderBadge(settings) {
        var $badge = $('#commission-badge');
        if (parseInt(settings.is_active, 10) === 1) {
            $badge.text('Commission Active');
            $badge.attr('class', 'badge badge-success');
        } else {
            $badge.text('No Commission Gate');
            $badge.attr('class', 'badge badge-secondary');
        }
    }

    // ── Populate form ─────────────────────────────────────────────────────────
    function populateForm(settings) {
        $('#cs-commission-pct').val(parseFloat(settings.commission_pct || 10).toFixed(2));
        $('#cs-fixed-fee').val(parseFloat(settings.fixed_fee_cop || 0).toFixed(0));
        $('#cs-currency').val(settings.currency || 'COP');
        $('#cs-payment-terms').val(settings.payment_terms || '');
        $('#cs-stripe-account').val(settings.stripe_account_id || '');
        $('#cs-is-active').prop('checked', parseInt(settings.is_active, 10) === 1);

        if (settings.updated_at) {
            $('#cs-updated-at').text(settings.updated_at);
            $('#cs-meta').show();
        }

        renderBadge(settings);
    }

    // ── Load settings (lazy, runs once when tab is first shown) ──────────────
    function loadSettings() {
        if (loaded) { return; }
        loaded = true;

        $('#commission-loading').show();
        $('#form-commission').hide();

        $.ajax({
            url: ENDPOINT,
            method: 'GET',
            data: { action: 'get_settings', provider_id: PROVIDER_ID },
            dataType: 'json'
        })
        .done(function (res) {
            if (!res || !res.ok || !res.settings) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Failed to load commission settings');
                }
                showInlineError('Could not load commission settings: ' + (res && res.message ? res.message : 'unknown error'));
                return;
            }
            populateForm(res.settings);
            $('#commission-loading').hide();
            $('#form-commission').show();
        })
        .fail(function (xhr) {
            showInlineError('Request failed (' + xhr.status + '). Check network or session.');
        })
        .always(function () {
            $('#commission-loading').hide();
        });
    }

    // ── Save settings ─────────────────────────────────────────────────────────
    function saveSettings(e) {
        e.preventDefault();

        var $btn = $('#btn-save-commission');
        var $msg = $('#cs-save-msg');

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');
        $msg.hide();

        // Build payload from form fields
        var payload = {
            action:           'save_settings',
            provider_id:      PROVIDER_ID,
            commission_pct:   $('#cs-commission-pct').val(),
            fixed_fee_cop:    $('#cs-fixed-fee').val(),
            currency:         $('#cs-currency').val(),
            payment_terms:    $('#cs-payment-terms').val(),
            stripe_account_id: $('#cs-stripe-account').val(),
            notes:            '',
        };

        // Checkbox: only include when checked (PHP reads absence as 0)
        if ($('#cs-is-active').is(':checked')) {
            payload.is_active = 1;
        }

        $.ajax({
            url:      ENDPOINT,
            method:   'POST',
            data:     payload,
            dataType: 'json'
        })
        .done(function (res) {
            if (res && res.ok) {
                showToast('Commission settings saved successfully.', 'success');
                $msg.html('<span class="text-success"><i class="fa fa-check"></i> Saved</span>').show();
                renderBadge({ is_active: $('#cs-is-active').is(':checked') ? 1 : 0 });
                $('#cs-updated-at').text('just now');
                $('#cs-meta').show();
            } else {
                showToast('Save failed: ' + (res.message || 'server error'), 'error');
                $msg.html('<span class="text-danger"><i class="fa fa-times"></i> ' + (res.message || 'error') + '</span>').show();
            }
        })
        .fail(function (xhr) {
            var msg = 'Request failed (' + xhr.status + ')';
            try {
                var body = JSON.parse(xhr.responseText);
                if (body.message) { msg += ': ' + body.message; }
            } catch (ignored) {}
            showToast(msg, 'error');
            $msg.html('<span class="text-danger"><i class="fa fa-times"></i> ' + msg + '</span>').show();
        })
        .always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Commission Settings');
        });
    }

    // ── Toast notification (Metronic toastr) ─────────────────────────────────
    function showToast(message, type) {
        if (typeof toastr !== 'undefined') {
            toastr.options = { positionClass: 'toast-top-right', timeOut: 3500 };
            if (type === 'success') {
                toastr.success(message);
            } else {
                toastr.error(message);
            }
            return;
        }
        // Fallback: browser alert
        if (type !== 'success') {
            alert(message);
        }
    }

    // ── Inline error inside the portlet body ──────────────────────────────────
    function showInlineError(message) {
        $('#tab-commission .portlet-body').append(
            '<div class="alert alert-danger mt-10"><i class="fa fa-exclamation-triangle"></i> ' +
            $('<span>').text(message).html() + '</div>'
        );
    }

    // ── Bootstrap: wire events ────────────────────────────────────────────────
    $(document).ready(function () {
        if (!PROVIDER_ID) { return; }

        // Lazy-load when the Commission tab is activated
        $('a[href="#tab-commission"]').on('shown.bs.tab', function () {
            loadSettings();
        });

        // If the URL already has #tab-commission (e.g. direct link), load straight away
        if (window.location.hash === '#tab-commission') {
            $('a[href="#tab-commission"]').tab('show');
            loadSettings();
        }

        // Form submit
        $('#form-commission').on('submit', saveSettings);
    });

}(jQuery));
