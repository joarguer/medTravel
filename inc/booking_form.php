<?php
function get_booking_texts() {
    static $booking_texts = null;
    if ($booking_texts !== null) return $booking_texts;
    global $conexion;
    $default = [
        'intro_title' => 'Online Booking',
        'intro_paragraph' => 'Tell us about the care you need, your travel preferences, and any special requests so our medical concierge can assemble a seamless experience from consultation to recovery.',
        'secondary_paragraph' => 'Complete the form to request your custom proposal, and we’ll respond with trusted providers, tailored schedules, and concierge support for your trip to Colombia.',
        'background_img' => 'img/tour-booking-bg.jpg',
        'cta_text' => 'Submit your request',
        'cta_subtext' => 'Our coordinating team replies within 24 hours.',
        'form_title' => 'Request Your Personalized Plan',
        'form_paragraph' => 'Complete the form and our team will contact you with options tailored to your treatment and travel needs.',
    ];
    if ($conexion) {
        $q = "SELECT intro_title,intro_paragraph,secondary_paragraph,background_img,cta_text,cta_subtext FROM home_booking WHERE activo = 1 ORDER BY id DESC LIMIT 1";
        $res = mysqli_query($conexion, $q);
        if ($res && mysqli_num_rows($res) > 0) {
            $booking_texts = mysqli_fetch_assoc($res);
            foreach ($default as $key => $value) {
                if (!isset($booking_texts[$key]) || $booking_texts[$key] === '') {
                    $booking_texts[$key] = $value;
                }
            }
            return $booking_texts;
        }
    }
    $booking_texts = $default;
    return $booking_texts;
}

function booking_background_style($booking_texts = null) {
    if ($booking_texts === null) {
        $booking_texts = get_booking_texts();
    }
    $bg = !empty($booking_texts['background_img']) ? $booking_texts['background_img'] : 'img/tour-booking-bg.jpg';
    $path = '/' . ltrim($bg, '/');
    return 'style="--booking-bg-image: url(\'' . htmlspecialchars($path, ENT_QUOTES) . '\')"';
}

function render_booking_form($origin = 'booking_page', $preselected_offer_id = null, $preload = [], $preselected_service_id = 0) {
    global $conexion;
    // Read UTM params from URL to forward through the booking form
    $form_utm = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $_utmK) {
        $form_utm[$_utmK] = !empty($_GET[$_utmK]) ? substr(trim((string)$_GET[$_utmK]), 0, 255) : '';
    }
    
    // Cargar ciudades/destinos dinámicamente desde proveedores
    $destinations = ['' => 'Select Destination'];
    if ($conexion) {
        $dest_query = "SELECT DISTINCT city FROM providers WHERE city IS NOT NULL AND city != '' ORDER BY city ASC";
        $dest_res = mysqli_query($conexion, $dest_query);
        if ($dest_res) {
            while ($row = mysqli_fetch_assoc($dest_res)) {
                $destinations[$row['city']] = $row['city'];
            }
        }
    }
    // Fallback si no hay ciudades
    if (count($destinations) === 1) {
        $destinations = [
            '' => 'Select Destination',
            'Bogotá' => 'Bogotá',
            'Medellín' => 'Medellín',
            'Cali' => 'Cali',
            'Cartagena' => 'Cartagena',
            'Barranquilla' => 'Barranquilla'
        ];
    }
    
    $persons = [
        '' => 'Select Persons',
        '1' => '1 Person',
        '2' => '2 Persons',
        '3' => '3 Persons',
        '4' => '4+ Persons'
    ];
    
    // Cargar categorías de servicios médicos dinámicamente
    $categories = ['' => 'Select Service Category'];
    if ($conexion) {
        $cat_query = "SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
        $cat_res = mysqli_query($conexion, $cat_query);
        if ($cat_res) {
            while ($row = mysqli_fetch_assoc($cat_res)) {
                $categories[$row['id']] = $row['name'];
            }
        }
    }
    // Fallback si no hay categorías
    if (count($categories) === 1) {
        $categories = [
            '' => 'Select Service Category',
            '1' => 'Dental Services',
            '2' => 'Cosmetic Surgery',
            '3' => 'Dermatology',
            '4' => 'General Medicine'
        ];
    }
    
    $texts = get_booking_texts();
    $booking_form_ui_texts = [
        'selected_complementary_services' => 'Selected complementary services:',
        'service_fallback' => 'Service',
        'provider_fallback' => 'Provider',
        'additional_services_suffix' => 'additional services',
    ];
    $termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.1';
    ?>
    <style>
    /* Scoped styles: improve contrast for legal links inside booking blocks */
    .container-fluid.booking .form-check a,
    .booking .form-check a {
        color: #ffffff;
        text-decoration: underline;
    }
    .container-fluid.booking .form-check a:hover,
    .booking .form-check a:hover,
    .container-fluid.booking .form-check a:focus,
    .booking .form-check a:focus {
        color: #ffffff;
        opacity: 0.95;
        outline: 2px solid rgba(255,255,255,0.12);
        outline-offset: 2px;
    }
    </style>

    <form method="POST" action="/booking/step-1.php" class="book-tour-form">
        <input type="hidden" name="origin" value="<?php echo htmlspecialchars($origin, ENT_QUOTES); ?>">
        <input type="hidden" name="selected_services" id="selected-services-input" value="">
        <input type="hidden" name="preselected_offer" value="<?php echo $preselected_offer_id ? intval($preselected_offer_id) : ''; ?>">
        <input type="hidden" name="preselected_service" value="<?php echo $preselected_service_id ? intval($preselected_service_id) : ''; ?>">
        <?php foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $_utmK): ?>
        <input type="hidden" name="<?php echo $_utmK; ?>" value="<?php echo htmlspecialchars($form_utm[$_utmK], ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
        
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="text" name="name" class="form-control bg-white border-0" id="book-name" placeholder="Your Name" value="<?php echo htmlspecialchars($preload['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    <label for="book-name"><i class="fas fa-user me-2"></i>Your Name</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="email" name="email" class="form-control bg-white border-0" id="book-email" placeholder="Your Email" value="<?php echo htmlspecialchars($preload['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    <label for="book-email"><i class="fas fa-envelope me-2"></i>Your Email</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="date" name="timeline_from" class="form-control bg-white border-0" id="book-date-from" placeholder="Start date">
                    <label for="book-date-from"><i class="fas fa-calendar me-2"></i>Start date</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="date" name="timeline_to" class="form-control bg-white border-0" id="book-date-to" placeholder="End date">
                    <label for="book-date-to"><i class="fas fa-calendar me-2"></i>End date</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="tel" name="phone" class="form-control bg-white border-0" id="book-phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($preload['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="book-phone"><i class="fas fa-phone me-2"></i>Phone Number (Optional)</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <select name="destination" class="form-select bg-white border-0" id="book-destination">
                        <?php foreach ($destinations as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="book-destination"><i class="fas fa-map-marker-alt me-2"></i>Preferred City</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <select name="category" class="form-select bg-white border-0" id="book-category">
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="book-category"><i class="fas fa-heartbeat me-2"></i>Service Type</label>
                </div>
            </div>
            <div class="col-12">
                <div class="form-floating">
                    <textarea name="special_request" class="form-control bg-white border-0" placeholder="Tell us about your needs" id="book-message" style="height: 100px"></textarea>
                    <label for="book-message"><i class="fas fa-comment-medical me-2"></i>Tell us about your needs</label>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 rounded mb-1" style="background:rgba(0,0,0,0.25);border-left:3px solid rgba(255,193,7,0.8);">
                    <p class="text-white small mb-0"><strong><i class="fas fa-info-circle me-1 text-warning"></i>Important notice:</strong> MedTravel is a coordination and facilitation platform only. We are not a hospital, clinic, or healthcare provider. Medical services are provided exclusively by independent third-party providers. MedTravel does not guarantee medical outcomes, procedure safety, or recovery results. We strongly recommend obtaining travel insurance with medical coverage before your trip.</p>
                </div>
            </div>
            <div class="col-12 mt-1">
                <div class="form-check text-white mb-2">
                    <input class="form-check-input" type="checkbox" name="consent_terms" id="book-consent-terms" value="1" required>
                    <label class="form-check-label" for="book-consent-terms">
                        I agree to the <a href="/terms.php" target="_blank" rel="noopener">Terms of Service</a> and understand that MedTravel acts solely as an intermediary platform.
                    </label>
                </div>
                <div class="form-check text-white mb-2">
                    <input class="form-check-input" type="checkbox" name="consent_privacy" id="book-consent-privacy" value="1" required>
                    <label class="form-check-label" for="book-consent-privacy">
                        I agree to the <a href="/privacy/" target="_blank" rel="noopener">Privacy Policy</a> and consent to the handling of my personal and medical information as described.
                    </label>
                </div>
                <div class="form-check text-white mb-1">
                    <input class="form-check-input" type="checkbox" name="consent_insurance" id="book-consent-insurance" value="1" required>
                    <label class="form-check-label" for="book-consent-insurance">
                        I understand that I am responsible for obtaining travel insurance with appropriate medical coverage for my trip.
                    </label>
                </div>
                <div class="text-warning small mt-2 d-none" id="book-terms-error">
                    You must accept all three items above to continue.
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary text-white w-100 py-3" type="submit">
                    <i class="fas fa-arrow-right me-2"></i><?php echo htmlspecialchars($texts['cta_text']); ?>
                </button>
                <?php if (!empty($texts['cta_subtext'])): ?>
                    <small class="text-white d-block mt-2 text-center">
                        <i class="fas fa-shield-alt me-1"></i>
                        <?php echo htmlspecialchars($texts['cta_subtext']); ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <script>
        (function() {
            var KEY_STARTED = 'mt_booking_started';
            var KEY_DRAFT = 'mt_booking_draft';
            var KEY_STEP1_SUBMITTED = 'mt_booking_step1_submitted';
            var KEY_SELECTED_SERVICES = 'mt_selected_services';
            var KEY_PRESELECTED_OFFER = 'mt_preselected_offer_id';
            var KEY_PRESELECTED_SERVICE = 'mt_preselected_service_id';
            var RETRY_DELAYS = [0, 250, 1000];
            var DEBUG = !!window.MT_DEBUG;
            var UI_TEXTS = <?php echo json_encode($booking_form_ui_texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            function logDebug() {
                if (!DEBUG || !window.console || !console.log) return;
                var args = Array.prototype.slice.call(arguments);
                args.unshift('[MT booking hydration]');
                console.log.apply(console, args);
            }

            function parseJson(raw) {
                if (!raw) return null;
                try { return JSON.parse(raw); } catch (e) { return null; }
            }

            function isDateCompatible(value) {
                return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
            }

            function emitFieldEvents(field) {
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function setFieldIfEmpty(form, name, value) {
                if (value === null || value === undefined) return;
                var field = form.querySelector('[name="' + name + '"]');
                if (!field) return;

                var current = String(field.value || '').trim();
                if (current !== '') return;

                var normalized = String(value);
                if (normalized.trim() === '') return;
                if (field.type === 'date' && !isDateCompatible(normalized)) return;

                field.value = normalized;
                emitFieldEvents(field);
            }

            function writeComplementarySummary(form, items) {
                if (!Array.isArray(items) || !items.length) return;
                var message = form.querySelector('[name="special_request"]');
                if (!message || String(message.value || '').trim() !== '') return;

                var summary = items.slice(0, 5).map(function(item) {
                    var price = item.price ? ' - ' + item.price + ' ' + (item.currency || '') : '';
                    return '- ' + (item.type || UI_TEXTS.service_fallback) + ': ' + (item.name || '') + ' (' + (item.provider || UI_TEXTS.provider_fallback) + ')' + price;
                }).join('\n');
                if (items.length > 5) {
                    summary += '\n- + ' + (items.length - 5) + ' ' + UI_TEXTS.additional_services_suffix;
                }
                message.value = UI_TEXTS.selected_complementary_services + '\n' + summary;
                emitFieldEvents(message);
            }

            function bindSubmitPersistence(form) {
                if (!form || form.dataset.mtSubmitBound === '1') return;
                form.dataset.mtSubmitBound = '1';

                form.addEventListener('submit', function() {
                    try {
                        function getFieldValue(name) {
                            var field = form.querySelector('[name="' + name + '"]');
                            return field ? (field.value || '') : '';
                        }
                        var payload = {
                            name: getFieldValue('name'),
                            email: getFieldValue('email'),
                            phone: getFieldValue('phone'),
                            origin: getFieldValue('origin'),
                            destination: getFieldValue('destination'),
                            category: getFieldValue('category'),
                            special_request: getFieldValue('special_request'),
                            timeline_from: getFieldValue('timeline_from'),
                            timeline_to: getFieldValue('timeline_to'),
                            persons: getFieldValue('persons'),
                            budget: getFieldValue('budget'),
                            additional_notes: getFieldValue('additional_notes'),
                            preselected_offer: getFieldValue('preselected_offer'),
                            preselected_service: getFieldValue('preselected_service'),
                            updated_at: new Date().toISOString()
                        };
                        localStorage.setItem(KEY_STARTED, '1');
                        localStorage.setItem(KEY_STEP1_SUBMITTED, '1');
                        localStorage.setItem(KEY_DRAFT, JSON.stringify(payload));
                        window.dispatchEvent(new Event('mt-booking-state-changed'));
                    } catch (e) {
                        logDebug('submit persistence error', e);
                    }
                });
            }

            function configureTermsGate(form) {
                if (!form || form.dataset.mtTermsBound === '1') return;
                form.dataset.mtTermsBound = '1';

                var consentNames = ['consent_terms', 'consent_privacy', 'consent_insurance'];
                var checkboxes = consentNames.map(function(n) {
                    return form.querySelector('[name="' + n + '"]');
                }).filter(Boolean);
                var submitBtn = form.querySelector('button[type="submit"]');
                var errorBox = document.getElementById('book-terms-error');
                if (!checkboxes.length || !submitBtn) return;

                function allAccepted() {
                    return checkboxes.every(function(cb) { return cb.checked; });
                }

                function updateState(showError) {
                    var accepted = allAccepted();
                    submitBtn.disabled = !accepted;
                    if (errorBox) {
                        errorBox.classList.toggle('d-none', accepted || !showError);
                    }
                }

                updateState(false);
                checkboxes.forEach(function(cb) {
                    cb.addEventListener('change', function() { updateState(false); });
                });
                form.addEventListener('submit', function(e) {
                    if (!allAccepted()) {
                        e.preventDefault();
                        updateState(true);
                    }
                });
            }

            function hydrateBookingForm(attemptIndex) {
                var tryIndex = typeof attemptIndex === 'number' ? attemptIndex : 0;
                var form = document.querySelector('.book-tour-form');
                if (!form) {
                    if (tryIndex < RETRY_DELAYS.length - 1) {
                        var next = tryIndex + 1;
                        setTimeout(function() { hydrateBookingForm(next); }, RETRY_DELAYS[next]);
                    }
                    return;
                }

                var draft = parseJson(localStorage.getItem(KEY_DRAFT)) || {};
                var hasProgressData = false;
                var supportedKeys = [
                    'name', 'email', 'phone', 'origin', 'destination', 'category',
                    'special_request', 'timeline_from', 'timeline_to', 'persons',
                    'budget', 'additional_notes', 'preselected_offer', 'preselected_service'
                ];
                supportedKeys.forEach(function(key) {
                    if (Object.prototype.hasOwnProperty.call(draft, key)) {
                        hasProgressData = true;
                        setFieldIfEmpty(form, key, draft[key]);
                    }
                });
                if ((draft.additional_notes || '').toString().trim() !== '') {
                    hasProgressData = true;
                    setFieldIfEmpty(form, 'special_request', draft.additional_notes);
                }

                var persistedOffer = localStorage.getItem(KEY_PRESELECTED_OFFER) || sessionStorage.getItem('preselected_offer_id');
                if (persistedOffer) {
                    hasProgressData = true;
                    setFieldIfEmpty(form, 'preselected_offer', persistedOffer);
                    try {
                        localStorage.setItem(KEY_PRESELECTED_OFFER, String(persistedOffer));
                    } catch (e) {}
                }

                var persistedService = localStorage.getItem(KEY_PRESELECTED_SERVICE) || sessionStorage.getItem('preselected_service_id');
                if (persistedService) {
                    hasProgressData = true;
                    setFieldIfEmpty(form, 'preselected_service', persistedService);
                    try {
                        localStorage.setItem(KEY_PRESELECTED_SERVICE, String(persistedService));
                    } catch (e) {}
                }

                var selectedServicesRaw = localStorage.getItem(KEY_SELECTED_SERVICES);
                if (selectedServicesRaw) {
                    hasProgressData = true;
                    setFieldIfEmpty(form, 'selected_services', selectedServicesRaw);
                    var parsedServices = parseJson(selectedServicesRaw);
                    writeComplementarySummary(form, parsedServices);
                }

                if (hasProgressData) {
                    try {
                        localStorage.setItem(KEY_STARTED, '1');
                        window.dispatchEvent(new Event('mt-booking-state-changed'));
                    } catch (e) {}
                }

                bindSubmitPersistence(form);
                configureTermsGate(form);
                logDebug('hydrated form', form);
            }

            window.hydrateBookingForm = hydrateBookingForm;

            if (!window.__mtBookingHydrationBootstrapped) {
                window.__mtBookingHydrationBootstrapped = true;
                document.addEventListener('DOMContentLoaded', function() {
                    hydrateBookingForm(0);
                });
                setTimeout(function() {
                    hydrateBookingForm(0);
                }, 250);
            }
        })();
    </script>
    <?php
}
