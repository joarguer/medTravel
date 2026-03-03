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
        var badge = document.getElementById('commission-badge');
        if (!badge) return;
        if (parseInt(settings.is_active, 10) === 1) {
            badge.className = 'badge badge-success';
            badge.innerText = 'Commission Active';
        } else {
            badge.className = 'badge badge-secondary';
            badge.innerText = 'No Commission Gate';
        }
    }

    // ── Populate form ─────────────────────────────────────────────────────────
    function populateForm(settings) {
        var pct = document.querySelector('[name="commission_pct"]');
        var fixed = document.querySelector('[name="fixed_fee_cop"]');
        var currency = document.querySelector('[name="currency"]');
        var terms = document.querySelector('[name="payment_terms"]');
        var stripe = document.querySelector('[name="stripe_account_id"]');
        var active = document.querySelector('[name="is_active"]');

        if (pct) pct.value = parseFloat(settings.commission_pct || 10).toFixed(2);
        if (fixed) fixed.value = parseFloat(settings.fixed_fee_cop || 0).toFixed(0);
        if (currency) currency.value = settings.currency || 'COP';
        if (terms) terms.value = settings.payment_terms || '';
        if (stripe) stripe.value = settings.stripe_account_id || '';
        if (active) active.checked = parseInt(settings.is_active, 10) === 1;

        if (settings.updated_at) {
            $('#cs-updated-at').text(settings.updated_at);
            $('#cs-meta').show();
        }

        renderBadge(settings);
    }

    // ── Load settings (runs on DOM ready) ────────────────────────────────────
    function loadCommissionSettings() {
        if (loaded) { return; }
        loaded = true;

        var spinner = document.getElementById('commission-loading');
        var form = document.getElementById('commission-form') || document.getElementById('form-commission');
        if (spinner) spinner.style.display = 'block';
        if (form) form.style.display = 'none';

        var url = '/admin/ajax/provider_commission_settings.php?action=get_settings&provider_id=' + encodeURIComponent(String(PROVIDER_ID));
        fetch(url, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (!res || !res.ok || !res.settings) {
                    if (typeof toastr !== 'undefined') {
                        toastr.error('Failed to load commission settings');
                    }
                    showInlineError('Could not load commission settings: ' + (res && res.message ? res.message : 'unknown error'));
                    return;
                }
                populateForm(res.settings);
                renderBadge(res.settings);
                if (spinner) spinner.style.display = 'none';
                if (form) form.style.display = 'block';
            })
            .catch(function (err) {
                console.error(err);
                if (typeof toastr !== 'undefined') {
                    toastr.error('Error loading commission settings');
                }
                showInlineError('Request failed. Check network or session.');
            })
            .finally(function () {
                if (spinner) spinner.style.display = 'none';
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

        // Load immediately on DOM ready
        if (!window.PROVIDER_ID) {
            console.error('PROVIDER_ID not defined');
            return;
        }
        loadCommissionSettings();

        // Form submit
        $('#form-commission').on('submit', saveSettings);
    });

}(jQuery));
