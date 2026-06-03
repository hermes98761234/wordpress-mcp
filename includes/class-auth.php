<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Auth {

    /**
     * Validate the incoming REST request by checking the Authorization header.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public static function validate_request( $request ) {
        $stored_key = get_option( 'wmcp_api_key' );

        if ( empty( $stored_key ) ) {
            return new WP_Error(
                'wmcp_no_key',
                'MCP API key is not configured.',
                array( 'status' => 500 )
            );
        }

        $auth_header = $request->get_header( 'Authorization' );

        if ( empty( $auth_header ) ) {
            return new WP_Error(
                'wmcp_no_auth',
                'Authorization header is missing.',
                array( 'status' => 401 )
            );
        }

        if ( ! preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
            return new WP_Error(
                'wmcp_bad_auth',
                'Authorization header must be: Bearer <api_key>.',
                array( 'status' => 401 )
            );
        }

        $provided_key = trim( $matches[1] );

        if ( ! hash_equals( $stored_key, $provided_key ) ) {
            return new WP_Error(
                'wmcp_invalid_key',
                'Invalid API key.',
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Generate a new API key and store it in wp_options.
     *
     * @return string The newly generated API key.
     */
    public static function generate_key() {
        $key = wp_generate_password( 64, false, false );
        update_option( 'wmcp_api_key', $key );
        return $key;
    }
}
