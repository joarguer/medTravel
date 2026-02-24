<?php
$page_title = 'User Data Deletion Instructions | MedTravel';
$page_description = 'Instructions for MedTravel users to request deletion of personal data stored in MedTravel systems.';
$page_canonical = 'https://medtravel.com.co/data-deletion.php';
include('inc/include.php');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php echo $head; ?>
    </head>
    <body>
        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Navbar -->
        <div class="container-fluid position-relative p-0">
            <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
                <?php echo $logo; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <?php echo $menu; ?>
            </nav>
        </div>
        <!-- Header -->
        <div class="container-fluid bg-breadcrumb">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h3 class="text-white display-4 mb-3">User Data Deletion Instructions</h3>
                <p class="text-white-50 mb-0">How to request deletion of personal data stored in MedTravel systems.</p>
            </div>
        </div>

        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5>Important</h5>
                            <ul class="mb-4">
                                <li>We do not provide self-service deletion. Requests are processed by support after verification.</li>
                                <li>We delete data stored in our systems. If you used WhatsApp, Meta may retain certain records per their policies; we only delete data under our control.</li>
                                <li>Processing time: as soon as possible.</li>
                            </ul>
                            <h5>Email instructions</h5>
                            <p>Send an email to <strong>info@medtravel.com</strong> with subject <strong>“Data Deletion Request”</strong> and include:</p>
                            <ol>
                                <li>Phone number (international format).</li>
                                <li>Name (optional).</li>
                                <li>Contact email.</li>
                                <li>Short description of the request.</li>
                            </ol>
                            <h5 class="mt-4">Request via form</h5>
                            <p class="text-muted small mb-3">This form only sends a request to support. No data is deleted automatically.</p>
                            <form id="dd-form" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone (required)</label>
                                    <input type="text" class="form-control" name="phone" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email (required)</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Name (optional)</label>
                                    <input type="text" class="form-control" name="name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Message (optional)</label>
                                    <textarea class="form-control" name="message" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirm" required>
                                        <label class="form-check-label" for="confirm">
                                            I confirm I am the owner of this phone number / account.
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 d-flex align-items-center">
                                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="dd-submit">Send request</button>
                                    <span class="ms-3 text-muted small" id="dd-status"></span>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-success d-none" id="dd-success"></div>
                                    <div class="alert alert-danger d-none" id="dd-error"></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php echo $footer; ?>
        <?php echo $copyright; ?>

        <a href="#" class="btn btn-primary btn-primary-outline-0 btn-md-square back-to-top"><i class="fa fa-arrow-up"></i></a>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <?php echo $script; ?>
        <script src="js/main.js"></script>
        <script>
            (function(){
                const form = document.getElementById('dd-form');
                const statusEl = document.getElementById('dd-status');
                const okEl = document.getElementById('dd-success');
                const errEl = document.getElementById('dd-error');
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    okEl.classList.add('d-none'); errEl.classList.add('d-none');
                    statusEl.textContent = 'Sending...';
                    const fd = new FormData(form);
                    fetch('/api/data_deletion_request.php', {
                        method: 'POST',
                        body: fd
                    }).then(r => r.json()).then(res => {
                        statusEl.textContent = '';
                        if(res.ok){
                            okEl.textContent = 'Request received. Your request ID: ' + res.request_id;
                            okEl.classList.remove('d-none');
                            form.reset();
                        } else {
                            errEl.textContent = res.error || 'Error sending request';
                            errEl.classList.remove('d-none');
                        }
                    }).catch(()=>{
                        statusEl.textContent = '';
                        errEl.textContent = 'Connection error';
                        errEl.classList.remove('d-none');
                    });
                });
            })();
        </script>
    </body>
</html>
