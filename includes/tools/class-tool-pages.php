<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Pages {

    /**
     * Execute a pages tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, int, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_pages':
                return self::list_pages( $params );
            case 'get_page':
                return self::get_page( $params );
            case 'create_page':
                return self::create_page( $params );
            case 'update_page':
                return self::update_page( $params );
            case 'delete_page':
                return self::delete_page( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_pages( array $params ): array {
        $args = array(
            'post_type'      => 'page',
            'post_status'    => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'publish',
            'posts_per_page' => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'paged'          => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
            'post_parent'    => isset( $params['parent'] ) ? absint( $params['parent'] ) : 0,
        );

        $query   = new WP_Query( $args );
        $results = array();

        while ( $query->have_posts() ) {
            $query->the_post();
            $post       = get_post();
            $results[]  = array(
                'id'     => $post->ID,
                'title'  => get_the_title(),
                'status' => $post->post_status,
                'parent' => $post->post_parent,
                'order'  => $post->menu_order,
                'link'   => get_permalink(),
            );
        }
        wp_reset_postdata();

        return $results;
    }

    private static function get_page( array $params ): array|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Page id is required.' );
        }

        $post = get_post( absint( $params['id'] ) );
        if ( ! $post || $post->post_type !== 'page' ) {
            return new WP_Error( 'wmcp_not_found', 'Page not found.' );
        }

        return array(
            'id'       => $post->ID,
            'title'    => $post->post_title,
            'content'  => $post->post_content,
            'excerpt'  => $post->post_excerpt,
            'status'   => $post->post_status,
            'parent'   => $post->post_parent,
            'order'    => $post->menu_order,
            'template' => get_page_template_slug( $post->ID ),
            'date'     => $post->post_date_gmt,
            'link'     => get_permalink( $post->ID ),
            'meta'     => get_post_meta( $post->ID ),
        );
    }

    private static function create_page( array $params ): int|WP_Error {
        if ( empty( $params['title'] ) || empty( $params['content'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'Title and content are required.' );
        }

        $page_data = array(
            'post_title'   => sanitize_text_field( $params['title'] ),
            'post_content' => wp_kses_post( $params['content'] ),
            'post_status'  => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'draft',
            'post_type'    => 'page',
            'post_parent'  => isset( $params['parent'] ) ? absint( $params['parent'] ) : 0,
            'menu_order'   => isset( $params['order'] ) ? absint( $params['order'] ) : 0,
        );

        if ( isset( $params['template'] ) ) {
            $page_data['page_template'] = sanitize_text_field( $params['template'] );
        }

        $page_id = wp_insert_post( $page_data, true );
        if ( is_wp_error( $page_id ) ) {
            return $page_id;
        }

        if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) ) {
            foreach ( $params['meta'] as $key => $value ) {
                update_post_meta( $page_id, sanitize_key( $key ), $value );
            }
        }

        return $page_id;
    }

    private static function update_page( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Page id is required.' );
        }

        $page_data = array( 'ID' => absint( $params['id'] ) );

        if ( isset( $params['title'] ) )   $page_data['post_title']   = sanitize_text_field( $params['title'] );
        if ( isset( $params['content'] ) ) $page_data['post_content'] = wp_kses_post( $params['content'] );
        if ( isset( $params['status'] ) )  $page_data['post_status']  = sanitize_key( $params['status'] );
        if ( isset( $params['parent'] ) )  $page_data['post_parent']  = absint( $params['parent'] );
        if ( isset( $params['order'] ) )   $page_data['menu_order']   = absint( $params['order'] );
        if ( isset( $params['template'] ) ) $page_data['page_template'] = sanitize_text_field( $params['template'] );

        $result = wp_update_post( $page_data, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) ) {
            foreach ( $params['meta'] as $key => $value ) {
                update_post_meta( absint( $params['id'] ), sanitize_key( $key ), $value );
            }
        }

        return true;
    }

    private static function delete_page( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Page id is required.' );
        }

        $force  = ! empty( $params['force'] );
        $result = wp_delete_post( absint( $params['id'] ), $force );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete page.' );
        }

        return true;
    }
}
