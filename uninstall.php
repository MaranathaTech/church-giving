<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$options = get_option( 'maranatha_giving_settings', array() );
$delete_data = isset( $options['delete_data_on_uninstall'] ) && $options['delete_data_on_uninstall'];

if ( $delete_data ) {
    global $wpdb;
    $prefix = $wpdb->prefix . 'maranatha_giving_';

    $wpdb->query( "DROP TABLE IF EXISTS {$prefix}donations" );
    $wpdb->query( "DROP TABLE IF EXISTS {$prefix}subscriptions" );
    $wpdb->query( "DROP TABLE IF EXISTS {$prefix}funds" );
    $wpdb->query( "DROP TABLE IF EXISTS {$prefix}donors" );

    delete_option( 'maranatha_giving_settings' );
    delete_option( 'maranatha_giving_db_version' );
}
