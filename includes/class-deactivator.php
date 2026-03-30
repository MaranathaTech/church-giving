<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Deactivator {

    public static function deactivate() {
        wp_clear_scheduled_hook( 'maranatha_giving_daily_cleanup' );
        flush_rewrite_rules();
    }
}
