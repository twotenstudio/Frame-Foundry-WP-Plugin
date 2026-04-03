<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Portal_Perks_Shortcode {

    public static function init() {
        add_shortcode( 'portal_perks', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts( [
            'limit'  => 20,
            'layout' => 'grid', // grid or list
        ], $atts, 'portal_perks' );

        $perks = self::fetch_perks();

        if ( is_wp_error( $perks ) ) {
            return '<div class="portal-perks-error"><p>Unable to load perks. Please try again later.</p></div>';
        }

        if ( empty( $perks ) ) {
            return '<div class="portal-perks-empty"><p>No perks available.</p></div>';
        }

        $perks  = array_slice( $perks, 0, (int) $atts['limit'] );
        $layout = in_array( $atts['layout'], [ 'grid', 'list' ], true ) ? $atts['layout'] : 'grid';

        ob_start();
        echo '<div class="portal-perks portal-perks--' . esc_attr( $layout ) . '">';

        foreach ( $perks as $perk ) {
            self::render_card( $perk );
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function render_card( $perk ) {
        $is_members_only = ! empty( $perk['membersOnly'] );
        $has_image       = ! empty( $perk['imageUrl'] );
        $has_link        = ! empty( $perk['linkUrl'] );
        $link_text       = ! empty( $perk['linkText'] ) ? $perk['linkText'] : 'Learn More';
        ?>
        <div class="portal-perk">
            <?php if ( $has_image ) : ?>
                <div class="portal-perk__image">
                    <img src="<?php echo esc_url( $perk['imageUrl'] ); ?>" alt="<?php echo esc_attr( $perk['name'] ); ?>" loading="lazy" />
                    <?php if ( $is_members_only ) : ?>
                        <span class="portal-perk__badge">Members Only</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="portal-perk__body">
                <h3 class="portal-perk__title"><?php echo esc_html( $perk['name'] ); ?></h3>
                <?php if ( $is_members_only && ! $has_image ) : ?>
                    <span class="portal-perk__badge">Members Only</span>
                <?php endif; ?>
                <?php if ( ! empty( $perk['description'] ) ) : ?>
                    <div class="portal-perk__description"><?php echo wp_kses_post( $perk['description'] ); ?></div>
                <?php endif; ?>
                <?php if ( $has_link ) : ?>
                    <a href="<?php echo esc_url( $perk['linkUrl'] ); ?>" class="portal-perk__link" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html( $link_text ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function fetch_perks() {
        $options       = Portal_Events_Settings::get_options();
        $api_url       = $options['api_url'];
        $api_key       = $options['api_key'];
        $cache_minutes = (int) $options['cache_minutes'];

        if ( empty( $api_url ) || empty( $api_key ) ) {
            return new WP_Error( 'not_configured', 'Plugin is not configured.' );
        }

        // Derive perks URL from events URL
        $perks_url = str_replace( '/events', '/perks', $api_url );

        // Check cache
        if ( $cache_minutes > 0 ) {
            $cached = get_transient( 'portal_perks_data' );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $response = wp_remote_get( $perks_url, [
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
        if ( ! isset( $body['perks'] ) || ! is_array( $body['perks'] ) ) {
            return new WP_Error( 'invalid_response', 'Unexpected API response format.' );
        }

        $perks = $body['perks'];

        if ( $cache_minutes > 0 ) {
            set_transient( 'portal_perks_data', $perks, $cache_minutes * MINUTE_IN_SECONDS );
        }

        return $perks;
    }
}
