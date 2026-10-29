=== HoursPilot ===
Contributors: tsa3000
Tags: business hours, opening hours, holidays, schema, open now
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
Version: 0.1.0
Author: Arthur Sam Mahdi
License: GPLv2 or later

Business hours, holidays and a live open/closed badge for one or more locations.

== Description ==

* Multiple locations, each with its own time zone
* Several opening hours per day (lunch breaks), "open 24 hours", hours past midnight
* Holidays and special hours (single days or date ranges)
* Day names in the site language automatically, week start from Settings > General
* Live "Open now / Closed now" badge, computed in the browser, so page caches never show a stale status
* schema.org OpeningHoursSpecification + specialOpeningHoursSpecification JSON-LD
* Works anywhere shortcodes do: block editor, Elementor, widgets

== Shortcode ==

[hourspilot id="123"]  (omit id to use the first location)

view="week|today"      group="1"          short_days="0"
hide_closed="0"        hide_days="6,7"    show_header="1"
show_status="1"        show_local_time="0"
show_holidays="1"      holiday_days="60"  date_format="j M"
show_info="1"          show_image="1"     schema="1"
title=""  (empty = location name, "0" = none)
accent="#0f766e"
open_text, closed_text, closed_label, open24_label, holiday_title

== Styling ==

Override CSS variables on .hourspilot:
--hp-accent, --hp-open, --hp-soon, --hp-shut, --hp-line, --hp-radius, --hp-pad

== Developers ==

Filter: hourspilot_schema( array $data, int $location_id )

Data is stored as post meta on the hp_location post type:
_hp_hours, _hp_holidays, _hp_info, _hp_timezone, _hp_schema_type

Uninstalling the plugin does not delete your locations.
