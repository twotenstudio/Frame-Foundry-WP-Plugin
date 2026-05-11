<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Portal_Events_Settings {

    const OPTION_GROUP = 'portal_events_settings';
    const OPTION_NAME  = 'portal_events_options';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_clear_cache' ] );
    }

    public static function add_menu() {
        $hook = add_options_page(
            'Frame Foundry Events',
            'Frame Foundry Events',
            'manage_options',
            'portal-events',
            [ __CLASS__, 'render_page' ]
        );
        add_action( 'admin_print_scripts-' . $hook, [ __CLASS__, 'enqueue_admin_scripts' ] );
    }

    public static function enqueue_admin_scripts() {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();
        wp_add_inline_script( 'wp-color-picker', "
            jQuery(document).ready(function($){
                $('.portal-color-picker').wpColorPicker();

                var frame;
                $(document).on('click', '.portal-default-image-upload', function(e){
                    e.preventDefault();
                    var \$button = $(this);
                    var \$wrap   = \$button.closest('.portal-default-image-field');
                    if (frame) { frame.off('select'); }
                    frame = wp.media({
                        title: 'Select Default Image',
                        button: { text: 'Use this image' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        \$wrap.find('.portal-default-image-url').val(att.url);
                        \$wrap.find('.portal-default-image-id').val(att.id);
                        \$wrap.find('.portal-default-image-preview').html('<img src=\"' + att.url + '\" style=\"max-width:200px;height:auto;display:block;margin-top:8px;\" />');
                        \$wrap.find('.portal-default-image-remove').show();
                    });
                    frame.open();
                });

                $(document).on('click', '.portal-default-image-remove', function(e){
                    e.preventDefault();
                    var \$wrap = $(this).closest('.portal-default-image-field');
                    \$wrap.find('.portal-default-image-url').val('');
                    \$wrap.find('.portal-default-image-id').val('');
                    \$wrap.find('.portal-default-image-preview').empty();
                    $(this).hide();
                });
            });
        " );
    }

    public static function register_settings() {
        register_setting( self::OPTION_GROUP, self::OPTION_NAME, [
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
        ] );

        // ── Connection ──────────────────────────────────
        add_settings_section(
            'portal_events_main',
            'Connection Settings',
            function () {
                echo '<p>Connect to your Frame Foundry portal to fetch event data.</p>';
            },
            'portal-events'
        );

        add_settings_field( 'api_url', 'Portal API URL', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['api_url'] ?? '';
            echo '<input type="url" name="' . self::OPTION_NAME . '[api_url]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://yourportal.framefoundry.co/api/public/events" />';
            echo '<p class="description">The full URL to the public events API endpoint.</p>';
        }, 'portal-events', 'portal_events_main' );

        add_settings_field( 'api_key', 'API Key', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['api_key'] ?? '';
            echo '<input type="password" name="' . self::OPTION_NAME . '[api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
            echo '<p class="description">Generate this from your Frame Foundry admin settings page.</p>';
        }, 'portal-events', 'portal_events_main' );

        add_settings_field( 'cache_minutes', 'Cache Duration (minutes)', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['cache_minutes'] ?? 15;
            echo '<input type="number" name="' . self::OPTION_NAME . '[cache_minutes]" value="' . esc_attr( $value ) . '" min="0" max="1440" class="small-text" />';
            echo '<p class="description">How long to cache event data. Set to 0 to disable caching.</p>';
        }, 'portal-events', 'portal_events_main' );

        // ── Display ─────────────────────────────────────
        add_settings_section(
            'portal_events_display',
            'Display Settings',
            function () {
                echo '<p>Customize how events are displayed on the frontend. These can be overridden per-shortcode with attributes.</p>';
            },
            'portal-events'
        );

        add_settings_field( 'card_style', 'Card Style', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['card_style'] ?? 'default';
            echo '<select name="' . self::OPTION_NAME . '[card_style]">';
            echo '<option value="default"' . selected( $value, 'default', false ) . '>Default — image, description, button</option>';
            echo '<option value="date-block"' . selected( $value, 'date-block', false ) . '>Date Block — image with date panel below</option>';
            echo '</select>';
            echo '<p class="description">Choose the card design. Override per-shortcode: <code>[portal_events style="date-block"]</code></p>';
        }, 'portal-events', 'portal_events_display' );

        add_settings_field( 'card_layout', 'Layout', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['card_layout'] ?? 'grid';
            echo '<select name="' . self::OPTION_NAME . '[card_layout]">';
            echo '<option value="grid"' . selected( $value, 'grid', false ) . '>Grid</option>';
            echo '<option value="list"' . selected( $value, 'list', false ) . '>List</option>';
            echo '</select>';
            echo '<p class="description">Override per-shortcode: <code>[portal_events layout="list"]</code></p>';
        }, 'portal-events', 'portal_events_display' );

        add_settings_field( 'button_text', 'Button Text', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['button_text'] ?? '';
            echo '<input type="text" name="' . self::OPTION_NAME . '[button_text]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="Book Now" />';
            echo '<p class="description">Text shown on the event card button. Leave blank for default ("Book Now" / "View Details"). Only applies to the Default card style.</p>';
        }, 'portal-events', 'portal_events_display' );

        add_settings_field( 'shop_button_text', 'Shop Button Text', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['shop_button_text'] ?? '';
            echo '<input type="text" name="' . self::OPTION_NAME . '[shop_button_text]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="Buy Now" />';
            echo '<p class="description">Text shown on shop product card buttons. Leave blank for default ("Buy Now").</p>';
        }, 'portal-events', 'portal_events_display' );

        // ── Default Image ───────────────────────────────
        add_settings_section(
            'portal_events_default_image',
            'Default Image',
            function () {
                echo '<p>Choose a fallback image shown when an item has no image, and a background colour applied behind images (useful for transparent PNGs).</p>';
            },
            'portal-events'
        );

        add_settings_field( 'default_image', 'Fallback Image', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $url     = $options['default_image_url'] ?? '';
            $id      = $options['default_image_id'] ?? '';
            echo '<div class="portal-default-image-field">';
            echo '<input type="hidden" class="portal-default-image-url" name="' . self::OPTION_NAME . '[default_image_url]" value="' . esc_attr( $url ) . '" />';
            echo '<input type="hidden" class="portal-default-image-id"  name="' . self::OPTION_NAME . '[default_image_id]"  value="' . esc_attr( $id ) . '" />';
            echo '<button type="button" class="button portal-default-image-upload">Choose Image</button> ';
            echo '<button type="button" class="button portal-default-image-remove"' . ( $url ? '' : ' style="display:none;"' ) . '>Remove</button>';
            echo '<div class="portal-default-image-preview">';
            if ( $url ) {
                echo '<img src="' . esc_url( $url ) . '" style="max-width:200px;height:auto;display:block;margin-top:8px;" />';
            }
            echo '</div>';
            echo '<p class="description">Used when an event, perk, or product has no image of its own.</p>';
            echo '</div>';
        }, 'portal-events', 'portal_events_default_image' );

        add_settings_field( 'image_bg_color', 'Image Background Colour', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['image_bg_color'] ?? '';
            echo '<input type="text" name="' . self::OPTION_NAME . '[image_bg_color]" value="' . esc_attr( $value ) . '" class="portal-color-picker" data-default-color="" />';
            echo '<p class="description">Background colour shown behind images. Useful when using PNGs with transparency.</p>';
        }, 'portal-events', 'portal_events_default_image' );

        // ── Category Colours ─────────────────────────────
        add_settings_section(
            'portal_events_category_colors',
            'Category Colours',
            function () {
                echo '<p>Assign a colour to each event category. The colour is applied as the date-block background.</p>';
            },
            'portal-events'
        );

        add_settings_field( 'category_colors', '', function () {
            $options    = get_option( self::OPTION_NAME, [] );
            $colors     = $options['category_colors'] ?? [];
            $categories = Portal_Events_Shortcode::get_categories();

            if ( empty( $categories ) ) {
                echo '<p class="description">No categories found. Make sure your API connection is configured and events with categories are available.</p>';
                return;
            }

            echo '<table class="form-table portal-category-colors">';
            foreach ( $categories as $cat ) {
                $slug       = $cat['slug'];
                $name       = $cat['name'];
                $color      = $colors[ $slug ] ?? '';
                $field_name = self::OPTION_NAME . '[category_colors][' . esc_attr( $slug ) . ']';
                echo '<tr>';
                echo '<th scope="row"><label for="cat-color-' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</label></th>';
                echo '<td><input type="text" id="cat-color-' . esc_attr( $slug ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $color ) . '" class="portal-color-picker" data-default-color="" /></td>';
                echo '</tr>';
            }
            echo '</table>';
        }, 'portal-events', 'portal_events_category_colors' );

        // ── Styling ─────────────────────────────────────
        add_settings_section(
            'portal_events_styling',
            'Custom CSS',
            function () {
                echo '<p>Add custom CSS to style the event cards.</p>';
            },
            'portal-events'
        );

        add_settings_field( 'custom_css', '', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['custom_css'] ?? '';
            echo '<textarea name="' . self::OPTION_NAME . '[custom_css]" rows="12" cols="60" class="large-text code" placeholder=".portal-event { }&#10;.portal-event__title { }">' . esc_textarea( $value ) . '</textarea>';
        }, 'portal-events', 'portal_events_styling' );
    }

    public static function sanitize( $input ) {
        $sanitized = [];
        $sanitized['api_url']       = esc_url_raw( $input['api_url'] ?? '' );
        $sanitized['api_key']       = sanitize_text_field( $input['api_key'] ?? '' );
        $sanitized['cache_minutes'] = absint( $input['cache_minutes'] ?? 15 );
        $sanitized['card_style']    = in_array( $input['card_style'] ?? '', [ 'default', 'date-block' ], true ) ? $input['card_style'] : 'default';
        $sanitized['card_layout']   = in_array( $input['card_layout'] ?? '', [ 'grid', 'list' ], true ) ? $input['card_layout'] : 'grid';
        $sanitized['button_text']   = sanitize_text_field( $input['button_text'] ?? '' );
        $sanitized['shop_button_text'] = sanitize_text_field( $input['shop_button_text'] ?? '' );
        $sanitized['default_image_url'] = esc_url_raw( $input['default_image_url'] ?? '' );
        $sanitized['default_image_id']  = absint( $input['default_image_id'] ?? 0 );
        $sanitized['image_bg_color']    = sanitize_hex_color( $input['image_bg_color'] ?? '' ) ?: '';
        $sanitized['custom_css']    = wp_strip_all_tags( $input['custom_css'] ?? '' );

        $sanitized['category_colors'] = [];
        if ( ! empty( $input['category_colors'] ) && is_array( $input['category_colors'] ) ) {
            foreach ( $input['category_colors'] as $slug => $color ) {
                $slug  = sanitize_key( $slug );
                $color = sanitize_hex_color( $color );
                if ( $slug && $color ) {
                    $sanitized['category_colors'][ $slug ] = $color;
                }
            }
        }

        // Clear cache when settings change
        delete_transient( 'portal_events_data' );

        return $sanitized;
    }

    public static function handle_clear_cache() {
        if (
            isset( $_POST['portal_events_clear_cache'] ) &&
            check_admin_referer( 'portal_events_clear_cache_action' )
        ) {
            delete_transient( 'portal_events_data' );
            add_settings_error(
                self::OPTION_GROUP,
                'cache_cleared',
                'Event cache cleared successfully.',
                'updated'
            );
        }
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>Frame Foundry Events</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( 'portal-events' );
                submit_button();
                ?>
            </form>
            <hr />
            <h2>Cache</h2>
            <p>Clear the cached event data to fetch fresh data from the API.</p>
            <form method="post">
                <?php wp_nonce_field( 'portal_events_clear_cache_action' ); ?>
                <input type="hidden" name="portal_events_clear_cache" value="1" />
                <?php submit_button( 'Clear Cache', 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public static function get_options() {
        return wp_parse_args( get_option( self::OPTION_NAME, [] ), [
            'api_url'       => '',
            'api_key'       => '',
            'cache_minutes' => 15,
            'card_style'    => 'default',
            'card_layout'   => 'grid',
            'button_text'   => '',
            'shop_button_text'  => '',
            'default_image_url' => '',
            'default_image_id'  => 0,
            'image_bg_color'    => '',
            'custom_css'        => '',
            'category_colors'   => [],
        ] );
    }
}
