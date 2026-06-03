<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Media {

    /**
     * Execute a media tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, int, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_media':
                return self::list_media( $params );
            case 'get_media':
                return self::get_media( $params );
            case 'upload_media':
                return self::upload_media( $params );
            case 'update_media':
                return self::update_media( $params );
            case 'delete_media':
                return self::delete_media( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_media( array $params ): array {
        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'paged'          => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
        );

        if ( ! empty( $params['mime_type'] ) ) {
            $args['post_mime_type'] = sanitize_text_field( $params['mime_type'] );
        }

        if ( ! empty( $params['search'] ) ) {
            $args['s'] = sanitize_text_field( $params['search'] );
        }

        $query   = new WP_Query( $args );
        $results = array();

        while ( $query->have_posts() ) {
            $query->the_post();
            $post       = get_post();
            $results[]  = array(
                'id'        => $post->ID,
                'title'     => get_the_title(),
                'url'       => wp_get_attachment_url( $post->ID ),
                'mime_type' => $post->post_mime_type,
                'date'      => $post->post_date_gmt,
            );
        }
        wp_reset_postdata();

        return $results;
    }

    private static function get_media( array $params ): array|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Media id is required.' );
        }

        $post = get_post( absint( $params['id'] ) );
        if ( ! $post || $post->post_type !== 'attachment' ) {
            return new WP_Error( 'wmcp_not_found', 'Media item not found.' );
        }

        $meta_data = wp_get_attachment_metadata( $post->ID );
        $sizes     = array();

        if ( ! empty( $meta_data['sizes'] ) ) {
            foreach ( $meta_data['sizes'] as $size_name => $size_info ) {
                $sizes[ $size_name ] = array(
                    'file'   => $size_info['file'],
                    'width'  => $size_info['width'],
                    'height' => $size_info['height'],
                    'url'    => wp_get_attachment_image_url( $post->ID, $size_name ),
                );
            }
        }

        return array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'url'       => wp_get_attachment_url( $post->ID ),
            'mime_type' => $post->post_mime_type,
            'alt_text'  => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
            'caption'   => $post->post_excerpt,
            'description' => $post->post_content,
            'sizes'     => $sizes,
        );
    }

    private static function upload_media( array $params ): int|WP_Error {
        if ( empty( $params['url'] ) ) {
            return new WP_Error( 'wmcp_missing_url', 'URL is required for media upload.' );
        }

        // Require WordPress admin functions for sideloading.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download the file to a temp location.
        $temp_file = download_url( esc_url_raw( $params['url'] ), 30 );

        if ( is_wp_error( $temp_file ) ) {
            return $temp_file;
        }

        // Determine the filename from the URL path.
        $path_parts = pathinfo( parse_url( $params['url'], PHP_URL_PATH ) );
        $filename   = isset( $path_parts['basename'] ) ? $path_parts['basename'] : 'upload.bin';

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $temp_file,
        );

        // Build optional post data (title).
        $post_data = array();
        if ( ! empty( $params['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $params['title'] );
        }

        // Sideload into the media library.
        $attachment_id = media_handle_sideload( $file_array, 0, $post_data );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $temp_file );
            return $attachment_id;
        }

        // Set alt text if provided.
        if ( ! empty( $params['alt_text'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $params['alt_text'] ) );
        }

        return $attachment_id;
    }

    private static function update_media( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Media id is required.' );
        }

        $media_id  = absint( $params['id'] );
        $post_data = array( 'ID' => $media_id );

        if ( isset( $params['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $params['title'] );
        }
        if ( isset( $params['caption'] ) ) {
            $post_data['post_excerpt'] = sanitize_text_field( $params['caption'] );
        }
        if ( isset( $params['description'] ) ) {
            $post_data['post_content'] = sanitize_textarea_field( $params['description'] );
        }

        $result = wp_update_post( $post_data, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( isset( $params['alt_text'] ) ) {
            update_post_meta( $media_id, '_wp_attachment_image_alt', sanitize_text_field( $params['alt_text'] ) );
        }

        return true;
    }

    private static function delete_media( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Media id is required.' );
        }

        $force  = ! empty( $params['force'] );
        $result = wp_delete_attachment( absint( $params['id'] ), $force );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete media item.' );
        }

        return true;
    }
}
