<?php
$booking_id = isset($booking_id) ? (int)$booking_id : 0;
$customer_name = isset($customer_name) ? (string)$customer_name : '';
$customer_email = isset($customer_email) ? (string)$customer_email : '';
$customer_phone = isset($customer_phone) ? (string)$customer_phone : '';
$destination = isset($destination) ? (string)$destination : '';
$dates = isset($dates) ? (string)$dates : '';
$total = isset($total) ? (string)$total : 'Price on request';
$items = (isset($items) && is_array($items)) ? $items : [];
$set_password_url = isset($set_password_url) ? (string)$set_password_url : '';
$login_url = isset($login_url) ? (string)$login_url : 'https://medtravel.com.co/login.php';
$website_url = isset($website_url) ? (string)$website_url : 'https://medtravel.com.co';
$logo_url = isset($logo_url) ? (string)$logo_url : 'https://medtravel.com.co/img/site/logo.png';
$contact_email = isset($contact_email) ? (string)$contact_email : 'patientcare@medtravel.com.co';
?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedTravel Booking Confirmation</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#223047;">
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#f4f7fb;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="640" border="0" cellspacing="0" cellpadding="0" style="width:640px; max-width:640px; background-color:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #e5eaf2;">
                    <tr>
                        <td style="padding:20px 24px; background-color:#ffffff; border-bottom:1px solid #e9eef5;">
                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="left" valign="middle">
                                        <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="MedTravel" width="170" style="display:block; border:0; outline:none; text-decoration:none; max-width:170px; height:auto;">
                                    </td>
                                    <td align="right" valign="middle" style="font-size:12px; color:#6c7a92;">
                                        Request ID<br>
                                        <span style="font-size:18px; font-weight:700; color:#13357b;">#<?php echo $booking_id; ?></span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <h1 style="margin:0 0 8px 0; font-size:24px; line-height:1.3; color:#13357b;">Request received</h1>
                            <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6; color:#334155;">
                                Thank you<?php echo $customer_name !== '' ? ', ' . htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8') : ''; ?>. We received your booking request and created your case.
                            </p>

                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border:1px solid #e5eaf2; border-radius:8px; margin-bottom:18px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:14px; line-height:1.6; color:#334155;">
                                        <strong>Patient:</strong> <?php echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($customer_email, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <?php if ($customer_phone !== ''): ?>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($customer_phone, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <?php endif; ?>
                                        <?php if ($destination !== ''): ?>
                                        <strong>Destination:</strong> <?php echo htmlspecialchars($destination, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <?php endif; ?>
                                        <?php if ($dates !== ''): ?>
                                        <strong>Preferred dates:</strong> <?php echo htmlspecialchars($dates, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin:0 0 10px 0; font-size:18px; color:#13357b;">Requested services</h2>
                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; border:1px solid #dce4ef; margin-bottom:12px;">
                                <tr style="background-color:#eff4fb;">
                                    <th align="left" style="padding:10px 10px; border-bottom:1px solid #dce4ef; font-size:13px; color:#0f172a;">Service</th>
                                    <th align="left" style="padding:10px 10px; border-bottom:1px solid #dce4ef; font-size:13px; color:#0f172a;">Type</th>
                                    <th align="left" style="padding:10px 10px; border-bottom:1px solid #dce4ef; font-size:13px; color:#0f172a;">Provider</th>
                                    <th align="left" style="padding:10px 10px; border-bottom:1px solid #dce4ef; font-size:13px; color:#0f172a;">Price</th>
                                </tr>
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $item_name = (string)($item['name'] ?? 'Service');
                                        $item_type = (string)($item['item_type_label'] ?? 'Service');
                                        $item_provider = (string)($item['provider'] ?? 'MedTravel');
                                        $item_price_display = isset($item['price_display']) ? (string)$item['price_display'] : '';
                                        if ($item_price_display === '') {
                                            $item_currency = strtoupper(trim((string)($item['currency'] ?? 'USD')));
                                            $item_price = isset($item['price']) ? (float)$item['price'] : 0.0;
                                            $item_price_display = ($item_price > 0) ? ($item_currency . ' $' . number_format($item_price, 2)) : 'On request';
                                        }
                                        ?>
                                        <tr>
                                            <td style="padding:10px 10px; border-bottom:1px solid #edf2f7; font-size:13px; color:#1e293b;">
                                                <?php echo htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding:10px 10px; border-bottom:1px solid #edf2f7; font-size:13px; color:#475569;">
                                                <?php echo htmlspecialchars($item_type, ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding:10px 10px; border-bottom:1px solid #edf2f7; font-size:13px; color:#475569;">
                                                <?php echo htmlspecialchars($item_provider, ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding:10px 10px; border-bottom:1px solid #edf2f7; font-size:13px; color:#0f172a; font-weight:700;">
                                                <?php echo htmlspecialchars($item_price_display, ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="padding:12px 10px; font-size:13px; color:#64748b;">
                                            Services pending detailed pricing confirmation.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>

                            <p style="margin:0 0 18px 0; font-size:15px; color:#0f172a;"><strong>Total estimated:</strong> <?php echo htmlspecialchars($total, ENT_QUOTES, 'UTF-8'); ?></p>

                            <h2 style="margin:0 0 8px 0; font-size:18px; color:#13357b;">Next steps</h2>
                            <ol style="margin:0 0 18px 18px; padding:0; font-size:14px; color:#334155; line-height:1.7;">
                                <li>Availability check</li>
                                <li>Virtual consultation scheduling</li>
                                <li>Budget confirmation</li>
                                <li>Schedule coordination</li>
                                <li>Payment</li>
                            </ol>

                            <table role="presentation" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 8px 0;">
                                <tr>
                                    <td>
                                        <?php if ($set_password_url !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($set_password_url, ENT_QUOTES, 'UTF-8'); ?>" style="display:inline-block; padding:12px 18px; font-size:14px; font-weight:700; color:#ffffff; background-color:#13357b; border-radius:6px; text-decoration:none;">Create your password</a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="width:10px;"></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8'); ?>" style="display:inline-block; padding:12px 18px; font-size:14px; font-weight:700; color:#13357b; background-color:#e8eefb; border-radius:6px; text-decoration:none;">Login</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px; background-color:#f8fbff; border-top:1px solid #e9eef5; font-size:12px; color:#64748b; line-height:1.6;">
                            MedTravel Patient Care<br>
                            <?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?> | <a href="<?php echo htmlspecialchars($website_url, ENT_QUOTES, 'UTF-8'); ?>" style="color:#13357b; text-decoration:none;">medtravel.com.co</a><br>
                            Please do not reply to this email directly.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
