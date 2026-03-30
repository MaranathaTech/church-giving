<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Email_Receipt {

    public function send( int $donation_id ): void {
        $donations_db = new Maranatha_Giving_Donations_DB();
        $donation     = $donations_db->get( $donation_id );

        if ( ! $donation || empty( $donation->donor_email ) ) {
            return;
        }

        // Don't re-send.
        if ( $donation->receipt_sent ) {
            return;
        }

        $tags    = Maranatha_Giving_Email_Templates::get_donation_tags( $donation_id );
        $subject = Maranatha_Giving::get_option( 'receipt_subject', 'Thank you for your gift of {donation_amount}' );
        $subject = Maranatha_Giving_Email_Templates::replace_tags( $subject, $tags );

        // Use custom body if set, otherwise use the HTML template.
        $custom_body = Maranatha_Giving::get_option( 'receipt_body', '' );
        if ( ! empty( trim( $custom_body ) ) ) {
            $body = Maranatha_Giving_Email_Templates::replace_tags( wpautop( $custom_body ), $tags );
        } else {
            $body = Maranatha_Giving_Email_Templates::render_receipt( $donation_id );
        }

        $from_name    = Maranatha_Giving::get_option( 'email_from_name', get_bloginfo( 'name' ) );
        $from_address = Maranatha_Giving::get_option( 'email_from_address', get_option( 'admin_email' ) );
        $bcc_email    = Maranatha_Giving::get_option( 'admin_bcc_email', '' );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_address}>",
        );

        $reply_to = Maranatha_Giving::get_option( 'email_reply_to', '' );
        if ( ! empty( $reply_to ) && is_email( $reply_to ) ) {
            $headers[] = "Reply-To: {$reply_to}";
        }

        if ( ! empty( $bcc_email ) && is_email( $bcc_email ) ) {
            $headers[] = "Bcc: {$bcc_email}";
        }

        $subject = apply_filters( 'maranatha_giving_receipt_subject', $subject, $donation_id );
        $body    = apply_filters( 'maranatha_giving_receipt_body', $body, $donation_id );
        $headers = apply_filters( 'maranatha_giving_receipt_headers', $headers, $donation_id );

        $sent = wp_mail( $donation->donor_email, $subject, $body, $headers );

        if ( $sent ) {
            $donations_db->update( $donation_id, array( 'receipt_sent' => 1 ) );
        }
    }
}
