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

    <!-- Header -->
    <tr>
        <td style="background-color:#2c3e50;padding:30px;text-align:center;">
            <?php if ( ! empty( $tags['church_logo'] ) ) : ?>
                <div style="margin-bottom:15px;">{church_logo}</div>
            <?php endif; ?>
            <h1 style="color:#ffffff;margin:0;font-size:24px;">Donation Receipt</h1>
        </td>
    </tr>

    <!-- Greeting -->
    <tr>
        <td style="padding:30px 30px 15px;">
            <p style="margin:0;font-size:16px;color:#333;">
                Dear {donor_first_name},
            </p>
            <p style="margin:15px 0 0;font-size:16px;color:#333;">
                Thank you for your generous gift to {church_name}. Your contribution makes a difference in our community.
            </p>
        </td>
    </tr>

    <!-- Donation Details -->
    <tr>
        <td style="padding:15px 30px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa;border-radius:6px;padding:20px;">
                <tr>
                    <td style="padding:8px 20px;font-size:14px;color:#666;border-bottom:1px solid #e9ecef;">Amount</td>
                    <td style="padding:8px 20px;font-size:14px;color:#333;text-align:right;border-bottom:1px solid #e9ecef;font-weight:bold;">{donation_amount}</td>
                </tr>
                <tr>
                    <td style="padding:8px 20px;font-size:14px;color:#666;border-bottom:1px solid #e9ecef;">Date</td>
                    <td style="padding:8px 20px;font-size:14px;color:#333;text-align:right;border-bottom:1px solid #e9ecef;">{donation_date}</td>
                </tr>
                <tr>
                    <td style="padding:8px 20px;font-size:14px;color:#666;border-bottom:1px solid #e9ecef;">Fund</td>
                    <td style="padding:8px 20px;font-size:14px;color:#333;text-align:right;border-bottom:1px solid #e9ecef;">{fund_name}</td>
                </tr>
                <tr>
                    <td style="padding:8px 20px;font-size:14px;color:#666;border-bottom:1px solid #e9ecef;">Type</td>
                    <td style="padding:8px 20px;font-size:14px;color:#333;text-align:right;border-bottom:1px solid #e9ecef;">{donation_type}</td>
                </tr>
                <tr>
                    <td style="padding:8px 20px;font-size:14px;color:#666;">Transaction ID</td>
                    <td style="padding:8px 20px;font-size:14px;color:#333;text-align:right;font-family:monospace;">{transaction_id}</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Year-to-Date -->
    <tr>
        <td style="padding:15px 30px;">
            <p style="margin:0;font-size:14px;color:#666;">
                Your year-to-date giving total: <strong style="color:#333;">{year_to_date_total}</strong>
            </p>
        </td>
    </tr>

    <!-- Tax Statement -->
    <?php if ( ! empty( $tags['tax_statement'] ) ) : ?>
    <tr>
        <td style="padding:15px 30px;">
            <div style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:15px;">
                <p style="margin:0;font-size:13px;color:#856404;">
                    {tax_statement}
                </p>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Church Info -->
    <tr>
        <td style="padding:15px 30px;">
            <p style="margin:0;font-size:13px;color:#999;">
                {church_name}<?php if ( ! empty( $tags['church_ein'] ) ) : ?> &middot; EIN: {church_ein}<?php endif; ?>
            </p>
            <?php if ( ! empty( $tags['church_address'] ) ) : ?>
            <p style="margin:5px 0 0;font-size:13px;color:#999;">{church_address}</p>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Donor Portal Link -->
    <?php if ( ! empty( $tags['donor_portal_url'] ) ) : ?>
    <tr>
        <td style="padding:15px 30px;" align="center">
            <a href="{donor_portal_url}" style="display:inline-block;background-color:#2c3e50;color:#ffffff;text-decoration:none;padding:12px 30px;border-radius:6px;font-size:14px;">View Giving History</a>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Footer -->
    <tr>
        <td style="padding:20px 30px;border-top:1px solid #e9ecef;">
            <p style="margin:0;font-size:12px;color:#999;text-align:center;">
                This is an automated receipt. Please keep it for your records.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
