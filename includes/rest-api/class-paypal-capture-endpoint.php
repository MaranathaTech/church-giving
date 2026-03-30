<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_PayPal_Capture_Endpoint {

    public function register_routes() {
        register_rest_route( 'maranatha-giving/v1', '/paypal/capture', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_capture' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'order_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'donation_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
                'mg_nonce' => array(
                    'required' => true,
                ),
            ),
        ) );
    }

    public function handle_capture( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'mg_nonce' );
        if ( ! wp_verify_nonce( $nonce, 'maranatha_giving_donate' ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid security token.' ), 403 );
        }

        $order_id    = $request->get_param( 'order_id' );
        $donation_id = $request->get_param( 'donation_id' );

        $paypal      = new Maranatha_Giving_PayPal_Gateway();
        if ( ! $paypal->is_available() ) {
            return new WP_REST_Response( array( 'error' => 'PayPal is not configured.' ), 400 );
        }

        $donations_db = new Maranatha_Giving_Donations_DB();
        $donation     = $donations_db->get( $donation_id );
        if ( ! $donation ) {
            return new WP_REST_Response( array( 'error' => 'Donation not found.' ), 404 );
        }

        try {
            $result = $paypal->capture_order( $order_id );

            $status      = $result['status'] ?? '';
            $capture_id  = '';
            $payment_source = '';

            // Extract capture ID and payment source.
            $purchase_units = $result['purchase_units'] ?? array();
            if ( ! empty( $purchase_units[0]['payments']['captures'][0] ) ) {
                $capture    = $purchase_units[0]['payments']['captures'][0];
                $capture_id = $capture['id'] ?? '';
            }

            // Check for Venmo.
            if ( isset( $result['payment_source']['venmo'] ) ) {
                $payment_source = 'venmo';
            }

            $gateway = $payment_source === 'venmo' ? 'venmo' : 'paypal';

            $donations_db->update( $donation_id, array(
                'gateway'                => $gateway,
                'gateway_transaction_id' => $capture_id,
            ) );

            if ( $status === 'COMPLETED' ) {
                $donations_db->mark_completed( $donation_id );

                if ( $donation->donor_id ) {
                    $donors_db = new Maranatha_Giving_Donors_DB();
                    $donors_db->increment_totals( $donation->donor_id, $donation->amount );
                }

                return new WP_REST_Response( array(
                    'success'    => true,
                    'status'     => 'completed',
                    'gateway'    => $gateway,
                    'capture_id' => $capture_id,
                ), 200 );
            }

            return new WP_REST_Response( array(
                'success' => false,
                'status'  => strtolower( $status ),
                'error'   => 'Payment was not completed.',
            ), 400 );

        } catch ( \Exception $e ) {
            error_log( 'Maranatha Giving PayPal capture error: ' . $e->getMessage() );
            return new WP_REST_Response( array(
                'error' => 'Payment capture failed. Please try again.',
            ), 500 );
        }
    }
}
