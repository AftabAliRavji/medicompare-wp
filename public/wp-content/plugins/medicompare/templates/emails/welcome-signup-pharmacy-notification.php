<?php

if (!defined('ABSPATH')) {
    exit;
}

$logo_url = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>We have received your registration of interest</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f7f8;font-family:Arial,Helvetica,sans-serif;color:#102b55;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f7f8;">
    <tr>
        <td align="center" style="padding:30px 15px;">

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:650px;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 3px 14px rgba(0,0,0,0.08);">

                <tr>
                    <td style="padding:36px 40px;">
                        <h1 style="margin:0 0 24px;color:#072765;font-size:28px;line-height:1.3;">
                            We have received your registration of interest
                        </h1>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.7;">
                            <strong>Dear {{contact_name}},</strong>
                        </p>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.7;">
                            Thank you for registering your interest in <strong>Source Med Pharma</strong>.
                        </p>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.7;">
                            We have received your information and are pleased to know that you are interested in our comparison platform.
                        </p>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.7;">
                            We are currently preparing for launch and will be in touch soon with further details.
                        </p>

                        <p style="margin:0 0 26px;font-size:16px;line-height:1.7;">
                            We will make sure you have all the information you need before the service launches.
                        </p>

                        <div style="margin:0 0 26px;padding:22px;background-color:#edf9f6;border-left:4px solid #008b74;border-radius:6px;">
                            <h2 style="margin:0 0 10px;color:#072765;font-size:19px;">
                                Have a question?
                            </h2>

                            <p style="margin:0 0 12px;font-size:16px;line-height:1.7;">
                                If you would like to know more about Source Med Pharma in the meantime, we would be very happy to hear from you.
                            </p>

                            <p style="margin:0;font-size:16px;line-height:1.7;">
                                Simply reply to this email or contact us directly.<br>
                                <strong>Email:</strong>
                                <a href="mailto:support@sourcemdpharma.co.uk" style="color:#006d7c;text-decoration:underline;">
                                    support@sourcemdpharma.co.uk
                                </a>
                            </p>
                        </div>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.7;">
                            There is nothing further you need to do at this stage.
                        </p>

                        <p style="margin:0 0 26px;font-size:16px;line-height:1.7;">
                            Thank you again for your interest in <strong>Source Med Pharma</strong>. We look forward to speaking with you soon.
                        </p>

                        <p style="margin:0;font-size:16px;line-height:1.7;">
                            <strong>
                                Kind regards,<br>
                                The Source Med Pharma Team
                            </strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:30px 30px 24px;border-bottom:1px solid #dce8ea;">
                            <img
                                src="<?php echo esc_url($logo_url); ?>"
                                alt="Source Med Pharma"
                                width="360"
                                style="display:block;width:100%;max-width:360px;height:auto;border:0;"
                            >
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>
