<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Donations_DB extends Maranatha_Giving_Database {

    protected $table_name = 'donations';

    public function get_by_transaction_id( $gateway_transaction_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE gateway_transaction_id = %s",
            $gateway_transaction_id
        ) );
    }

    public function create_donation( array $data ) {
        $now = current_time( 'mysql', true );
        return $this->insert( array(
            'donor_id'               => (int) ( $data['donor_id'] ?? 0 ),
            'subscription_id'        => $data['subscription_id'] ?? null,
            'fund_id'                => $data['fund_id'] ?? null,
            'amount'                 => (float) ( $data['amount'] ?? 0 ),
            'currency'               => strtoupper( sanitize_text_field( $data['currency'] ?? 'USD' ) ),
            'donation_type'          => sanitize_text_field( $data['donation_type'] ?? 'one-time' ),
            'status'                 => sanitize_text_field( $data['status'] ?? 'pending' ),
            'gateway'                => sanitize_text_field( $data['gateway'] ?? '' ),
            'gateway_transaction_id' => sanitize_text_field( $data['gateway_transaction_id'] ?? '' ),
            'gateway_customer_id'    => sanitize_text_field( $data['gateway_customer_id'] ?? '' ),
            'donor_email'            => sanitize_email( $data['donor_email'] ?? '' ),
            'donor_name'             => sanitize_text_field( $data['donor_name'] ?? '' ),
            'form_id'                => sanitize_text_field( $data['form_id'] ?? 'default' ),
            'notes'                  => sanitize_textarea_field( $data['notes'] ?? '' ),
            'created_at'             => $now,
        ) );
    }

    public function mark_completed( $id ) {
        $now = current_time( 'mysql', true );
        $this->update( $id, array(
            'status'       => 'completed',
            'completed_at' => $now,
        ) );

        do_action( 'maranatha_giving_donation_completed', $id );
    }

    public function mark_failed( $id ) {
        $this->update( $id, array( 'status' => 'failed' ) );
    }

    public function mark_refunded( $id ) {
        $this->update( $id, array( 'status' => 'refunded' ) );
        do_action( 'maranatha_giving_donation_refunded', $id );
    }

    public function get_donations( array $args = array() ) {
        $args['search_columns'] = array( 'donor_email', 'donor_name', 'gateway_transaction_id' );
        if ( ! isset( $args['orderby'] ) ) {
            $args['orderby'] = 'created_at';
        }
        return $this->query( $args );
    }

    public function get_total_by_status( $status = 'completed' ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$this->table()} WHERE status = %s",
            $status
        ) );
    }

    public function get_donations_by_donor( $donor_id, $args = array() ) {
        $args['where'] = array_merge( $args['where'] ?? array(), array( 'donor_id' => $donor_id ) );
        return $this->get_donations( $args );
    }
}
