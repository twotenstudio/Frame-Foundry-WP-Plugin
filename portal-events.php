<?php
/**
 * Plugin Name: Frame Foundry Events
 * Description: Display events from your Frame Foundry portal using the [portal_events] shortcode.
 * Version: 1.2.1
 * Author: TwoTen Studio
 * Author URI: https://twotenstudio.co.uk
 * License: GPL-2.0-or-later
 * Text Domain: portal-events
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PORTAL_EVENTS_VERSION', '1.2.1' );
define( 'PORTAL_EVENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PORTAL_EVENTS_URL', plugin_dir_url( __FILE__ ) );

// ── Auto-update from GitHub ────────────────────────────────
require_once PORTAL_EVENTS_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$portalEventsUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/twotenstudio/Frame-Foundry-WP-Plugin/',
    __FILE__,
    'portal-events'
);

// Use GitHub releases as the source
$portalEventsUpdateChecker->getVcsApi()->enableReleaseAssets();

// ── Plugin bootstrap ───────────────────────────────────────
require_once PORTAL_EVENTS_PATH . 'includes/class-portal-events-settings.php';
require_once PORTAL_EVENTS_PATH . 'includes/class-portal-events-shortcode.php';

// Initialise
add_action( 'init', function () {
    Portal_Events_Settings::init();
    Portal_Events_Shortcode::init();
} );

// Enqueue styles only when shortcode is present
add_action( 'wp_enqueue_scripts', function () {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'portal_events' ) ) {
        wp_enqueue_style(
            'portal-events',
            PORTAL_EVENTS_URL . 'assets/portal-events.css',
            [],
            PORTAL_EVENTS_VERSION
        );

        // Output custom CSS inline after the main stylesheet
        $options    = Portal_Events_Settings::get_options();
        $custom_css = $options['custom_css'] ?? '';
        if ( ! empty( $custom_css ) ) {
            wp_add_inline_style( 'portal-events', $custom_css );
        }
    }
} );
