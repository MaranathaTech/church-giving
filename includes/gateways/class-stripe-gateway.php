<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Stripe_Gateway implements Maranatha_Giving_Gateway_Interface {

    public function get_id(): string {
        return 'stripe';
    }

    public function is_available(): bool {
        return Maranatha_Giving::get_option( 'stripe_enabled' ) === '1'
            && ! empty( $this->get_secret_key() )
            && class_exists( '\Stripe\Stripe' );
    }

    public function get_publishable_key(): string {
        $mode = Maranatha_Giving::get_option( 'stripe_mode', 'test' );
        return Maranatha_Giving::get_option( "stripe_{$mode}_publishable", '' );
    }

    private function get_secret_key(): string {
        $mode = Maranatha_Giving::get_option( 'stripe_mode', 'test' );
        return Maranatha_Giving::get_option( "stripe_{$mode}_secret", '' );
    }

    private function init_stripe(): void {
        \Stripe\Stripe::setApiKey( $this->get_secret_key() );
        \Stripe\Stripe::setApiVersion( '2024-06-20' );
        \Stripe\Stripe::setAppInfo(
            'Maranatha Giving',
            MARANATHA_GIVING_VERSION,
            'https://github.com/temple-church/maranatha-giving'
        );
    }

    /**
     * Get or create a Stripe Customer for the donor.
     */
    public function get_or_create_customer( object $donor ): string {
        $this->init_stripe();

        if ( ! empty( $donor->stripe_customer_id ) ) {
            try {
                $customer = \Stripe\Customer::retrieve( $donor->stripe_customer_id );
                if ( ! $customer->isDeleted() ) {
                    return $customer->id;
                }
            } catch ( \Stripe\Exception\ApiErrorException $e ) {
                // Customer not found, create a new one.
            }
        }

        $customer = \Stripe\Customer::create( array(
            'email'    => $donor->email,
            'name'     => trim( $donor->first_name . ' ' . $donor->last_name ),
            'metadata' => array(
                'donor_id' => $donor->id,
                'source'   => 'maranatha-giving',
            ),
        ) );

        $donors_db = new Maranatha_Giving_Donors_DB();
        $donors_db->update( $donor->id, array( 'stripe_customer_id' => $customer->id ) );

        return $customer->id;
    }

    public function create_payment( array $data ): array {
        $this->init_stripe();

        $donor       = $data['donor'];
        $customer_id = $this->get_or_create_customer( $donor );

        $amount_cents = (int) round( (float) $data['amount'] * 100 );

        $intent_data = array(
            'amount'               => $amount_cents,
            'currency'             => strtolower( $data['currency'] ?? 'usd' ),
            'customer'             => $customer_id,
            'automatic_payment_methods' => array( 'enabled' => true ),
            'metadata'             => array(
                'donation_id' => $data['donation_id'],
                'donor_id'    => $donor->id,
                'fund_id'     => $data['fund_id'] ?? '',
                'form_id'     => $data['form_id'] ?? 'default',
                'source'      => 'maranatha-giving',
            ),
        );

        $intent = \Stripe\PaymentIntent::create( $intent_data );

        // Store the PaymentIntent ID on the donation row.
        $donations_db = new Maranatha_Giving_Donations_DB();
        $donations_db->update( $data['donation_id'], array(
            'gateway_transaction_id' => $intent->id,
            'gateway_customer_id'    => $customer_id,
            'status'                 => 'processing',
        ) );

        return array(
            'client_secret' => $intent->client_secret,
            'customer_id'   => $customer_id,
        );
    }

    public function create_subscription( array $data ): array {
        $this->init_stripe();

        $donor       = $data['donor'];
        $customer_id = $this->get_or_create_customer( $donor );

        $amount_cents = (int) round( (float) $data['amount'] * 100 );

        $interval_map = array(
            'weekly'    => array( 'interval' => 'week', 'count' => 1 ),
            'biweekly'  => array( 'interval' => 'week', 'count' => 2 ),
            'monthly'   => array( 'interval' => 'month', 'count' => 1 ),
            'quarterly' => array( 'interval' => 'month', 'count' => 3 ),
            'annually'  => array( 'interval' => 'year', 'count' => 1 ),
        );

        $frequency = $data['frequency'] ?? 'monthly';
        $interval  = $interval_map[ $frequency ] ?? $interval_map['monthly'];

        // Create or retrieve the product.
        $product_id = get_option( 'maranatha_giving_stripe_product_id', '' );
        if ( empty( $product_id ) ) {
            $product    = \Stripe\Product::create( array(
                'name'     => Maranatha_Giving::get_option( 'church_name', 'Church' ) . ' Donation',
                'metadata' => array( 'source' => 'maranatha-giving' ),
            ) );
            $product_id = $product->id;
            update_option( 'maranatha_giving_stripe_product_id', $product_id );
        }

        // Create a price for this specific amount/frequency.
        $price = \Stripe\Price::create( array(
            'unit_amount' => $amount_cents,
            'currency'    => strtolower( $data['currency'] ?? 'usd' ),
            'recurring'   => array(
                'interval'       => $interval['interval'],
                'interval_count' => $interval['count'],
            ),
            'product'     => $product_id,
        ) );

        $subscription = \Stripe\Subscription::create( array(
            'customer'               => $customer_id,
            'items'                  => array( array( 'price' => $price->id ) ),
            'payment_behavior'       => 'default_incomplete',
            'expand'                 => array( 'latest_invoice.payment_intent' ),
            'metadata'         => array(
                'donor_id'        => $donor->id,
                'fund_id'         => $data['fund_id'] ?? '',
                'form_id'         => $data['form_id'] ?? 'default',
                'frequency'       => $frequency,
                'subscription_id' => $data['subscription_id'] ?? '',
                'source'          => 'maranatha-giving',
            ),
        ) );

        // Update local subscription record with Stripe IDs.
        if ( ! empty( $data['subscription_id'] ) ) {
            $subs_db = new Maranatha_Giving_Subscriptions_DB();
            $subs_db->update( $data['subscription_id'], array(
                'gateway_subscription_id' => $subscription->id,
                'gateway_customer_id'     => $customer_id,
            ) );
        }

        $pi            = $subscription->latest_invoice->payment_intent;
        $client_secret = $pi->client_secret;

        // Update the first donation row with the PaymentIntent ID so invoice.paid webhook can find it.
        if ( ! empty( $data['donation_id'] ) ) {
            $donations_db = new Maranatha_Giving_Donations_DB();
            $donations_db->update( $data['donation_id'], array(
                'gateway_transaction_id' => $pi->id,
                'gateway_customer_id'    => $customer_id,
                'status'                 => 'processing',
            ) );
        }

        return array(
            'client_secret'   => $client_secret,
            'subscription_id' => $subscription->id,
            'customer_id'     => $customer_id,
        );
    }

    public function verify_webhook( string $payload, string $signature ): bool {
        $secret = Maranatha_Giving::get_option( 'stripe_webhook_secret', '' );
        if ( empty( $secret ) ) {
            return false;
        }

        try {
            \Stripe\Webhook::constructEvent( $payload, $signature, $secret );
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }
}
