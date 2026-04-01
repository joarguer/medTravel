<?php
$page_title = 'Terms of Service & Medical Disclaimer | MedTravel';
$page_description = 'Read MedTravel terms of service and medical disclaimer for platform use, provider relationships, and liability scope.';
$page_canonical = 'https://medtravel.com.co/terms.php';
include(__DIR__ . '/inc/include.php');
$termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.1';
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
            <h3 class="text-white display-3 mb-3">Terms of Service &amp; Medical Disclaimer</h3>
            <p class="text-white mb-0">Version: <?php echo htmlspecialchars($termsVersion); ?></p>
            <p class="text-white mb-0">Effective Date: 2026-04-01</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h4>Summary</h4>
                <p>MedTravel is a coordination and facilitation platform that connects patients with independent healthcare providers and related travel services. MedTravel does not provide medical care and does not guarantee medical outcomes.</p>

                <hr>

                <h4>1. Platform Role (No Provider Status)</h4>
                <p>MedTravel acts solely as a coordination and introduction platform. MedTravel is not a hospital, clinic, medical practice, physician group, travel agency, or healthcare provider. No medical services are provided by MedTravel.</p>

                <hr>

                <h4>2. No Medical Advice or Medical Relationship</h4>
                <p>MedTravel does not provide medical advice, diagnosis, or treatment. Use of this platform does not create a doctor-patient relationship between you and MedTravel. Any medical relationship is exclusively between you and the independent provider you select.</p>

                <hr>

                <h4>3. Independent Third-Party Providers</h4>
                <p>All healthcare providers and travel service providers listed on MedTravel are independent third parties. MedTravel does not control, supervise, or direct medical decisions or treatment protocols. Providers are solely responsible for the services they render.</p>

                <hr>

                <h4>4. Assumption of Risk</h4>
                <p>You understand that medical procedures carry inherent risks, including complications, dissatisfaction with outcomes, or unforeseen medical conditions. By submitting a booking request, you acknowledge and accept these risks.</p>

                <hr>

                <h4>5. No Guarantee of Outcomes</h4>
                <p>Medical results vary based on individual conditions, provider judgment, and other factors. MedTravel makes no representations or warranties regarding:</p>
                <ul>
                    <li>Treatment suitability</li>
                    <li>Medical outcomes</li>
                    <li>Recovery time</li>
                    <li>Travel safety</li>
                    <li>Financial expectations</li>
                </ul>

                <hr>

                <h4>6. Limitation of Liability</h4>
                <p>To the fullest extent permitted by law, MedTravel shall not be liable for:</p>
                <ul>
                    <li>Medical malpractice</li>
                    <li>Complications</li>
                    <li>Provider negligence</li>
                    <li>Travel disruptions</li>
                    <li>Personal injury</li>
                    <li>Loss of income</li>
                    <li>Emotional distress</li>
                    <li>Indirect, incidental, or consequential damages</li>
                </ul>
                <p>MedTravel's liability, if any, shall not exceed the coordination fee paid to MedTravel.</p>

                <hr>

                <h4>7. No Emergency Services</h4>
                <p>MedTravel does not provide emergency medical services. In case of emergency, you must contact local emergency services immediately.</p>

                <hr>

                <h4>8. User Representations</h4>
                <p>By submitting a booking request, you represent that:</p>
                <ul>
                    <li>You are at least 18 years old.</li>
                    <li>You have the legal capacity to enter into agreements.</li>
                    <li>The information you provide is accurate and truthful.</li>
                </ul>

                <hr>

                <h4>9. Indemnification</h4>
                <p>You agree to indemnify and hold harmless MedTravel from any claims, damages, or disputes arising from services provided by third-party providers.</p>

                <hr>

                <h4>10. Governing Law and Dispute Resolution</h4>
                <p>These Terms shall be governed by the laws of the United States. Any disputes shall be resolved through binding arbitration unless otherwise required by law. Class action claims are waived to the extent permitted.</p>

                <hr>

                <h4>11. Electronic Acceptance</h4>
                <p>By checking the acceptance box and submitting a booking request, you acknowledge that this constitutes a legally binding electronic signature.</p>

                <hr>

                <h4>12. Travel Insurance Requirement</h4>
                <p>You are solely responsible for obtaining and maintaining adequate travel insurance coverage prior to your trip. MedTravel strongly recommends that your policy include, at minimum: emergency medical treatment, surgical and hospitalization costs, medical complications arising from planned procedures, medical evacuation and repatriation, and trip cancellation or interruption.</p>
                <p>MedTravel shall not be liable for any medical costs, losses, additional expenses, or consequences of any kind arising from your failure to obtain adequate coverage or from any gap, exclusion, or limitation in your insurance policy. No coordination or facilitation fee paid to MedTravel constitutes or replaces insurance coverage.</p>

                <hr>

                <h4>13. Cancellation and Refund Policy</h4>
                <p>MedTravel may charge coordination, facilitation, or reservation fees in connection with arranging services between you and independent providers. Unless expressly stated otherwise in writing at the time of payment, coordination and facilitation fees paid to MedTravel are non-refundable once the management of your request has commenced.</p>
                <p>Payments made directly to third-party providers are governed exclusively by those providers' own cancellation and refund policies. MedTravel has no control over, and cannot guarantee refunds for, amounts paid directly to providers.</p>
                <p>Cancellations, rescheduling, or no-shows may result in forfeiture of deposits, partial payments, or other charges imposed by providers. MedTravel bears no liability for financial losses resulting from a provider's cancellation policy or from travel disruptions outside MedTravel's control.</p>

                <hr>

                <h4>14. Telemedicine and Virtual Assessment Consent</h4>
                <p>Some coordination activities may involve video calls, virtual consultations, or other forms of remote communication between you and independent medical providers. MedTravel may facilitate access to such interactions but does not participate in, supervise, or perform any medical act or clinical assessment conducted during these sessions.</p>
                <p>By using the platform you consent to: the scheduling and facilitation of virtual interactions by MedTravel; the sharing of relevant information with the provider for coordination purposes; and the use of third-party communication technologies, including but not limited to Google Meet or similar platforms. You acknowledge that electronic communications may be subject to technical failures, interruptions, or security limitations beyond MedTravel's control, and that MedTravel is not liable for any consequences arising from such failures. The medical relationship remains exclusively between you and the independent provider.</p>

                <hr>

                <h4>15. Force Majeure</h4>
                <p>MedTravel shall not be in breach of these Terms, and shall not be liable for any delay or failure to perform, to the extent that such delay or failure is caused by circumstances reasonably beyond MedTravel's control, including without limitation: travel restrictions or border closures imposed by any government or authority; flight cancellations or transportation disruptions; pandemics, epidemics, or public health emergencies; natural disasters or acts of God; provider insolvency, closure, or withdrawal of services; acts of war, civil unrest, or terrorism; regulatory or legislative changes; and failures of telecommunications, internet, or third-party technology infrastructure.</p>
                <p>In such circumstances, MedTravel will use reasonable efforts to communicate with you and assist in identifying alternatives, but cannot guarantee the availability, timing, or cost of substitute arrangements.</p>

                <hr>

                <h4>16. Provider Verification Disclaimer</h4>
                <p>MedTravel may display designations such as "verified," "featured," "highlighted," or similar indicators for certain listed providers. These designations reflect an administrative or commercial review process conducted by MedTravel and do not constitute: a guarantee of clinical outcomes or procedure safety; confirmation that a provider holds a current and valid professional license; accreditation by any medical or regulatory body; an endorsement of medical quality, competence, or standards of care; or a representation that the provider is free from past complaints or legal actions.</p>
                <p>You remain solely responsible for evaluating any provider's credentials, qualifications, and suitability before proceeding. MedTravel encourages all patients to conduct their own due diligence and, where appropriate, to seek independent professional advice.</p>

                <hr>

                <h4>17. Changes to These Terms</h4>
                <p>MedTravel reserves the right to update these Terms at any time. When updated, the version number and effective date will be revised. Continued use of the platform after such updates constitutes acceptance of the revised Terms. The version accepted at the time of your booking request will govern that specific engagement.</p>
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
