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

        $tool    = isset( $body['tool'] ) ? sanitize_text_field( $body['tool'] ) : '';
        $action  = isset( $body['action'] ) ? sanitize_text_field( $body['action'] ) : '';
        $params  = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

        if ( empty( $tool ) ) {
            return new WP_Error(
                'wmcp_missing_tool',
                '"tool" field is required.',
                array( 'status' => 400 )
            );
        }

        if ( empty( $action ) ) {
            return new WP_Error(
                'wmcp_missing_action',
                '"action" field is required.',
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

        $result = call_user_func( array( $class_name, 'execute' ), $action, $params );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ), (int) $result->get_error_data( 'status' ) ?: 400 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $result,
        ), 200 );
    }

    /**
     * Get a list of all available tools and their actions.
     *
     * @return array
     */
    public static function get_available_tools(): array {
        $tools = array(
            'posts'     => array( 'list_posts', 'get_post', 'create_post', 'update_post', 'delete_post', 'list_post_types' ),
            'pages'     => array( 'list_pages', 'get_page', 'create_page', 'update_page', 'delete_page' ),
            'users'     => array( 'list_users', 'get_user', 'create_user', 'update_user', 'delete_user' ),
            'media'     => array( 'list_media', 'get_media', 'upload_media', 'update_media', 'delete_media' ),
            'settings'  => array( 'get_settings', 'update_settings' ),
            'plugins'   => array( 'list_plugins', 'activate_plugin', 'deactivate_plugin', 'update_plugin' ),
            'themes'    => array( 'list_themes', 'activate_theme', 'delete_theme' ),
            'comments'  => array( 'list_comments', 'get_comment', 'create_comment', 'update_comment', 'delete_comment', 'approve_comment', 'spam_comment' ),
            'taxonomies'=> array( 'list_taxonomies', 'list_terms', 'get_term', 'create_term', 'update_term', 'delete_term' ),
            'menus'     => array( 'list_menus', 'get_menu', 'create_menu', 'update_menu', 'delete_menu', 'add_menu_item' ),
        );

        if ( class_exists( 'WooCommerce' ) ) {
            $tools['woocommerce'] = array(
                'list_products', 'get_product', 'update_product',
                'list_orders', 'get_order', 'update_order_status',
                'list_customers',
            );
        }

        return $tools;
    }
}
