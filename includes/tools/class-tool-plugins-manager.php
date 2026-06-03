<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Plugins {

    /**
     * Execute a plugins tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_plugins':
                return self::list_plugins();
            case 'activate_plugin':
                return self::activate_plugin( $params );
            case 'deactivate_plugin':
                return self::deactivate_plugin( $params );
            case 'delete_plugin':
                return self::delete_plugin( $params );
            case 'get_plugin_info':
                return self::get_plugin_info( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function check_capability(): bool {
        if ( ! function_exists( 'current_user_can' ) ) {
            return false;
        }
        return current_user_can( 'activate_plugins' ) || current_user_can( 'manage_options' );
    }

    private static function list_plugins(): array|WP_Error {
        if ( ! self::check_capability() ) {
            return new WP_Error( 'wmcp_forbidden', 'Insufficient permissions to list plugins.' );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );
        $result         = array();

        foreach ( $all_plugins as $file => $data ) {
            $result[] = array(
                'file'        => $file,
                'name'        => $data['Name'] ?? '',
                'version'     => $data['Version'] ?? '',
                'active'      => in_array( $file, $active_plugins, true ),
                'description' => $data['Description'] ?? '',
            );
        }

        return $result;
    }

    private static function activate_plugin( array $params ): bool|WP_Error {
        if ( ! self::check_capability() ) {
            return new WP_Error( 'wmcp_forbidden', 'Insufficient permissions to activate plugins.' );
        }

        if ( empty( $params['plugin_file'] ) ) {
            return new WP_Error( 'wmcp_missing_plugin_file', 'Plugin file is required.' );
        }

        if ( ! function_exists( 'activate_plugin' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = sanitize_text_field( $params['plugin_file'] );
        $result      = activate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    private static function deactivate_plugin( array $params ): bool|WP_Error {
        if ( ! self::check_capability() ) {
            return new WP_Error( 'wmcp_forbidden', 'Insufficient permissions to deactivate plugins.' );
        }

        if ( empty( $params['plugin_file'] ) ) {
            return new WP_Error( 'wmcp_missing_plugin_file', 'Plugin file is required.' );
        }

        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = sanitize_text_field( $params['plugin_file'] );
        deactivate_plugins( $plugin_file );

        return true;
    }

    private static function delete_plugin( array $params ): bool|WP_Error {
        if ( ! self::check_capability() ) {
            return new WP_Error( 'wmcp_forbidden', 'Insufficient permissions to delete plugins.' );
        }

        if ( empty( $params['plugin_file'] ) ) {
            return new WP_Error( 'wmcp_missing_plugin_file', 'Plugin file is required.' );
        }

        if ( ! function_exists( 'delete_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = sanitize_text_field( $params['plugin_file'] );

        if ( is_plugin_active( $plugin_file ) ) {
            return new WP_Error( 'wmcp_plugin_active', 'Cannot delete an active plugin. Deactivate it first.' );
        }

        $result = delete_plugins( array( $plugin_file ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    private static function get_plugin_info( array $params ): array|WP_Error {
        if ( ! self::check_capability() ) {
            return new WP_Error( 'wmcp_forbidden', 'Insufficient permissions to get plugin info.' );
        }

        if ( empty( $params['plugin_file'] ) ) {
            return new WP_Error( 'wmcp_missing_plugin_file', 'Plugin file is required.' );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = sanitize_text_field( $params['plugin_file'] );
        $all_plugins = get_plugins();

        if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
            return new WP_Error( 'wmcp_not_found', 'Plugin not found.' );
        }

        $data    = $all_plugins[ $plugin_file ];
        $active_plugins = get_option( 'active_plugins', array() );

        return array(
            'file'        => $plugin_file,
            'name'        => $data['Name'] ?? '',
            'version'     => $data['Version'] ?? '',
            'description' => $data['Description'] ?? '',
            'author'      => $data['Author'] ?? '',
            'author_uri'  => $data['AuthorURI'] ?? '',
            'plugin_uri'  => $data['PluginURI'] ?? '',
            'text_domain' => $data['TextDomain'] ?? '',
            'requires_wp' => $data['RequiresWP'] ?? '',
            'requires_php'=> $data['RequiresPHP'] ?? '',
            'active'      => in_array( $plugin_file, $active_plugins, true ),
        );
    }
}
