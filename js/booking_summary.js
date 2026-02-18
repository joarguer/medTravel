(function () {
    var KEY_STARTED = 'mt_booking_started';
    var KEY_DRAFT = 'mt_booking_draft';
    var KEY_STEP1_SUBMITTED = 'mt_booking_step1_submitted';
    var KEY_SELECTED_SERVICES = 'mt_selected_services';
    var KEY_SELECTED_OFFERS = 'mt_selected_offers';
    var KEY_PRESELECTED_OFFER = 'mt_preselected_offer_id';

    function isWizardPage() {
        var path = String((window.location && window.location.pathname) || '').toLowerCase();
        return path.indexOf('/booking/wizard.php') !== -1;
    }

    function parseJson(raw) {
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function getSummaryElements() {
        return {
            summary: document.getElementById('wizard-package-summary'),
            list: document.getElementById('wizard-summary-list'),
            total: document.getElementById('wizard-summary-total'),
            continueBtn: document.getElementById('wizard-summary-continue'),
            clearBtn: document.getElementById('wizard-summary-clear')
        };
    }

    function ensureSummaryStyle() {
        if (document.getElementById('wizard-package-summary-style')) return;
        var style = document.createElement('style');
        style.id = 'wizard-package-summary-style';
        style.textContent =
            '#wizard-package-summary.package-summary{position:fixed;left:50%;transform:translateX(-50%);bottom:18px;z-index:1050;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,0.12);padding:18px 20px;width:min(1200px, calc(100% - 32px))}' +
            '#wizard-package-summary.package-summary h5{margin:0 0 6px 0;color:#0f172a}' +
            '#wizard-package-summary.package-summary small{color:#475569}' +
            '#wizard-package-summary .summary-total{color:#0f1c4d;font-weight:700}' +
            '#wizard-package-summary .summary-actions .btn{white-space:nowrap;border-radius:999px;padding:10px 18px;font-weight:700}' +
            '.summary-active #stage4-header{display:none}' +
            'body.summary-active{padding-bottom:120px}' +
            'body.summary-active .container{padding-bottom:120px}';
        document.head.appendChild(style);
    }

    function hideSummary() {
        var els = getSummaryElements();
        if (!els.summary || !els.list || !els.total) return;
        els.summary.classList.add('d-none');
        document.body.classList.remove('summary-active');
        els.list.textContent = 'No has añadido servicios.';
        els.total.textContent = '';
    }

    function renderFromSelections(items, options) {
        var opts = options || {};
        var addBodyClass = (typeof opts.addBodyClass === 'boolean') ? opts.addBodyClass : isWizardPage();
        var els = getSummaryElements();
        if (!els.summary || !els.list || !els.total) return;

        var selected = Array.isArray(items) ? items : [];
        if (!selected.length) {
            hideSummary();
            return;
        }

        els.summary.classList.remove('d-none');
        if (addBodyClass) {
            document.body.classList.add('summary-active');
        } else {
            document.body.classList.remove('summary-active');
        }

        var preview = selected.slice(0, 3).map(function (item) {
            var dataset = item.dataset || {};
            var name = dataset.name || item.value || 'Item';
            var type = dataset.type || '';
            return type ? (name + ' (' + type + ')') : name;
        }).join(' · ');
        if (selected.length > 3) {
            preview += ' + ' + (selected.length - 3) + ' más';
        }
        els.list.textContent = preview;

        var totals = {};
        selected.forEach(function (item) {
            var dataset = item.dataset || {};
            var price = parseFloat(dataset.price) || 0;
            var currency = dataset.currency || '';
            if (!currency) return;
            if (!totals[currency]) totals[currency] = 0;
            totals[currency] += price;
        });
        var parts = Object.keys(totals).map(function (currency) {
            var val = totals[currency];
            var symbol = currency === 'USD' ? '$' : (currency === 'COP' ? '$' : '');
            return (symbol + val.toLocaleString('en-US') + ' ' + currency).trim();
        });
        els.total.textContent = parts.length ? ('Total estimado: ' + parts.join(' / ')) : '';
    }

    function buildItemsFromStorage() {
        var items = [];
        var selectedServices = parseJson(localStorage.getItem(KEY_SELECTED_SERVICES));
        if (Array.isArray(selectedServices)) {
            selectedServices.forEach(function (service) {
                var id = String((service && service.id) || '').trim();
                if (!id) return;
                items.push({
                    value: id,
                    dataset: {
                        name: service.name || ('Servicio #' + id),
                        type: service.type || 'complementary_service',
                        price: service.price || '0',
                        currency: service.currency || ''
                    }
                });
            });
        }

        var selectedOffers = parseJson(localStorage.getItem(KEY_SELECTED_OFFERS));
        if (Array.isArray(selectedOffers)) {
            selectedOffers.forEach(function (offer) {
                var id = String((offer && offer.id) || '').trim();
                if (!id) return;
                items.push({
                    value: id,
                    dataset: {
                        name: offer.name || ('Oferta #' + id),
                        type: offer.type || 'medical_offer',
                        price: offer.price || '0',
                        currency: offer.currency || ''
                    }
                });
            });
        }

        var preOffer = String(localStorage.getItem(KEY_PRESELECTED_OFFER) || '').trim();
        if (/^\d+$/.test(preOffer)) {
            items.push({
                value: preOffer,
                dataset: {
                    name: 'Oferta médica preseleccionada #' + preOffer,
                    type: 'medical_offer',
                    price: '0',
                    currency: ''
                }
            });
        }

        var draft = parseJson(localStorage.getItem(KEY_DRAFT)) || {};
        if (draft.destination) {
            items.push({
                value: 'destination',
                dataset: {
                    name: 'Destino: ' + draft.destination,
                    type: 'context',
                    price: '0',
                    currency: ''
                }
            });
        }
        if (draft.timeline_from || draft.timeline_to) {
            items.push({
                value: 'timeline',
                dataset: {
                    name: 'Fechas: ' + (draft.timeline_from || 'N/D') + ' - ' + (draft.timeline_to || 'N/D'),
                    type: 'context',
                    price: '0',
                    currency: ''
                }
            });
        }

        return items;
    }

    function renderFromStorage() {
        var items = buildItemsFromStorage();
        if (!items.length) {
            hideSummary();
            return;
        }
        renderFromSelections(items, { addBodyClass: false });
    }

    function clearStorageState() {
        localStorage.removeItem(KEY_STARTED);
        localStorage.removeItem(KEY_DRAFT);
        localStorage.removeItem(KEY_STEP1_SUBMITTED);
        localStorage.removeItem(KEY_SELECTED_SERVICES);
        localStorage.removeItem(KEY_SELECTED_OFFERS);
        localStorage.removeItem(KEY_PRESELECTED_OFFER);
        sessionStorage.removeItem('preselected_offer_id');
    }

    function clearWizardSelections() {
        document.querySelectorAll('.offer-checkbox, .medtravel-checkbox').forEach(function (cb) {
            cb.checked = false;
            var card = cb.closest('.offer-card, .service-card');
            if (card) card.classList.remove('selected');
            var button = card ? card.querySelector('[data-service-trigger]') : null;
            if (button) button.classList.remove('active');
        });
        window.mtWizardBridgeMissingOfferId = null;
        if (typeof window.updateSelectionSummary === 'function') {
            window.updateSelectionSummary();
        } else {
            hideSummary();
        }
    }

    function bindSummaryActions() {
        var els = getSummaryElements();
        if (!els.summary || !els.continueBtn || !els.clearBtn) return;

        if (els.continueBtn.dataset.bsBound !== '1') {
            els.continueBtn.dataset.bsBound = '1';
            els.continueBtn.addEventListener('click', function () {
                if (isWizardPage()) {
                    var submitBtn = document.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        submitBtn.focus();
                    }
                    return;
                }
                window.location.href = '/booking/wizard.php';
            });
        }

        if (els.clearBtn.dataset.bsBound !== '1') {
            els.clearBtn.dataset.bsBound = '1';
            els.clearBtn.addEventListener('click', function () {
                clearStorageState();
                if (isWizardPage()) {
                    clearWizardSelections();
                } else {
                    hideSummary();
                }
                window.dispatchEvent(new Event('mt-booking-state-changed'));
            });
        }
    }

    function init() {
        var els = getSummaryElements();
        if (!els.summary) return;

        ensureSummaryStyle();
        bindSummaryActions();

        if (isWizardPage()) {
            var selected = Array.from(document.querySelectorAll('input[name="selected_offers[]"]:checked'))
                .concat(Array.from(document.querySelectorAll('.medtravel-checkbox:checked')));
            renderFromSelections(selected, { addBodyClass: true });
            return;
        }
        renderFromStorage();
    }

    window.BookingSummary = {
        renderFromSelections: renderFromSelections,
        renderFromStorage: renderFromStorage,
        hide: hideSummary,
        refresh: init
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('storage', function () {
        if (!isWizardPage()) {
            renderFromStorage();
        }
    });
    window.addEventListener('mt-booking-state-changed', function () {
        if (!isWizardPage()) {
            renderFromStorage();
        }
    });
})();
