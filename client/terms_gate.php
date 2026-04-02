<?php
/**
 * Terms Gate — mandatory on first login for clients whose account was created
 * by an agent (or any client who has not yet accepted the Terms of Service).
 *
 * This page must NOT be behind the normal terms-gate check in include.php
 * (would cause an infinite redirect loop). The check in include.php
 * explicitly skips this file.
 */

require_once __DIR__ . '/../inc/auth_client.php';
require_client_auth();
// No terms gate redirect here — this IS the gate.

$termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>MedTravel – Terms of Service Acceptance</title>
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <link href="/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
    <link href="/assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" />
    <style>
        body { background: #f4f7fc; font-family: 'Open Sans', Arial, sans-serif; }
        .gate-wrapper { max-width: 720px; margin: 60px auto; padding: 0 16px 60px; }
        .gate-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.10); padding: 40px; }
        .gate-logo { text-align: center; margin-bottom: 28px; }
        .gate-logo img { height: 44px; }
        h2 { font-size: 22px; font-weight: 700; color: #13357b; margin-bottom: 6px; }
        .gate-subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .terms-box { height: 280px; overflow-y: auto; border: 1px solid #dbe4f0; border-radius: 4px;
                     padding: 16px 20px; font-size: 13px; color: #334155; line-height: 1.7;
                     background: #f8fbff; margin-bottom: 20px; }
        .consent-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
        .consent-row input { flex-shrink: 0; margin-top: 3px; width: 18px; height: 18px; cursor: pointer; }
        .consent-row label { font-size: 14px; color: #1e293b; cursor: pointer; }
        .btn-accept { width: 100%; padding: 14px; font-size: 15px; font-weight: 700; }
        .agent-note { background: #eff8ff; border: 1px solid #b8daf8; border-radius: 4px;
                      padding: 10px 14px; font-size: 13px; color: #1a6ca8; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="gate-wrapper">
    <div class="gate-card">
        <div class="gate-logo">
            <img src="/admin/img/logoColor.png" alt="MedTravel" onerror="this.style.display='none'">
        </div>

        <h2>Welcome to MedTravel</h2>
        <p class="gate-subtitle">Before accessing your patient portal, please review and accept our Terms of Service.</p>

        <div class="agent-note">
            <strong>Note:</strong> A MedTravel coordinator has set up your account. To complete your activation
            and access your case, you must personally accept the Terms of Service below.
        </div>

        <div class="terms-box" id="terms-scroll-box">
            <h4 style="margin-top:0;">MedTravel – Terms of Service (<?php echo htmlspecialchars($termsVersion, ENT_QUOTES, 'UTF-8'); ?>)</h4>

            <p><strong>1. Role of MedTravel</strong><br>
            MedTravel acts exclusively as a <strong>coordinator and facilitator</strong> of medical tourism services.
            MedTravel is not a healthcare provider, clinic, or medical institution. All clinical decisions,
            diagnoses, procedures, and treatments are the sole responsibility of the licensed medical providers
            you are referred to.</p>

            <p><strong>2. Intermediary Relationship</strong><br>
            By using the MedTravel platform, you acknowledge that MedTravel connects patients with independent
            medical and complementary service providers in Colombia. MedTravel does not employ the medical
            professionals, own the facilities, or control the quality of care delivered by those providers.</p>

            <p><strong>3. Patient Responsibility</strong><br>
            You are responsible for verifying the credentials of any provider before proceeding with treatment.
            You accept that medical procedures carry inherent risks, and MedTravel cannot be held liable for
            clinical outcomes, complications, or provider actions.</p>

            <p><strong>4. Privacy</strong><br>
            Personal and medical information shared through MedTravel is used solely to facilitate your
            coordination. We do not sell your data. See our <a href="/privacy.php" target="_blank">Privacy Policy</a>
            for full details.</p>

            <p><strong>5. Agent-assisted bookings</strong><br>
            If your booking was created by a MedTravel agent on your behalf, that agent has not accepted these
            Terms on your behalf. Your explicit acceptance below constitutes your personal, binding agreement.</p>

            <p><strong>6. Governing Law</strong><br>
            These Terms are governed by the laws of the Republic of Colombia. Any disputes shall be resolved
            through the applicable Colombian legal framework.</p>

            <p><strong>7. Acceptance</strong><br>
            By checking the boxes below and clicking "I Accept", you confirm that you have read, understood,
            and agree to these Terms and our Privacy Policy.</p>
        </div>

        <form id="terms-form" novalidate>
            <input type="hidden" name="terms_version" value="<?php echo htmlspecialchars($termsVersion, ENT_QUOTES, 'UTF-8'); ?>" />

            <div class="consent-row">
                <input type="checkbox" id="consent_terms" name="consent_terms" value="1" required />
                <label for="consent_terms">
                    I have read and accept the <a href="/terms.php" target="_blank">Terms of Service</a>.
                </label>
            </div>
            <div class="consent-row">
                <input type="checkbox" id="consent_privacy" name="consent_privacy" value="1" required />
                <label for="consent_privacy">
                    I have read and accept the <a href="/privacy.php" target="_blank">Privacy Policy</a>.
                </label>
            </div>
            <div class="consent-row">
                <input type="checkbox" id="consent_intermediary" name="consent_intermediary" value="1" required />
                <label for="consent_intermediary">
                    I understand that MedTravel is an intermediary and not a direct healthcare provider.
                    All clinical responsibility rests with the assigned medical provider.
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-accept" id="btn-accept" disabled>
                I Accept – Activate my portal
            </button>
            <p style="text-align:center;margin-top:12px;font-size:12px;color:#94a3b8;">
                You must check all boxes to continue. &nbsp;|&nbsp;
                <a href="/admin/include/salir.php">Log out</a>
            </p>
        </form>
    </div>
</div>

<script src="/assets/global/plugins/jquery.min.js"></script>
<script src="/assets/global/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="/assets/global/plugins/bootstrap-toastr/toastr.min.js"></script>
<script>
(function () {
    'use strict';
    var btn = document.getElementById('btn-accept');
    var checkboxes = document.querySelectorAll('#terms-form input[type=checkbox]');

    function updateBtn() {
        var allChecked = true;
        checkboxes.forEach(function (c) { if (!c.checked) allChecked = false; });
        btn.disabled = !allChecked;
    }

    checkboxes.forEach(function (c) { c.addEventListener('change', updateBtn); });

    document.getElementById('terms-form').addEventListener('submit', function (e) {
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Saving…';

        $.post('/client/ajax/accept_terms.php', $(this).serialize(), function (res) {
            if (res.success) {
                toastr.success('Terms accepted. Welcome to MedTravel!');
                setTimeout(function () { window.location = '/client/index.php'; }, 1200);
            } else {
                toastr.error(res.message || 'Could not save acceptance. Please try again.');
                btn.disabled = false;
                btn.textContent = 'I Accept – Activate my portal';
            }
        }, 'json').fail(function () {
            toastr.error('Server error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'I Accept – Activate my portal';
        });
    });
}());
</script>
</body>
</html>
