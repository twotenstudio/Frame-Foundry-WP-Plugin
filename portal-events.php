<?php
/**
 * Plugin Name: Frame Foundry
 * Description: Display events, perks, and shop products from your Frame Foundry portal using [portal_events], [portal_perks], and [portal_shop] shortcodes.
 * Version: 3.4.1
 * Author: TwoTen Studio
 * Author URI: https://twotenstudio.co.uk
 * License: GPL-2.0-or-later
 * Text Domain: portal-events
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PORTAL_EVENTS_VERSION', '3.4.1' );
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

// Authenticate to avoid GitHub API rate limits
// Define PORTAL_EVENTS_GITHUB_TOKEN in wp-config.php if needed
if ( defined( 'PORTAL_EVENTS_GITHUB_TOKEN' ) && PORTAL_EVENTS_GITHUB_TOKEN ) {
    $portalEventsUpdateChecker->setAuthentication( PORTAL_EVENTS_GITHUB_TOKEN );
}

// ── Plugin bootstrap ───────────────────────────────────────
require_once PORTAL_EVENTS_PATH . 'includes/class-portal-events-settings.php';
require_once PORTAL_EVENTS_PATH . 'includes/class-portal-events-shortcode.php';
require_once PORTAL_EVENTS_PATH . 'includes/class-portal-perks-shortcode.php';
require_once PORTAL_EVENTS_PATH . 'includes/class-portal-shop-shortcode.php';

// Initialise
add_action( 'init', function () {
    Portal_Events_Settings::init();
    Portal_Events_Shortcode::init();
    Portal_Perks_Shortcode::init();
    Portal_Shop_Shortcode::init();
} );

// Enqueue styles when either shortcode is present
add_action( 'wp_enqueue_scripts', function () {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) {
        return;
    }

    $has_events     = has_shortcode( $post->post_content, 'portal_events' );
    $has_categories = has_shortcode( $post->post_content, 'portal_event_categories' );
    $has_perks      = has_shortcode( $post->post_content, 'portal_perks' );
    $has_shop       = has_shortcode( $post->post_content, 'portal_shop' );

    if ( $has_events || $has_categories || $has_perks || $has_shop ) {
        wp_enqueue_style(
            'portal-events',
            PORTAL_EVENTS_URL . 'assets/portal-events.css',
            [],
            PORTAL_EVENTS_VERSION
        );

        $options = Portal_Events_Settings::get_options();

        // Category colour overrides for date-block backgrounds
        $category_colors = $options['category_colors'] ?? [];
        if ( ! empty( $category_colors ) ) {
            $color_css = '';
            foreach ( $category_colors as $slug => $color ) {
                $class = sanitize_html_class( $slug );
                if ( $class && $color ) {
                    $color_css .= ".portal-event.category-{$class} .portal-event__date-block{background:{$color};}";
                }
            }
            if ( $color_css ) {
                wp_add_inline_style( 'portal-events', $color_css );
            }
        }

        // Custom CSS (added last so it can override everything)
        $custom_css = $options['custom_css'] ?? '';
        if ( ! empty( $custom_css ) ) {
            wp_add_inline_style( 'portal-events', $custom_css );
        }
    }
} );
