<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_PayPal_Subscription_Endpoint {

    public function register_routes() {
        // Get a plan_id for the PayPal Buttons createSubscription callback.
        register_rest_route( 'maranatha-giving/v1', '/paypal/plan', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_create_plan' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'amount'    => array( 'required' => true ),
                'frequency' => array( 'required' => true ),
                'fund_id'   => array( 'default' => null ),
                'form_id'   => array( 'default' => 'default' ),
                'mg_nonce'  => array( 'required' => true ),
            ),
        ) );

        // Record a subscription after PayPal onApprove.
        register_rest_route( 'maranatha-giving/v1', '/paypal/subscription', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_subscription_approved' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'subscription_id' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'email'           => array( 'required' => true, 'sanitize_callback' => 'sanitize_email' ),
                'first_name'      => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'last_name'       => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'amount'          => array( 'required' => true ),
                'frequency'       => array( 'required' => true ),
                'fund_id'         => array( 'default' => null ),
                'form_id'         => array( 'default' => 'default' ),
                'gateway'         => array( 'default' => 'paypal' ),
                'mg_nonce'        => array( 'required' => true ),
            ),
        ) );
    }

    public function handle_create_plan( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'mg_nonce' );
        if ( ! wp_verify_nonce( $nonce, 'maranatha_giving_donate' ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid security token.' ), 403 );
        }

        $paypal = new Maranatha_Giving_PayPal_Gateway();
        if ( ! $paypal->is_available() ) {
            return new WP_REST_Response( array( 'error' => 'PayPal is not configured.' ), 400 );
        }

        try {
            $plan_id = $paypal->create_subscription_plan( array(
                'amount'    => (float) $request->get_param( 'amount' ),
                'currency'  => Maranatha_Giving::get_option( 'currency', 'USD' ),
                'frequency' => sanitize_text_field( $request->get_param( 'frequency' ) ),
            ) );

            return new WP_REST_Response( array( 'plan_id' => $plan_id ), 200 );
        } catch ( \Exception $e ) {
            error_log( 'Maranatha Giving PayPal plan error: ' . $e->getMessage() );
            return new WP_REST_Response( array( 'error' => 'Could not create subscription plan.' ), 500 );
        }
    }

    public function handle_subscription_approved( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'mg_nonce' );
        if ( ! wp_verify_nonce( $nonce, 'maranatha_giving_donate' ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid security token.' ), 403 );
        }

        $pp_subscription_id = $request->get_param( 'subscription_id' );
        $email              = $request->get_param( 'email' );
        $first_name         = $request->get_param( 'first_name' );
        $last_name          = $request->get_param( 'last_name' );
        $amount             = (float) $request->get_param( 'amount' );
        $frequency          = sanitize_text_field( $request->get_param( 'frequency' ) );
        $fund_id            = $request->get_param( 'fund_id' ) ? absint( $request->get_param( 'fund_id' ) ) : null;
        $form_id            = sanitize_text_field( $request->get_param( 'form_id' ) );
        $gateway            = sanitize_text_field( $request->get_param( 'gateway' ) );

        // Get or create donor.
        $donors_db = new Maranatha_Giving_Donors_DB();
        $donor     = $donors_db->get_or_create( array(
            'email'      => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
        ) );

        if ( ! $donor ) {
            return new WP_REST_Response( array( 'error' => 'Could not process donor.' ), 500 );
        }

        $currency = Maranatha_Giving::get_option( 'currency', 'USD' );

        // Create local subscription record.
        $subs_db         = new Maranatha_Giving_Subscriptions_DB();
        $subscription_id = $subs_db->create_subscription( array(
            'donor_id'                => $donor->id,
            'fund_id'                 => $fund_id,
            'amount'                  => $amount,
            'currency'                => $currency,
            'frequency'               => $frequency,
            'gateway'                 => $gateway,
            'gateway_subscription_id' => $pp_subscription_id,
            'form_id'                 => $form_id,
            'status'                  => 'active',
        ) );

        return new WP_REST_Response( array(
            'success'         => true,
            'subscription_id' => $subscription_id,
        ), 200 );
    }
}
