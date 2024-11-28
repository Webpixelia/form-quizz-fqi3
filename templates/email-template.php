<?php
/**
 * Template for email template
 *
 * @var array $badges_data Array of badge data
 * @var array $static_strings Array of pre-escaped static strings
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width">
    <title><?php echo __( 'Your Free Quiz Attempts Have Been Reset', 'form-quizz-fqi3' ); ?></title>
    <style type="text/css">
        @media only screen and (max-width: 599px) {
            table.body .container { width: 95% !important; }
            .header { padding: 15px 15px 12px 15px !important; }
            .header img { width: 200px !important; height: auto !important; }
            .content, .aside { padding: 30px 40px 20px 40px !important; }
        }
    </style>
</head>
<body style="background-color: #ffffff; color: #444; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; text-align: center;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" class="body">
    <tr>
        <td align="center" valign="top">
            <table border="0" cellpadding="0" cellspacing="0" class="container" width="600px" style="margin: 0 auto; background-color: #f1f1f1;">
                <!-- Header -->
                <tr>
                    <td align="center" valign="middle" class="header" style="padding: 30px 30px 10px;">
                        <img src="<?php echo esc_url($logo_url); ?>" width="150" alt="Logo">
                    </td>
                </tr>
                <!-- Message Content -->
                <tr>
                    <td align="left" valign="top" class="content" style="padding: 10px 75px;">
                        <p style="font-size: 16px;"><?php echo $message_content; ?></p>
                        <?php if (isset($cta_button)) : ?>
                            <div style="padding-top: 20px;"><?php echo $cta_button; ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Signature -->
                <tr>
                    <td align="left" valign="top" class="content" style="padding: 10px 75px 60px;">
                        <p><?php echo __( 'Thank you', 'form-quizz-fqi3' ); ?>, <br><?php echo esc_html($site_name); ?></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>