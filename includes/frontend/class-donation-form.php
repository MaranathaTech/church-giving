<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Donation_Form {

    private $has_form = false;

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'funds'          => '',
            'amounts'        => '',
            'show_recurring' => 'yes',
            'form_id'        => 'default',
        ), $atts, 'maranatha_giving_form' );

        $this->has_form = true;

        // Determine amounts.
        $amounts = $atts['amounts']
            ? array_map( 'floatval', explode( ',', $atts['amounts'] ) )
            : array_map( 'floatval', explode( ',', Maranatha_Giving::get_option( 'default_amounts', '25,50,100,250' ) ) );

        // Determine funds.
        $funds_db     = new Maranatha_Giving_Funds_DB();
        $fund_ids     = $atts['funds'] ? array_map( 'absint', explode( ',', $atts['funds'] ) ) : array();
        $active_funds = $funds_db->get_active_funds();

        if ( ! empty( $fund_ids ) ) {
            $active_funds = array_filter( $active_funds, function ( $f ) use ( $fund_ids ) {
                return in_array( (int) $f->id, $fund_ids, true );
            } );
        }

        $show_recurring    = strtolower( $atts['show_recurring'] ) === 'yes';
        $allow_custom      = Maranatha_Giving::get_option( 'allow_custom_amount', '1' ) === '1';
        $min_amount        = (float) Maranatha_Giving::get_option( 'min_amount', 5 );
        $currency          = Maranatha_Giving::get_option( 'currency', 'USD' );
        $stripe_enabled    = Maranatha_Giving::get_option( 'stripe_enabled' ) === '1';
        $paypal_enabled    = Maranatha_Giving::get_option( 'paypal_enabled' ) === '1';
        $venmo_enabled     = $paypal_enabled && Maranatha_Giving::get_option( 'venmo_enabled' ) === '1';
        $tax_statement     = Maranatha_Giving::get_option( 'tax_statement', '' );

        $stripe_gateway = new Maranatha_Giving_Stripe_Gateway();
        $paypal_gateway = new Maranatha_Giving_PayPal_Gateway();

        $paypal_mode = Maranatha_Giving::get_option( 'paypal_mode', 'sandbox' );

        $form_vars = array(
            'amounts'          => $amounts,
            'funds'            => array_values( $active_funds ),
            'show_recurring'   => $show_recurring,
            'allow_custom'     => $allow_custom,
            'min_amount'       => $min_amount,
            'currency'         => $currency,
            'stripe_enabled'   => $stripe_enabled,
            'stripe_pk'        => $stripe_enabled ? $stripe_gateway->get_publishable_key() : '',
            'paypal_enabled'   => $paypal_enabled,
            'paypal_client_id' => $paypal_enabled ? $paypal_gateway->get_client_id() : '',
            'paypal_mode'      => $paypal_mode,
            'venmo_enabled'    => $venmo_enabled,
            'tax_statement'    => $tax_statement,
            'form_heading'     => Maranatha_Giving::get_option( 'form_heading', '' ),
            'form_lead_in'     => Maranatha_Giving::get_option( 'form_lead_in', '' ),
            'form_id'          => $atts['form_id'],
            'nonce'            => wp_create_nonce( 'maranatha_giving_donate' ),
            'rest_url'         => rest_url( 'maranatha-giving/v1/' ),
        );

        $form_vars = apply_filters( 'maranatha_giving_form_vars', $form_vars, $atts );

        // Load the template.
        ob_start();
        $template = locate_template( 'maranatha-giving/frontend/donation-form.php' );
        if ( ! $template ) {
            $template = MARANATHA_GIVING_PLUGIN_DIR . 'templates/frontend/donation-form.php';
        }
        $template = apply_filters( 'maranatha_giving_form_template', $template, $atts );
        extract( array( 'form_vars' => $form_vars ), EXTR_SKIP );
        include $template;
        return ob_get_clean();
    }

    public function enqueue_assets() {
        // Always register — only enqueue when shortcode is used.
        wp_register_style(
            'maranatha-giving-form',
            MARANATHA_GIVING_PLUGIN_URL . 'assets/css/donation-form.css',
            array(),
            MARANATHA_GIVING_VERSION
        );

        wp_register_script(
            'maranatha-giving-form',
            MARANATHA_GIVING_PLUGIN_URL . 'assets/js/donation-form.js',
            array(),
            MARANATHA_GIVING_VERSION,
            true
        );

        // Enqueue custom CSS if set.
        $custom_css = Maranatha_Giving::get_option( 'custom_css', '' );
        if ( $custom_css ) {
            wp_add_inline_style( 'maranatha-giving-form', $custom_css );
        }
    }
}
