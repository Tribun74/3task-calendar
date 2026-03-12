=== 3task Calendar ===
Contributors: 3task
Tags: calendar, events, event calendar, schedule, wordpress calendar
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional WordPress Event Calendar with beautiful themes, event categories, and modern design. Create and display events easily.

== Description ==

**3task Calendar** is a powerful and easy-to-use WordPress event calendar plugin. Create, manage, and display events on your website with a beautiful, responsive calendar.

= Key Features =

* **Unlimited Events** - Create as many events as you need
* **Unlimited Categories** - Organize events with color-coded categories
* **Multiple Calendar Views** - Month and list views
* **Beautiful Design** - Clean, modern interface that looks great on any theme
* **Mobile Responsive** - Works perfectly on all devices
* **Gutenberg Block** - Easy drag-and-drop calendar insertion
* **Shortcodes** - Flexible placement options
* **Schema.org SEO** - Event markup for better search visibility
* **GDPR Friendly** - Made in Germany with privacy in mind

= Easy to Use =

1. Install and activate
2. Create your first event
3. Add the calendar to any page with shortcode or Gutenberg block
4. Done!

= Shortcodes =

**Display Calendar:**
`[threecal]`

**With Options:**
`[threecal view="month" category="1"]`

**Event List:**
`[threecal_events limit="10"]`

**Upcoming Events:**
`[threecal_upcoming limit="5"]`

**Mini Calendar (Perfect for Sidebars):**
`[threecal_mini]`

= Gutenberg Block =

Simply search for "3task Calendar" in the block inserter to add a calendar to any page or post.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/3task-calendar/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 3task Calendar in your admin menu to create events
4. Add the calendar to any page using `[threecal]` shortcode or Gutenberg block

== Frequently Asked Questions ==

= How do I display the calendar? =

Use the shortcode `[threecal]` or add the 3task Calendar block from the Gutenberg editor.

= Can I have multiple calendars? =

Yes! Each shortcode or block is an independent calendar. You can filter by category.

= How do I style the calendar? =

The calendar automatically adapts to your theme. You can also add custom CSS for further customization.

= Is this plugin GDPR compliant? =

Yes! 3task Calendar is developed in Germany with privacy in mind. The core calendar functionality works without any external requests. If you choose to enable the optional Google Maps integration (for event locations), you will need to configure your own API key and ensure GDPR compliance for your use case.

= Where can I get support? =

Please use the [WordPress.org support forum](https://wordpress.org/support/plugin/3task-calendar/) for questions and bug reports.

== External Services ==

This plugin provides **optional** integration with Google Maps for event location features. This integration is completely optional and disabled by default.

= Google Maps Platform (Optional) =

When you enable the Google Maps integration by adding your own API key in the plugin settings:

* **What it does:** Displays event locations on a map and converts addresses to coordinates (geocoding).
* **When data is sent:** Only when you add a Google Maps API key AND create events with location data.
* **What data is sent:** Event location addresses are sent to Google's Geocoding API to retrieve coordinates.
* **Service provider:** Google LLC
* **Terms of Service:** [Google Maps Platform Terms of Service](https://cloud.google.com/maps-platform/terms)
* **Privacy Policy:** [Google Privacy Policy](https://policies.google.com/privacy)

**Note:** If you do not add a Google Maps API key, no data is ever sent to Google and the plugin works entirely without external requests.

== Screenshots ==

1. Calendar month view
2. Event list view
3. Admin dashboard
4. Event editor
5. Category management
6. Settings page

== Changelog ==

= 1.2.2 =
* Improved code quality: Replaced inline CSS/JS with properly enqueued assets
* Added External Services documentation for optional Google Maps integration
* Fixed WordPress.org coding standards compliance

= 1.2.1 =
* Fixed plugin name to match WordPress.org slug requirements
* Text domain now correctly matches plugin slug

= 1.2.0 =
* Rebranded from CalendarCraft to 3task Calendar
* Updated all shortcodes to use [threecal], [threecal_events], [threecal_upcoming], [threecal_mini]
* Updated Gutenberg block registration
* Internal code refactoring for WordPress.org compatibility

= 1.1.0 =
* NEW: Mini Calendar shortcode [threecal_mini] - perfect for sidebar widgets
* NEW: Clickable event popup on mini calendar days
* NEW: List view now fully functional with view switcher
* NEW: Help tab in admin with complete shortcode documentation
* IMPROVED: Events grouped by day in list view
* IMPROVED: Full weekday names in list view headers

= 1.0.0 =
* Initial release
* Unlimited events and categories
* Month and list views
* Gutenberg block support
* Responsive design
* Schema.org event markup
* German translation included

== Upgrade Notice ==

= 1.2.2 =
Improved code quality and WordPress.org coding standards compliance.

= 1.2.1 =
Fixed plugin name to match WordPress.org requirements.

= 1.1.0 =
New Mini Calendar shortcode for sidebar widgets and improved list view!

= 1.0.0 =
Initial release of 3task Calendar - Professional WordPress Event Calendar.

== About 3task.de ==

3task Calendar is developed by **3task.de**, specialists in WordPress plugin development. We create focused, lightweight tools that solve real problems.

[Visit 3task.de](https://www.3task.de/)

== Support ==

**Free Version:**
* [WordPress.org Support Forum](https://wordpress.org/support/plugin/3task-calendar/)
* Built-in Help tab in plugin settings

**Pro Version:**
* Priority email support
* Custom development available

[Contact 3task.de](https://www.3task.de/)
