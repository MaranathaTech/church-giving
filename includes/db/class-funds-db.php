<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Funds_DB extends Maranatha_Giving_Database {

    protected $table_name = 'funds';

    public function get_active_funds() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table()} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
    }

    public function get_all_funds( array $args = array() ) {
        $args['search_columns'] = array( 'name' );
        if ( ! isset( $args['orderby'] ) ) {
            $args['orderby'] = 'sort_order';
            $args['order']   = 'ASC';
        }
        return $this->query( $args );
    }

    public function create_fund( array $data ) {
        return $this->insert( array(
            'name'        => sanitize_text_field( $data['name'] ?? '' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'is_active'   => (int) ( $data['is_active'] ?? 1 ),
            'sort_order'  => (int) ( $data['sort_order'] ?? 0 ),
            'created_at'  => current_time( 'mysql', true ),
        ) );
    }

    public function update_fund( $id, array $data ) {
        $update = array();
        if ( isset( $data['name'] ) ) {
            $update['name'] = sanitize_text_field( $data['name'] );
        }
        if ( isset( $data['description'] ) ) {
            $update['description'] = sanitize_textarea_field( $data['description'] );
        }
        if ( isset( $data['is_active'] ) ) {
            $update['is_active'] = (int) $data['is_active'];
        }
        if ( isset( $data['sort_order'] ) ) {
            $update['sort_order'] = (int) $data['sort_order'];
        }
        return $this->update( $id, $update );
    }
}
