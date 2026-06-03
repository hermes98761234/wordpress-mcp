<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Posts {

    /**
     * Execute a posts tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, int, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_posts':
                return self::list_posts( $params );
            case 'get_post':
                return self::get_post( $params );
            case 'create_post':
                return self::create_post( $params );
            case 'update_post':
                return self::update_post( $params );
            case 'delete_post':
                return self::delete_post( $params );
            case 'list_post_types':
                return self::list_post_types();
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_posts( array $params ): array {
        $args = array(
            'post_type'      => isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : 'post',
            'post_status'    => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'publish',
            'posts_per_page' => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'paged'          => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
        );

        if ( ! empty( $params['search'] ) ) {
            $args['s'] = sanitize_text_field( $params['search'] );
        }

        $query   = new WP_Query( $args );
        $results = array();

        while ( $query->have_posts() ) {
            $query->the_post();
            $post       = get_post();
            $results[]  = array(
                'id'      => $post->ID,
                'title'   => get_the_title(),
                'status'  => $post->post_status,
                'date'    => $post->post_date_gmt,
                'link'    => get_permalink(),
                'excerpt' => get_the_excerpt(),
            );
        }
        wp_reset_postdata();

        return $results;
    }

    private static function get_post( array $params ): array|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Post id is required.' );
        }

        $post = get_post( absint( $params['id'] ) );
        if ( ! $post ) {
            return new WP_Error( 'wmcp_not_found', 'Post not found.' );
        }

        return array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'content'   => $post->post_content,
            'excerpt'   => $post->post_excerpt,
            'status'    => $post->post_status,
            'post_type' => $post->post_type,
            'date'      => $post->post_date_gmt,
            'link'      => get_permalink( $post->ID ),
            'meta'      => get_post_meta( $post->ID ),
            'terms'     => self::get_post_terms( $post->ID ),
        );
    }

    private static function create_post( array $params ): int|WP_Error {
        if ( empty( $params['title'] ) || empty( $params['content'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'Title and content are required.' );
        }

        $post_data = array(
            'post_title'   => sanitize_text_field( $params['title'] ),
            'post_content' => wp_kses_post( $params['content'] ),
            'post_status'  => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'draft',
            'post_type'    => isset( $params['post_type'] ) ? sanitize_key( $params['post_type'] ) : 'post',
        );

        if ( isset( $params['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
        }

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'absint', $params['categories'] ), 'category' );
        }

        if ( ! empty( $params['tags'] ) && is_array( $params['tags'] ) ) {
            wp_set_object_terms( $post_id, $params['tags'], 'post_tag' );
        }

        if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) ) {
            foreach ( $params['meta'] as $key => $value ) {
                update_post_meta( $post_id, sanitize_key( $key ), $value );
            }
        }

        return $post_id;
    }

    private static function update_post( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Post id is required.' );
        }

        $post_data = array( 'ID' => absint( $params['id'] ) );

        if ( isset( $params['title'] ) )   $post_data['post_title']   = sanitize_text_field( $params['title'] );
        if ( isset( $params['content'] ) ) $post_data['post_content'] = wp_kses_post( $params['content'] );
        if ( isset( $params['status'] ) )  $post_data['post_status']  = sanitize_key( $params['status'] );
        if ( isset( $params['excerpt'] ) ) $post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );

        $result = wp_update_post( $post_data, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( isset( $params['categories'] ) && is_array( $params['categories'] ) ) {
            wp_set_object_terms( absint( $params['id'] ), array_map( 'absint', $params['categories'] ), 'category' );
        }

        if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
            wp_set_object_terms( absint( $params['id'] ), $params['tags'], 'post_tag' );
        }

        if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) ) {
            foreach ( $params['meta'] as $key => $value ) {
                update_post_meta( absint( $params['id'] ), sanitize_key( $key ), $value );
            }
        }

        return true;
    }

    private static function delete_post( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Post id is required.' );
        }

        $force  = ! empty( $params['force'] );
        $result = wp_delete_post( absint( $params['id'] ), $force );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete post.' );
        }

        return true;
    }

    private static function list_post_types(): array {
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        $result     = array();

        foreach ( $post_types as $pt ) {
            $result[] = array(
                'name'  => $pt->name,
                'label' => $pt->label,
            );
        }

        return $result;
    }

    private static function get_post_terms( int $post_id ): array {
        $taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'names' );
        $terms      = array();

        foreach ( $taxonomies as $tax ) {
            $post_terms = get_the_terms( $post_id, $tax );
            if ( $post_terms && ! is_wp_error( $post_terms ) ) {
                $terms[ $tax ] = array_map( function ( $t ) {
                    return array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
                }, $post_terms );
            }
        }

        return $terms;
    }
}
