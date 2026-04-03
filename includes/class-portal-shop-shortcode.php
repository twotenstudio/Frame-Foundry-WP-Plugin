<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Portal_Shop_Shortcode {

    public static function init() {
        add_shortcode( 'portal_shop', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts( [
            'limit'  => 20,
            'layout' => 'grid', // grid or list
            'type'   => '',     // "physical", "digital", or empty for all
        ], $atts, 'portal_shop' );

        $products = self::fetch_products();

        if ( is_wp_error( $products ) ) {
            return '<div class="portal-shop-error"><p>Unable to load products. Please try again later.</p></div>';
        }

        if ( empty( $products ) ) {
            return '<div class="portal-shop-empty"><p>No products available.</p></div>';
        }

        // Filter by type if specified
        if ( ! empty( $atts['type'] ) ) {
            $type = strtoupper( $atts['type'] );
            $products = array_filter( $products, function ( $p ) use ( $type ) {
                return ( $p['type'] ?? '' ) === $type;
            } );
        }

        $products = array_slice( $products, 0, (int) $atts['limit'] );
        $layout   = in_array( $atts['layout'], [ 'grid', 'list' ], true ) ? $atts['layout'] : 'grid';

        ob_start();
        echo '<div class="portal-shop portal-shop--' . esc_attr( $layout ) . '">';

        foreach ( $products as $product ) {
            self::render_card( $product );
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function render_card( $product ) {
        $has_image      = ! empty( $product['imageUrl'] );
        $buy_url        = ! empty( $product['buyUrl'] ) ? esc_url( $product['buyUrl'] ) : '#';
        $type           = $product['type'] ?? 'PHYSICAL';
        $is_members_only = ! empty( $product['membersOnly'] );
        $variants   = $product['variants'] ?? [];
        $min_price  = isset( $product['minPrice'] ) ? (int) $product['minPrice'] : null;
        $lowest     = isset( $product['lowestPrice'] ) ? (int) $product['lowestPrice'] : null;
        $highest    = isset( $product['highestPrice'] ) ? (int) $product['highestPrice'] : null;

        // Price display
        if ( $min_price !== null && $lowest !== null && $highest !== null ) {
            if ( $lowest === $highest ) {
                $price_text = self::format_price( $lowest );
            } else {
                $price_text = 'From ' . self::format_price( $min_price );
            }
        } else {
            $price_text = 'View';
        }

        // Stock status
        $all_in_stock = true;
        $any_in_stock = false;
        foreach ( $variants as $v ) {
            if ( isset( $v['inStock'] ) && $v['inStock'] ) {
                $any_in_stock = true;
            } elseif ( isset( $v['inStock'] ) && ! $v['inStock'] ) {
                $all_in_stock = false;
            }
        }

        $options = Portal_Events_Settings::get_options();
        $btn_text = ! empty( $options['shop_button_text'] ) ? $options['shop_button_text'] : 'Buy Now';
        if ( ! $any_in_stock && count( $variants ) > 0 ) {
            $btn_text = 'Out of Stock';
        }

        ?>
        <div class="portal-shop-product">
            <?php if ( $has_image ) : ?>
                <div class="portal-shop-product__image">
                    <img src="<?php echo esc_url( $product['imageUrl'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" loading="lazy" />
                    <?php if ( $is_members_only ) : ?>
                        <span class="portal-shop-product__badge portal-shop-product__badge--members">Members Only</span>
                    <?php elseif ( $type === 'DIGITAL' ) : ?>
                        <span class="portal-shop-product__badge">Digital</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="portal-shop-product__body">
                <h3 class="portal-shop-product__title"><?php echo esc_html( $product['name'] ); ?></h3>
                <span class="portal-shop-product__price"><?php echo esc_html( $price_text ); ?></span>
                <?php if ( ! empty( $product['description'] ) ) : ?>
                    <div class="portal-shop-product__description"><?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $product['description'] ), 20 ) ); ?></div>
                <?php endif; ?>
                <?php if ( ! $all_in_stock && $any_in_stock ) : ?>
                    <span class="portal-shop-product__stock">Limited stock</span>
                <?php endif; ?>
                <?php if ( count( $variants ) > 1 ) : ?>
                    <span class="portal-shop-product__variants"><?php echo count( $variants ); ?> options</span>
                <?php endif; ?>
                <a href="<?php echo $buy_url; ?>" class="portal-shop-product__btn" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( $btn_text ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    private static function format_price( $cents ) {
        if ( $cents === 0 ) {
            return 'Free';
        }
        return '£' . number_format( $cents / 100, 2 );
    }

    private static function fetch_products() {
        $options       = Portal_Events_Settings::get_options();
        $api_url       = $options['api_url'];
        $api_key       = $options['api_key'];
        $cache_minutes = (int) $options['cache_minutes'];

        if ( empty( $api_url ) || empty( $api_key ) ) {
            return new WP_Error( 'not_configured', 'Plugin is not configured.' );
        }

        // Derive shop URL from events URL
        $shop_url = str_replace( '/events', '/shop', $api_url );

        if ( $cache_minutes > 0 ) {
            $cached = get_transient( 'portal_shop_data' );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $response = wp_remote_get( $shop_url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', 'API returned status ' . $code );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! isset( $body['products'] ) || ! is_array( $body['products'] ) ) {
            return new WP_Error( 'invalid_response', 'Unexpected API response format.' );
        }

        $products = $body['products'];

        if ( $cache_minutes > 0 ) {
            set_transient( 'portal_shop_data', $products, $cache_minutes * MINUTE_IN_SECONDS );
        }

        return $products;
    }
}
