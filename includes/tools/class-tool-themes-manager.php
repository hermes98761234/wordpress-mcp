<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Themes {

    /**
     * Execute a themes tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_themes':
                return self::list_themes();
            case 'get_active_theme':
                return self::get_active_theme();
            case 'activate_theme':
                return self::activate_theme( $params );
            case 'get_theme_mods':
                return self::get_theme_mods( $params );
            case 'update_theme_mod':
                return self::update_theme_mod( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_themes(): array {
        $themes = wp_get_themes( array( 'allowed' => true ) );
        $result = array();

        foreach ( $themes as $stylesheet => $theme ) {
            $result[] = array(
                'stylesheet'  => $stylesheet,
                'name'        => $theme->get( 'Name' ),
                'version'     => $theme->get( 'Version' ),
                'description' => $theme->get( 'Description' ),
                'active'      => ( $stylesheet === get_option( 'stylesheet' ) ),
            );
        }

        return $result;
    }

    private static function get_active_theme(): array|WP_Error {
        $theme = wp_get_theme();

        if ( ! $theme->exists() ) {
            return new WP_Error( 'wmcp_not_found', 'No active theme found.' );
        }

        return array(
            'stylesheet'  => $theme->get_stylesheet(),
            'name'        => $theme->get( 'Name' ),
            'version'     => $theme->get( 'Version' ),
            'description' => $theme->get( 'Description' ),
            'author'      => $theme->get( 'Author' ),
            'template'    => $theme->get_template(),
            'theme_root'  => $theme->get_theme_root(),
        );
    }

    private static function activate_theme( array $params ): bool|WP_Error {
        if ( empty( $params['stylesheet'] ) ) {
            return new WP_Error( 'wmcp_missing_stylesheet', 'Theme stylesheet is required.' );
        }

        $stylesheet = sanitize_text_field( $params['stylesheet'] );
        $theme      = wp_get_theme( $stylesheet );

        if ( ! $theme->exists() ) {
            return new WP_Error( 'wmcp_not_found', 'Theme not found.' );
        }

        switch_theme( $stylesheet );

        return true;
    }

    private static function get_theme_mods( array $params ): array {
        $stylesheet = isset( $params['stylesheet'] ) ? sanitize_text_field( $params['stylesheet'] ) : get_option( 'stylesheet' );

        return get_theme_mods( $stylesheet );
    }

    private static function update_theme_mod( array $params ): bool|WP_Error {
        if ( empty( $params['mod_name'] ) || ! isset( $params['value'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'mod_name and value are required.' );
        }

        $mod_name = sanitize_text_field( $params['mod_name'] );
        $value    = $params['value'];

        set_theme_mod( $mod_name, $value );

        return true;
    }
}
