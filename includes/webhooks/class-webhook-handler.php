<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Webhook_Handler {

    public function register_routes() {
        register_rest_route( 'maranatha-giving/v1', '/webhook/stripe', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_stripe' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'maranatha-giving/v1', '/webhook/paypal', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_paypal' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle_stripe( WP_REST_Request $request ) {
        $payload   = $request->get_body();
        $signature = $request->get_header( 'stripe-signature' );

        if ( empty( $signature ) ) {
            return new WP_REST_Response( array( 'error' => 'Missing signature' ), 400 );
        }

        $gateway = new Maranatha_Giving_Stripe_Gateway();
        if ( ! $gateway->verify_webhook( $payload, $signature ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 403 );
        }

        try {
            $event = json_decode( $payload, false );
            if ( ! $event || empty( $event->type ) ) {
                return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
            }

            $handler = new Maranatha_Giving_Stripe_Webhook();
            $handler->process( $event );

            return new WP_REST_Response( array( 'received' => true ), 200 );
        } catch ( \Exception $e ) {
            error_log( 'Maranatha Giving Stripe webhook error: ' . $e->getMessage() );
            return new WP_REST_Response( array( 'error' => 'Processing failed' ), 500 );
        }
    }

    public function handle_paypal( WP_REST_Request $request ) {
        $payload = $request->get_body();

        $gateway = new Maranatha_Giving_PayPal_Gateway();
        if ( ! $gateway->verify_webhook( $payload, '' ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 403 );
        }

        try {
            $event = json_decode( $payload, false );
            if ( ! $event || empty( $event->event_type ) ) {
                return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
            }

            $handler = new Maranatha_Giving_PayPal_Webhook();
            $handler->process( $event );

            return new WP_REST_Response( array( 'received' => true ), 200 );
        } catch ( \Exception $e ) {
            error_log( 'Maranatha Giving PayPal webhook error: ' . $e->getMessage() );
            return new WP_REST_Response( array( 'error' => 'Processing failed' ), 500 );
        }
    }
}
