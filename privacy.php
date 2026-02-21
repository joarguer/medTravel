<?php
include(__DIR__ . '/inc/include.php');
$privacyVersion = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : 'v1.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php echo $head; ?>
</head>
<body>
	<div class="container-fluid position-relative p-0">
		<nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
			<?php echo $logo; ?>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
				<span class="fa fa-bars"></span>
			</button>
			<?php echo $menu; ?>
		</nav>
	</div>

	<div class="container-fluid bg-breadcrumb">
		<div class="container text-center py-5" style="max-width: 900px;">
			<h3 class="text-white display-3 mb-3">Privacy Policy</h3>
			<p class="text-white mb-0">Version: <?php echo htmlspecialchars($privacyVersion); ?></p>
		</div>
	</div>

	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-lg-10">
				<h4>Summary</h4>
				<p>MedTravel is a medical coordination platform that connects patients with independent healthcare providers and related travel services. This Privacy Policy explains how we collect, use, and share information in connection with our coordination services, including cross-border engagements that are U.S.-oriented.</p>

				<hr>

				<h4>1. Information We Collect</h4>
				<p>We collect information that helps us coordinate your request and communicate with providers, including:</p>
				<ul>
					<li>Contact details (name, email, phone).</li>
					<li>Medical and health-related details you choose to provide.</li>
					<li>Travel details (destination, dates, companions, and preferences).</li>
					<li>Booking and coordination details (selected services, budget, notes).</li>
					<li>Payment and transaction details for coordination fees (processed by third parties).</li>
					<li>Technical data (IP address, user agent, and basic usage events).</li>
				</ul>

				<hr>

				<h4>2. How We Use Information</h4>
				<p>We use your information to:</p>
				<ul>
					<li>Coordinate and facilitate services with independent providers.</li>
					<li>Communicate updates, proposals, and requests for additional information.</li>
					<li>Process coordination fees and manage billing support.</li>
					<li>Improve service quality, security, and platform reliability.</li>
				</ul>

				<hr>

				<h4>3. Independent Providers and Third Parties</h4>
				<p>Providers listed on MedTravel are independent third parties. We share the information necessary to coordinate your request with those providers and related travel services. Providers are responsible for their own privacy and medical record practices.</p>

				<hr>

				<h4>4. Payments, Commissions, and Chargebacks</h4>
				<p>MedTravel operates on a coordination fee or commission model for facilitation services. Payments are handled by third-party payment processors. We do not store full payment card numbers. Chargebacks and payment disputes are handled with processors and may require us to share relevant transaction and booking data.</p>

				<hr>

				<h4>5. Not a HIPAA Covered Entity</h4>
				<p>MedTravel is not a HIPAA covered entity or business associate. Medical information you provide is used solely for coordination and is shared with independent providers at your request. Providers may be subject to their own regulatory obligations.</p>

				<hr>

				<h4>6. International Data Transfers</h4>
				<p>MedTravel coordinates services that may involve cross-border travel and international providers. Your information may be transferred and processed in other countries as needed to fulfill your request.</p>

				<hr>

				<h4>7. Data Retention</h4>
				<p>We retain information for as long as necessary to provide services, comply with legal obligations, resolve disputes, and enforce agreements. Retention periods may vary depending on the type of data and the nature of your request.</p>

				<hr>

				<h4>8. Security</h4>
				<p>We implement reasonable technical and organizational measures to protect information. No system can be guaranteed to be 100% secure, and you use the platform at your own risk.</p>

				<hr>

				<h4>9. Your Choices</h4>
				<p>You may request access, correction, or deletion of certain information by contacting us. Some information may be retained where required by law or to fulfill coordination obligations.</p>

				<hr>

				<h4>10. Updates</h4>
				<p>We may update this Privacy Policy from time to time. The current version is shown at the top of this page.</p>

				<p class="text-muted mt-4">If you have questions about this Privacy Policy, contact us before submitting a booking request.</p>
			</div>
		</div>
	</div>

	<?php echo $footer; ?>
	<?php echo $copyright; ?>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
	<?php echo $script; ?>
	<script src="js/main.js"></script>
</body>
</html>
