<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Comments {

    /**
     * Execute a comments tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_comments':
                return self::list_comments( $params );
            case 'get_comment':
                return self::get_comment( $params );
            case 'create_comment':
                return self::create_comment( $params );
            case 'update_comment':
                return self::update_comment( $params );
            case 'delete_comment':
                return self::delete_comment( $params );
            case 'approve_comment':
                return self::approve_comment( $params );
            case 'spam_comment':
                return self::spam_comment( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_comments( array $params ): array {
        $args = array(
            'status'     => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'approve',
            'number'     => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'offset'     => 0,
            'orderby'    => 'comment_date_gmt',
            'order'      => 'DESC',
        );

        if ( ! empty( $params['page'] ) ) {
            $args['offset'] = ( absint( $params['page'] ) - 1 ) * $args['number'];
        }

        if ( ! empty( $params['post_id'] ) ) {
            $args['post_id'] = absint( $params['post_id'] );
        }

        $comments = get_comments( $args );
        $result   = array();

        foreach ( $comments as $comment ) {
            $result[] = self::format_comment( $comment );
        }

        return $result;
    }

    private static function get_comment( array $params ): array|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Comment id is required.' );
        }

        $comment = get_comment( absint( $params['id'] ) );

        if ( ! $comment ) {
            return new WP_Error( 'wmcp_not_found', 'Comment not found.' );
        }

        return self::format_comment( $comment );
    }

    private static function create_comment( array $params ): int|WP_Error {
        if ( empty( $params['post_id'] ) || empty( $params['content'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'post_id and content are required.' );
        }

        $post = get_post( absint( $params['post_id'] ) );
        if ( ! $post ) {
            return new WP_Error( 'wmcp_not_found', 'Post not found.' );
        }

        $comment_data = array(
            'comment_post_ID' => absint( $params['post_id'] ),
            'comment_content' => wp_kses_post( $params['content'] ),
            'comment_approved' => isset( $params['status'] )
                ? ( ( $params['status'] === 'approve' ) ? 1 : 0 )
                : 0,
        );

        if ( ! empty( $params['author_name'] ) ) {
            $comment_data['comment_author'] = sanitize_text_field( $params['author_name'] );
        }

        if ( ! empty( $params['author_email'] ) ) {
            $comment_data['comment_author_email'] = sanitize_email( $params['author_email'] );
        }

        $comment_id = wp_insert_comment( $comment_data );

        if ( ! $comment_id ) {
            return new WP_Error( 'wmcp_insert_failed', 'Failed to create comment.' );
        }

        return $comment_id;
    }

    private static function update_comment( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Comment id is required.' );
        }

        $comment = array( 'comment_ID' => absint( $params['id'] ) );

        if ( isset( $params['content'] ) ) {
            $comment['comment_content'] = wp_kses_post( $params['content'] );
        }

        if ( isset( $params['status'] ) ) {
            $comment['comment_approved'] = ( sanitize_key( $params['status'] ) === 'approve' ) ? 1 : 0;
        }

        $result = wp_update_comment( $comment, true );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( false === $result ) {
            return new WP_Error( 'wmcp_update_failed', 'Failed to update comment.' );
        }

        return true;
    }

    private static function delete_comment( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Comment id is required.' );
        }

        $force    = ! empty( $params['force'] );
        $comment_id = absint( $params['id'] );
        $result   = wp_delete_comment( $comment_id, $force );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete comment.' );
        }

        return true;
    }

    private static function approve_comment( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Comment id is required.' );
        }

        $result = wp_set_comment_status( absint( $params['id'] ), 'approve' );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_approve_failed', 'Failed to approve comment.' );
        }

        return true;
    }

    private static function spam_comment( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Comment id is required.' );
        }

        $comment_id = absint( $params['id'] );
        $result = wp_spam_comment( $comment_id );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_spam_failed', 'Failed to mark comment as spam.' );
        }

        return true;
    }

    private static function format_comment( object $comment ): array {
        return array(
            'id'           => (int) $comment->comment_ID,
            'post_id'      => (int) $comment->comment_post_ID,
            'content'      => $comment->comment_content,
            'author_name'  => $comment->comment_author,
            'author_email' => $comment->comment_author_email,
            'status'       => ( (int) $comment->comment_approved === 1 ) ? 'approved' : 'hold',
            'date_gmt'     => $comment->comment_date_gmt,
        );
    }
}
