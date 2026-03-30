<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Magic_Link {

    const COOKIE_NAME = 'maranatha_giving_donor_session';

    /**
     * Generate a magic link token, store hash + expiry on the donor, and email the link.
     */
    public function send_magic_link( string $email ): bool {
        $donors_db = new Maranatha_Giving_Donors_DB();
        $donor     = $donors_db->get_by_email( $email );

        if ( ! $donor ) {
            // Don't reveal whether the email exists — return true silently.
            return true;
        }

        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );
        $expiration = Maranatha_Giving::get_option( 'magic_link_expiration', 15 );
        $expires    = gmdate( 'Y-m-d H:i:s', time() + ( (int) $expiration * 60 ) );

        $donors_db->update( $donor->id, array(
            'magic_link_token'   => $token_hash,
            'magic_link_expires' => $expires,
        ) );

        $portal_page = Maranatha_Giving::get_option( 'donor_portal_page', '' );
        $portal_url  = $portal_page ? get_permalink( (int) $portal_page ) : home_url();
        $link        = add_query_arg( array(
            'mg_token' => $token,
            'mg_email' => rawurlencode( $donor->email ),
        ), $portal_url );

        $church_name = Maranatha_Giving::get_option( 'church_name', get_bloginfo( 'name' ) );
        $subject     = $church_name . ' — Access Your Giving Portal';

        $template = locate_template( 'maranatha-giving/email/magic-link.php' );
        if ( ! $template ) {
            $template = MARANATHA_GIVING_PLUGIN_DIR . 'templates/email/magic-link.php';
        }

        ob_start();
        extract( array(
            'donor'       => $donor,
            'magic_link'  => $link,
            'church_name' => $church_name,
            'expiration'  => $expiration,
        ), EXTR_SKIP );
        include $template;
        $body = ob_get_clean();

        $from_name    = Maranatha_Giving::get_option( 'email_from_name', get_bloginfo( 'name' ) );
        $from_address = Maranatha_Giving::get_option( 'email_from_address', get_option( 'admin_email' ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_address}>",
        );

        return wp_mail( $donor->email, $subject, $body, $headers );
    }

    /**
     * Validate a magic link token and set a session cookie.
     */
    public function validate_token( string $email, string $token ): bool {
        $donors_db  = new Maranatha_Giving_Donors_DB();
        $donor      = $donors_db->get_by_email( $email );

        if ( ! $donor || empty( $donor->magic_link_token ) ) {
            return false;
        }

        // Check expiry.
        if ( strtotime( $donor->magic_link_expires ) < time() ) {
            // Expired — clear token.
            $donors_db->update( $donor->id, array(
                'magic_link_token'   => '',
                'magic_link_expires' => null,
            ) );
            return false;
        }

        // Verify hash.
        $token_hash = hash( 'sha256', $token );
        if ( ! hash_equals( $donor->magic_link_token, $token_hash ) ) {
            return false;
        }

        // Clear token (one-time use).
        $donors_db->update( $donor->id, array(
            'magic_link_token'   => '',
            'magic_link_expires' => null,
        ) );

        // Set cookie.
        $this->set_session_cookie( $donor );

        return true;
    }

    /**
     * Set a signed HMAC session cookie.
     */
    private function set_session_cookie( object $donor ): void {
        $data = wp_json_encode( array(
            'donor_id' => $donor->id,
            'email'    => $donor->email,
            'exp'      => time() + ( 30 * DAY_IN_SECONDS ),
        ) );

        $signature = hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
        $cookie    = base64_encode( $data ) . '.' . $signature;

        setcookie( self::COOKIE_NAME, $cookie, array(
            'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );

        // Also set for current request.
        $_COOKIE[ self::COOKIE_NAME ] = $cookie;
    }

    /**
     * Get the authenticated donor from the session cookie.
     */
    public function get_authenticated_donor(): ?object {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }

        $parts = explode( '.', $_COOKIE[ self::COOKIE_NAME ], 2 );
        if ( count( $parts ) !== 2 ) {
            return null;
        }

        $data      = base64_decode( $parts[0] );
        $signature = $parts[1];

        // Verify HMAC.
        $expected = hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
        if ( ! hash_equals( $expected, $signature ) ) {
            return null;
        }

        $payload = json_decode( $data );
        if ( ! $payload || empty( $payload->donor_id ) ) {
            return null;
        }

        // Check expiry.
        if ( ( $payload->exp ?? 0 ) < time() ) {
            return null;
        }

        $donors_db = new Maranatha_Giving_Donors_DB();
        return $donors_db->get( $payload->donor_id );
    }

    /**
     * Logout — clear the session cookie.
     */
    public function logout(): void {
        setcookie( self::COOKIE_NAME, '', array(
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
        unset( $_COOKIE[ self::COOKIE_NAME ] );
    }
}
