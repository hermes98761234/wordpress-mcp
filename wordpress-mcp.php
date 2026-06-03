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
    $api_key = get_option( 'wmcp_api_key' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wmcp_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wmcp_api_key">API Key</label></th>
                    <td>
                        <input type="text" id="wmcp_api_key" name="wmcp_api_key"
                               value="<?php echo esc_attr( $api_key ); ?>"
                               class="regular-text" readonly />
                        <p class="description">Use this key in the Authorization header as: Bearer &lt;key&gt;</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action( 'admin_init', function () {
    register_setting( 'wmcp_settings_group', 'wmcp_api_key' );
} );

register_activation_hook( __FILE__, function () {
    if ( ! get_option( 'wmcp_api_key' ) ) {
        update_option( 'wmcp_api_key', wp_generate_password( 64, false, false ) );
    }
} );
