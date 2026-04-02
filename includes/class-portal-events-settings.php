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
        add_options_page(
            'Portal Events',
            'Portal Events',
            'manage_options',
            'portal-events',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings() {
        register_setting( self::OPTION_GROUP, self::OPTION_NAME, [
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
        ] );

        add_settings_section(
            'portal_events_main',
            'Connection Settings',
            function () {
                echo '<p>Connect to your Portal site to fetch event data.</p>';
            },
            'portal-events'
        );

        add_settings_field( 'api_url', 'Portal API URL', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['api_url'] ?? '';
            echo '<input type="url" name="' . self::OPTION_NAME . '[api_url]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://yourportal.com/api/public/events" />';
            echo '<p class="description">The full URL to the public events API endpoint.</p>';
        }, 'portal-events', 'portal_events_main' );

        add_settings_field( 'api_key', 'API Key', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['api_key'] ?? '';
            echo '<input type="password" name="' . self::OPTION_NAME . '[api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
            echo '<p class="description">Generate this from your Portal admin settings page.</p>';
        }, 'portal-events', 'portal_events_main' );

        add_settings_field( 'cache_minutes', 'Cache Duration (minutes)', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['cache_minutes'] ?? 15;
            echo '<input type="number" name="' . self::OPTION_NAME . '[cache_minutes]" value="' . esc_attr( $value ) . '" min="0" max="1440" class="small-text" />';
            echo '<p class="description">How long to cache event data. Set to 0 to disable caching.</p>';
        }, 'portal-events', 'portal_events_main' );

        // Display section
        add_settings_section(
            'portal_events_display',
            'Display Settings',
            function () {
                echo '<p>Customize how events are displayed on the frontend.</p>';
            },
            'portal-events'
        );

        add_settings_field( 'button_text', 'Button Text', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['button_text'] ?? '';
            echo '<input type="text" name="' . self::OPTION_NAME . '[button_text]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="Book Now" />';
            echo '<p class="description">Text shown on the event card button. Leave blank for default ("Book Now" / "View Details").</p>';
        }, 'portal-events', 'portal_events_display' );

        // Styling section
        add_settings_section(
            'portal_events_styling',
            'Styling',
            function () {
                echo '<p>Add custom CSS to style the event cards. This will be output on pages that use the <code>[portal_events]</code> shortcode.</p>';
            },
            'portal-events'
        );

        add_settings_field( 'custom_css', 'Custom CSS', function () {
            $options = get_option( self::OPTION_NAME, [] );
            $value   = $options['custom_css'] ?? '';
            echo '<textarea name="' . self::OPTION_NAME . '[custom_css]" rows="12" cols="60" class="large-text code" placeholder=".portal-event { }&#10;.portal-event__title { }&#10;.portal-event__btn { }">' . esc_textarea( $value ) . '</textarea>';
            echo '<p class="description">Available classes: <code>.portal-events</code>, <code>.portal-event</code>, <code>.portal-event__image</code>, <code>.portal-event__body</code>, <code>.portal-event__header</code>, <code>.portal-event__title</code>, <code>.portal-event__price</code>, <code>.portal-event__badge</code>, <code>.portal-event__description</code>, <code>.portal-event__meta</code>, <code>.portal-event__date</code>, <code>.portal-event__time</code>, <code>.portal-event__location</code>, <code>.portal-event__spots</code>, <code>.portal-event__btn</code></p>';
        }, 'portal-events', 'portal_events_styling' );
    }

    public static function sanitize( $input ) {
        $sanitized = [];
        $sanitized['api_url']       = esc_url_raw( $input['api_url'] ?? '' );
        $sanitized['api_key']       = sanitize_text_field( $input['api_key'] ?? '' );
        $sanitized['cache_minutes'] = absint( $input['cache_minutes'] ?? 15 );
        $sanitized['button_text']   = sanitize_text_field( $input['button_text'] ?? '' );
        $sanitized['custom_css']    = wp_strip_all_tags( $input['custom_css'] ?? '' );

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
            <h1>Portal Events Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( 'portal-events' );
                submit_button();
                ?>
            </form>
            <hr />
            <h2>Cache</h2>
            <p>Clear the cached event data to fetch fresh data from the Portal API.</p>
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
            'button_text'   => '',
            'custom_css'    => '',
        ] );
    }
}
