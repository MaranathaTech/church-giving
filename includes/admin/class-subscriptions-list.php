<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Maranatha_Giving_Subscriptions_List extends WP_List_Table {

    private $subscriptions_db;

    public function __construct() {
        parent::__construct( array(
            'singular' => 'subscription',
            'plural'   => 'subscriptions',
            'ajax'     => false,
        ) );
        $this->subscriptions_db = new Maranatha_Giving_Subscriptions_DB();
    }

    public function get_columns() {
        return array(
            'donor'      => 'Donor',
            'amount'     => 'Amount',
            'frequency'  => 'Frequency',
            'gateway'    => 'Gateway',
            'status'     => 'Status',
            'created_at' => 'Started',
        );
    }

    public function get_sortable_columns() {
        return array(
            'amount'     => array( 'amount', false ),
            'created_at' => array( 'created_at', true ),
            'status'     => array( 'status', false ),
        );
    }

    public function prepare_items() {
        $per_page = 20;
        $page     = $this->get_pagenum();
        $orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'created_at';
        $order    = isset( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ), true )
            ? strtoupper( $_REQUEST['order'] )
            : 'DESC';

        $status_filter = isset( $_REQUEST['status'] ) ? sanitize_text_field( $_REQUEST['status'] ) : '';
        $where         = array();
        if ( $status_filter ) {
            $where['status'] = $status_filter;
        }

        $this->items = $this->subscriptions_db->get_subscriptions( array(
            'where'    => $where,
            'orderby'  => $orderby,
            'order'    => $order,
            'per_page' => $per_page,
            'page'     => $page,
        ) );

        $total = $this->subscriptions_db->count( $where );

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

    protected function get_views() {
        $current = isset( $_REQUEST['status'] ) ? sanitize_text_field( $_REQUEST['status'] ) : '';
        $base    = admin_url( 'admin.php?page=maranatha-giving-subscriptions' );

        $statuses = array(
            ''          => 'All',
            'active'    => 'Active',
            'paused'    => 'Paused',
            'cancelled' => 'Cancelled',
            'failing'   => 'Failing',
        );

        $views = array();
        foreach ( $statuses as $status => $label ) {
            $count   = $this->subscriptions_db->count( $status ? array( 'status' => $status ) : array() );
            $url     = $status ? add_query_arg( 'status', $status, $base ) : $base;
            $class   = $current === $status ? ' class="current"' : '';
            $views[ $status ?: 'all' ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $url ),
                $class,
                esc_html( $label ),
                $count
            );
        }

        return $views;
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'donor':
                if ( $item->donor_id ) {
                    $donors_db = new Maranatha_Giving_Donors_DB();
                    $donor     = $donors_db->get( $item->donor_id );
                    if ( $donor ) {
                        $name = trim( $donor->first_name . ' ' . $donor->last_name );
                        return esc_html( $name ?: $donor->email );
                    }
                }
                return '—';
            case 'amount':
                return '$' . number_format( (float) $item->amount, 2 );
            case 'frequency':
                return esc_html( ucfirst( $item->frequency ) );
            case 'gateway':
                return esc_html( ucfirst( $item->gateway ) );
            case 'status':
                $status_colors = array(
                    'active'    => '#28a745',
                    'paused'    => '#ffc107',
                    'cancelled' => '#6c757d',
                    'expired'   => '#6c757d',
                    'failing'   => '#dc3545',
                );
                $color = $status_colors[ $item->status ] ?? '#6c757d';
                return sprintf(
                    '<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%s;color:#fff;font-size:12px;">%s</span>',
                    esc_attr( $color ),
                    esc_html( ucfirst( $item->status ) )
                );
            case 'created_at':
                return esc_html( wp_date( 'M j, Y', strtotime( $item->created_at ) ) );
            default:
                return '';
        }
    }
}
