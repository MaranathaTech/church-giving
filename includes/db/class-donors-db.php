<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Donors_DB extends Maranatha_Giving_Database {

    protected $table_name = 'donors';

    public function get_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE email = %s",
            sanitize_email( $email )
        ) );
    }

    public function get_by_stripe_customer( $customer_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE stripe_customer_id = %s",
            $customer_id
        ) );
    }

    public function get_or_create( array $data ) {
        $email = sanitize_email( $data['email'] ?? '' );
        if ( ! $email ) {
            return null;
        }

        $donor = $this->get_by_email( $email );
        if ( $donor ) {
            // Update name if provided and donor had no name.
            $update = array( 'updated_at' => current_time( 'mysql', true ) );
            if ( empty( $donor->first_name ) && ! empty( $data['first_name'] ) ) {
                $update['first_name'] = sanitize_text_field( $data['first_name'] );
            }
            if ( empty( $donor->last_name ) && ! empty( $data['last_name'] ) ) {
                $update['last_name'] = sanitize_text_field( $data['last_name'] );
            }
            $this->update( $donor->id, $update );
            return $this->get( $donor->id );
        }

        $now = current_time( 'mysql', true );
        $id  = $this->insert( array(
            'email'      => $email,
            'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
            'created_at' => $now,
            'updated_at' => $now,
        ) );

        return $id ? $this->get( $id ) : null;
    }

    public function increment_totals( $donor_id, $amount ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table()} SET total_donated = total_donated + %f, donation_count = donation_count + 1, updated_at = %s WHERE id = %d",
            $amount,
            current_time( 'mysql', true ),
            $donor_id
        ) );
    }

    public function get_year_to_date_total( $donor_id ) {
        global $wpdb;
        $donations_table = $wpdb->prefix . 'maranatha_giving_donations';
        $year_start = gmdate( 'Y-01-01 00:00:00' );
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$donations_table} WHERE donor_id = %d AND status = 'completed' AND completed_at >= %s",
            $donor_id,
            $year_start
        ) );
    }

    public function get_donors( array $args = array() ) {
        $args['search_columns'] = array( 'email', 'first_name', 'last_name' );
        return $this->query( $args );
    }
}
