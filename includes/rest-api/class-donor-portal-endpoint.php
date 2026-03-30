<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Donor_Portal_Endpoint {

    public function register_routes() {
        register_rest_route( 'maranatha-giving/v1', '/donor/history', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_history' ),
            'permission_callback' => array( $this, 'check_donor_auth' ),
            'args'                => array(
                'page' => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
            ),
        ) );

        register_rest_route( 'maranatha-giving/v1', '/donor/subscriptions', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_subscriptions' ),
            'permission_callback' => array( $this, 'check_donor_auth' ),
        ) );

        register_rest_route( 'maranatha-giving/v1', '/donor/cancel-subscription', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'cancel_subscription' ),
            'permission_callback' => array( $this, 'check_donor_auth' ),
            'args'                => array(
                'subscription_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
            ),
        ) );

        register_rest_route( 'maranatha-giving/v1', '/donor/profile', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_profile' ),
            'permission_callback' => array( $this, 'check_donor_auth' ),
        ) );

        register_rest_route( 'maranatha-giving/v1', '/donor/resend-receipt', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'resend_receipt' ),
            'permission_callback' => array( $this, 'check_donor_auth' ),
            'args'                => array(
                'donation_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
            ),
        ) );
    }

    public function check_donor_auth(): bool {
        $magic_link = new Maranatha_Giving_Magic_Link();
        return $magic_link->get_authenticated_donor() !== null;
    }

    private function get_donor(): ?object {
        $magic_link = new Maranatha_Giving_Magic_Link();
        return $magic_link->get_authenticated_donor();
    }

    public function get_history( WP_REST_Request $request ) {
        $donor        = $this->get_donor();
        $page         = $request->get_param( 'page' );
        $donations_db = new Maranatha_Giving_Donations_DB();
        $funds_db     = new Maranatha_Giving_Funds_DB();

        $donations = $donations_db->get_donations_by_donor( $donor->id, array(
            'page'     => $page,
            'per_page' => 15,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ) );

        $items = array();
        foreach ( $donations as $d ) {
            $fund = $d->fund_id ? $funds_db->get( $d->fund_id ) : null;
            $items[] = array(
                'id'             => $d->id,
                'amount'         => number_format( (float) $d->amount, 2 ),
                'date'           => wp_date( 'M j, Y', strtotime( $d->created_at ) ),
                'fund'           => $fund ? $fund->name : 'General Fund',
                'type'           => ucfirst( $d->donation_type ),
                'status'         => ucfirst( $d->status ),
                'transaction_id' => $d->gateway_transaction_id,
                'receipt_sent'   => (bool) $d->receipt_sent,
            );
        }

        $total = $donations_db->count( array( 'donor_id' => $donor->id ) );

        return new WP_REST_Response( array(
            'donations'  => $items,
            'total'      => $total,
            'page'       => $page,
            'total_pages' => ceil( $total / 15 ),
        ), 200 );
    }

    public function get_subscriptions() {
        $donor   = $this->get_donor();
        $subs_db = new Maranatha_Giving_Subscriptions_DB();
        $funds_db = new Maranatha_Giving_Funds_DB();

        $subscriptions = $subs_db->get_by_donor( $donor->id );

        $items = array();
        foreach ( $subscriptions as $s ) {
            $fund = $s->fund_id ? $funds_db->get( $s->fund_id ) : null;
            $items[] = array(
                'id'        => $s->id,
                'amount'    => number_format( (float) $s->amount, 2 ),
                'frequency' => ucfirst( $s->frequency ),
                'fund'      => $fund ? $fund->name : 'General Fund',
                'gateway'   => ucfirst( $s->gateway ),
                'status'    => ucfirst( $s->status ),
                'started'   => wp_date( 'M j, Y', strtotime( $s->created_at ) ),
            );
        }

        return new WP_REST_Response( array( 'subscriptions' => $items ), 200 );
    }

    public function cancel_subscription( WP_REST_Request $request ) {
        $donor           = $this->get_donor();
        $subscription_id = $request->get_param( 'subscription_id' );
        $subs_db         = new Maranatha_Giving_Subscriptions_DB();

        $subscription = $subs_db->get( $subscription_id );
        if ( ! $subscription || (int) $subscription->donor_id !== (int) $donor->id ) {
            return new WP_REST_Response( array( 'error' => 'Subscription not found.' ), 404 );
        }

        if ( $subscription->status === 'cancelled' ) {
            return new WP_REST_Response( array( 'error' => 'Already cancelled.' ), 400 );
        }

        // Cancel at the gateway.
        try {
            if ( $subscription->gateway === 'stripe' && $subscription->gateway_subscription_id ) {
                $stripe = new Maranatha_Giving_Stripe_Gateway();
                if ( $stripe->is_available() && class_exists( '\Stripe\Stripe' ) ) {
                    $mode = Maranatha_Giving::get_option( 'stripe_mode', 'test' );
                    $secret = Maranatha_Giving::get_option( "stripe_{$mode}_secret", '' );
                    \Stripe\Stripe::setApiKey( $secret );
                    \Stripe\Subscription::update( $subscription->gateway_subscription_id, array(
                        'cancel_at_period_end' => true,
                    ) );
                }
            }
        } catch ( \Exception $e ) {
            error_log( 'Maranatha Giving cancel subscription error: ' . $e->getMessage() );
        }

        $subs_db->update( $subscription_id, array(
            'status'       => 'cancelled',
            'cancelled_at' => current_time( 'mysql', true ),
        ) );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function update_profile( WP_REST_Request $request ) {
        $donor     = $this->get_donor();
        $donors_db = new Maranatha_Giving_Donors_DB();

        $update = array( 'updated_at' => current_time( 'mysql', true ) );

        $fields = array( 'first_name', 'last_name', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'zip' );
        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $update[ $field ] = sanitize_text_field( $value );
            }
        }

        $donors_db->update( $donor->id, $update );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function resend_receipt( WP_REST_Request $request ) {
        $donor        = $this->get_donor();
        $donation_id  = $request->get_param( 'donation_id' );
        $donations_db = new Maranatha_Giving_Donations_DB();

        $donation = $donations_db->get( $donation_id );
        if ( ! $donation || (int) $donation->donor_id !== (int) $donor->id ) {
            return new WP_REST_Response( array( 'error' => 'Donation not found.' ), 404 );
        }

        // Reset receipt_sent so it sends again.
        $donations_db->update( $donation_id, array( 'receipt_sent' => 0 ) );

        $receipt = new Maranatha_Giving_Email_Receipt();
        $receipt->send( $donation_id );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }
}
