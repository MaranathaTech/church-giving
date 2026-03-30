<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Maranatha_Giving_Donations_List extends WP_List_Table {

    private $donations_db;

    public function __construct() {
        parent::__construct( array(
            'singular' => 'donation',
            'plural'   => 'donations',
            'ajax'     => false,
        ) );
        $this->donations_db = new Maranatha_Giving_Donations_DB();
    }

    public function get_columns() {
        return array(
            'donor_name'             => 'Donor',
            'donor_email'            => 'Email',
            'amount'                 => 'Amount',
            'fund'                   => 'Fund',
            'donation_type'          => 'Type',
            'gateway'                => 'Gateway',
            'status'                 => 'Status',
            'created_at'             => 'Date',
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
        $search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
        $orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( $_REQUEST['orderby'] . ' ASC' ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'created_at' : 'created_at';
        $order    = isset( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ), true )
            ? strtoupper( $_REQUEST['order'] )
            : 'DESC';

        $status_filter = isset( $_REQUEST['status'] ) ? sanitize_text_field( $_REQUEST['status'] ) : '';
        $where         = array();
        if ( $status_filter ) {
            $where['status'] = $status_filter;
        }

        $this->items = $this->donations_db->get_donations( array(
            'where'    => $where,
            'orderby'  => $orderby,
            'order'    => $order,
            'per_page' => $per_page,
            'page'     => $page,
            'search'   => $search,
        ) );

        $total = $this->donations_db->count( $where );

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
        $base    = admin_url( 'admin.php?page=maranatha-giving' );

        $statuses = array(
            ''          => 'All',
            'completed' => 'Completed',
            'pending'   => 'Pending',
            'processing' => 'Processing',
            'failed'    => 'Failed',
            'refunded'  => 'Refunded',
        );

        $views = array();
        foreach ( $statuses as $status => $label ) {
            $count   = $this->donations_db->count( $status ? array( 'status' => $status ) : array() );
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
            case 'donor_name':
                return esc_html( $item->donor_name ?: '(anonymous)' );
            case 'donor_email':
                return esc_html( $item->donor_email );
            case 'amount':
                return '$' . number_format( (float) $item->amount, 2 );
            case 'fund':
                if ( $item->fund_id ) {
                    $funds_db = new Maranatha_Giving_Funds_DB();
                    $fund     = $funds_db->get( $item->fund_id );
                    return $fund ? esc_html( $fund->name ) : '—';
                }
                return '—';
            case 'donation_type':
                return esc_html( ucfirst( $item->donation_type ) );
            case 'gateway':
                return esc_html( ucfirst( $item->gateway ) );
            case 'status':
                $status_colors = array(
                    'completed'  => '#28a745',
                    'pending'    => '#ffc107',
                    'processing' => '#17a2b8',
                    'failed'     => '#dc3545',
                    'refunded'   => '#6c757d',
                );
                $color = $status_colors[ $item->status ] ?? '#6c757d';
                return sprintf(
                    '<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%s;color:#fff;font-size:12px;">%s</span>',
                    esc_attr( $color ),
                    esc_html( ucfirst( $item->status ) )
                );
            case 'created_at':
                return esc_html( wp_date( 'M j, Y g:i a', strtotime( $item->created_at ) ) );
            default:
                return '';
        }
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        $total = $this->donations_db->get_total_by_status( 'completed' );
        echo '<div class="alignleft actions">';
        echo '<strong>Total Completed: $' . esc_html( number_format( $total, 2 ) ) . '</strong>';
        echo '</div>';

        $export_url = wp_nonce_url(
            admin_url( 'admin.php?mg_export=donations' ),
            'mg_export_donations'
        );
        $status = isset( $_REQUEST['status'] ) ? sanitize_text_field( $_REQUEST['status'] ) : '';
        if ( $status ) {
            $export_url = add_query_arg( 'status', $status, $export_url );
        }
        echo '<div class="alignright actions">';
        echo '<a href="' . esc_url( $export_url ) . '" class="button">Export CSV</a>';
        echo '</div>';
    }
}
