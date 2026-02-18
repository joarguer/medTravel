<?php

if (!function_exists('renderMedTravelEmail')) {
    function renderMedTravelEmail($title, $preheader, $contentHtml, $footerNote = null, $cta = null)
    {
        $titleText = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
        $preheaderText = htmlspecialchars((string)$preheader, ENT_QUOTES, 'UTF-8');
        $footerNoteText = trim((string)$footerNote) !== ''
            ? htmlspecialchars((string)$footerNote, ENT_QUOTES, 'UTF-8')
            : 'This is an automated message.';

        $logoAbsoluteUrl = 'https://medtravel.com.co/img/site/logo.png';
        $hasLogo = is_file(__DIR__ . '/../img/site/logo.png');
        $ctaHtml = '';
        if (is_array($cta) && !empty($cta['text']) && !empty($cta['url'])) {
            $ctaText = htmlspecialchars((string)$cta['text'], ENT_QUOTES, 'UTF-8');
            $ctaUrl = htmlspecialchars((string)$cta['url'], ENT_QUOTES, 'UTF-8');
            $ctaHtml = '
                <tr>
                    <td style="padding: 0 32px 10px 32px; text-align: left;">
                        <a href="' . $ctaUrl . '" style="display:inline-block; background:#0b4ea2; color:#ffffff; text-decoration:none; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:18px; padding:12px 20px; border-radius:4px; font-weight:700;">
                            ' . $ctaText . '
                        </a>
                    </td>
                </tr>';
        }

        return '<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>' . $titleText . '</title>
</head>
<body style="margin:0; padding:0; background:#f3f7fc;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">' . $preheaderText . '</div>
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background:#f3f7fc; margin:0; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" border="0" cellspacing="0" cellpadding="0" style="width:100%; max-width:600px; background:#ffffff; border:1px solid #d9e4f1;">
          <tr>
            <td style="padding:24px 32px 16px 32px; background:#0b4ea2; text-align:left;">
              ' . ($hasLogo
                ? '<img src="' . htmlspecialchars($logoAbsoluteUrl, ENT_QUOTES, 'UTF-8') . '" alt="MedTravel" width="160" style="display:block; border:0; outline:none; text-decoration:none; width:160px; max-width:100%; height:auto;">'
                : '<div style="font-family:Arial,Helvetica,sans-serif; color:#ffffff; font-size:28px; font-weight:700;">MedTravel</div>') . '
            </td>
          </tr>
          <tr>
            <td style="padding:24px 32px 14px 32px;">
              <h1 style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:24px; line-height:1.3; color:#13357b;">' . $titleText . '</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 10px 32px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#334155;">
              ' . $contentHtml . '
            </td>
          </tr>
          ' . $ctaHtml . '
          <tr>
            <td style="padding:14px 32px 24px 32px; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#64748b;">
              <p style="margin:0 0 8px 0;"><strong>MedTravel Patient Care</strong></p>
              <p style="margin:0 0 8px 0;"><a href="mailto:patientcare@medtravel.com.co" style="color:#0b4ea2; text-decoration:none;">patientcare@medtravel.com.co</a></p>
              <p style="margin:0;">' . $footerNoteText . '</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}
