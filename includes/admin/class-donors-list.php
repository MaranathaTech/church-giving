<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Maranatha_Giving_Donors_List extends WP_List_Table {

    private $donors_db;

    public function __construct() {
        parent::__construct( array(
            'singular' => 'donor',
            'plural'   => 'donors',
            'ajax'     => false,
        ) );
        $this->donors_db = new Maranatha_Giving_Donors_DB();
    }

    public function get_columns() {
        return array(
            'name'           => 'Name',
            'email'          => 'Email',
            'total_donated'  => 'Total Donated',
            'donation_count' => 'Donations',
            'created_at'     => 'First Donation',
        );
    }

    public function get_sortable_columns() {
        return array(
            'total_donated'  => array( 'total_donated', true ),
            'donation_count' => array( 'donation_count', false ),
            'created_at'     => array( 'created_at', false ),
        );
    }

    public function prepare_items() {
        $per_page = 20;
        $page     = $this->get_pagenum();
        $search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
        $orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'total_donated';
        $order    = isset( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ), true )
            ? strtoupper( $_REQUEST['order'] )
            : 'DESC';

        $this->items = $this->donors_db->get_donors( array(
            'orderby'  => $orderby,
            'order'    => $order,
            'per_page' => $per_page,
            'page'     => $page,
            'search'   => $search,
        ) );

        $total = $this->donors_db->count();

        $this->set_pagination_args( array(
            'total_items' => $total,
            'per_page'    => $per_page,
        ) );

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'name':
                $name = trim( $item->first_name . ' ' . $item->last_name );
                return esc_html( $name ?: '(no name)' );
            case 'email':
                return esc_html( $item->email );
            case 'total_donated':
                return '$' . number_format( (float) $item->total_donated, 2 );
            case 'donation_count':
                return (int) $item->donation_count;
            case 'created_at':
                return esc_html( wp_date( 'M j, Y', strtotime( $item->created_at ) ) );
            default:
                return '';
        }
    }
}
