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

function render_booking_form($origin = 'booking_page') {
    $destinations = [
        '' => 'Select Destination',
        '1' => 'Destination 1',
        '2' => 'Destination 2',
        '3' => 'Destination 3',
    ];
    $persons = [
        '' => 'Select Persons',
        '1' => 'Persons 1',
        '2' => 'Persons 2',
        '3' => 'Persons 3',
    ];
    $categories = [
        '' => 'Select Category',
        '1' => 'Kids',
        '2' => 'Family',
        '3' => 'Wellness',
        '4' => 'Corporate',
    ];
    $texts = get_booking_texts();
    ?>
    <form method="POST" action="/booking/step-1.php" class="book-tour-form">
        <input type="hidden" name="origin" value="<?php echo htmlspecialchars($origin, ENT_QUOTES); ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="text" name="name" class="form-control bg-white border-0" id="book-name" placeholder="Your Name" required>
                    <label for="book-name">Your Name</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="email" name="email" class="form-control bg-white border-0" id="book-email" placeholder="Your Email" required>
                    <label for="book-email">Your Email</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating date" id="booking-date" data-target-input="nearest">
                    <input type="text" name="datetime" class="form-control bg-white border-0" id="book-datetime" placeholder="Date & Time" data-target="#booking-date" data-toggle="datetimepicker">
                    <label for="book-datetime">Date & Time</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <select name="destination" class="form-select bg-white border-0" id="book-destination" required>
                        <?php foreach ($destinations as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="book-destination">Destination</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <select name="persons" class="form-select bg-white border-0" id="book-persons" required>
                        <?php foreach ($persons as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="book-persons">Persons</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <select name="category" class="form-select bg-white border-0" id="book-category">
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="book-category">Categories</label>
                </div>
            </div>
            <div class="col-12">
                <div class="form-floating">
                    <textarea name="special_request" class="form-control bg-white border-0" placeholder="Special Request" id="book-message" style="height: 100px"></textarea>
                    <label for="book-message">Special Request</label>
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary text-white w-100 py-3" type="submit"><?php echo htmlspecialchars($texts['cta_text']); ?></button>
                <?php if (!empty($texts['cta_subtext'])): ?>
                    <small class="text-white d-block mt-2"><?php echo htmlspecialchars($texts['cta_subtext']); ?></small>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <?php
}
