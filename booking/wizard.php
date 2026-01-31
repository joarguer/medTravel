<?php
session_start();
include(__DIR__ . '/../inc/include.php');
$booking = isset($_SESSION['booking_request']) ? $_SESSION['booking_request'] : [];
$submission_status = isset($_SESSION['booking_request_status']) ? $_SESSION['booking_request_status'] : '';
$submission_message = isset($_SESSION['booking_request_message']) ? $_SESSION['booking_request_message'] : '';
unset($_SESSION['booking_request_status'], $_SESSION['booking_request_message']);

$categories = [];
$cat_query = "SELECT id, name, description FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, id DESC";
if ($cat_res = $conexion->query($cat_query)) {
    while ($row = $cat_res->fetch_assoc()) {
        $categories[] = $row;
    }
}

$services = [];
$svc_sql = "SELECT sc.id, sc.name, sc.short_description, sc.category_id, c.name AS category_name
            FROM service_catalog sc
            LEFT JOIN service_categories c ON c.id = sc.category_id
            WHERE sc.is_active = 1
            ORDER BY c.name ASC, sc.sort_order ASC, sc.id DESC";
if ($svc_res = $conexion->query($svc_sql)) {
    while ($row = $svc_res->fetch_assoc()) {
        $services[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        .wizard-summary { background: #f8fafc; border-radius: 12px; padding: 24px; margin-bottom: 32px; }
        .wizard-summary h2 { margin-top: 0; }
        .wizard-summary p { margin-bottom: 6px; }
        .wizard-stage { border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
        .wizard-stage h3 { font-size: 1.2rem; margin-bottom: 12px; }
        .service-card { background: #ffffff; border: 1px solid #e5e7eb; }
        .list-group-item { cursor: pointer; }
    </style>
</head>
<body>
    <?php echo $topbar; ?>
    <div class="container-fluid position-relative p-0">
        <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
            <?php echo $logo; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars"></span>
            </button>
            <?php echo $menu; ?>
        </nav>
    </div>

    <div class="container py-5">
        <?php if ($submission_status === 'submitted'): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($submission_message ?: 'Thank you. Your request was recorded. One of our coordinators will contact you soon.'); ?>
            </div>
        <?php elseif ($submission_status === 'error'): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($submission_message ?: 'Please review the form.'); ?>
            </div>
        <?php endif; ?>
        <div class="wizard-summary">
            <h2>Step 1 completed</h2>
            <p>We captured your contact context so we can continue with the wizard.</p>
            <?php if (!empty($booking)): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?></p>
                <?php if (!empty($booking['destination'])): ?>
                    <p><strong>Destination:</strong> <?php echo htmlspecialchars($booking['destination']); ?></p>
                <?php endif; ?>
                <?php if (!empty($booking['datetime'])): ?>
                    <p><strong>Preferred time:</strong> <?php echo htmlspecialchars($booking['datetime']); ?></p>
                <?php endif; ?>
                <?php if (!empty($booking['special_request'])): ?>
                    <p><strong>Special request:</strong> <?php echo htmlspecialchars($booking['special_request']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p>No data captured yet.</p>
            <?php endif; ?>
        </div>

        <form action="/booking/submit.php" method="POST" id="booking-wizard-form">
            <div class="wizard-stage">
                <h3>Stage 2 – Select Services</h3>
                <p>Here we will show the list of services and medical services so you can build your itinerary.</p>
                <div class="row">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-md-4">
                            <div class="card mb-3 service-card">
                                <div class="card-body p-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="service_categories[]" value="<?php echo $category['id']; ?>" id="cat-<?php echo $category['id']; ?>">
                                        <label class="form-check-label" for="cat-<?php echo $category['id']; ?>">
                                            <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                        </label>
                                        <?php if (!empty($category['description'])): ?>
                                            <p class="small text-muted mb-0"><?php echo htmlspecialchars($category['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="wizard-stage">
                <h3>Stage 3 – Finalize Context</h3>
            <p>Add any budget, urgency or additional notes before sending the request.</p>
            <div class="mb-3">
                <label class="form-label">Medical Services</label>
                <div class="list-group">
                    <?php foreach ($services as $service): ?>
                        <label class="list-group-item list-group-item-action">
                            <input class="form-check-input me-2" type="checkbox" name="medical_services[]" value="<?php echo $service['id']; ?>">
                            <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                            <?php if (!empty($service['category_name'])): ?>
                                <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($service['category_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($service['short_description'])): ?>
                                <div class="small text-muted mt-1"><?php echo htmlspecialchars($service['short_description']); ?></div>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="budget" class="form-label">Budget (USD)</label>
                    <input type="number" step="50" min="0" class="form-control" name="budget" id="budget" placeholder="Example: 5000">
                </div>
                <div class="col-md-6">
                    <label for="timeline" class="form-label">Preferred timeline</label>
                    <input type="text" class="form-control" name="timeline" id="timeline" placeholder="e.g. Between March 10-20">
                </div>
                <div class="col-12">
                    <label for="additional_notes" class="form-label">Additional context</label>
                    <textarea name="additional_notes" id="additional_notes" class="form-control" rows="4" placeholder="Anything else we should know?"></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4">Submit request</button>
            </div>
        </form>
        <div class="mt-3">
            <a href="/offers.php" class="btn btn-outline-primary">Back to catalog</a>
        </div>
    </div>

    <?php echo $footer; ?>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
