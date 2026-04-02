=== Frame Foundry Events ===
Contributors: twotenstudio
Tags: events, membership, booking, portal, frame-foundry
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later

Display events from your Frame Foundry portal on your WordPress site.

== Description ==

Frame Foundry Events connects your WordPress site to your Frame Foundry membership portal, displaying upcoming events with a simple shortcode. Visitors can browse events and click through to book on the portal.

The plugin auto-updates from GitHub releases.

== Installation ==

1. Upload the `portal-events` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Settings > Portal Events
4. Enter your Portal API URL (e.g. `https://yourportal.framefoundry.co/api/public/events`)
5. Enter the API key generated from your Portal admin settings
6. Save changes

== Usage ==

Add the shortcode to any page or post:

`[portal_events]`

**Shortcode attributes:**

* `limit` — Maximum number of events to display (default: 10)
* `layout` — Display layout: `grid` or `list` (default: grid)

**Examples:**

`[portal_events limit="6" layout="grid"]`

`[portal_events limit="3" layout="list"]`

== Customisation ==

The plugin uses CSS custom properties for easy styling. Override these in your theme:

    :root {
        --portal-events-accent: #2563eb;       /* Button & badge colour */
        --portal-events-accent-text: #ffffff;   /* Button text colour */
        --portal-events-radius: 0.75rem;        /* Card border radius */
    }

Or use the Custom CSS box in Settings > Portal Events to add your own styles.

== Changelog ==

= 1.1.0 =
* Added Custom CSS field in settings for styling event cards
* Added GitHub auto-update support
* Renamed to Frame Foundry Events
* Image URLs now proxied through portal for reliability

= 1.0.0 =
* Initial release
