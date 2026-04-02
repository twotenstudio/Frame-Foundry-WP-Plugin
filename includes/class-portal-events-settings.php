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
            'Frame Foundry Events',
            'Frame Foundry Events',
            'manage_options',
            'portal-events',
            [ __CLASS__, 'render_page' ]
        );
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
            'custom_css'    => '',
        ] );
    }
}
