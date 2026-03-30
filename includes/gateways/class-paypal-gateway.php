<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_PayPal_Gateway implements Maranatha_Giving_Gateway_Interface {

    public function get_id(): string {
        return 'paypal';
    }

    public function is_available(): bool {
        return Maranatha_Giving::get_option( 'paypal_enabled' ) === '1'
            && ! empty( $this->get_client_id() )
            && ! empty( $this->get_secret() );
    }

    public function is_venmo_enabled(): bool {
        return $this->is_available() && Maranatha_Giving::get_option( 'venmo_enabled' ) === '1';
    }

    public function get_client_id(): string {
        return Maranatha_Giving::get_option( 'paypal_client_id', '' );
    }

    private function get_secret(): string {
        return Maranatha_Giving::get_option( 'paypal_secret', '' );
    }

    private function get_base_url(): string {
        $mode = Maranatha_Giving::get_option( 'paypal_mode', 'sandbox' );
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function get_access_token(): string {
        $transient_key = 'maranatha_giving_paypal_token';
        $cached        = get_transient( $transient_key );
        if ( $cached ) {
            return $cached;
        }

        $response = wp_remote_post( $this->get_base_url() . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->get_client_id() . ':' . $this->get_secret() ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => 'grant_type=client_credentials',
        ) );

        if ( is_wp_error( $response ) ) {
            throw new \Exception( 'PayPal authentication failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) {
            throw new \Exception( 'PayPal authentication failed: no access token' );
        }

        $expires = ( $body['expires_in'] ?? 3600 ) - 60;
        set_transient( $transient_key, $body['access_token'], max( 60, $expires ) );

        return $body['access_token'];
    }

    private function api_request( string $method, string $endpoint, array $body = array() ): array {
        $token = $this->get_access_token();
        $url   = $this->get_base_url() . $endpoint;

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization'                 => 'Bearer ' . $token,
                'Content-Type'                  => 'application/json',
                'PayPal-Request-Id'             => wp_generate_uuid4(),
                'Prefer'                        => 'return=representation',
            ),
            'timeout' => 30,
        );

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new \Exception( 'PayPal API error: ' . $response->get_error_message() );
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();

        if ( $code >= 400 ) {
            $message = $response_body['message'] ?? ( $response_body['details'][0]['description'] ?? 'Unknown error' );
            throw new \Exception( 'PayPal API error (' . $code . '): ' . $message );
        }

        return $response_body;
    }

    /**
     * Test connection by requesting an access token. Throws on failure.
     */
    public function test_connection(): bool {
        // Clear cached token to force a fresh request.
        delete_transient( 'maranatha_giving_paypal_token' );
        $this->get_access_token();
        return true;
    }

    public function create_payment( array $data ): array {
        $amount   = number_format( (float) $data['amount'], 2, '.', '' );
        $currency = strtoupper( $data['currency'] ?? 'USD' );

        $order = $this->api_request( 'POST', '/v2/checkout/orders', array(
            'intent'         => 'CAPTURE',
            'purchase_units' => array( array(
                'amount'      => array(
                    'currency_code' => $currency,
                    'value'         => $amount,
                ),
                'description' => Maranatha_Giving::get_option( 'church_name', 'Church' ) . ' Donation',
                'custom_id'   => (string) ( $data['donation_id'] ?? '' ),
            ) ),
            'payment_source' => array(
                'paypal' => array(
                    'experience_context' => array(
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'user_action'               => 'PAY_NOW',
                        'return_url'                => home_url(),
                        'cancel_url'                => home_url(),
                    ),
                ),
            ),
        ) );

        return array(
            'order_id' => $order['id'] ?? '',
        );
    }

    public function capture_order( string $order_id ): array {
        return $this->api_request( 'POST', '/v2/checkout/orders/' . $order_id . '/capture' );
    }

    /**
     * Create a PayPal product + plan for recurring, return plan_id.
     */
    public function create_subscription_plan( array $data ): string {
        $amount   = number_format( (float) $data['amount'], 2, '.', '' );
        $currency = strtoupper( $data['currency'] ?? 'USD' );

        // Get or create product.
        $product_id = get_option( 'maranatha_giving_paypal_product_id', '' );
        if ( empty( $product_id ) ) {
            $product    = $this->api_request( 'POST', '/v1/catalogs/products', array(
                'name'        => Maranatha_Giving::get_option( 'church_name', 'Church' ) . ' Donation',
                'type'        => 'SERVICE',
                'category'    => 'CHARITY',
                'description' => 'Recurring donation',
            ) );
            $product_id = $product['id'] ?? '';
            update_option( 'maranatha_giving_paypal_product_id', $product_id );
        }

        $interval_map = array(
            'weekly'    => array( 'interval_unit' => 'WEEK', 'interval_count' => 1 ),
            'biweekly'  => array( 'interval_unit' => 'WEEK', 'interval_count' => 2 ),
            'monthly'   => array( 'interval_unit' => 'MONTH', 'interval_count' => 1 ),
            'quarterly' => array( 'interval_unit' => 'MONTH', 'interval_count' => 3 ),
            'annually'  => array( 'interval_unit' => 'YEAR', 'interval_count' => 1 ),
        );

        $frequency = $data['frequency'] ?? 'monthly';
        $interval  = $interval_map[ $frequency ] ?? $interval_map['monthly'];

        $plan = $this->api_request( 'POST', '/v1/billing/plans', array(
            'product_id'          => $product_id,
            'name'                => ucfirst( $frequency ) . ' Donation — $' . $amount,
            'billing_cycles'      => array( array(
                'frequency'      => array(
                    'interval_unit'  => $interval['interval_unit'],
                    'interval_count' => $interval['interval_count'],
                ),
                'tenure_type'    => 'REGULAR',
                'sequence'       => 1,
                'total_cycles'   => 0,
                'pricing_scheme' => array(
                    'fixed_price' => array(
                        'value'         => $amount,
                        'currency_code' => $currency,
                    ),
                ),
            ) ),
            'payment_preferences' => array(
                'auto_bill_outstanding'     => true,
                'payment_failure_threshold' => 3,
            ),
        ) );

        return $plan['id'] ?? '';
    }

    public function create_subscription( array $data ): array {
        $plan_id = $this->create_subscription_plan( $data );

        return array(
            'plan_id' => $plan_id,
        );
    }

    public function verify_webhook( string $payload, string $signature ): bool {
        $webhook_id = Maranatha_Giving::get_option( 'paypal_webhook_id', '' );
        if ( empty( $webhook_id ) ) {
            return false;
        }

        // Parse the headers from the signature (PayPal sends multiple headers).
        // For simplicity, verify via PayPal API.
        $headers = array();
        foreach ( $_SERVER as $key => $value ) {
            if ( strpos( $key, 'HTTP_PAYPAL_' ) === 0 ) {
                $header_name             = str_replace( 'HTTP_PAYPAL_', 'PAYPAL-', $key );
                $header_name             = str_replace( '_', '-', $header_name );
                $headers[ $header_name ] = $value;
            }
        }

        try {
            $result = $this->api_request( 'POST', '/v1/notifications/verify-webhook-signature', array(
                'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
                'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
                'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                'webhook_id'        => $webhook_id,
                'webhook_event'     => json_decode( $payload, true ),
            ) );

            return ( $result['verification_status'] ?? '' ) === 'SUCCESS';
        } catch ( \Exception $e ) {
            error_log( 'Maranatha Giving PayPal webhook verification failed: ' . $e->getMessage() );
            return false;
        }
    }
}
