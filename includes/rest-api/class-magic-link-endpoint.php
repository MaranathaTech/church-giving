<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Magic_Link_Endpoint {

    public function register_routes() {
        register_rest_route( 'maranatha-giving/v1', '/magic-link', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_send' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'email' => array(
                    'required'          => true,
                    'validate_callback' => function ( $value ) {
                        return is_email( $value );
                    },
                    'sanitize_callback' => 'sanitize_email',
                ),
                'mg_nonce' => array( 'required' => true ),
                'bot_token' => array(
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    public function handle_send( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'mg_nonce' );
        if ( ! wp_verify_nonce( $nonce, 'maranatha_giving_portal' ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid security token.' ), 403 );
        }

        // Bot protection verification.
        $bot_result = Maranatha_Giving::verify_bot_protection( $request->get_param( 'bot_token' ) ?? '' );
        if ( is_wp_error( $bot_result ) ) {
            return new WP_REST_Response( array( 'error' => $bot_result->get_error_message() ), 403 );
        }

        $email      = $request->get_param( 'email' );
        $magic_link = new Maranatha_Giving_Magic_Link();
        $magic_link->send_magic_link( $email );

        // Always return success to avoid email enumeration.
        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'If an account exists with that email, a login link has been sent.',
        ), 200 );
    }
}
