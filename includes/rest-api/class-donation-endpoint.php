<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Donation_Endpoint {

    public function register_routes() {
        register_rest_route( 'maranatha-giving/v1', '/donate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_donate' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_donate_args(),
        ) );
    }

    private function get_donate_args(): array {
        return array(
            'amount' => array(
                'required'          => true,
                'validate_callback' => function ( $value ) {
                    return is_numeric( $value ) && (float) $value > 0;
                },
                'sanitize_callback' => function ( $value ) {
                    return round( (float) $value, 2 );
                },
            ),
            'email' => array(
                'required'          => true,
                'validate_callback' => function ( $value ) {
                    return is_email( $value );
                },
                'sanitize_callback' => 'sanitize_email',
            ),
            'first_name' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'last_name' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'gateway' => array(
                'required'          => true,
                'validate_callback' => function ( $value ) {
                    return in_array( $value, array( 'stripe', 'paypal', 'venmo' ), true );
                },
            ),
            'type' => array(
                'default'           => 'one-time',
                'validate_callback' => function ( $value ) {
                    return in_array( $value, array( 'one-time', 'recurring' ), true );
                },
            ),
            'frequency' => array(
                'default'           => 'monthly',
                'validate_callback' => function ( $value ) {
                    return in_array( $value, array( 'weekly', 'biweekly', 'monthly', 'quarterly', 'annually' ), true );
                },
            ),
            'fund_id' => array(
                'default'           => null,
                'sanitize_callback' => function ( $value ) {
                    return $value ? absint( $value ) : null;
                },
            ),
            'form_id' => array(
                'default'           => 'default',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'mg_nonce' => array(
                'required' => true,
            ),
            'bot_token' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    public function handle_donate( WP_REST_Request $request ) {
        // Verify nonce.
        $nonce = $request->get_param( 'mg_nonce' );
        if ( ! wp_verify_nonce( $nonce, 'maranatha_giving_donate' ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid security token. Please refresh and try again.' ), 403 );
        }

        // Bot protection verification.
        $bot_result = Maranatha_Giving::verify_bot_protection( $request->get_param( 'bot_token' ) ?? '' );
        if ( is_wp_error( $bot_result ) ) {
            return new WP_REST_Response( array( 'error' => $bot_result->get_error_message() ), 403 );
        }

        $amount     = $request->get_param( 'amount' );
        $email      = $request->get_param( 'email' );
        $first_name = $request->get_param( 'first_name' );
        $last_name  = $request->get_param( 'last_name' );
        $gateway    = $request->get_param( 'gateway' );
        $type       = $request->get_param( 'type' );
        $frequency  = $request->get_param( 'frequency' );
        $fund_id    = $request->get_param( 'fund_id' );
        $form_id    = $request->get_param( 'form_id' );

        // Validate minimum amount.
        $min_amount = (float) apply_filters( 'maranatha_giving_min_amount', (float) Maranatha_Giving::get_option( 'min_amount', 5 ), $form_id );
        if ( $amount < $min_amount ) {
            return new WP_REST_Response( array(
                'error' => sprintf( 'Minimum donation amount is $%s.', number_format( $min_amount, 2 ) ),
            ), 400 );
        }

        // Allow external validation.
        $validation_error = apply_filters( 'maranatha_giving_validate_donation', null, $request );
        if ( is_string( $validation_error ) ) {
            return new WP_REST_Response( array( 'error' => $validation_error ), 400 );
        }

        // Get or create donor.
        $donors_db = new Maranatha_Giving_Donors_DB();
        $donor     = $donors_db->get_or_create( array(
            'email'      => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
        ) );

        if ( ! $donor ) {
            return new WP_REST_Response( array( 'error' => 'Could not process donor information.' ), 500 );
        }

        $donor_name = trim( $first_name . ' ' . $last_name );
        $currency   = Maranatha_Giving::get_option( 'currency', 'USD' );

        if ( $gateway === 'stripe' ) {
            return $this->process_stripe( $donor, $amount, $currency, $type, $frequency, $fund_id, $form_id, $donor_name );
        }

        if ( $gateway === 'paypal' || $gateway === 'venmo' ) {
            return $this->process_paypal( $donor, $amount, $currency, $type, $fund_id, $form_id, $donor_name, $gateway );
        }

        return new WP_REST_Response( array( 'error' => 'Unsupported payment gateway.' ), 400 );
    }

    private function process_stripe( object $donor, float $amount, string $currency, string $type, string $frequency, ?int $fund_id, string $form_id, string $donor_name ): WP_REST_Response {
        $stripe = new Maranatha_Giving_Stripe_Gateway();
        if ( ! $stripe->is_available() ) {
            return new WP_REST_Response( array( 'error' => 'Stripe is not configured.' ), 400 );
        }

        $donations_db = new Maranatha_Giving_Donations_DB();

        try {
            if ( $type === 'recurring' ) {
                // Create local subscription first.
                $subs_db         = new Maranatha_Giving_Subscriptions_DB();
                $subscription_id = $subs_db->create_subscription( array(
                    'donor_id'  => $donor->id,
                    'fund_id'   => $fund_id,
                    'amount'    => $amount,
                    'currency'  => $currency,
                    'frequency' => $frequency,
                    'gateway'   => 'stripe',
                    'form_id'   => $form_id,
                ) );

                // Create a pending donation for the first payment.
                $donation_id = $donations_db->create_donation( array(
                    'donor_id'      => $donor->id,
                    'subscription_id' => $subscription_id,
                    'fund_id'       => $fund_id,
                    'amount'        => $amount,
                    'currency'      => $currency,
                    'donation_type' => 'recurring',
                    'gateway'       => 'stripe',
                    'donor_email'   => $donor->email,
                    'donor_name'    => $donor_name,
                    'form_id'       => $form_id,
                ) );

                $result = $stripe->create_subscription( array(
                    'donor'           => $donor,
                    'amount'          => $amount,
                    'currency'        => $currency,
                    'frequency'       => $frequency,
                    'fund_id'         => $fund_id,
                    'form_id'         => $form_id,
                    'donation_id'     => $donation_id,
                    'subscription_id' => $subscription_id,
                ) );

                return new WP_REST_Response( array(
                    'client_secret' => $result['client_secret'],
                    'donation_id'   => $donation_id,
                    'gateway'       => 'stripe',
                    'type'          => 'recurring',
                ), 200 );

            } else {
                // One-time donation.
                $donation_id = $donations_db->create_donation( array(
                    'donor_id'      => $donor->id,
                    'fund_id'       => $fund_id,
                    'amount'        => $amount,
                    'currency'      => $currency,
                    'donation_type' => 'one-time',
                    'gateway'       => 'stripe',
                    'donor_email'   => $donor->email,
                    'donor_name'    => $donor_name,
                    'form_id'       => $form_id,
                ) );

                $result = $stripe->create_payment( array(
                    'donor'       => $donor,
                    'amount'      => $amount,
                    'currency'    => $currency,
                    'donation_id' => $donation_id,
                    'fund_id'     => $fund_id,
                    'form_id'     => $form_id,
                ) );

                return new WP_REST_Response( array(
                    'client_secret' => $result['client_secret'],
                    'donation_id'   => $donation_id,
                    'gateway'       => 'stripe',
                    'type'          => 'one-time',
                ), 200 );
            }
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( 'Maranatha Giving Stripe error: ' . $e->getMessage() );
            return new WP_REST_Response( array(
                'error' => 'Payment processing error. Please try again.',
            ), 500 );
        }
    }

    private function process_paypal( object $donor, float $amount, string $currency, string $type, ?int $fund_id, string $form_id, string $donor_name, string $gateway ): WP_REST_Response {
        $paypal = new Maranatha_Giving_PayPal_Gateway();
        if ( ! $paypal->is_available() ) {
            return new WP_REST_Response( array( 'error' => 'PayPal is not configured.' ), 400 );
        }

        // For one-time PayPal/Venmo, create a pending donation and a PayPal order.
        // The JS will handle the PayPal Buttons flow, then call /paypal/capture.
        $donations_db = new Maranatha_Giving_Donations_DB();

        try {
            $donation_id = $donations_db->create_donation( array(
                'donor_id'      => $donor->id,
                'fund_id'       => $fund_id,
                'amount'        => $amount,
                'currency'      => $currency,
                'donation_type' => $type === 'recurring' ? 'recurring' : 'one-time',
                'gateway'       => $gateway,
                'donor_email'   => $donor->email,
                'donor_name'    => $donor_name,
                'form_id'       => $form_id,
            ) );

            $result = $paypal->create_payment( array(
                'amount'      => $amount,
                'currency'    => $currency,
                'donation_id' => $donation_id,
            ) );

            return new WP_REST_Response( array(
                'order_id'    => $result['order_id'],
                'donation_id' => $donation_id,
                'gateway'     => $gateway,
                'type'        => $type === 'recurring' ? 'recurring' : 'one-time',
            ), 200 );

        } catch ( \Exception $e ) {
            error_log( 'Maranatha Giving PayPal error: ' . $e->getMessage() );
            return new WP_REST_Response( array(
                'error' => 'Payment processing error. Please try again.',
            ), 500 );
        }
    }
}
