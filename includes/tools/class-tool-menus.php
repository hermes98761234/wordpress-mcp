<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Menus {

    /**
     * Execute a menus tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, int, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_menus':
                return self::list_menus();
            case 'get_menu':
                return self::get_menu( $params );
            case 'list_menu_locations':
                return self::list_menu_locations();
            case 'assign_menu_to_location':
                return self::assign_menu_to_location( $params );
            case 'create_menu':
                return self::create_menu( $params );
            case 'add_menu_item':
                return self::add_menu_item( $params );
            case 'delete_menu_item':
                return self::delete_menu_item( $params );
            case 'delete_menu':
                return self::delete_menu( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_menus(): array {
        $menus  = wp_get_nav_menus( array( 'hide_empty' => false ) );
        $result = array();

        foreach ( $menus as $menu ) {
            $locations = get_registered_nav_menu_locations();
            $menu_locations = array();

            if ( is_array( $locations ) ) {
                foreach ( $locations as $location => $assigned_id ) {
                    if ( (int) $assigned_id === (int) $menu->term_id ) {
                        $menu_locations[] = $location;
                    }
                }
            }

            $result[] = array(
                'id'        => (int) $menu->term_id,
                'name'      => $menu->name,
                'slug'      => $menu->slug,
                'locations' => $menu_locations,
            );
        }

        return $result;
    }

    private static function get_menu( array $params ): array|WP_Error {
        if ( empty( $params['menu_id'] ) ) {
            return new WP_Error( 'wmcp_missing_menu_id', 'menu_id is required.' );
        }

        $menu_id = absint( $params['menu_id'] );
        $menu    = wp_get_nav_menu_object( $menu_id );

        if ( ! $menu ) {
            return new WP_Error( 'wmcp_not_found', 'Menu not found.' );
        }

        $items = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );

        if ( ! is_array( $items ) ) {
            $items = array();
        }

        return array(
            'id'    => (int) $menu->term_id,
            'name'  => $menu->name,
            'slug'  => $menu->slug,
            'items' => self::build_menu_tree( $items ),
        );
    }

    private static function list_menu_locations(): array {
        $locations = get_registered_nav_menu_locations();
        $result    = array();

        if ( is_array( $locations ) ) {
            foreach ( $locations as $location => $menu_id ) {
                $result[] = array(
                    'location' => $location,
                    'menu_id'  => (int) $menu_id,
                );
            }
        }

        return $result;
    }

    private static function assign_menu_to_location( array $params ): bool|WP_Error {
        if ( empty( $params['menu_id'] ) || empty( $params['location'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'menu_id and location are required.' );
        }

        $menu_id  = absint( $params['menu_id'] );
        $location = sanitize_key( $params['location'] );

        $locations = get_registered_nav_menu_locations();

        if ( ! isset( $locations[ $location ] ) ) {
            return new WP_Error( 'wmcp_not_found', 'Menu location not found.' );
        }

        $theme_locations = get_nav_menu_locations();
        $theme_locations[ $location ] = $menu_id;

        set_theme_mod( 'nav_menu_locations', $theme_locations );

        return true;
    }

    private static function create_menu( array $params ): int|WP_Error {
        if ( empty( $params['name'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'Menu name is required.' );
        }

        $name   = sanitize_text_field( $params['name'] );
        $slug   = ! empty( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
        $args   = array();

        if ( $slug ) {
            $args['slug'] = $slug;
        }

        $result = wp_create_nav_menu( $name, $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $menu_id = (int) $result;

        if ( ! empty( $params['location'] ) ) {
            $location = sanitize_key( $params['location'] );
            $locations = get_registered_nav_menu_locations();

            if ( isset( $locations[ $location ] ) ) {
                $theme_locations = get_nav_menu_locations();
                $theme_locations[ $location ] = $menu_id;
                set_theme_mod( 'nav_menu_locations', $theme_locations );
            }
        }

        return $menu_id;
    }

    private static function add_menu_item( array $params ): int|WP_Error {
        if ( empty( $params['menu_id'] ) || empty( $params['type'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'menu_id and type are required.' );
        }

        $menu_id = absint( $params['menu_id'] );
        $type    = sanitize_key( $params['type'] );

        $menu = wp_get_nav_menu_object( $menu_id );
        if ( ! $menu ) {
            return new WP_Error( 'wmcp_not_found', 'Menu not found.' );
        }

        $item_data = array(
            'menu-item-type'      => $type,
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => isset( $params['parent_item'] ) ? absint( $params['parent_item'] ) : 0,
            'menu-item-position'  => isset( $params['position'] ) ? absint( $params['position'] ) : 0,
        );

        switch ( $type ) {
            case 'post_type':
                if ( empty( $params['object_id'] ) ) {
                    return new WP_Error( 'wmcp_missing_object_id', 'object_id is required for post_type menu items.' );
                }
                $item_data['menu-item-object-id'] = absint( $params['object_id'] );
                $item_data['menu-item-object']    = get_post_type( absint( $params['object_id'] ) );
                $post = get_post( absint( $params['object_id'] ) );
                $item_data['menu-item-title'] = $post ? $post->post_title : '';
                break;

            case 'taxonomy':
                if ( empty( $params['object_id'] ) ) {
                    return new WP_Error( 'wmcp_missing_object_id', 'object_id is required for taxonomy menu items.' );
                }
                $item_data['menu-item-object-id'] = absint( $params['object_id'] );
                $item_data['menu-item-object']    = get_term( absint( $params['object_id'] ) )->taxonomy ?? '';
                $term = get_term( absint( $params['object_id'] ) );
                $item_data['menu-item-title'] = $term ? $term->name : '';
                break;

            case 'custom':
                if ( empty( $params['url'] ) || empty( $params['title'] ) ) {
                    return new WP_Error( 'wmcp_missing_fields', 'url and title are required for custom menu items.' );
                }
                $item_data['menu-item-url']   = esc_url_raw( $params['url'] );
                $item_data['menu-item-title'] = sanitize_text_field( $params['title'] );
                break;

            default:
                return new WP_Error( 'wmcp_invalid_type', 'Invalid menu item type. Use post_type, taxonomy, or custom.' );
        }

        $item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

        if ( is_wp_error( $item_id ) ) {
            return $item_id;
        }

        return $item_id;
    }

    private static function delete_menu_item( array $params ): bool|WP_Error {
        if ( empty( $params['menu_item_id'] ) ) {
            return new WP_Error( 'wmcp_missing_menu_item_id', 'menu_item_id is required.' );
        }

        $menu_item_id = absint( $params['menu_item_id'] );
        $result       = wp_delete_post( $menu_item_id, true );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete menu item.' );
        }

        return true;
    }

    private static function delete_menu( array $params ): bool|WP_Error {
        if ( empty( $params['menu_id'] ) ) {
            return new WP_Error( 'wmcp_missing_menu_id', 'menu_id is required.' );
        }

        $menu_id = absint( $params['menu_id'] );
        $result  = wp_delete_nav_menu( $menu_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete menu.' );
        }

        return true;
    }

    /**
     * Build a nested tree from flat menu items list.
     *
     * @param array $items Flat array of menu item objects.
     * @return array Nested tree with children.
     */
    private static function build_menu_tree( array $items ): array {
        $tree  = array();
        $index = array();

        // Index by ID
        foreach ( $items as $item ) {
            $index[ $item->ID ] = array(
                'id'         => (int) $item->ID,
                'title'      => $item->title,
                'url'        => $item->url,
                'type'       => $item->type,
                'object_id'  => (int) $item->object_id,
                'parent_id'  => (int) $item->menu_item_parent,
                'position'   => (int) $item->menu_order,
                'children'   => array(),
            );
        }

        // Build tree
        foreach ( $index as $id => &$node ) {
            if ( $node['parent_id'] && isset( $index[ $node['parent_id'] ] ) ) {
                $index[ $node['parent_id'] ]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset( $node );

        return $tree;
    }
}
