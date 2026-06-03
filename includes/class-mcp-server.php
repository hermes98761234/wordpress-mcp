<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Server {

    /**
     * Map of tool names to their implementing class names.
     *
     * @var array<string, string>
     */
    private static $tool_map = array(
        'posts'            => 'WMCP_Tool_Posts',
        'pages'            => 'WMCP_Tool_Pages',
        'users'            => 'WMCP_Tool_Users',
        'media'            => 'WMCP_Tool_Media',
        'settings'         => 'WMCP_Tool_Settings',
        'plugins_manager'  => 'WMCP_Tool_Plugins',
        'themes_manager'   => 'WMCP_Tool_Themes',
        'comments_tool'    => 'WMCP_Tool_Comments',
        'taxonomies'       => 'WMCP_Tool_Taxonomies',
        'menus'            => 'WMCP_Tool_Menus',
    );

    /**
     * Handle an incoming MCP execute request.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_request( $request ) {
        $auth = WMCP_Auth::validate_request( $request );

        if ( is_wp_error( $auth ) ) {
            return $auth;
        }

        $body = json_decode( $request->get_body(), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'wmcp_invalid_json',
                'Request body must be valid JSON.',
                array( 'status' => 400 )
            );
        }

        $tool  = isset( $body['tool'] ) ? sanitize_text_field( $body['tool'] ) : '';
        $params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

        if ( empty( $tool ) ) {
            return new WP_Error(
                'wmcp_missing_tool',
                '"tool" field is required.',
                array( 'status' => 400 )
            );
        }

        // Add WooCommerce tool only if WooCommerce is active.
        if ( class_exists( 'WooCommerce' ) ) {
            self::$tool_map['woocommerce'] = 'WMCP_Tool_WooCommerce';
        }

        if ( ! isset( self::$tool_map[ $tool ] ) ) {
            return new WP_Error(
                'wmcp_unknown_tool',
                sprintf( 'Unknown tool: "%s". Available tools: %s', $tool, implode( ', ', array_keys( self::$tool_map ) ) ),
                array( 'status' => 400 )
            );
        }

        $class_name = self::$tool_map[ $tool ];
        $file_path  = WMCP_DIR . 'includes/tools/class-tool-' . sanitize_title( $tool ) . '.php';

        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }

        if ( ! class_exists( $class_name ) ) {
            return new WP_Error(
                'wmcp_tool_not_implemented',
                sprintf( 'Tool "%s" is registered but its class %s is not loaded.', $tool, $class_name ),
                array( 'status' => 501 )
            );
        }

        if ( ! method_exists( $class_name, 'execute' ) ) {
            return new WP_Error(
                'wmcp_tool_no_execute',
                sprintf( 'Tool class %s does not implement execute().', $class_name ),
                array( 'status' => 501 )
            );
        }

        $result = call_user_func( array( $class_name, 'execute' ), $params );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 'result' => $result ), 200 );
    }
}
