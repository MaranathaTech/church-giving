<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Stripe_Webhook {

    private $donations_db;
    private $donors_db;
    private $subscriptions_db;

    public function __construct() {
        $this->donations_db     = new Maranatha_Giving_Donations_DB();
        $this->donors_db        = new Maranatha_Giving_Donors_DB();
        $this->subscriptions_db = new Maranatha_Giving_Subscriptions_DB();
    }

    public function process( object $event ): void {
        $type = $event->type;
        $obj  = $event->data->object;

        switch ( $type ) {
            case 'payment_intent.succeeded':
                $this->handle_payment_succeeded( $obj );
                break;

            case 'payment_intent.payment_failed':
                $this->handle_payment_failed( $obj );
                break;

            case 'invoice.paid':
                $this->handle_invoice_paid( $obj );
                break;

            case 'invoice.payment_failed':
                $this->handle_invoice_failed( $obj );
                break;

            case 'customer.subscription.updated':
                $this->handle_subscription_updated( $obj );
                break;

            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted( $obj );
                break;

            case 'charge.refunded':
                $this->handle_charge_refunded( $obj );
                break;
        }
    }

    private function handle_payment_succeeded( object $intent ): void {
        // Skip subscription-related payment intents — handled via invoice.paid.
        if ( ! empty( $intent->invoice ) ) {
            return;
        }

        $donation = $this->donations_db->get_by_transaction_id( $intent->id );
        if ( ! $donation || $donation->status === 'completed' ) {
            return;
        }

        $this->donations_db->mark_completed( $donation->id );

        if ( $donation->donor_id ) {
            $this->donors_db->increment_totals( $donation->donor_id, $donation->amount );
        }
    }

    private function handle_payment_failed( object $intent ): void {
        if ( ! empty( $intent->invoice ) ) {
            return;
        }

        $donation = $this->donations_db->get_by_transaction_id( $intent->id );
        if ( $donation ) {
            $this->donations_db->mark_failed( $donation->id );
        }
    }

    private function handle_invoice_paid( object $invoice ): void {
        $sub_id = $invoice->subscription ?? '';
        if ( empty( $sub_id ) ) {
            return;
        }

        $subscription = $this->subscriptions_db->get_by_gateway_id( $sub_id );
        if ( ! $subscription ) {
            return;
        }

        // Check if we already recorded this invoice.
        $pi_id = $invoice->payment_intent ?? '';
        if ( $pi_id && $this->donations_db->get_by_transaction_id( $pi_id ) ) {
            // Already recorded — might be the first payment created at checkout.
            $existing = $this->donations_db->get_by_transaction_id( $pi_id );
            if ( $existing->status !== 'completed' ) {
                $this->donations_db->mark_completed( $existing->id );
                if ( $existing->donor_id ) {
                    $this->donors_db->increment_totals( $existing->donor_id, $existing->amount );
                }
            }
            $this->subscriptions_db->record_payment( $subscription->id );
            return;
        }

        // Create a new donation row for this recurring payment.
        $donor = $this->donors_db->get( $subscription->donor_id );
        $amount = ( $invoice->amount_paid ?? 0 ) / 100;

        $donation_id = $this->donations_db->create_donation( array(
            'donor_id'               => $subscription->donor_id,
            'subscription_id'        => $subscription->id,
            'fund_id'                => $subscription->fund_id,
            'amount'                 => $amount,
            'currency'               => strtoupper( $invoice->currency ?? 'usd' ),
            'donation_type'          => 'recurring',
            'status'                 => 'completed',
            'gateway'                => 'stripe',
            'gateway_transaction_id' => $pi_id,
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

    private function handle_invoice_failed( object $invoice ): void {
        $sub_id = $invoice->subscription ?? '';
        if ( empty( $sub_id ) ) {
            return;
        }

        $subscription = $this->subscriptions_db->get_by_gateway_id( $sub_id );
        if ( $subscription && $subscription->status !== 'failing' ) {
            $this->subscriptions_db->update( $subscription->id, array( 'status' => 'failing' ) );
        }
    }

    private function handle_subscription_updated( object $sub ): void {
        $subscription = $this->subscriptions_db->get_by_gateway_id( $sub->id );
        if ( ! $subscription ) {
            return;
        }

        $status_map = array(
            'active'            => 'active',
            'past_due'          => 'failing',
            'canceled'          => 'cancelled',
            'unpaid'            => 'failing',
            'incomplete'        => 'active',
            'incomplete_expired' => 'expired',
            'paused'            => 'paused',
        );

        $new_status = $status_map[ $sub->status ] ?? $subscription->status;
        $update     = array( 'status' => $new_status );

        if ( ! empty( $sub->current_period_end ) ) {
            $update['next_payment_date'] = gmdate( 'Y-m-d H:i:s', $sub->current_period_end );
        }

        if ( $new_status === 'cancelled' && empty( $subscription->cancelled_at ) ) {
            $update['cancelled_at'] = current_time( 'mysql', true );
        }

        $this->subscriptions_db->update( $subscription->id, $update );
    }

    private function handle_subscription_deleted( object $sub ): void {
        $subscription = $this->subscriptions_db->get_by_gateway_id( $sub->id );
        if ( $subscription ) {
            $update = array( 'status' => 'cancelled' );
            if ( empty( $subscription->cancelled_at ) ) {
                $update['cancelled_at'] = current_time( 'mysql', true );
            }
            $this->subscriptions_db->update( $subscription->id, $update );
        }
    }

    private function handle_charge_refunded( object $charge ): void {
        $pi_id = $charge->payment_intent ?? '';
        if ( empty( $pi_id ) ) {
            return;
        }

        $donation = $this->donations_db->get_by_transaction_id( $pi_id );
        if ( $donation ) {
            $this->donations_db->mark_refunded( $donation->id );
        }
    }
}
