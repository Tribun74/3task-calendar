=== 3task Calendar ===
Contributors: 3task
Tags: event calendar, recurring events, ical, gdpr, events
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Event calendar without Google, CDN or tracking. Set dates right in your posts, recurring events, iCal subscription, German included.

== Description ==

**3task Calendar** is an event calendar for clubs, practices, schools and small businesses that want to publish dates without sending visitor data to third parties. Everything runs on your own WordPress: no external fonts, no CDN, no tracking, no account.

Dates can come straight from your posts. Set a date while you write, for example the cinema release of a film, a book release or a match day, and the post appears in the calendar by itself, with its title, link and image. A date you already keep in a custom field (Advanced Custom Fields, Meta Box or any other plugin) can be used without entering anything twice.

Visitors can add a single event to their own calendar or subscribe to the whole calendar (or one category) on their phone. Changes appear there automatically.

= Key Features =

* **Unlimited Events**: Create as many events as you need
* **Dates from Posts**: A date field in the post editor (block editor and classic editor) for posts, pages and custom post types; the calendar entry stays in sync with title, link, image and date
* **Existing Date Fields**: Use a date you already store in a custom field (ACF, Meta Box, any plugin) and apply it to all existing posts with one click
* **Date Box in Posts**: Block "Date of this post" or `[threecal_post_date]` shows the date with an "Add to my calendar" button, also automatically at the start or end of a post
* **Poster View**: Upcoming dates as large tiles with the post image, for example "This week at the cinema"
* **Postponed Dates**: When a published date moves, the calendar shows "Postponed, previously …" and subscribers get the new date
* **Recurring Events**: Daily, weekly, every two weeks, monthly or yearly, with an end date; single dates can be cancelled, removed or changed on their own
* **Cancelled Events**: A cancelled date stays visible, crossed out, so nobody turns up in vain; subscribers and search engines get the cancellation too
* **iCal Export and Subscription**: "Add to my calendar" for every event and a subscription feed (all events or one category) for Google Calendar, Apple Calendar, Outlook and Thunderbird
* **German Translation**: Informal (du) and formal (Sie) German included
* **Unlimited Categories**: Organize events with color-coded categories
* **Month and List Views**: Multi-day events appear on every day they cover
* **Three Designs**: Clear (light), Accent (bold color header) and Night (dark), with your own accent color; events take their category color automatically
* **Responsive**: Adapts to small screens
* **Gutenberg Block**: Live preview in the editor, choose view, design and category
* **Shortcodes**: Flexible placement options
* **Schema.org SEO**: Event markup with ISO dates and time zone for events at a real place; office hours, holidays or release dates can be excluded per category, as Google requires
* **Route Link**: "Plan route" opens OpenStreetMap only when the visitor clicks it
* **Accessible Popup**: Dialog role, keyboard focus and Escape to close
* **Mobile List**: On small screens the calendar starts in list view
* **GDPR Friendly**: Made in Germany with privacy in mind

= Easy to Use =

1. Install and activate
2. Create your first event
3. Add the calendar to any page with shortcode or Gutenberg block
4. Done!

= Shortcodes =

**Display Calendar:**
`[threecal]`

Options: `view` (month, list), `category`, `location`, `theme`, `show_filters`, `show_legend`, `show_subscribe`, `mobile_list` (true/false).

**With Options:**
`[threecal view="month" category="1"]`

**Event List:**
`[threecal_events limit="10"]`

Options: `view` (list, grid, compact, poster), `category`, `location`, `limit` (per page), `columns`, `show_past`, `show_pagination`.

**Posters with Post Images:**
`[threecal_events view="poster" category="1" columns="4"]`

**Date of a Post (with "Add to my calendar"):**
`[threecal_post_date]`

**Upcoming Events:**
`[threecal_upcoming limit="5"]`

**Mini Calendar (Perfect for Sidebars):**
`[threecal_mini]`

= Gutenberg Block =

Simply search for "3task Calendar" in the block inserter to add a calendar to any page or post.

= No feature locks =

3task Calendar works completely on its own. Every feature in this plugin is unlocked.

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

Yes. 3task Calendar is developed in Germany with privacy in mind and makes no external requests: no web fonts, no CDN, no tracking, no account. Visitor data stays on your site.

= Does the plugin send data to external services? =

No. The calendar, the iCal files and the subscription feed are generated by your own site. The "Plan route" link only opens OpenStreetMap when a visitor clicks it.

= Can I put posts into the calendar? =

Yes. Under 3task Calendar > Posts, switch on the date for posts, pages or a custom post type and give the field a name, for example "Release date". While writing, set the date in the editor sidebar. The post appears in the calendar once it is published and disappears again as a draft or in the trash.

= I already store dates in a custom field. Do I have to enter them again? =

No. Choose the field under "Date comes from". The list shows every field that holds dates, how many posts use it and an example value, so the right one is easy to spot. "Apply to existing posts" puts all of them into the calendar in one go. Dates like 2026-10-01, 20261001 (ACF), 01.10.2026 and Unix timestamps are read, and changes made by imports or other plugins are picked up automatically.

= Why do some events not appear in the event schema? =

Google only accepts events that take place at a real location, and it does not allow office hours or release dates as events. Events without a location get no markup, and every category can be excluded with "Not an event for search engines".

= How do visitors subscribe to the calendar? =

Every calendar shows a "Subscribe to this calendar" link. It opens the calendar app via webcal://. The address can also be copied into Google Calendar ("From URL"). Use `[threecal category="2"]` to offer a feed for one category only.

= How do recurring events work? =

Choose a repeat pattern and an end date when editing an event. The plugin creates the single dates. Below the form, "Dates of this series" lists every date: cancel one (it stays visible, crossed out), restore it, remove it or edit it on its own.

= Does the calendar work with page caching plugins? =

Yes. Month navigation and the event popup use public read-only requests without a nonce, so cached pages keep working.

= Where can I get support? =

Please use the [WordPress.org support forum](https://wordpress.org/support/plugin/3task-calendar/) for questions and bug reports.

== External Services ==

3task Calendar does not connect to any external service. The "Plan route" link of an event opens OpenStreetMap in a new tab only when a visitor clicks it; nothing is sent before that.

== Screenshots ==

1. Month view in the Accent design: recurring events, multi-day events and category colors
2. Event details with location, route link and "Add to my calendar"
3. List view, which small screens use automatically
4. The Night design for dark websites
5. Admin dashboard with upcoming events and quick actions
6. Editing a recurring event: repeat rule, end date, categories and location
7. Design tab with a preview of all three designs
8. Event list with series, categories and status
9. Dates from posts: set the release date in the post sidebar and the post appears in the calendar by itself
10. Poster view with the post images, and the date box with "Add to my calendar" in a post

== Changelog ==

= 1.4.0 =
* NEW: Dates from posts. Switch on a date field for posts, pages or custom post types; the post appears in the calendar and stays in sync (title, link, image, date, draft and trash)
* NEW: Use a date from an existing custom field (ACF, Meta Box or any plugin) and apply it to all existing posts with one click; the settings list the fields found with count and example
* NEW: Block "Date of this post" and shortcode [threecal_post_date] with an "Add to my calendar" button, optionally added automatically to the start or end of posts
* NEW: Poster view: upcoming dates as tiles with the post image ([threecal_events view="poster"] or the calendar block)
* NEW: "Postponed, previously …" when the date of a published post moves
* NEW: Sortable date column in the post list
* NEW: Event markup only for events with a location; categories can be excluded (office hours, holidays, release dates)
* NEW: "Dates of this series" in the event editor: cancel, restore or remove a single date, for example when a training is called off
* NEW: Cancelled events stay visible, crossed out and marked "Cancelled", in all views, in the event schema (EventCancelled) and in the iCal feed
* NEW: Text on event and accent colors switches to dark when the color is too light to read white text on it
* Fixed: the accent color in the Design tab was not saved
* Fixed: after deleting an event the admin showed the dashboard without a confirmation
* IMPROVED: Titles in the upcoming list link to the event page or post
* IMPROVED: Every event list on a page has its own page navigation
* IMPROVED: The month title is announced to screen readers when it changes and no longer adds a heading for every calendar to the page outline
* IMPROVED: A cancelled first date of a series can be restored from the list of dates
* IMPROVED: Event markup uses the image of the page or the site icon when an event has no image of its own
* IMPROVED: long event lists show a shortened page navigation (1 2 3 … 16)
* IMPROVED: the event dot of today stays visible in the mini calendar
* Removed: unused code for Google Maps geocoding, e-mail reminders and old admin pages; new installs no longer create the unused subscribers table

= 1.3.1 =
* Fixed: calendars set to the list view (shortcode view="list" or the block setting) showed the month grid
* Fixed: the list view showed dates in German order ("2. October") in every language; the format is now translatable
* Fixed: categories created without a sort order could not be saved on databases in strict mode

= 1.3.0 =
* NEW: Three redesigned calendar designs (Clear, Accent, Night) with accent color setting and a Design tab with preview
* NEW: Events use the color of their category automatically
* NEW: Redesigned admin with dashboard, upcoming events, quick actions and a compact event list with filters
* NEW: Block editor shows a live preview and offers design and category
* FIXED: The block did not render on the front end (block name started with a digit); existing pages are converted automatically
* NEW: Recurring events (daily, weekly, every two weeks, monthly, yearly) with end date and editable single dates
* NEW: "Add to my calendar" (.ics) for every event and an iCal subscription feed for all events or one category
* NEW: German translation (informal and formal)
* NEW: "Plan route" link to OpenStreetMap for event locations
* NEW: On small screens the calendar starts in list view (shortcode option mobile_list)
* IMPROVED: Event popup is an accessible dialog with focus handling; keyboard support in the list view
* Fixed: month navigation did not update the calendar grid when the new month had events
* Fixed: multi-day events now appear on every day they cover and in the upcoming list while they are running
* Fixed: event list pagination links showed page 1 again
* Fixed: "today" and the current month now use the site time zone instead of UTC
* Fixed: "+N more" in the month view now shows the remaining events of that day
* Fixed: month navigation and popups keep working on cached pages
* Security: the REST API no longer returns draft events to visitors
* Security: settings can only be changed by users with the calendar settings capability; saving no longer resets other settings
* Security: the mini calendar popup no longer renders event data as HTML
* SEO: event schema now uses ISO 8601 dates with time zone, works with the block and respects the category of the calendar
* Performance: the front end no longer loads Dashicons (icons are inline SVG); fewer database queries for categories and locations
* REST API: pagination (per_page, page) and date validation for /events
* Settings: options for event schema and data removal on uninstall
* Database upgrades now run automatically after plugin updates

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
* NEW: Mini Calendar shortcode [threecal_mini] for sidebar widgets
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

= 1.4.0 =
Dates straight from your posts, poster view, cancelling single dates of a series. Fixes saving the accent color and the list view.

= 1.3.1 =
Fixes the list view, which showed the month grid in 1.3.0.

= 1.3.0 =
Recurring events, iCal export and subscription, German translation. Also fixes month navigation, multi-day events, pagination and time zone handling, plus security hardening. Recommended for all users.

= 1.2.2 =
Improved code quality and WordPress.org coding standards compliance.

= 1.2.1 =
Fixed plugin name to match WordPress.org requirements.

= 1.1.0 =
New Mini Calendar shortcode for sidebar widgets and improved list view!

= 1.0.0 =
Initial release of 3task Calendar, a WordPress event calendar.

== Credits ==

Icons: [Tabler Icons](https://tabler.io/icons), MIT license, embedded as inline SVG (no icon font, no external request).

== About 3task.de ==

3task Calendar is developed by **3task.de**, specialists in WordPress plugin development. We create focused, lightweight tools that solve real problems.

[Visit 3task.de](https://www.3task.de/)

== Support ==

* [WordPress.org Support Forum](https://wordpress.org/support/plugin/3task-calendar/)
* Built-in Help tab in plugin settings

[Contact 3task.de](https://www.3task.de/)
