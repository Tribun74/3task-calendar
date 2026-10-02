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
- **Recurring events:** daily, weekly, every two weeks, monthly or yearly; single dates can be cancelled or removed
- **iCal:** "Add to my calendar" for every event and a subscription feed for Google Calendar, Apple Calendar, Outlook and Thunderbird
- **Views:** month, list, cards, compact, poster and a mini calendar for sidebars
- **Three designs:** Clear, Accent and Night, with your own accent color
- **Event schema** for events at a real place, switchable per category
- **Accessible popup**, keyboard support, list view on small screens
- **German included:** informal (du) and formal (Sie)
- **Privacy:** no external requests; the route link opens OpenStreetMap only on click

Every feature in this plugin is unlocked.

## Shortcodes

```
[threecal]
[threecal view="list" category="1"]
[threecal_events view="poster" columns="4"]
[threecal_upcoming limit="5"]
[threecal_mini]
[threecal_post_date]
```

## Installation

Install from [WordPress.org](https://wordpress.org/plugins/3task-calendar/), or upload the folder `3task-calendar` to `/wp-content/plugins/` and activate it. Then open **3task Calendar** in the admin menu and add your first event, or switch on "Dates from posts" in the tab **Posts**.

Requires WordPress 5.8 and PHP 7.4.

## Changelog

The full changelog is in [readme.txt](readme.txt). Latest release: **1.4.0** (2 October 2026) with dates from posts, poster view, postponed and cancelled dates.

## Pro add-on

An add-on with more designs, sign-ups, office hours with holidays and calendar import is in the works: [3task Calendar Pro](https://www.3task.de/3task-calendar-pro/).

## Support

- [WordPress.org support forum](https://wordpress.org/support/plugin/3task-calendar/)
- [Report a bug](https://github.com/Tribun74/3task-calendar/issues)

## License

GPL-2.0, see [LICENSE](LICENSE).

Made by [3task](https://www.3task.de) in Germany.
