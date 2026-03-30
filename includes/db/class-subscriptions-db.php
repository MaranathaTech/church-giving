<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Subscriptions_DB extends Maranatha_Giving_Database {

    protected $table_name = 'subscriptions';

    public function get_by_gateway_id( $gateway_subscription_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE gateway_subscription_id = %s",
            $gateway_subscription_id
        ) );
    }

    public function create_subscription( array $data ) {
        return $this->insert( array(
            'donor_id'                => (int) ( $data['donor_id'] ?? 0 ),
            'fund_id'                 => $data['fund_id'] ?? null,
            'amount'                  => (float) ( $data['amount'] ?? 0 ),
            'currency'                => strtoupper( sanitize_text_field( $data['currency'] ?? 'USD' ) ),
            'frequency'               => sanitize_text_field( $data['frequency'] ?? 'monthly' ),
            'gateway'                 => sanitize_text_field( $data['gateway'] ?? '' ),
            'gateway_subscription_id' => sanitize_text_field( $data['gateway_subscription_id'] ?? '' ),
            'gateway_customer_id'     => sanitize_text_field( $data['gateway_customer_id'] ?? '' ),
            'status'                  => sanitize_text_field( $data['status'] ?? 'active' ),
            'form_id'                 => sanitize_text_field( $data['form_id'] ?? 'default' ),
            'created_at'              => current_time( 'mysql', true ),
        ) );
    }

    public function get_subscriptions( array $args = array() ) {
        $args['search_columns'] = array( 'gateway_subscription_id' );
        return $this->query( $args );
    }

    public function get_by_donor( $donor_id, $status = null ) {
        $where = array( 'donor_id' => $donor_id );
        if ( $status ) {
            $where['status'] = $status;
        }
        return $this->query( array( 'where' => $where, 'per_page' => 100 ) );
    }

    public function record_payment( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table()} SET times_billed = times_billed + 1, last_payment_date = %s WHERE id = %d",
            current_time( 'mysql', true ),
            $id
        ) );
    }
}
