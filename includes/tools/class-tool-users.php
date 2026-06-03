<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_Users {

    /**
     * Execute a users tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array, int, bool, or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        switch ( $action ) {
            case 'list_users':
                return self::list_users( $params );
            case 'get_user':
                return self::get_user( $params );
            case 'create_user':
                return self::create_user( $params );
            case 'update_user':
                return self::update_user( $params );
            case 'delete_user':
                return self::delete_user( $params );
            case 'list_roles':
                return self::list_roles();
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_users( array $params ): array {
        $args = array(
            'number' => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'paged'  => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
        );

        if ( ! empty( $params['role'] ) ) {
            $args['role'] = sanitize_key( $params['role'] );
        }

        if ( ! empty( $params['search'] ) ) {
            $args['search'] = sanitize_text_field( '*' . $params['search'] . '*' );
        }

        $user_query = new WP_User_Query( $args );
        $users      = $user_query->get_results();
        $results    = array();

        foreach ( $users as $user ) {
            $results[] = array(
                'id'           => $user->ID,
                'login'        => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'roles'        => $user->roles,
            );
        }

        return $results;
    }

    private static function get_user( array $params ): array|WP_Error {
        $user = null;

        if ( ! empty( $params['id'] ) ) {
            $user = get_user_by( 'id', absint( $params['id'] ) );
        } elseif ( ! empty( $params['login'] ) ) {
            $user = get_user_by( 'login', sanitize_user( $params['login'] ) );
        } else {
            return new WP_Error( 'wmcp_missing_param', 'User id or login is required.' );
        }

        if ( ! $user ) {
            return new WP_Error( 'wmcp_not_found', 'User not found.' );
        }

        return array(
            'id'           => $user->ID,
            'login'        => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
            'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
            'roles'        => $user->roles,
            'registered'   => $user->user_registered,
        );
    }

    private static function create_user( array $params ): int|WP_Error {
        if ( empty( $params['username'] ) || empty( $params['email'] ) || empty( $params['password'] ) ) {
            return new WP_Error( 'wmcp_missing_fields', 'Username, email, and password are required.' );
        }

        $user_data = array(
            'user_login' => sanitize_user( $params['username'] ),
            'user_email' => sanitize_email( $params['email'] ),
            'user_pass'  => $params['password'],
            'role'       => isset( $params['role'] ) ? sanitize_key( $params['role'] ) : 'subscriber',
        );

        if ( isset( $params['first_name'] ) ) {
            $user_data['first_name'] = sanitize_text_field( $params['first_name'] );
        }
        if ( isset( $params['last_name'] ) ) {
            $user_data['last_name'] = sanitize_text_field( $params['last_name'] );
        }

        $notify = ! empty( $params['send_notification'] );
        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        if ( $notify ) {
            wp_new_user_notification( $user_id, null, 'user' );
        }

        return $user_id;
    }

    private static function update_user( array $params ): int|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'User id is required.' );
        }

        $user_data = array( 'ID' => absint( $params['id'] ) );

        if ( isset( $params['email'] ) )        $user_data['user_email']   = sanitize_email( $params['email'] );
        if ( isset( $params['password'] ) )     $user_data['user_pass']    = $params['password'];
        if ( isset( $params['first_name'] ) )   $user_data['first_name']   = sanitize_text_field( $params['first_name'] );
        if ( isset( $params['last_name'] ) )    $user_data['last_name']    = sanitize_text_field( $params['last_name'] );
        if ( isset( $params['display_name'] ) ) $user_data['display_name'] = sanitize_text_field( $params['display_name'] );
        if ( isset( $params['role'] ) )         $user_data['role']         = sanitize_key( $params['role'] );

        $result = wp_update_user( $user_data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result;
    }

    private static function delete_user( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'User id is required.' );
        }

        $reassign = isset( $params['reassign'] ) ? absint( $params['reassign'] ) : null;
        $result   = wp_delete_user( absint( $params['id'] ), $reassign );

        if ( ! $result ) {
            return new WP_Error( 'wmcp_delete_failed', 'Failed to delete user.' );
        }

        return true;
    }

    private static function list_roles(): array {
        $wp_roles = wp_roles();
        $results  = array();

        foreach ( $wp_roles->roles as $name => $role ) {
            $results[] = array(
                'name'  => $name,
                'label' => translate_user_role( $role['name'] ),
            );
        }

        return $results;
    }
}
