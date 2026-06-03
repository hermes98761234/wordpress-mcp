<?php
/**
 * Plugin Name: WordPress MCP
 * Plugin URI: https://github.com/hermes98761234/wordpress-mcp
 * Description: Full-featured MCP (Model Context Protocol) server for managing WordPress via AI assistants
 * Version: 1.0.0
 * Author: hermes98761234
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: wordpress-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WMCP_VERSION', '1.0.0' );
define( 'WMCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMCP_URL', plugin_dir_url( __FILE__ ) );

require_once WMCP_DIR . 'includes/class-auth.php';
require_once WMCP_DIR . 'includes/class-mcp-server.php';

add_action( 'rest_api_init', function () {
    register_rest_route( 'mcp/v1', '/execute', array(
        'methods'             => 'POST',
        'callback'            => array( WMCP_Server::class, 'handle_request' ),
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'mcp/v1', '/tools', array(
        'methods'             => 'GET',
        'callback'            => function() {
            return new WP_REST_Response( array(
                'success' => true,
                'data'    => WMCP_Server::get_available_tools(),
            ), 200 );
        },
        'permission_callback' => '__return_true',
    ) );
} );

add_action( 'admin_menu', function () {
    add_options_page(
        __( 'MCP Settings', 'wordpress-mcp' ),
        __( 'MCP Settings', 'wordpress-mcp' ),
        'manage_options',
        'mcp-settings',
        'wmcp_render_settings_page'
    );
} );

function wmcp_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle regenerate key action.
    if ( isset( $_POST['wmcp_regenerate_key'] ) && check_admin_referer( 'wmcp_regenerate_key_action', 'wmcp_regenerate_key_nonce' ) ) {
        $new_key = WMCP_Auth::generate_key();
        echo '<div class="notice notice-success is-dismissible"><p>API key regenerated successfully.</p></div>';
    }

    $api_key     = get_option( 'wmcp_api_key' );
    $masked_key  = $api_key ? substr( $api_key, 0, 8 ) . '...' : 'Not set';
    $tools       = WMCP_Server::get_available_tools();
    $endpoint    = site_url( '/wp-json/mcp/v1/execute' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <h2>API Key</h2>
        <form method="post">
            <?php wp_nonce_field( 'wmcp_regenerate_key_action', 'wmcp_regenerate_key_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label>Current API Key</label></th>
                    <td>
                        <code><?php echo esc_html( $masked_key ); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wmcp_full_key">Full Key</label></th>
                    <td>
                        <textarea id="wmcp_full_key" class="large-text" rows="2" readonly onclick="this.select()"><?php echo esc_textarea( $api_key ); ?></textarea>
                        <p class="description">Click the textarea above to select all, then copy. Use in the Authorization header as: <code>Bearer &lt;key&gt;</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Endpoint URL</label></th>
                    <td>
                        <code><?php echo esc_html( $endpoint ); ?></code>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Regenerate API Key', 'secondary', 'wmcp_regenerate_key' ); ?>
        </form>

        <h2>Available MCP Tools</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $tools as $tool_name => $actions ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $tool_name ); ?></strong></td>
                    <td><code><?php echo esc_html( implode( ', ', $actions ) ); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

register_activation_hook( __FILE__, function () {
    if ( ! get_option( 'wmcp_api_key' ) ) {
        update_option( 'wmcp_api_key', wp_generate_password( 64, false, false ) );
    }
} );
