<?php
/**
 * WooCommerce Cart Stand-alone and Force Sells Custom Sorting.
 *
 * @package pekky-wc-stand-alone-force-sells-sorting
 * @author Precious Omonzejele (CodeXplorer 🤾🏽‍♂️🥞🦜🤡)
 *
 * @wordpress-plugin
 * Plugin Name: WooCommerce Cart Stand-alone and Force Sells Custom Sorting
 * Plugin URI: https://gist.github.com/Preciousomonze/f41ebbd43b751d00e86fa4207e88413f
 * Description: This MU-Plugin helps sort Predefined Products to behave a certain way when they're added or removed from the cart page. Requires WooCommerce to work.
 * Author: Precious Omonzejele (CodeXplorer 🤾🏽‍♂️🥞🦜🤡)
 * Author URI: https://codexplorer.ninja
 * Version: 2.1.0
 * Requires at least: 5.0
 * Tested up to: 5.4
 * WC requires at least: 4.0
 * WC tested up to: 4.5
 */
class Pekky_WC_Stand_Alone_Force_Sells_Sorting {

    // The grouped Products.

    /**
     *  Stand Alone Products
     *
     * Sample values: array( 23,45,43 );
     *
     * @var array
     */
    protected static $wc_stand_alone_products = array( 25221, 29237, 29359 );

    /**
     * Friendly products that can stay in the cart with other products.
     *
     * But They can't stay with Stand Alone products if found in the cart.
     * Sample values: array( 23,45,43 );
     *
     * @var array
     */

    protected static $wc_other_products = array( 28745, 28746, 28747, 28748 );

    /**
     * Products attached to the standalone products.
     *
     * These should be added to cart when a standalone product is in the cart.
     * Sample values: array( 23,45,43 );
     *
     * @var array
     */
    protected static $wc_force_sells_products = array( 25556, 29234, 29444 );

    /**
     * Products attached to the standalone products.
     *
     * These should be added to cart when a standalone product is in the cart.
     * patterns [{standalone_product_id} => array({force_sells_ids})]
     *
     * @var array
     */
    protected static $wc_force_sells_products_map = array(
        '29237' => array( 25556 ),
        '25221' => array( 29234, 29444 ),
    );

    /**
     * Suggested upsell products to show based on cart amount
     * All prices are assumed in $dollars.
     * patterns [ ['level' => {level number}, 'min_price' => {price}, 'product_id' => {product_id_to_upsell}, 'upsell_note' => {text},
     * 'cart_btn_txt' => {add to cart button text} ] ]
     *
     * @var array
     */
    protected static $wc_suggested_upsell_products_data = array(
        array(
            'level'       => 2,
            'min_price'   => 7,
            'product_id'  => 28745,
            'upsell_note' => 'Massive Value! Get Your Diabetes Under Better Control By Learning About Healthy Nutrition with Diabetes - Upgrade Today',
        ),
        array(
            'level'       => 3,
            'min_price'   => 197,
            'product_id'  => 29237,
            'upsell_note' => 'Get The Full Benefits Of Diabetes Education By Upgrading To The Diabetes Education Program for $297 + $14.99/month! (Great Value!)',
        ),
        array(
            'level'       => 4,
            'min_price'   => 297,
            'product_id'  => 25221,
            'upsell_note' => 'Join The ELITE 3% Group By Upgrading To The 3% Diabetes Program Today for $397 + $19.99/month. (Best Value!)',
        ),
    );


    /**
     * Suggested upsell products to show based on coupon in the cart.
     *
     * All prices are assumed in $dollars.
     * patterns [ ['coupon_code' => {coupon code}, 'product_id' => {product_id_to_upsell}, 'upsell_note' => {text},
     * 'cart_btn_txt' => {add to cart button text}, 'next_upsell' => [ 'coupon_code => {code}, 'product_id' => {id} ] ] ]
     * next_upsell data is useful if you want to upsell another product and coupon code, leave blank if you want to
     * bounce back to upselling the normal product_id.
     *
     * @var array
     */
    protected static $wc_suggested_upsell_coupon_data = array(
        array(
            'coupon_code' => 'free-gift',
            'product_id'  => 226,
            'upsell_note' => 'yaees - Upgrade Today',
            'next_upsell' => array(
                'coupon_code' => '',
                'product_id'  => 0,
            ),
        ),
    );

    /**
     * Boostrap and shoot.
     */
    public static function init() {
        // Manipulate when added to cart.
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'sort_product_add_to_cart_validation' ), 20, 3 );

        // Manipulate when removed from cart.
        add_action( 'woocommerce_remove_cart_item', array( __CLASS__, 'sort_product_removal_from_cart' ), 10, 2 );

        // Show Upsell notice in cart.
        add_action( 'woocommerce_before_cart', array( __CLASS__, 'show_upsell_notice' ) );

    }

    /**
     * Cart Item adding validation
     *
     * Sorts a product being added to cart,
     * checking if its standalone or other
     *
     * @param bool $passed return true or not.
     * @param int  $product_id Product being added to cart.
     * @param int  $quantity quantity of product.
     * @return bool
     */
    public static function sort_product_add_to_cart_validation( $passed, $product_id, $quantity ) {
        $stand_alone_products     = self::$wc_stand_alone_products;
        $other_products           = self::$wc_other_products;
        $force_sells_products_map = self::$wc_force_sells_products_map;

        // Add only level 2 or level three program to the cart.
        if ( in_array( $product_id, $stand_alone_products, true ) ) {

            self::pekky_sort_stand_alone_products( $product_id, $stand_alone_products, $force_sells_products_map );

        } elseif ( in_array( $product_id, $other_products, true ) ) {
            // Allow other products to be in cart together.

            // Make sure stand alone products aren't in the cart.
            foreach ( $stand_alone_products as $p_id ) {
                // Is this standalone product in the cart?
                if ( in_array( $p_id, array_column( WC()->cart->get_cart(), 'product_id' ), true ) ) {
                    // echo "<br>Otherrrrrr producccts<br><br>";
                    // Product is there, remove.
                    self::pekky_sort_stand_alone_products( $p_id, $stand_alone_products, $force_sells_products_map, false );
                }
            }
        }
        return $passed;
    }


    /**
     * Remove Necessary Products
     *
     * If a product in the standalone list or forcesells list was removed, empty cart.
     *
     * @hook woocommerce_remove_cart_item
     * @param string  $cart_item_key The cart item key.
     * @param WC_Cart $cart Cart object.
     */
    public static function sort_product_removal_from_cart( $cart_item_key, $cart ) {
        $stand_alone_products = self::$wc_stand_alone_products;
        $other_products       = self::$wc_other_products;
        $force_sells_products = self::$wc_force_sells_products;

        $cart_contents = $cart->get_cart_contents();
        $cart_item     = isset( $cart_contents[ $cart_item_key ] ) ? $cart_contents[ $cart_item_key ] : array();

        if ( isset( $cart_item['product_id'] ) ) {
            $product_id = $cart_item['product_id'];

            if ( in_array( $product_id, $stand_alone_products, true )
            || in_array( $product_id, $force_sells_products, true ) ) {
                // Product is in standalone list, or force_sells list, empty cart.
                $cart->empty_cart();
            }
        }
    }

    /**
     * Show Upsell Message.
     *
     * Show on cart page alone.
     */
    public static function show_upsell_notice() {
        if ( ! is_cart() ) {
            return;
        }

        $cart       = WC()->cart;
        $cart_total = WC()->cart->get_displayed_subtotal();

        // Calculate Appropriately.
        if ( $cart->display_prices_including_tax() ) {
            $cart_total = round( $cart_total - ( $cart->get_discount_total() + $cart->get_discount_tax() ), wc_get_price_decimals() );
        } else {
            $cart_total = round( $cart_total - $cart->get_discount_total(), wc_get_price_decimals() );
        }

        // Sort Upsell note for cart total.
        self::pekky_sort_product_upsell_note( self::$wc_suggested_upsell_products_data, $cart, $cart_total );

        // Sort Upsell note for coupon added.
        self::pekky_sort_coupon_upsell_note( self::$wc_suggested_upsell_coupon_data, $cart, $cart_total );

    }

    // Inner functions to help run some parole.

    /**
     * Sort showing of upsell message.
     *
     * @param array   $suggested_upsell_products_data The product data.
     * @param WC_Cart $cart The cart object.
     * @param int     $cart_total The cart total.
     * @return bool True if message is added, false otherwise.
     */
    private static function pekky_sort_product_upsell_note( $suggested_upsell_products_data, $cart, $cart_total ) {
        // Check if cart total meets some qualifications.
        $upsell_data      = array();
        $last_large_price = 0; // Incase cart total is larger than 1 min_price in the list.

        foreach ( $suggested_upsell_products_data as $data ) {
            // Does cart_total_qualify at all.
            if ( $cart_total >= $data['min_price'] ) {
                // Is this min_price larger than the last one?
                if ( $last_large_price < $data['min_price'] ) {
                    // Update last large price.
                    $last_large_price = $data['min_price'];
                    $upsell_data      = $data;
                }
            }
        }

        if ( ! empty( $upsell_data ) ) {

            $p_id = $upsell_data['product_id'];

            // Check if product is in cart already.
            $cart_id = $cart->generate_cart_id( $p_id );

            if ( $cart->find_product_in_cart( $cart_id ) ) {
                // Product already in cart, no need to add notice.
                return false;
            }

            $product = wc_get_product( $p_id );

            // Does product exist? to play safe and not break the site.
            if ( ! $product ) {
                return false;
            }
            // Default link text if the upsell 'cart_btn_txt' is empty.
            $default_link_txt = 'Click Here to Upgrade';

            $link_text = ( isset( $upsell_data['cart_btn_txt'] ) && ! empty( $upsell_data['cart_btn_txt'] )
            ? $upsell_data['cart_btn_txt'] : $default_link_txt );

            // Get link.
            $raw_link = add_query_arg( 'add-to-cart', $p_id );
            $link     = '<a href="' . esc_url( $raw_link ) . '" class="button">' . $link_text . '</a>';

            // Translators: 1: The upsell note. 2:add to cart link.
            $message = sprintf( __( '%1$s %2$s', 'woocommerce' ), $upsell_data['upsell_note'], $link );

            wc_add_notice( $message );
            return true;
        }

        return false;
    }


    /**
     * Sort showing of upsell message when a coupon is applied.
     *
     * @param array   $suggested_upsell_coupon_data The product data.
     * @param WC_Cart $cart The cart object.
     * @param int     $cart_total The cart total.
     * @return bool True if message is added, false otherwise.
     */
    private static function pekky_sort_coupon_upsell_note( $suggested_upsell_coupon_data, $cart, $cart_total ) {
        $upsell_data = array();

        foreach ( $suggested_upsell_coupon_data as $data ) {
            // Is coupon in the cart?
            if ( $cart->has_discount( $data['coupon_code'] ) ) {
                // Update.
                $upsell_data = $data;
            }
        }

        if ( ! empty( $upsell_data ) ) {

            $p_id = $upsell_data['product_id'];

            // Check if product is in cart already.
            $cart_id = $cart->generate_cart_id( $p_id );

            if ( $cart->find_product_in_cart( $cart_id ) ) {
                // Product already in cart, no need to add notice.
                return false;
            }

            $product = wc_get_product( $p_id );

            // Does product exist? to play safe and not break the site.
            if ( ! $product ) {
                return false;
            }
            // Get link.
            $link = self::pekky_get_upsell_content_link( $upsell_data );

            // Translators: 1: The upsell note. 2:add to cart link.
            $message = sprintf( __( '%1$s %2$s', 'woocommerce' ), $upsell_data['upsell_note'], $link );

            wc_add_notice( $message );
            return true;
        }

        return false;
    }

    /**
     * Sort content to upsell.
     *
     * @param array $upsell_data The upsell data.
     * @return string The prepared link.
     */
    private static function pekky_get_upsell_content_link( $upsell_data ) {
        // Default link text if the upsell 'cart_btn_txt' is empty.
        $default_link_txt = 'Click Here to Upgrade';

        $link_text = ( isset( $upsell_data['cart_btn_txt'] ) && ! empty( $upsell_data['cart_btn_txt'] )
        ? $upsell_data['cart_btn_txt'] : $default_link_txt );

        $query_args = array( 'add-to-cart' => $upsell_data['product_id'] );

        $next_upsell = isset( $upsell_data['next_upsell'] ) ? $upsell_data['next_upsell'] : '';

        // Check if there's any useful data.
        if ( ! empty( $next_upsell ) && ( ! empty( $next_upsell['coupon_code'] ) || 0 < $next_upsell['product_id'] ) ) {
            $product = wc_get_product( $next_upsell['product_id'] );

            // Does product exist? to play safe and not break the site.
            if ( ! $product ) {
                unset( $query_args['add-to-cart'] );
            } else {
                $query_args['add-to-cart'] = $next_upsell['product_id'];
            }

            $query_args['apply_coupon'] = $next_upsell['coupon_code'];
        }

        // Get link.
        $raw_link = add_query_arg( $query_args );
        $link     = '<a href="' . esc_url( $raw_link ) . '" class="button">' . $link_text . '</a>';
        return $link;
    }

    /**
     * Sorts the stand alone products.
     *
     * @param   int   $product_id Standalone Product Id.
     * @param   array $stand_alone_products List of standalone products.
     * @param   array $force_sells_products_map Products attached to the standalone products
     * These should be added to cart/removed when their standalone product is in the cart/removed.
     * Patterns [{standalone_product_id} => array({force_sells_ids})].
     * @param bool  $add_to_cart (optional) true, add to cart, if false, removes, default is true.
     * @return bool false if empty field, true otherwise.
     */
    private static function pekky_sort_stand_alone_products( $product_id, $stand_alone_products, $force_sells_products_map, $add_to_cart = true ) {
        if ( empty( $product_id ) || ( empty( $stand_alone_products ) || ! is_array( $stand_alone_products ) )
        || ( empty( $force_sells_products_map ) || ! is_array( $force_sells_products_map ) ) ) {
            return false;
        }
        $wc_cart   = WC()->cart;
        $cart_func = 'remove_cart_item';
        if ( $add_to_cart ) {
            $cart_func = 'add_to_cart';
            // Empty cart.
            $wc_cart->empty_cart();
        }
        $p_id = ( $add_to_cart ? $product_id : $wc_cart->generate_cart_id( $product_id ) );

        if ( ! $add_to_cart ) {
            $wc_cart->{"{$cart_func}"}( $p_id );
        }

        // Get list of standalone product id.
        $product_force_sells_list = $force_sells_products_map[ $product_id ];

        if ( empty( $product_force_sells_list ) ) {
            // Wrong list.
            return false;
        }

        // Also add the force_sells product to cart/remove.
        foreach ( $product_force_sells_list as $_id ) {
            $p_id = ( $add_to_cart ? $_id : $wc_cart->generate_cart_id( $_id ) );
            $wc_cart->{"{$cart_func}"}( $p_id );

            // Reset quantity to 1.
            if ( $add_to_cart ) {
                $cart_item_key = $wc_cart->generate_cart_id( $p_id );
                $wc_cart->set_quantity( $cart_item_key, 1 ); // Change quantity.
            }
        }
        return true;
    }

}
// Launch!
Pekky_WC_Stand_Alone_Force_Sells_Sorting::init();
