<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Portal_Events_Shortcode {

    public static function init() {
        add_shortcode( 'portal_events', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ) {
        $options = Portal_Events_Settings::get_options();

        $atts = shortcode_atts( [
            'limit'    => 10,
            'layout'   => $options['card_layout'],
            'style'    => $options['card_style'],
            'category' => '', // Filter by category slug
        ], $atts, 'portal_events' );

        $events = self::fetch_events();

        if ( is_wp_error( $events ) ) {
            return '<div class="portal-events-error"><p>Unable to load events. Please try again later.</p></div>';
        }

        if ( empty( $events ) ) {
            return '<div class="portal-events-empty"><p>No upcoming events.</p></div>';
        }

        // Filter by category slug if specified
        if ( ! empty( $atts['category'] ) ) {
            $cat_slug = strtolower( trim( $atts['category'] ) );
            $events = array_filter( $events, function ( $e ) use ( $cat_slug ) {
                if ( empty( $e['categories'] ) ) return false;
                foreach ( $e['categories'] as $cat ) {
                    if ( ( $cat['slug'] ?? '' ) === $cat_slug ) return true;
                }
                return false;
            } );
        }

        $events = array_slice( $events, 0, (int) $atts['limit'] );
        $layout = in_array( $atts['layout'], [ 'grid', 'list' ], true ) ? $atts['layout'] : 'grid';
        $style  = in_array( $atts['style'], [ 'default', 'date-block' ], true ) ? $atts['style'] : 'default';

        ob_start();
        echo '<div class="portal-events portal-events--' . esc_attr( $layout ) . ' portal-events--' . esc_attr( $style ) . '">';

        foreach ( $events as $event ) {
            if ( 'date-block' === $style ) {
                self::render_card_date_block( $event );
            } else {
                self::render_card( $event );
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function get_event_data( $event ) {
        $start    = strtotime( $event['startDate'] );
        $end      = strtotime( $event['endDate'] );
        $date_str = wp_date( 'l, j F Y', $start );
        $time_str = wp_date( 'g:ia', $start ) . ' - ' . wp_date( 'g:ia', $end );

        $is_members_only  = ! empty( $event['membersOnly'] );
        $non_member_price = isset( $event['nonMemberPrice'] ) ? (int) $event['nonMemberPrice'] : null;
        $min_price        = isset( $event['minPrice'] ) ? (int) $event['minPrice'] : null;
        $max_price        = isset( $event['maxPrice'] ) ? (int) $event['maxPrice'] : null;
        $has_plan_prices  = ! empty( $event['planPrices'] );

        if ( $has_plan_prices && $min_price !== null && $max_price !== null ) {
            if ( $min_price === $max_price ) {
                $price = self::format_price( $min_price );
            } elseif ( $min_price === 0 ) {
                $price = 'From Free';
            } else {
                $price = 'From ' . self::format_price( $min_price );
            }
        } elseif ( $is_members_only ) {
            $price = 'Members Only';
        } elseif ( $non_member_price !== null ) {
            $price = self::format_price( $non_member_price );
        } else {
            $price = 'Free';
        }

        $spots_html = '';
        if ( ! $is_members_only && $event['spotsLeft'] !== null ) {
            if ( $event['spotsLeft'] <= 0 ) {
                $spots_html = '<span class="portal-event__spots portal-event__spots--full">Fully booked</span>';
            } else {
                $spots_html = '<span class="portal-event__spots">' . esc_html( $event['spotsLeft'] ) . ' spots left</span>';
            }
        }

        $is_full    = $event['spotsLeft'] !== null && $event['spotsLeft'] <= 0;
        $options    = Portal_Events_Settings::get_options();
        $custom_btn = $options['button_text'] ?? '';

        if ( ! empty( $custom_btn ) ) {
            $btn_text = $custom_btn;
        } elseif ( $is_members_only ) {
            $btn_text = 'View Details';
        } elseif ( $is_full ) {
            $btn_text = 'View Details';
        } else {
            $btn_text = 'Book Now';
        }

        return [
            'start'           => $start,
            'end'             => $end,
            'date_str'        => $date_str,
            'time_str'        => $time_str,
            'price'           => $price,
            'spots_html'      => $spots_html,
            'is_members_only' => $is_members_only,
            'is_full'         => $is_full,
            'btn_text'        => $btn_text,
            'booking_url'     => esc_url( $event['bookingUrl'] ),
            'day'             => wp_date( 'd', $start ),
            'month'           => strtoupper( wp_date( 'M', $start ) ),
            'categories'      => $event['categories'] ?? [],
        ];
    }

    private static function get_category_classes( $categories ) {
        if ( empty( $categories ) ) return '';
        $classes = array_map( function ( $cat ) {
            return 'category-' . sanitize_html_class( $cat['slug'] ?? $cat['name'] );
        }, $categories );
        return ' ' . implode( ' ', $classes );
    }

    /**
     * Default card style
     */
    private static function render_card( $event ) {
        $d = self::get_event_data( $event );
        $cat_classes = self::get_category_classes( $d['categories'] );
        ?>
        <div class="portal-event<?php echo esc_attr( $cat_classes ); ?>">
            <?php if ( ! empty( $event['imageUrl'] ) ) : ?>
                <div class="portal-event__image">
                    <img src="<?php echo esc_url( $event['imageUrl'] ); ?>" alt="<?php echo esc_attr( $event['title'] ); ?>" loading="lazy" />
                    <?php if ( $d['is_members_only'] ) : ?>
                        <span class="portal-event__badge portal-event__badge--members">Members Only</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="portal-event__body">
                <div class="portal-event__header">
                    <h3 class="portal-event__title"><?php echo esc_html( $event['title'] ); ?></h3>
                    <span class="portal-event__price"><?php echo esc_html( $d['price'] ); ?></span>
                </div>
                <?php if ( $d['is_members_only'] && empty( $event['imageUrl'] ) ) : ?>
                    <span class="portal-event__badge portal-event__badge--members">Members Only</span>
                <?php endif; ?>
                <?php if ( ! empty( $d['categories'] ) ) : ?>
                    <div class="portal-event__categories">
                        <?php foreach ( $d['categories'] as $cat ) : ?>
                            <span class="portal-event__category"><?php echo esc_html( $cat['name'] ); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $event['description'] ) ) : ?>
                    <p class="portal-event__description"><?php echo esc_html( wp_trim_words( $event['description'], 25 ) ); ?></p>
                <?php endif; ?>
                <div class="portal-event__meta">
                    <span class="portal-event__date"><?php echo esc_html( $d['date_str'] ); ?></span>
                    <span class="portal-event__time"><?php echo esc_html( $d['time_str'] ); ?></span>
                    <?php if ( ! empty( $event['location'] ) ) : ?>
                        <span class="portal-event__location"><?php echo esc_html( $event['location'] ); ?></span>
                    <?php endif; ?>
                    <?php echo $d['spots_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <a href="<?php echo $d['booking_url']; ?>" class="portal-event__btn" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( $d['btn_text'] ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Date-block card style — prominent date, image top, compact info
     */
    private static function render_card_date_block( $event ) {
        $d = self::get_event_data( $event );
        $cat_classes = self::get_category_classes( $d['categories'] );
        ?>
        <a href="<?php echo $d['booking_url']; ?>" class="portal-event portal-event--date-block<?php echo esc_attr( $cat_classes ); ?>" target="_blank" rel="noopener noreferrer">
            <div class="portal-event__image-area">
                <?php if ( ! empty( $event['imageUrl'] ) ) : ?>
                    <img src="<?php echo esc_url( $event['imageUrl'] ); ?>" alt="<?php echo esc_attr( $event['title'] ); ?>" loading="lazy" />
                <?php else : ?>
                    <div class="portal-event__image-placeholder"></div>
                <?php endif; ?>
                <?php if ( $d['is_members_only'] ) : ?>
                    <span class="portal-event__badge portal-event__badge--members">Members Only</span>
                <?php endif; ?>
            </div>
            <div class="portal-event__info">
                <div class="portal-event__date-block">
                    <span class="portal-event__date-day"><?php echo esc_html( $d['day'] ); ?></span>
                    <span class="portal-event__date-month"><?php echo esc_html( $d['month'] ); ?></span>
                </div>
                <div class="portal-event__details">
                    <h3 class="portal-event__title"><?php echo esc_html( $event['title'] ); ?></h3>
                    <span class="portal-event__price-inline"><?php echo esc_html( $d['price'] ); ?></span>
                    <?php if ( ! empty( $event['location'] ) ) : ?>
                        <span class="portal-event__location"><?php echo esc_html( $event['location'] ); ?></span>
                    <?php endif; ?>
                    <span class="portal-event__time"><?php echo esc_html( $d['time_str'] ); ?></span>
                </div>
            </div>
        </a>
        <?php
    }

    /**
     * Get unique categories from all events.
     */
    public static function get_categories() {
        $events = self::fetch_events();
        if ( is_wp_error( $events ) || empty( $events ) ) {
            return [];
        }

        $seen = [];
        $categories = [];
        foreach ( $events as $event ) {
            if ( empty( $event['categories'] ) ) continue;
            foreach ( $event['categories'] as $cat ) {
                $slug = $cat['slug'] ?? sanitize_title( $cat['name'] );
                if ( isset( $seen[ $slug ] ) ) continue;
                $seen[ $slug ] = true;
                $categories[] = [
                    'slug' => $slug,
                    'name' => $cat['name'],
                ];
            }
        }

        usort( $categories, function ( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        } );

        return $categories;
    }

    private static function format_price( $cents ) {
        if ( $cents === 0 ) {
            return 'Free';
        }
        return '£' . number_format( $cents / 100, 2 );
    }

    private static function fetch_events() {
        $options       = Portal_Events_Settings::get_options();
        $api_url       = $options['api_url'];
        $api_key       = $options['api_key'];
        $cache_minutes = (int) $options['cache_minutes'];

        if ( empty( $api_url ) || empty( $api_key ) ) {
            return new WP_Error( 'not_configured', 'Portal Events plugin is not configured.' );
        }

        // Check cache
        if ( $cache_minutes > 0 ) {
            $cached = get_transient( 'portal_events_data' );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $response = wp_remote_get( $api_url, [
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
        if ( ! isset( $body['events'] ) || ! is_array( $body['events'] ) ) {
            return new WP_Error( 'invalid_response', 'Unexpected API response format.' );
        }

        $events = $body['events'];

        // Cache the result
        if ( $cache_minutes > 0 ) {
            set_transient( 'portal_events_data', $events, $cache_minutes * MINUTE_IN_SECONDS );
        }

        return $events;
    }
}
