# 3task Calendar: Event Calendar for WordPress

An event calendar for clubs, practices, schools and small businesses that want to publish dates without sending visitor data to third parties. Everything runs on your own WordPress: no external fonts, no CDN, no tracking, no account.

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/3task-calendar)](https://wordpress.org/plugins/3task-calendar/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/3task-calendar)](https://wordpress.org/plugins/3task-calendar/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

## What is special about it

**Dates come straight from your posts.** Set a date while you write, for example the cinema release of a film, a book launch or a match day, and the post appears in the calendar by itself, with its title, link and image. A date you already keep in a custom field (ACF, Meta Box or any other plugin) can be used without entering anything twice.

## Features

- **Dates from posts:** a date field in the block editor and the classic editor, for posts, pages and custom post types
- **Existing date fields:** pick a custom field, apply it to all existing posts with one click
- **Date box in posts:** block "Date of this post" or `[threecal_post_date]` with an "Add to my calendar" button
- **Poster view:** upcoming dates as tiles with the post image
- **Postponed and cancelled dates:** "Postponed, previously …", cancelled events stay visible and crossed out
- **Only the month known:** festivals and other dates without a fixed day show "July 2027, date to be announced" and stay out of the iCal feed and the event schema until the day is set
- **CSV import:** preview before saving, updates instead of duplicates via `import_key`, also as WP-CLI command `wp threecal import`
- **Recurring events:** daily, weekly, every two weeks, monthly or yearly; single dates can be cancelled or removed
- **iCal:** "Add to my calendar" for every event and a subscription feed for Google Calendar, Apple Calendar, Outlook and Thunderbird
- **Views:** month, list, cards, compact, poster and a mini calendar for sidebars
- **Four designs:** Clear, Accent, Night and "Theme colors", which takes colors and font from your theme (theme.json)
- **Search and day filter:** a search field in the calendar and in event lists, a click on a day lists its events under the month grid
- **Highlighted categories:** partner events with a frame and a badge; links to other websites are nofollow by default, single categories or the whole calendar can be set to follow
- **Visitors submit events:** `[threecal_submit]` with spam protection without external services; submissions wait as drafts
- **Fill in from a website:** paste the event URL and the form reads title, date, place and organizer from its schema.org or Open Graph markup
- **Schema check:** the settings show which events get event markup and why not
- **JSON feed and llms.txt block:** `/wp-json/threecal/v1/events` with ISO dates, place, organizer and categories, for AI search and apps
- **Reminder in the calendar file** and a **print view** of the month
- **Event schema** for events at a real place, switchable per category
- **Accessible popup**, keyboard support, list view on small screens
- **Translations included:** German (du and Sie) complete; Spanish, French and Italian for everything visitors see
- **Privacy:** no external requests; the route link opens OpenStreetMap only on click

Every feature in this plugin is unlocked.

## Shortcodes

```
[threecal]
[threecal view="list" category="1"]
[threecal_events view="poster" columns="4"]
[threecal_events view="poster" month="current"]
[threecal_events view="poster" running="40"]
[threecal_upcoming limit="5"]
[threecal_mini]
[threecal_post_date]
[threecal_submit]
[threecal view="month" theme="site" show_print="true"]
[threecal_events day="2026-10-24"]
```

## Installation

Install from [WordPress.org](https://wordpress.org/plugins/3task-calendar/), or upload the folder `3task-calendar` to `/wp-content/plugins/` and activate it. Then open **3task Calendar** in the admin menu and add your first event, or switch on "Dates from posts" in the tab **Posts**.

Requires WordPress 6.2 and PHP 7.4.

## Changelog

The full changelog is in [readme.txt](readme.txt). Latest release: **1.8.1** (19 October 2026): the location line no longer repeats the city when it is part of the location name. 1.8.0 (17 October 2026) brought search and day filter, the "Theme colors" design, highlighted categories, visitor submissions, fill-in from a website, schema check, JSON feed and Spanish, French and Italian translations.

## Pro add-on

An add-on with more designs, sign-ups, office hours with holidays and calendar import is in the works: [3task Calendar Pro](https://www.3task.de/3task-calendar-pro/).

## Support

- [WordPress.org support forum](https://wordpress.org/support/plugin/3task-calendar/)
- [Report a bug](https://github.com/Tribun74/3task-calendar/issues)

## License

GPL-2.0, see [LICENSE](LICENSE).

Made by [3task](https://www.3task.de) in Germany.
