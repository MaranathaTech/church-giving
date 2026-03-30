<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">

    <tr>
        <td style="background-color:#2c3e50;padding:30px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:24px;">Your Giving Portal</h1>
        </td>
    </tr>

    <tr>
        <td style="padding:30px;">
            <p style="margin:0 0 15px;font-size:16px;color:#333;">
                Hi <?php echo esc_html( $donor->first_name ?: 'there' ); ?>,
            </p>
            <p style="margin:0 0 25px;font-size:16px;color:#333;">
                Click the button below to access your giving portal at <?php echo esc_html( $church_name ); ?>.
            </p>
            <p style="text-align:center;margin:0 0 25px;">
                <a href="<?php echo esc_url( $magic_link ); ?>" style="display:inline-block;background-color:#27ae60;color:#ffffff;text-decoration:none;padding:14px 40px;border-radius:6px;font-size:16px;font-weight:bold;">Access My Portal</a>
            </p>
            <p style="margin:0 0 15px;font-size:14px;color:#666;">
                This link will expire in <?php echo esc_html( $expiration ); ?> minutes and can only be used once.
            </p>
            <p style="margin:0;font-size:13px;color:#999;">
                If you didn't request this link, you can safely ignore this email.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:15px 30px;border-top:1px solid #e9ecef;">
            <p style="margin:0;font-size:12px;color:#999;text-align:center;">
                <?php echo esc_html( $church_name ); ?>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
