<?php
$form_action = '/booking/step-1.php';
$origin_value = isset($form_origin) && $form_origin ? $form_origin : 'booking_page';
?>
<form method="POST" action="<?php echo htmlspecialchars($form_action); ?>" class="book-tour-form">
    <input type="hidden" name="origin" value="<?php echo htmlspecialchars($origin_value); ?>">
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
                    <option value="">Select Destination</option>
                    <option value="1">Destination 1</option>
                    <option value="2">Destination 2</option>
                    <option value="3">Destination 3</option>
                </select>
                <label for="book-destination">Destination</label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-floating">
                <select name="persons" class="form-select bg-white border-0" id="book-persons" required>
                    <option value="">Select Persons</option>
                    <option value="1">Persons 1</option>
                    <option value="2">Persons 2</option>
                    <option value="3">Persons 3</option>
                </select>
                <label for="book-persons">Persons</label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-floating">
                <select name="category" class="form-select bg-white border-0" id="book-category">
                    <option value="">Select Category</option>
                    <option value="1">Kids</option>
                    <option value="2">Family</option>
                    <option value="3">Wellness</option>
                    <option value="4">Corporate</option>
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
            <button class="btn btn-primary text-white w-100 py-3" type="submit">Continue to Services</button>
        </div>
    </div>
</form>
