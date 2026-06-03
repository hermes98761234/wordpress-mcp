<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMCP_Tool_WooCommerce {

    /**
     * Execute a WooCommerce tool action.
     *
     * @param string $action The action to perform.
     * @param array  $params Parameters for the action.
     * @return mixed Result array or WP_Error.
     */
    public static function execute( string $action, array $params ): mixed {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'wmcp_wc_inactive', 'WooCommerce is not active.' );
        }

        switch ( $action ) {
            case 'list_products':
                return self::list_products( $params );
            case 'get_product':
                return self::get_product( $params );
            case 'update_product':
                return self::update_product( $params );
            case 'list_orders':
                return self::list_orders( $params );
            case 'get_order':
                return self::get_order( $params );
            case 'update_order_status':
                return self::update_order_status( $params );
            case 'list_customers':
                return self::list_customers( $params );
            default:
                return new WP_Error( 'wmcp_unknown_action', sprintf( 'Unknown action: %s', $action ) );
        }
    }

    private static function list_products( array $params ): array {
        $args = array(
            'status'  => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'publish',
            'limit'   => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'page'    => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        if ( ! empty( $params['search'] ) ) {
            $args['s'] = sanitize_text_field( $params['search'] );
        }

        if ( ! empty( $params['category_id'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => absint( $params['category_id'] ),
                ),
            );
        }

        $products = wc_get_products( $args );
        $results  = array();

        foreach ( $products as $product ) {
            $results[] = array(
                'id'           => $product->get_id(),
                'name'         => $product->get_name(),
                'price'        => $product->get_price(),
                'stock_status' => $product->get_stock_status(),
                'status'       => $product->get_status(),
            );
        }

        return $results;
    }

    private static function get_product( array $params ): array|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Product id is required.' );
        }

        $product = wc_get_product( absint( $params['id'] ) );
        if ( ! $product ) {
            return new WP_Error( 'wmcp_not_found', 'Product not found.' );
        }

        return array(
            'id'               => $product->get_id(),
            'name'             => $product->get_name(),
            'slug'             => $product->get_slug(),
            'sku'              => $product->get_sku(),
            'description'      => $product->get_description(),
            'short_description'=> $product->get_short_description(),
            'price'            => $product->get_price(),
            'regular_price'    => $product->get_regular_price(),
            'sale_price'       => $product->get_sale_price(),
            'stock_quantity'   => $product->get_stock_quantity(),
            'stock_status'     => $product->get_stock_status(),
            'status'           => $product->get_status(),
            'type'             => $product->get_type(),
            'categories'       => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
            'tags'             => wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) ),
            'image'            => wp_get_attachment_url( $product->get_image_id() ),
            'date_created'     => $product->get_date_created() ? $product->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            'date_modified'    => $product->get_date_modified() ? $product->get_date_modified()->date( 'Y-m-d H:i:s' ) : '',
        );
    }

    private static function update_product( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Product id is required.' );
        }

        $product = wc_get_product( absint( $params['id'] ) );
        if ( ! $product ) {
            return new WP_Error( 'wmcp_not_found', 'Product not found.' );
        }

        if ( isset( $params['price'] ) ) {
            $product->set_price( wc_format_decimal( $params['price'] ) );
        }
        if ( isset( $params['regular_price'] ) ) {
            $product->set_regular_price( wc_format_decimal( $params['regular_price'] ) );
        }
        if ( isset( $params['sale_price'] ) ) {
            $product->set_sale_price( wc_format_decimal( $params['sale_price'] ) );
        }
        if ( isset( $params['stock_quantity'] ) ) {
            $product->set_stock_quantity( absint( $params['stock_quantity'] ) );
        }
        if ( isset( $params['status'] ) ) {
            $product->set_status( sanitize_key( $params['status'] ) );
        }
        if ( isset( $params['description'] ) ) {
            $product->set_description( wp_kses_post( $params['description'] ) );
        }

        $product->save();

        return true;
    }

    private static function list_orders( array $params ): array {
        $args = array(
            'limit'    => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'page'     => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'paginate' => false,
        );

        if ( ! empty( $params['status'] ) ) {
            $args['status'] = sanitize_key( $params['status'] );
        }

        if ( ! empty( $params['customer_id'] ) ) {
            $args['customer_id'] = absint( $params['customer_id'] );
        }

        $orders  = wc_get_orders( $args );
        $results = array();

        foreach ( $orders as $order ) {
            $results[] = array(
                'id'            => $order->get_id(),
                'status'        => $order->get_status(),
                'total'         => $order->get_total(),
                'currency'      => $order->get_currency(),
                'customer_email'=> $order->get_billing_email(),
                'date'          => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            );
        }

        return $results;
    }

    private static function get_order( array $params ): array|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Order id is required.' );
        }

        $order = wc_get_order( absint( $params['id'] ) );
        if ( ! $order ) {
            return new WP_Error( 'wmcp_not_found', 'Order not found.' );
        }

        $line_items = array();
        foreach ( $order->get_items() as $item ) {
            $line_items[] = array(
                'product_id' => $item->get_product_id(),
                'name'       => $item->get_name(),
                'quantity'   => $item->get_quantity(),
                'total'      => $item->get_total(),
            );
        }

        return array(
            'id'             => $order->get_id(),
            'status'         => $order->get_status(),
            'total'          => $order->get_total(),
            'currency'       => $order->get_currency(),
            'customer_id'    => $order->get_customer_id(),
            'customer_email' => $order->get_billing_email(),
            'customer_name'  => $order->get_formatted_billing_full_name(),
            'payment_method' => $order->get_payment_method_title(),
            'shipping_method'=> $order->get_shipping_method(),
            'date'           => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            'line_items'     => $line_items,
            'billing'        => $order->get_address( 'billing' ),
            'shipping'       => $order->get_address( 'shipping' ),
        );
    }

    private static function update_order_status( array $params ): bool|WP_Error {
        if ( empty( $params['id'] ) ) {
            return new WP_Error( 'wmcp_missing_id', 'Order id is required.' );
        }
        if ( empty( $params['status'] ) ) {
            return new WP_Error( 'wmcp_missing_status', 'Order status is required.' );
        }

        $order = wc_get_order( absint( $params['id'] ) );
        if ( ! $order ) {
            return new WP_Error( 'wmcp_not_found', 'Order not found.' );
        }

        $order->update_status( sanitize_key( $params['status'] ) );

        return true;
    }

    private static function list_customers( array $params ): array {
        $args = array(
            'role'   => 'customer',
            'number' => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10,
            'paged'  => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
        );

        if ( ! empty( $params['search'] ) ) {
            $args['search']         = '*' . sanitize_text_field( $params['search'] ) . '*';
            $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
        }

        $query   = new WP_User_Query( $args );
        $users   = $query->get_results();
        $results = array();

        foreach ( $users as $user ) {
            $results[] = array(
                'id'           => $user->ID,
                'email'        => $user->user_email,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'orders_count' => wc_get_customer_order_count( $user->ID ),
            );
        }

        return $results;
    }
}
