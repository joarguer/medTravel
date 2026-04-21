<?php

if (!function_exists('booking_patient_notification_escape')) {
    function booking_patient_notification_escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('booking_patient_notification_login_url')) {
    function booking_patient_notification_login_url()
    {
        return 'https://medtravel.com.co/login.php';
    }
}

if (!function_exists('booking_patient_notification_set_password_url')) {
    function booking_patient_notification_set_password_url($token = '')
    {
        $baseUrl = 'https://medtravel.com.co/set_password.php';
        $token = trim((string)$token);
        if ($token === '') {
            return $baseUrl;
        }
        return $baseUrl . '?token=' . urlencode($token);
    }
}

if (!function_exists('booking_patient_build_access_payload')) {
    function booking_patient_build_access_payload(array $context)
    {
        $patientEmail = trim((string)($context['patient_email'] ?? ''));
        $isNewUser = !empty($context['is_new_user']);
        $hasLinkedPatientAccount = !array_key_exists('has_linked_patient_account', $context)
            ? true
            : !empty($context['has_linked_patient_account']);
        $resetToken = trim((string)($context['reset_token'] ?? ''));

        $loginUrl = booking_patient_notification_login_url();
        $setPasswordUrl = booking_patient_notification_set_password_url($resetToken);
        $genericSetPasswordUrl = booking_patient_notification_set_password_url();

        if ($isNewUser && $resetToken !== '') {
            $cta = ['text' => 'Create your password', 'url' => $setPasswordUrl];
            $html = ''
                . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Access your MedTravel Patient Portal</h3>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">We created a secure patient portal account for you.</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Username:</strong> ' . booking_patient_notification_escape($patientEmail !== '' ? $patientEmail : 'Not available') . '</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">To activate your access, please create your password using the secure link below.</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Create your password:</strong> <a href="' . booking_patient_notification_escape($setPasswordUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($setPasswordUrl) . '</a></p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">If the button does not work, copy and paste this link:<br><a href="' . booking_patient_notification_escape($setPasswordUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($setPasswordUrl) . '</a></p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">For security reasons, this link expires in 24 hours. If it expires, you can request a new one on the same page.</p>'
                . '<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">After creating your password, you can sign in here: <a href="' . booking_patient_notification_escape($loginUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($loginUrl) . '</a></p>';
            $altBody = "Access your MedTravel Patient Portal\n"
                . "We created a secure patient portal account for you.\n"
                . "Username: " . ($patientEmail !== '' ? $patientEmail : 'Not available') . "\n"
                . "Create your password:\n" . $setPasswordUrl . "\n"
                . "If the button does not work, copy and paste this link:\n" . $setPasswordUrl . "\n"
                . "For security reasons, this link expires in 24 hours. If it expires, you can request a new one on the same page.\n"
                . "After creating your password, you can sign in here:\n" . $loginUrl . "\n";

            return [
                'mode' => 'new_user_activation',
                'cta' => $cta,
                'html' => $html,
                'alt_body' => $altBody,
                'login_url' => $loginUrl,
                'set_password_url' => $setPasswordUrl,
            ];
        }

        if ($isNewUser) {
            $cta = ['text' => 'Set or recover password', 'url' => $genericSetPasswordUrl];
            $html = ''
                . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Access your MedTravel Patient Portal</h3>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">We prepared your patient portal access.</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Username:</strong> ' . booking_patient_notification_escape($patientEmail !== '' ? $patientEmail : 'Not available') . '</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">Use the secure access page below to set or recover your password.</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Access page:</strong> <a href="' . booking_patient_notification_escape($genericSetPasswordUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($genericSetPasswordUrl) . '</a></p>'
                . '<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">After setting your password, you can sign in here: <a href="' . booking_patient_notification_escape($loginUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($loginUrl) . '</a></p>';
            $altBody = "Access your MedTravel Patient Portal\n"
                . "We prepared your patient portal access.\n"
                . "Username: " . ($patientEmail !== '' ? $patientEmail : 'Not available') . "\n"
                . "Use the secure access page below to set or recover your password:\n" . $genericSetPasswordUrl . "\n"
                . "After setting your password, you can sign in here:\n" . $loginUrl . "\n";

            return [
                'mode' => 'new_user_no_token',
                'cta' => $cta,
                'html' => $html,
                'alt_body' => $altBody,
                'login_url' => $loginUrl,
                'set_password_url' => $genericSetPasswordUrl,
            ];
        }

        if (!$hasLinkedPatientAccount) {
            $cta = ['text' => 'Set or recover password', 'url' => $genericSetPasswordUrl];
            $html = ''
                . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Access your MedTravel Patient Portal</h3>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">We received your request and your case is now open.</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Username:</strong> ' . booking_patient_notification_escape($patientEmail !== '' ? $patientEmail : 'Not available') . '</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">Use the secure access page below if you need to set or recover your password.</p>'
                . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Access page:</strong> <a href="' . booking_patient_notification_escape($genericSetPasswordUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($genericSetPasswordUrl) . '</a></p>'
                . '<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">You can also review the patient portal here: <a href="' . booking_patient_notification_escape($loginUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($loginUrl) . '</a></p>';
            $altBody = "Access your MedTravel Patient Portal\n"
                . "We received your request and your case is now open.\n"
                . "Username: " . ($patientEmail !== '' ? $patientEmail : 'Not available') . "\n"
                . "Use the secure access page below if you need to set or recover your password:\n" . $genericSetPasswordUrl . "\n"
                . "You can also review the patient portal here:\n" . $loginUrl . "\n";

            return [
                'mode' => 'no_linked_patient_account',
                'cta' => $cta,
                'html' => $html,
                'alt_body' => $altBody,
                'login_url' => $loginUrl,
                'set_password_url' => $genericSetPasswordUrl,
            ];
        }

        $cta = ['text' => 'Go to patient portal', 'url' => $loginUrl];
        $html = ''
            . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Access your MedTravel Patient Portal</h3>'
            . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">Your patient portal account is already available.</p>'
            . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Username:</strong> ' . booking_patient_notification_escape($patientEmail !== '' ? $patientEmail : 'Not available') . '</p>'
            . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">Sign in with your existing credentials to review your case.</p>'
            . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Sign in:</strong> <a href="' . booking_patient_notification_escape($loginUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($loginUrl) . '</a></p>'
            . '<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">If you need to reset your password, use this secure page: <a href="' . booking_patient_notification_escape($genericSetPasswordUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . booking_patient_notification_escape($genericSetPasswordUrl) . '</a></p>';
        $altBody = "Access your MedTravel Patient Portal\n"
            . "Your patient portal account is already available.\n"
            . "Username: " . ($patientEmail !== '' ? $patientEmail : 'Not available') . "\n"
            . "Sign in with your existing credentials:\n" . $loginUrl . "\n"
            . "If you need to reset your password, use this secure page:\n" . $genericSetPasswordUrl . "\n";

        return [
            'mode' => 'existing_user_login',
            'cta' => $cta,
            'html' => $html,
            'alt_body' => $altBody,
            'login_url' => $loginUrl,
            'set_password_url' => $genericSetPasswordUrl,
        ];
    }
}

if (!function_exists('booking_patient_build_email_payload')) {
    function booking_patient_build_email_payload(array $context)
    {
        $flow = trim((string)($context['flow'] ?? 'public'));
        if ($flow !== 'assisted') {
            $flow = 'public';
        }

        $bookingId = (int)($context['booking_id'] ?? 0);
        $patientName = trim((string)($context['patient_name'] ?? ''));
        $patientEmail = trim((string)($context['patient_email'] ?? ''));
        $destination = trim((string)($context['destination'] ?? ''));
        $timeline = trim((string)($context['timeline'] ?? ''));
        $totalDisplay = trim((string)($context['total_display'] ?? ''));
        if ($patientName === '') {
            $patientName = 'Patient';
        }
        if ($totalDisplay === '') {
            $totalDisplay = 'Price on request';
        }

        $accessPayload = booking_patient_build_access_payload($context);
        $items = (isset($context['items']) && is_array($context['items'])) ? $context['items'] : [];

        $title = $flow === 'assisted' ? 'Your booking has been created' : 'Request received';
        $preheader = $flow === 'assisted'
            ? 'A MedTravel coordinator created a booking on your behalf.'
            : 'We received your request and opened your MedTravel case.';
        $subject = $flow === 'assisted'
            ? "Your MedTravel booking (case #{$bookingId}) — Created by your coordinator"
            : "MedTravel – Request received (ID #{$bookingId})";

        $introHtml = $flow === 'assisted'
            ? '<p style="margin:0 0 14px 0;">Hello ' . booking_patient_notification_escape($patientName) . ',</p>'
                . '<p style="margin:0 0 14px 0;">A MedTravel coordinator has created a booking on your behalf.</p>'
                . '<p style="margin:0 0 14px 0;">Your case is now open and our team will continue coordinating the next steps with you.</p>'
            : '<p style="margin:0 0 14px 0;">Hello ' . booking_patient_notification_escape($patientName) . ',</p>'
                . '<p style="margin:0 0 14px 0;">We received your request and opened your case with MedTravel.</p>';

        $summaryTable = ''
            . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 16px 0;">'
            . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Booking ID:</strong> #' . $bookingId . '</td></tr>'
            . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Patient:</strong> ' . booking_patient_notification_escape($patientName) . '</td></tr>'
            . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Email:</strong> ' . booking_patient_notification_escape($patientEmail) . '</td></tr>'
            . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Destination:</strong> ' . booking_patient_notification_escape($destination !== '' ? $destination : 'Not specified') . '</td></tr>'
            . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Timeline:</strong> ' . booking_patient_notification_escape($timeline !== '' ? $timeline : 'To be defined') . '</td></tr>'
            . '</table>';

        $itemsHtml = '';
        if ($flow === 'public') {
            foreach ($items as $item) {
                $typeLabel = trim((string)($item['item_type_label'] ?? ''));
                if ($typeLabel === '') {
                    $typeLabel = ((string)($item['item_type'] ?? '') === 'complementary_service') ? 'Complementary' : 'Medical';
                }
                $itemsHtml .= '<tr>'
                    . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#0f172a;">' . booking_patient_notification_escape($item['name'] ?? '-') . '</td>'
                    . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . booking_patient_notification_escape($typeLabel) . '</td>'
                    . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . booking_patient_notification_escape($item['provider'] ?? 'MedTravel') . '</td>'
                    . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . booking_patient_notification_escape($item['price_display'] ?? '-') . '</td>'
                    . '</tr>';
            }
            if ($itemsHtml === '') {
                $itemsHtml = '<tr><td colspan="4" style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#64748b;">No services were itemized yet. A coordinator will complete details with you.</td></tr>';
            }

            $itemsHtml = ''
                . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Selected services</h3>'
                . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 12px 0;">'
                . '<tr>'
                . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Service</th>'
                . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Type</th>'
                . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Provider</th>'
                . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Price</th>'
                . '</tr>'
                . $itemsHtml
                . '</table>'
                . '<p style="margin:0 0 16px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#0f172a;"><strong>Total estimated:</strong> ' . booking_patient_notification_escape($totalDisplay) . '</p>';
        }

        $extraAssistedNote = ($flow === 'assisted' && !empty($context['is_new_user']))
            ? '<p style="margin:0 0 16px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Important:</strong> On your first login you will be asked to review and accept the MedTravel Terms of Service to complete the activation.</p>'
            : '';

        $nextStepsHtml = ''
            . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Next steps</h3>'
            . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 16px 0;">'
            . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">1. MedTravel reviews your case.</td></tr>'
            . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">2. We coordinate providers and update your request.</td></tr>'
            . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">3. You can follow your case status in the patient portal.</td></tr>'
            . '</table>';

        $contentHtml = $introHtml . $summaryTable . $itemsHtml . $extraAssistedNote . $accessPayload['html'] . $nextStepsHtml;

        $bodyHtml = '';
        if (function_exists('renderMedTravelEmail')) {
            $bodyHtml = renderMedTravelEmail(
                $title,
                $preheader,
                $contentHtml,
                'This is an automated message from MedTravel.',
                $accessPayload['cta']
            );
        }

        if ($bodyHtml === '') {
            $bodyHtml = '<h2>' . booking_patient_notification_escape($title) . '</h2>'
                . '<p>Hello ' . booking_patient_notification_escape($patientName) . ',</p>'
                . ($flow === 'assisted'
                    ? '<p>A MedTravel coordinator created booking #' . $bookingId . ' on your behalf.</p>'
                    : '<p>We received your request and opened case #' . $bookingId . '.</p>')
                . '<p><strong>Email:</strong> ' . booking_patient_notification_escape($patientEmail) . '</p>'
                . '<p><strong>Destination:</strong> ' . booking_patient_notification_escape($destination !== '' ? $destination : 'Not specified') . '</p>'
                . '<p><strong>Timeline:</strong> ' . booking_patient_notification_escape($timeline !== '' ? $timeline : 'To be defined') . '</p>'
                . ($flow === 'public' ? '<p><strong>Total estimated:</strong> ' . booking_patient_notification_escape($totalDisplay) . '</p>' : '')
                . '<p><strong>Access your portal:</strong> <a href="' . booking_patient_notification_escape($accessPayload['cta']['url']) . '">' . booking_patient_notification_escape($accessPayload['cta']['url']) . '</a></p>';
        }

        $altBody = ($flow === 'assisted'
            ? "Your MedTravel booking has been created (case #{$bookingId})\n"
            : "MedTravel - Request received (ID #{$bookingId})\n")
            . "Patient: " . $patientName . "\n"
            . "Email: " . $patientEmail . "\n"
            . "Destination: " . ($destination !== '' ? $destination : 'Not specified') . "\n"
            . "Timeline: " . ($timeline !== '' ? $timeline : 'To be defined') . "\n";
        if ($flow === 'public') {
            $altBody .= "Total estimated: " . $totalDisplay . "\n";
        }
        if ($flow === 'assisted') {
            $altBody .= "A MedTravel coordinator created this booking on your behalf.\n";
            if (!empty($context['is_new_user'])) {
                $altBody .= "On your first login you will be asked to review and accept the MedTravel Terms of Service.\n";
            }
        } else {
            $altBody .= "We received your request and opened your case with MedTravel.\n";
        }
        $altBody .= "\n" . $accessPayload['alt_body'];

        return [
            'subject' => $subject,
            'title' => $title,
            'preheader' => $preheader,
            'body_html' => $bodyHtml,
            'alt_body' => $altBody,
            'cta' => $accessPayload['cta'],
            'access_mode' => $accessPayload['mode'],
            'access_url' => (string)($accessPayload['cta']['url'] ?? ''),
        ];
    }
}
