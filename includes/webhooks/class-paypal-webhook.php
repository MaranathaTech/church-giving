<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_PayPal_Webhook {

    private $donations_db;
    private $donors_db;
    private $subscriptions_db;

    public function __construct() {
        $this->donations_db     = new Maranatha_Giving_Donations_DB();
        $this->donors_db        = new Maranatha_Giving_Donors_DB();
        $this->subscriptions_db = new Maranatha_Giving_Subscriptions_DB();
    }

    public function process( object $event ): void {
        $type     = $event->event_type ?? '';
        $resource = $event->resource ?? null;

        if ( ! $resource ) {
            return;
        }

        switch ( $type ) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handle_capture_completed( $resource );
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->handle_capture_denied_or_refunded( $resource, $type );
                break;

            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $this->handle_subscription_activated( $resource );
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                $this->handle_subscription_cancelled( $resource );
                break;

            case 'PAYMENT.SALE.COMPLETED':
                $this->handle_sale_completed( $resource );
                break;
        }
    }

    private function handle_capture_completed( object $resource ): void {
        $capture_id = $resource->id ?? '';
        if ( empty( $capture_id ) ) {
            return;
        }

        // Find by custom_id (donation_id set during order creation).
        $custom_id = $resource->custom_id ?? '';
        if ( $custom_id ) {
            $donation = $this->donations_db->get( (int) $custom_id );
        } else {
            $donation = $this->donations_db->get_by_transaction_id( $capture_id );
        }

        if ( ! $donation || $donation->status === 'completed' ) {
            return;
        }

        $this->donations_db->update( $donation->id, array(
            'gateway_transaction_id' => $capture_id,
        ) );
        $this->donations_db->mark_completed( $donation->id );

        if ( $donation->donor_id ) {
            $this->donors_db->increment_totals( $donation->donor_id, $donation->amount );
        }
    }

    private function handle_capture_denied_or_refunded( object $resource, string $type ): void {
        $capture_id = $resource->id ?? '';
        $donation   = $this->donations_db->get_by_transaction_id( $capture_id );

        if ( ! $donation ) {
            return;
        }

        if ( $type === 'PAYMENT.CAPTURE.REFUNDED' ) {
            $this->donations_db->mark_refunded( $donation->id );
        } else {
            $this->donations_db->mark_failed( $donation->id );
        }
    }

    private function handle_subscription_activated( object $resource ): void {
        $sub_id = $resource->id ?? '';
        if ( empty( $sub_id ) ) {
            return;
        }

        $subscription = $this->subscriptions_db->get_by_gateway_id( $sub_id );
        if ( $subscription ) {
            $this->subscriptions_db->update( $subscription->id, array( 'status' => 'active' ) );
        }
    }

    private function handle_subscription_cancelled( object $resource ): void {
        $sub_id = $resource->id ?? '';
        if ( empty( $sub_id ) ) {
            return;
        }

        $subscription = $this->subscriptions_db->get_by_gateway_id( $sub_id );
        if ( $subscription ) {
            $this->subscriptions_db->update( $subscription->id, array(
                'status'       => 'cancelled',
                'cancelled_at' => current_time( 'mysql', true ),
            ) );
        }
    }

    private function handle_sale_completed( object $resource ): void {
        // Recurring payment — find subscription by billing_agreement_id.
        $billing_agreement_id = $resource->billing_agreement_id ?? '';
        if ( empty( $billing_agreement_id ) ) {
            return;
        }

        $subscription = $this->subscriptions_db->get_by_gateway_id( $billing_agreement_id );
        if ( ! $subscription ) {
            return;
        }

        $sale_id = $resource->id ?? '';

        // Don't double-record.
        if ( $sale_id && $this->donations_db->get_by_transaction_id( $sale_id ) ) {
            return;
        }

        $amount = isset( $resource->amount->total ) ? (float) $resource->amount->total : $subscription->amount;
        $donor  = $this->donors_db->get( $subscription->donor_id );

        // Determine gateway — check payment source for Venmo.
        $gateway = 'paypal';

        $donation_id = $this->donations_db->create_donation( array(
            'donor_id'               => $subscription->donor_id,
            'subscription_id'        => $subscription->id,
            'fund_id'                => $subscription->fund_id,
            'amount'                 => $amount,
            'currency'               => strtoupper( $resource->amount->currency ?? 'USD' ),
            'donation_type'          => 'recurring',
            'status'                 => 'completed',
            'gateway'                => $gateway,
            'gateway_transaction_id' => $sale_id,
            'gateway_customer_id'    => $subscription->gateway_customer_id,
            'donor_email'            => $donor ? $donor->email : '',
            'donor_name'             => $donor ? trim( $donor->first_name . ' ' . $donor->last_name ) : '',
            'form_id'                => $subscription->form_id,
        ) );

        if ( $donation_id ) {
            $this->donations_db->update( $donation_id, array(
                'completed_at' => current_time( 'mysql', true ),
            ) );
            do_action( 'maranatha_giving_donation_completed', $donation_id );

            if ( $subscription->donor_id ) {
                $this->donors_db->increment_totals( $subscription->donor_id, $amount );
            }
        }

        $this->subscriptions_db->record_payment( $subscription->id );
    }
}
