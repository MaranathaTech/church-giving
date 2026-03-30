<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Database {

    protected $table_name;

    protected function table() {
        global $wpdb;
        return $wpdb->prefix . 'maranatha_giving_' . $this->table_name;
    }

    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d",
            $id
        ) );
    }

    public function insert( array $data ) {
        global $wpdb;
        $wpdb->insert( $this->table(), $data );
        return $wpdb->insert_id;
    }

    public function update( $id, array $data ) {
        global $wpdb;
        return $wpdb->update( $this->table(), $data, array( 'id' => $id ) );
    }

    public function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( $this->table(), array( 'id' => $id ) );
    }

    public function count( array $where = array() ) {
        global $wpdb;
        $sql = "SELECT COUNT(*) FROM {$this->table()}";
        if ( ! empty( $where ) ) {
            $clauses = array();
            $values  = array();
            foreach ( $where as $col => $val ) {
                $clauses[] = "{$col} = %s";
                $values[]  = $val;
            }
            $sql .= ' WHERE ' . implode( ' AND ', $clauses );
            $sql = $wpdb->prepare( $sql, ...$values );
        }
        return (int) $wpdb->get_var( $sql );
    }

    protected function query( array $args = array() ) {
        global $wpdb;

        $defaults = array(
            'where'    => array(),
            'orderby'  => 'id',
            'order'    => 'DESC',
            'per_page' => 20,
            'page'     => 1,
            'search'   => '',
            'search_columns' => array(),
        );

        $args   = wp_parse_args( $args, $defaults );
        $sql    = "SELECT * FROM {$this->table()}";
        $values = array();

        $clauses = array();
        foreach ( $args['where'] as $col => $val ) {
            $clauses[] = "{$col} = %s";
            $values[]  = $val;
        }

        if ( $args['search'] && ! empty( $args['search_columns'] ) ) {
            $search_clauses = array();
            foreach ( $args['search_columns'] as $col ) {
                $search_clauses[] = "{$col} LIKE %s";
                $values[]         = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            }
            $clauses[] = '(' . implode( ' OR ', $search_clauses ) . ')';
        }

        if ( ! empty( $clauses ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $clauses );
        }

        $allowed_order = array( 'ASC', 'DESC' );
        $order = in_array( strtoupper( $args['order'] ), $allowed_order, true ) ? strtoupper( $args['order'] ) : 'DESC';
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $order );
        if ( $orderby ) {
            $sql .= " ORDER BY {$orderby}";
        }

        $per_page = max( 1, (int) $args['per_page'] );
        $offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );
        $sql     .= ' LIMIT %d OFFSET %d';
        $values[] = $per_page;
        $values[] = $offset;

        $sql = $wpdb->prepare( $sql, ...$values );

        return $wpdb->get_results( $sql );
    }
}
