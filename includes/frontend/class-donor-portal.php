<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Donor_Portal {

    public function render_shortcode( $atts ) {
        $magic_link = new Maranatha_Giving_Magic_Link();

        // Handle magic link token in URL.
        if ( isset( $_GET['mg_token'] ) && isset( $_GET['mg_email'] ) ) {
            $token = sanitize_text_field( $_GET['mg_token'] );
            $email = sanitize_email( rawurldecode( $_GET['mg_email'] ) );

            if ( $magic_link->validate_token( $email, $token ) ) {
                // Redirect to clean URL (remove token params).
                $clean_url = remove_query_arg( array( 'mg_token', 'mg_email' ) );
                wp_redirect( $clean_url );
                exit;
            }
        }

        // Handle logout.
        if ( isset( $_GET['mg_logout'] ) ) {
            $magic_link->logout();
            $clean_url = remove_query_arg( 'mg_logout' );
            wp_redirect( $clean_url );
            exit;
        }

        // Check authentication.
        $donor = $magic_link->get_authenticated_donor();

        if ( ! $donor ) {
            return $this->render_login();
        }

        return $this->render_portal( $donor );
    }

    private function render_login(): string {
        wp_enqueue_style( 'maranatha-giving-portal', MARANATHA_GIVING_PLUGIN_URL . 'assets/css/donor-portal.css', array(), MARANATHA_GIVING_VERSION );
        wp_enqueue_script( 'maranatha-giving-portal', MARANATHA_GIVING_PLUGIN_URL . 'assets/js/donor-portal.js', array(), MARANATHA_GIVING_VERSION, true );

        // Bot protection scripts.
        $bot_protection = Maranatha_Giving::get_option( 'bot_protection', 'none' );
        $bot_site_key   = Maranatha_Giving::get_option( 'bot_site_key', '' );

        if ( $bot_protection === 'turnstile' && $bot_site_key ) {
            wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true );
        } elseif ( $bot_protection === 'recaptcha' && $bot_site_key ) {
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . urlencode( $bot_site_key ), array(), null, true );
        }

        $localize_data = array(
            'restUrl' => rest_url( 'maranatha-giving/v1/' ),
            'nonce'   => wp_create_nonce( 'maranatha_giving_portal' ),
        );

        if ( $bot_protection !== 'none' && $bot_site_key ) {
            $localize_data['bot_protection'] = $bot_protection;
            $localize_data['bot_site_key']   = $bot_site_key;
        }

        wp_localize_script( 'maranatha-giving-portal', 'mgPortalConfig', $localize_data );

        $template = locate_template( 'maranatha-giving/frontend/donor-portal-login.php' );
        if ( ! $template ) {
            $template = MARANATHA_GIVING_PLUGIN_DIR . 'templates/frontend/donor-portal-login.php';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }

    private function render_portal( object $donor ): string {
        wp_enqueue_style( 'maranatha-giving-portal', MARANATHA_GIVING_PLUGIN_URL . 'assets/css/donor-portal.css', array(), MARANATHA_GIVING_VERSION );
        wp_enqueue_script( 'maranatha-giving-portal', MARANATHA_GIVING_PLUGIN_URL . 'assets/js/donor-portal.js', array(), MARANATHA_GIVING_VERSION, true );

        $donors_db = new Maranatha_Giving_Donors_DB();
        $ytd       = $donors_db->get_year_to_date_total( $donor->id );

        wp_localize_script( 'maranatha-giving-portal', 'mgPortalConfig', array(
            'restUrl'        => rest_url( 'maranatha-giving/v1/' ),
            'nonce'          => wp_create_nonce( 'maranatha_giving_portal' ),
            'authenticated'  => true,
            'donorFirstName' => $donor->first_name,
        ) );

        $template = locate_template( 'maranatha-giving/frontend/donor-portal.php' );
        if ( ! $template ) {
            $template = MARANATHA_GIVING_PLUGIN_DIR . 'templates/frontend/donor-portal.php';
        }

        ob_start();
        extract( array(
            'donor'           => $donor,
            'year_to_date'    => $ytd,
            'lifetime_total'  => (float) $donor->total_donated,
            'donation_count'  => (int) $donor->donation_count,
            'logout_url'      => add_query_arg( 'mg_logout', '1' ),
        ), EXTR_SKIP );
        include $template;
        return ob_get_clean();
    }
}
