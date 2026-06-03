<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Taxonomies {

    /**
     * Execute a taxonomies tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, int, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_taxonomies':
                return self::list_taxonomies();
            case 'list_terms':
                return self::list_terms( $params );
            case 'get_term':
                return self::get_term( $params );
            case 'create_term':
                return self::create_term( $params );
            case 'update_term':
                return self::update_term( $params );
            case 'delete_term':
                return self::delete_term( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_taxonomies(): array {
        $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        $result     = array();

        foreach ( $taxonomies as $tax ) {
            $result[] = array(
                'name'  => $tax->name,
                'label' => $tax->label,
                'hierarchical' => $tax->hierarchical,
            );
        }

        return $result;
    }

    private static function list_terms( array $params ): array|WP_Error {
        if ( empty( $params['taxonomy'] ) ) {
            return new WP_Error( 'wmcp_missing_taxonomy', 'Taxonomy is required.' );
        }

        $taxonomy = sanitize_key( $params['taxonomy'] );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wmcp_not_found', 'Taxonomy not found.' );
        }

        $args = array(
            'taxonomy'   => $taxonomy,
            'number'     => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 20,
            'offset'     => 0,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        if ( ! empty( $params['page'] ) ) {
            $args['offset'] = ( absint( $params['page'] ) - 1 ) * $args['number'];
        }

        if ( ! empty( $params['search'] ) ) {
            $args['search'] = sanitize_text_field( $params['search'] );
        }

        if ( isset( $params['parent'] ) ) {
            $args['parent'] = absint( $params['parent'] );
        }

        $terms  = get_terms( $args );
        $result = array();

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        foreach ( $terms as $term ) {
            $result[] = self::format_term( $term );
        }

        return $result;
    }

    private static function get_term( array $params ): array|WP_Error {
        if ( empty( $params['taxonomy'] ) ) {
            return new WP_Error( 'wmcp_missing_taxonomy', 'Taxonomy is required.' );
        }

        $taxonomy = sanitize_key( $params['taxonomy'] );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wmcp_not_found', 'Taxonomy not found.' );
        }

        if ( ! empty( $params['term_id'] ) ) {
            $term = get_term( absint( $params['term_id'] ), $taxonomy );
        } elseif ( ! empty( $params['slug'] ) ) {
            $term = get_term_by( 'slug', sanitize_title( $params['slug'] ), $taxonomy );
        } else {
            return new WP_Error( 'wmcp_missing_identifier', 'term_id or slug is required.' );
        }

        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'wmcp_not_found', 'Term not found.' );
        }

        return self::format_term( $term );
    }

    private static function create_term( array $params ): int|WP_Error {
        if ( empty( $params['taxonomy'] ) || empty( $params['name'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'taxonomy and name are required.' );
        }

        $taxonomy = sanitize_key( $params['params']['taxonomy'] ?? $params['taxonomy'] );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wmcp_not_found', 'Taxonomy not found.' );
        }

        $args = array();

        if ( ! empty( $params['slug'] ) ) {
            $args['slug'] = sanitize_title( $params['slug'] );
        }

        if ( ! empty( $params['description'] ) ) {
            $args['description'] = sanitize_text_field( $params['description'] );
        }

        if ( isset( $params['parent'] ) ) {
            $args['parent'] = absint( $params['parent'] );
        }

        $result = wp_insert_term( sanitize_text_field( $params['name'] ), $taxonomy, $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result['term_id'];
    }

    private static function update_term( array $params ): array|WP_Error {
        if ( empty( $params['taxonomy'] ) || empty( $params['term_id'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'taxonomy and term_id are required.' );
        }

        $taxonomy = sanitize_key( $params['taxonomy'] );
        $term_id  = absint( $params['term_id'] );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wmcp_not_found', 'Taxonomy not found.' );
        }

        $args = array();

        if ( isset( $params['name'] ) ) {
            $args['name'] = sanitize_text_field( $params['name'] );
        }

        if ( isset( $params['slug'] ) ) {
            $args['slug'] = sanitize_title( $params['slug'] );
        }

        if ( isset( $params['description'] ) ) {
            $args['description'] = sanitize_text_field( $params['description'] );
        }

        if ( isset( $params['parent'] ) ) {
            $args['parent'] = absint( $params['parent'] );
        }

        if ( empty( $args ) ) {
            return new WP_Error( 'wmcp_no_fields', 'No fields provided to update.' );
        }

        $result = wp_update_term( $term_id, $taxonomy, $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return self::format_term( get_term( $result['term_id'], $taxonomy ) );
    }

    private static function delete_term( array $params ): bool|WP_Error {
        if ( empty( $params['taxonomy'] ) || empty( $params['term_id'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'taxonomy and term_id are required.' );
        }

        $taxonomy = sanitize_key( $params['taxonomy'] );
        $term_id  = absint( $params['term_id'] );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wmcp_not_found', 'Taxonomy not found.' );
        }

        $result = wp_delete_term( $term_id, $taxonomy );

        if ( is_wp_error( $result ) || ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete term.' );
        }

        return true;
    }

    private static function format_term( object $term ): array {
        return array(
            'id'          => (int) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'parent'      => (int) $term->parent,
            'count'       => (int) $term->count,
            'taxonomy'    => $term->taxonomy,
        );
    }
}
