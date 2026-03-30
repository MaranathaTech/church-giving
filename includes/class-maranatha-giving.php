<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies() {
        $dir = MARANATHA_GIVING_PLUGIN_DIR . 'includes/';

        // Database layer.
        require_once $dir . 'db/schema.php';
        require_once $dir . 'db/class-database.php';
        require_once $dir . 'db/class-donations-db.php';
        require_once $dir . 'db/class-donors-db.php';
        require_once $dir . 'db/class-funds-db.php';
        require_once $dir . 'db/class-subscriptions-db.php';

        // Gateways.
        require_once $dir . 'gateways/class-gateway-interface.php';
        require_once $dir . 'gateways/class-stripe-gateway.php';
        require_once $dir . 'gateways/class-paypal-gateway.php';

        // Webhooks.
        require_once $dir . 'webhooks/class-webhook-handler.php';
        require_once $dir . 'webhooks/class-stripe-webhook.php';
        require_once $dir . 'webhooks/class-paypal-webhook.php';

        // Email.
        require_once $dir . 'email/class-email-templates.php';
        require_once $dir . 'email/class-email-receipt.php';

        // REST API.
        require_once $dir . 'rest-api/class-donation-endpoint.php';
        require_once $dir . 'rest-api/class-paypal-capture-endpoint.php';
        require_once $dir . 'rest-api/class-paypal-subscription-endpoint.php';
        require_once $dir . 'rest-api/class-magic-link-endpoint.php';
        require_once $dir . 'rest-api/class-donor-portal-endpoint.php';

        // Frontend.
        require_once $dir . 'frontend/class-donation-form.php';
        require_once $dir . 'frontend/class-magic-link.php';
        require_once $dir . 'frontend/class-donor-portal.php';

        // Admin.
        if ( is_admin() ) {
            require_once $dir . 'admin/class-admin-menu.php';
            require_once $dir . 'admin/class-settings-page.php';
            require_once $dir . 'admin/class-donations-list.php';
            require_once $dir . 'admin/class-donors-list.php';
            require_once $dir . 'admin/class-subscriptions-list.php';
            require_once $dir . 'admin/class-funds-admin.php';
            require_once $dir . 'admin/class-dashboard-widget.php';
            require_once $dir . 'admin/class-csv-export.php';
        }
    }

    private function register_hooks() {
        // REST API routes.
        add_action( 'rest_api_init', array( new Maranatha_Giving_Donation_Endpoint(), 'register_routes' ) );
        add_action( 'rest_api_init', array( new Maranatha_Giving_Webhook_Handler(), 'register_routes' ) );
        add_action( 'rest_api_init', array( new Maranatha_Giving_PayPal_Capture_Endpoint(), 'register_routes' ) );
        add_action( 'rest_api_init', array( new Maranatha_Giving_PayPal_Subscription_Endpoint(), 'register_routes' ) );
        add_action( 'rest_api_init', array( new Maranatha_Giving_Magic_Link_Endpoint(), 'register_routes' ) );
        add_action( 'rest_api_init', array( new Maranatha_Giving_Donor_Portal_Endpoint(), 'register_routes' ) );
        add_action( 'rest_api_init', array( $this, 'register_nonce_route' ) );

        // Frontend shortcodes and assets.
        $form = new Maranatha_Giving_Donation_Form();
        add_shortcode( 'maranatha_giving_form', array( $form, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $form, 'enqueue_assets' ) );

        // Donor portal shortcode.
        $portal = new Maranatha_Giving_Donor_Portal();
        add_shortcode( 'maranatha_giving_portal', array( $portal, 'render_shortcode' ) );

        // Email receipt on donation completed.
        $receipt = new Maranatha_Giving_Email_Receipt();
        add_action( 'maranatha_giving_donation_completed', array( $receipt, 'send' ) );

        // Admin hooks.
        if ( is_admin() ) {
            $menu = new Maranatha_Giving_Admin_Menu();
            add_action( 'admin_menu', array( $menu, 'register_menus' ) );
            add_action( 'admin_enqueue_scripts', array( $menu, 'enqueue_assets' ) );
            add_action( 'admin_init', array( new Maranatha_Giving_Settings_Page(), 'register_settings' ) );

            $dashboard = new Maranatha_Giving_Dashboard_Widget();
            add_action( 'wp_dashboard_setup', array( $dashboard, 'register' ) );

            $csv_export = new Maranatha_Giving_CSV_Export();
            add_action( 'admin_init', array( $csv_export, 'handle_export' ) );

            add_action( 'wp_ajax_maranatha_giving_test_stripe', array( $this, 'ajax_test_stripe' ) );
            add_action( 'wp_ajax_maranatha_giving_test_paypal', array( $this, 'ajax_test_paypal' ) );
            add_action( 'wp_ajax_maranatha_giving_test_email', array( $this, 'ajax_test_email' ) );
        }
    }

    /**
     * Get a plugin option with optional default.
     */
    public static function get_option( $key, $default = '' ) {
        $options = get_option( 'maranatha_giving_settings', array() );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Update a single plugin option key.
     */
    public static function update_option( $key, $value ) {
        $options = get_option( 'maranatha_giving_settings', array() );
        $options[ $key ] = $value;
        update_option( 'maranatha_giving_settings', $options );
    }

    /**
     * Register a lightweight REST route that returns a fresh nonce.
     * This allows the JS to fetch a valid nonce even when Cloudflare
     * or other CDNs cache the page HTML (which embeds a stale nonce).
     */
    public function register_nonce_route() {
        register_rest_route( 'maranatha-giving/v1', '/nonce', array(
            'methods'             => 'GET',
            'callback'            => function () {
                return new WP_REST_Response( array(
                    'nonce' => wp_create_nonce( 'maranatha_giving_donate' ),
                ) );
            },
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Verify bot protection token (Turnstile or reCAPTCHA v3).
     * Returns true on success/not configured, WP_Error on failure.
     * Fails open on network errors to avoid blocking legitimate users.
     */
    public static function verify_bot_protection( $token ) {
        $protection = self::get_option( 'bot_protection', 'none' );
        if ( $protection === 'none' || $protection === '' ) {
            return true;
        }

        $secret = self::get_option( 'bot_secret_key', '' );
        if ( empty( $secret ) ) {
            return true; // Not fully configured — fail open.
        }

        if ( empty( $token ) ) {
            // Fail open — the widget may not load due to ad blockers,
            // script blockers, CDN caching, or other client-side issues.
            error_log( 'Maranatha Giving bot token empty — failing open.' );
            return true;
        }

        if ( $protection === 'turnstile' ) {
            $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                'body'    => array(
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ),
                'timeout' => 10,
            ) );

            if ( is_wp_error( $response ) ) {
                error_log( 'Maranatha Giving Turnstile verification network error: ' . $response->get_error_message() );
                return true; // Fail open on network errors.
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $body['success'] ) ) {
                return new WP_Error( 'bot_check_failed', 'Bot verification failed. Please try again.' );
            }

            return true;
        }

        if ( $protection === 'recaptcha' ) {
            $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
                'body'    => array(
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ),
                'timeout' => 10,
            ) );

            if ( is_wp_error( $response ) ) {
                error_log( 'Maranatha Giving reCAPTCHA verification network error: ' . $response->get_error_message() );
                return true; // Fail open on network errors.
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $body['success'] ) ) {
                return new WP_Error( 'bot_check_failed', 'Bot verification failed. Please try again.' );
            }

            $threshold = (float) self::get_option( 'recaptcha_threshold', '0.5' );
            $score     = (float) ( $body['score'] ?? 0 );
            if ( $score < $threshold ) {
                return new WP_Error( 'bot_check_failed', 'Bot verification failed. Please try again.' );
            }

            return true;
        }

        return true;
    }

    /**
     * AJAX: Test Stripe connection by retrieving account balance.
     */
    public function ajax_test_stripe() {
        check_ajax_referer( 'maranatha_giving_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        try {
            $gateway = new Maranatha_Giving_Stripe_Gateway();
            if ( ! $gateway->is_available() ) {
                wp_send_json_error( 'Stripe is not enabled or keys are missing.' );
            }

            $mode   = self::get_option( 'stripe_mode', 'test' );
            $secret = $mode === 'live'
                ? self::get_option( 'stripe_live_secret' )
                : self::get_option( 'stripe_test_secret' );

            \Stripe\Stripe::setApiKey( $secret );
            \Stripe\Balance::retrieve();
            wp_send_json_success();
        } catch ( \Exception $e ) {
            wp_send_json_error( 'Stripe error: ' . $e->getMessage() );
        }
    }

    /**
     * AJAX: Send a test email to the admin using the configured receipt settings.
     */
    public function ajax_test_email() {
        check_ajax_referer( 'maranatha_giving_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $to        = get_option( 'admin_email' );
        $from_name = self::get_option( 'email_from_name', get_bloginfo( 'name' ) );
        $from_addr = self::get_option( 'email_from_address', get_option( 'admin_email' ) );

        // Build sample merge tags for preview.
        $tags = array(
            'donor_first_name'   => 'John',
            'donor_last_name'    => 'Doe',
            'donation_amount'    => '$100.00',
            'donation_date'      => wp_date( get_option( 'date_format' ) ),
            'fund_name'          => 'General Fund',
            'donation_type'      => 'One-time',
            'transaction_id'     => 'test_' . wp_generate_password( 12, false ),
            'church_name'        => self::get_option( 'church_name', get_bloginfo( 'name' ) ),
            'church_address'     => self::get_option( 'church_address', '' ),
            'church_ein'         => self::get_option( 'church_ein', '' ),
            'church_logo'        => '',
            'tax_statement'      => self::get_option( 'tax_statement', '' ),
            'year_to_date_total' => '$250.00',
            'donor_portal_url'   => home_url(),
        );

        $logo_url = self::get_option( 'church_logo', '' );
        if ( empty( $logo_url ) ) {
            $logo_id = self::get_option( 'church_logo_id', '' );
            if ( $logo_id ) {
                $logo_url = wp_get_attachment_url( (int) $logo_id );
            }
        }
        if ( $logo_url ) {
            $tags['church_logo'] = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $tags['church_name'] ) . '" style="max-width:200px;height:auto;">';
        }

        $subject = self::get_option( 'receipt_subject', 'Thank you for your gift of {donation_amount}' );
        $subject = '[TEST] ' . Maranatha_Giving_Email_Templates::replace_tags( $subject, $tags );

        $custom_body = self::get_option( 'receipt_body', '' );
        if ( ! empty( trim( $custom_body ) ) ) {
            $body = Maranatha_Giving_Email_Templates::replace_tags( wpautop( $custom_body ), $tags );
        } else {
            // Render the HTML template with sample tags.
            $template = locate_template( 'maranatha-giving/email/receipt.php' );
            if ( ! $template ) {
                $template = MARANATHA_GIVING_PLUGIN_DIR . 'templates/email/receipt.php';
            }
            ob_start();
            extract( array( 'tags' => $tags ), EXTR_SKIP );
            include $template;
            $body = ob_get_clean();
            $body = Maranatha_Giving_Email_Templates::replace_tags( $body, $tags );
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_addr}>",
        );

        $reply_to = self::get_option( 'email_reply_to', '' );
        if ( ! empty( $reply_to ) && is_email( $reply_to ) ) {
            $headers[] = "Reply-To: {$reply_to}";
        }

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( $sent ) {
            wp_send_json_success( 'Test email sent to ' . $to );
        } else {
            wp_send_json_error( 'Failed to send test email. Check your mail server configuration.' );
        }
    }

    /**
     * AJAX: Test PayPal connection by requesting an access token.
     */
    public function ajax_test_paypal() {
        check_ajax_referer( 'maranatha_giving_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        try {
            $gateway = new Maranatha_Giving_PayPal_Gateway();
            if ( ! $gateway->is_available() ) {
                wp_send_json_error( 'PayPal is not enabled or credentials are missing.' );
            }

            $gateway->test_connection();
            wp_send_json_success();
        } catch ( \Exception $e ) {
            wp_send_json_error( 'PayPal error: ' . $e->getMessage() );
        }
    }
}
