<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Email_Templates {

    /**
     * Replace merge tags in a string with actual values.
     */
    public static function replace_tags( string $content, array $tags ): string {
        foreach ( $tags as $key => $value ) {
            $content = str_replace( '{' . $key . '}', $value, $content );
        }
        return $content;
    }

    /**
     * Build the merge tag array for a donation.
     */
    public static function get_donation_tags( int $donation_id ): array {
        $donations_db = new Maranatha_Giving_Donations_DB();
        $donors_db    = new Maranatha_Giving_Donors_DB();
        $funds_db     = new Maranatha_Giving_Funds_DB();

        $donation = $donations_db->get( $donation_id );
        if ( ! $donation ) {
            return array();
        }

        $donor     = $donation->donor_id ? $donors_db->get( $donation->donor_id ) : null;
        $fund      = $donation->fund_id ? $funds_db->get( $donation->fund_id ) : null;
        $ytd_total = $donor ? $donors_db->get_year_to_date_total( $donor->id ) : 0;

        $church_logo = Maranatha_Giving::get_option( 'church_logo', '' );
        if ( empty( $church_logo ) ) {
            $logo_id = Maranatha_Giving::get_option( 'church_logo_id', '' );
            if ( $logo_id ) {
                $church_logo = wp_get_attachment_url( (int) $logo_id );
            }
        }
        $logo_html = '';
        if ( $church_logo ) {
            $logo_html = '<img src="' . esc_url( $church_logo ) . '" alt="' . esc_attr( Maranatha_Giving::get_option( 'church_name' ) ) . '" style="max-width:200px;height:auto;">';
        }

        $portal_url = '';
        $portal_page = Maranatha_Giving::get_option( 'donor_portal_page', '' );
        if ( $portal_page ) {
            $portal_url = get_permalink( (int) $portal_page );
        }

        return array(
            'donor_first_name'   => $donor ? $donor->first_name : '',
            'donor_last_name'    => $donor ? $donor->last_name : '',
            'donation_amount'    => '$' . number_format( (float) $donation->amount, 2 ),
            'donation_date'      => $donation->completed_at
                ? wp_date( get_option( 'date_format' ), strtotime( $donation->completed_at ) )
                : wp_date( get_option( 'date_format' ) ),
            'fund_name'          => $fund ? $fund->name : 'General Fund',
            'donation_type'      => ucfirst( $donation->donation_type ),
            'transaction_id'     => $donation->gateway_transaction_id,
            'church_name'        => Maranatha_Giving::get_option( 'church_name', get_bloginfo( 'name' ) ),
            'church_address'     => Maranatha_Giving::get_option( 'church_address', '' ),
            'church_ein'         => Maranatha_Giving::get_option( 'church_ein', '' ),
            'church_logo'        => $logo_html,
            'tax_statement'      => Maranatha_Giving::get_option( 'tax_statement', '' ),
            'year_to_date_total' => '$' . number_format( $ytd_total, 2 ),
            'donor_portal_url'   => $portal_url,
        );
    }

    /**
     * Render the receipt template.
     */
    public static function render_receipt( int $donation_id ): string {
        $tags = self::get_donation_tags( $donation_id );
        if ( empty( $tags ) ) {
            return '';
        }

        // Check for theme override.
        $template = locate_template( 'maranatha-giving/email/receipt.php' );
        if ( ! $template ) {
            $template = MARANATHA_GIVING_PLUGIN_DIR . 'templates/email/receipt.php';
        }

        ob_start();
        // Make tags available as $tags in the template.
        extract( array( 'tags' => $tags ), EXTR_SKIP );
        include $template;
        $html = ob_get_clean();

        return self::replace_tags( $html, $tags );
    }
}
