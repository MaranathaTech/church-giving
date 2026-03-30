<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Activator {

    public static function activate() {
        self::create_tables();
        self::seed_defaults();
        flush_rewrite_rules();
    }

    private static function create_tables() {
        require_once MARANATHA_GIVING_PLUGIN_DIR . 'includes/db/schema.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = maranatha_giving_get_schema();
        dbDelta( $sql );

        update_option( 'maranatha_giving_db_version', MARANATHA_GIVING_VERSION );
    }

    private static function seed_defaults() {
        $existing = get_option( 'maranatha_giving_settings' );
        if ( $existing ) {
            return;
        }

        $defaults = array(
            'church_name'             => get_bloginfo( 'name' ),
            'church_ein'              => '',
            'church_address'          => '',
            'church_phone'            => '',
            'church_website'          => home_url(),
            'church_logo'             => '',
            'currency'                => 'USD',
            'default_amounts'         => '25,50,100,250',
            'allow_custom_amount'     => '1',
            'min_amount'              => '5',

            'stripe_enabled'          => '0',
            'stripe_mode'             => 'test',
            'stripe_test_publishable' => '',
            'stripe_test_secret'      => '',
            'stripe_live_publishable' => '',
            'stripe_live_secret'      => '',
            'stripe_webhook_secret'   => '',

            'paypal_enabled'          => '0',
            'paypal_mode'             => 'sandbox',
            'paypal_client_id'        => '',
            'paypal_secret'           => '',
            'paypal_webhook_id'       => '',
            'venmo_enabled'           => '0',

            'admin_bcc_email'         => get_option( 'admin_email' ),
            'email_from_name'         => get_bloginfo( 'name' ),
            'email_from_address'      => get_option( 'admin_email' ),
            'receipt_subject'         => 'Thank you for your gift of {donation_amount}',
            'receipt_body'            => 'Dear {donor_first_name},

Thank you for your generous gift of {donation_amount} given on {donation_date}. Your contribution has been recorded and will be applied to {fund_name}.

We are grateful for your faithful support of our ministry.

This email serves as your official donation receipt for tax purposes. No goods or services were provided in exchange for this contribution.

— Donation Summary —
Donor: {donor_first_name} {donor_last_name}
Date: {donation_date}
Amount: {donation_amount}
Fund: {fund_name}
Transaction ID: {transaction_id}

If you have any questions about your gift or your giving history, please don\'t hesitate to contact us.

With gratitude,

{church_name}
{church_address}',
            'tax_statement'           => 'This gift is tax-deductible to the extent allowed by law. No goods or services were provided in exchange for this contribution.',

            'delete_data_on_uninstall' => '0',
            'enable_magic_link'        => '1',
            'magic_link_expiration'    => '15',
            'donor_portal_page'        => '',
            'custom_css'               => '',
        );

        // Create a Donor Portal page with the shortcode.
        $portal_page_id = wp_insert_post( array(
            'post_title'   => 'Donor Portal',
            'post_content' => '<!-- wp:shortcode -->[maranatha_giving_portal]<!-- /wp:shortcode -->',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        if ( $portal_page_id && ! is_wp_error( $portal_page_id ) ) {
            $defaults['donor_portal_page'] = (string) $portal_page_id;
        }

        update_option( 'maranatha_giving_settings', $defaults );

        // Seed a default General Fund.
        global $wpdb;
        $table = $wpdb->prefix . 'maranatha_giving_funds';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            if ( 0 === $count ) {
                $wpdb->insert( $table, array(
                    'name'        => 'General Fund',
                    'description' => 'Tithes and general offerings',
                    'is_active'   => 1,
                    'sort_order'  => 0,
                    'created_at'  => current_time( 'mysql', true ),
                ) );
            }
        }
    }
}
