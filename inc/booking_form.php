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

function render_booking_form($origin = 'booking_page', $preselected_offer_id = null) {
    global $conexion;
    
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
    ?>
    <form method="POST" action="/booking/step-1.php" class="book-tour-form">
        <input type="hidden" name="origin" value="<?php echo htmlspecialchars($origin, ENT_QUOTES); ?>">
        <input type="hidden" name="selected_services" id="selected-services-input" value="">
        <?php if ($preselected_offer_id): ?>
            <input type="hidden" name="preselected_offer" value="<?php echo intval($preselected_offer_id); ?>">
        <?php endif; ?>
        
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="text" name="name" class="form-control bg-white border-0" id="book-name" placeholder="Your Name" required>
                    <label for="book-name"><i class="fas fa-user me-2"></i>Your Name</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="email" name="email" class="form-control bg-white border-0" id="book-email" placeholder="Your Email" required>
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
                    <input type="tel" name="phone" class="form-control bg-white border-0" id="book-phone" placeholder="Phone Number">
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
        // Auto-rellenar el campo preselected_offer si existe en sessionStorage
        (function() {
            var offerId = sessionStorage.getItem('preselected_offer_id');
            if (offerId) {
                var input = document.querySelector('input[name="preselected_offer"]');
                if (input) {
                    input.value = offerId;
                }
                // Limpiar sessionStorage después de usarlo
                sessionStorage.removeItem('preselected_offer_id');
                
                // Opcional: Mostrar notificación al usuario
                var form = document.querySelector('.book-tour-form');
                if (form) {
                    var notice = document.createElement('div');
                    notice.className = 'alert alert-info alert-dismissible fade show';
                    notice.style.cssText = 'margin-bottom: 15px; background: #e0f2fe; border: 1px solid #0369a1; color: #0c4a6e;';
                    notice.innerHTML = '<i class="fas fa-info-circle me-2"></i>Selected offer will be included in your booking request. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    form.insertBefore(notice, form.firstChild);
                }
            }
        })();
        // Inyectar servicios complementarios seleccionados desde localStorage
        (function() {
            var STORAGE_KEY = 'mt_selected_services';
            var input = document.getElementById('selected-services-input');
            if (!input) return;

            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return;

            var parsed;
            try {
                parsed = JSON.parse(raw);
            } catch (e) {
                parsed = [];
            }
            if (!Array.isArray(parsed) || !parsed.length) return;

            input.value = JSON.stringify(parsed);

            var message = document.getElementById('book-message');
            if (message && (!message.value || message.value.trim() === '')) {
                var summary = parsed.slice(0, 5).map(function(item) {
                    var price = item.price ? ' - ' + item.price + ' ' + (item.currency || '') : '';
                    return '- ' + (item.type || 'Servicio') + ': ' + (item.name || '') + ' (' + (item.provider || 'Proveedor') + ')' + price;
                }).join('\n');
                if (parsed.length > 5) {
                    summary += '\n- + ' + (parsed.length - 5) + ' servicios adicionales';
                }
                message.value = 'Servicios complementarios seleccionados:\n' + summary;
            }
        })();
    </script>
    <?php
}
