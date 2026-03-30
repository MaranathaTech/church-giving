<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_CSV_Export {

    public function handle_export() {
        if ( ! isset( $_GET['mg_export'] ) || 'donations' !== $_GET['mg_export'] ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mg_export_donations' ) ) {
            wp_die( 'Invalid nonce' );
        }

        $donations_db = new Maranatha_Giving_Donations_DB();
        $funds_db     = new Maranatha_Giving_Funds_DB();

        $args = array(
            'where'    => array(),
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'per_page' => 10000,
            'page'     => 1,
        );

        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        if ( $status ) {
            $args['where']['status'] = $status;
        }

        $year = isset( $_GET['year'] ) ? (int) $_GET['year'] : 0;
        if ( $year ) {
            $args['year'] = $year;
        }

        $donations = $donations_db->get_donations( $args );

        // Filter by year if specified (since query doesn't support date filtering natively).
        if ( $year ) {
            $donations = array_filter( $donations, function ( $d ) use ( $year ) {
                return (int) date( 'Y', strtotime( $d->created_at ) ) === $year;
            } );
        }

        $funds_cache = array();

        $filename = 'church-giving-donations';
        if ( $status ) {
            $filename .= '-' . $status;
        }
        if ( $year ) {
            $filename .= '-' . $year;
        }
        $filename .= '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        $headers = apply_filters( 'maranatha_giving_csv_headers', array(
            'Date',
            'Donor Name',
            'Email',
            'Amount',
            'Currency',
            'Fund',
            'Type',
            'Gateway',
            'Transaction ID',
            'Status',
        ) );

        fputcsv( $output, $headers );

        foreach ( $donations as $d ) {
            $fund_name = '';
            if ( $d->fund_id ) {
                if ( ! isset( $funds_cache[ $d->fund_id ] ) ) {
                    $fund = $funds_db->get( $d->fund_id );
                    $funds_cache[ $d->fund_id ] = $fund ? $fund->name : '';
                }
                $fund_name = $funds_cache[ $d->fund_id ];
            }

            $row = apply_filters( 'maranatha_giving_csv_row', array(
                wp_date( 'Y-m-d', strtotime( $d->completed_at ?: $d->created_at ) ),
                $d->donor_name,
                $d->donor_email,
                number_format( (float) $d->amount, 2, '.', '' ),
                $d->currency,
                $fund_name,
                ucfirst( $d->donation_type ),
                ucfirst( $d->gateway ),
                $d->gateway_transaction_id,
                ucfirst( $d->status ),
            ), $d );

            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
