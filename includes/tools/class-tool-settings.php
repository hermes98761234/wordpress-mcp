<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Settings {

    /**
     * Execute a settings tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, value, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'get_option':
                return self::get_option( $params );
            case 'update_option':
                return self::update_option( $params );
            case 'list_options':
                return self::list_options( $params );
            case 'get_site_info':
                return self::get_site_info();
            case 'update_site_info':
                return self::update_site_info( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function get_option( array $params ): mixed {
        if ( empty( $params['key'] ) ) {
            return new WP_Error( 'wmcp_missing_key', 'Option key is required.' );
        }

        return get_option( sanitize_key( $params['key'] ) );
    }

    private static function update_option( array $params ): bool|WP_Error {
        if ( empty( $params['key'] ) || ! isset( $params['value'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'Key and value are required.' );
        }

        $updated = update_option( sanitize_key( $params['key'] ), $params['value'] );

        if ( ! $updated ) {
            return new WP_Error( 'wmcp_update_failed', 'Failed to update option.' );
        }

        return true;
    }

    private static function list_options( array $params ): array {
        global $wpdb;

        $prefix = isset( $params['prefix'] ) ? sanitize_text_field( $params['prefix'] ) : '';

        // Skip transients (option_name starts with _transient or _site_transient)
        // and other internal keys starting with _
        $skip_patterns = array( '_transient_%', '_site_transient_%', '%_transient_%' );

        if ( $prefix ) {
            $like     = $wpdb->esc_like( $prefix ) . '%';
            $not_like = implode( ' AND ', array_fill( 0, count( $skip_patterns ), 'option_name NOT LIKE %s' ) );
            $query    = $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s" . ( $not_like ? " AND {$not_like}" : '' ) . ' ORDER BY option_name LIMIT 200',
                array_merge( array( $like ), $skip_patterns )
            );
        } else {
            $not_like = implode( ' AND ', array_fill( 0, count( $skip_patterns ), 'option_name NOT LIKE %s' ) );
            $query    = $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE {$not_like} ORDER BY option_name LIMIT 200",
                $skip_patterns
            );
        }

        $results = $wpdb->get_results( $query );
        $options = array();

        foreach ( $results as $row ) {
            $options[] = array(
                'key'   => $row->option_name,
                'value' => maybe_unserialize( $row->option_value ),
            );
        }

        return $options;
    }

    private static function get_site_info(): array {
        return array(
            'blogname'        => get_option( 'blogname' ),
            'blogdescription' => get_option( 'blogdescription' ),
            'siteurl'         => get_option( 'siteurl' ),
            'home'            => get_option( 'home' ),
            'admin_email'     => get_option( 'admin_email' ),
            'timezone'        => wp_timezone_string(),
            'date_format'     => get_option( 'date_format' ),
            'time_format'     => get_option( 'time_format' ),
            'language'        => get_option( 'WPLANG' ),
        );
    }

    private static function update_site_info( array $params ): bool|WP_Error {
        $allowed_keys = array(
            'blogname',
            'blogdescription',
            'siteurl',
            'home',
            'admin_email',
            'date_format',
            'time_format',
        );

        $updated_any = false;

        foreach ( $allowed_keys as $key ) {
            if ( isset( $params[ $key ] ) ) {
                $value = sanitize_text_field( $params[ $key ] );

                if ( $key === 'admin_email' ) {
                    $value = sanitize_email( $params[ $key ] );
                }

                update_option( $key, $value );
                $updated_any = true;
            }
        }

        if ( isset( $params['language'] ) ) {
            update_option( 'WPLANG', sanitize_text_field( $params['language'] ) );
            $updated_any = true;
        }

        if ( ! $updated_any ) {
            return new WP_Error( 'wmcp_no_fields', 'No valid fields provided to update.' );
        }

        return true;
    }
}
