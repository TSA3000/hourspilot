# HoursPilot

Business hours, holidays and a live open/closed badge for one or more locations.

| | |
|---|---|
| **Version** | 0.7.0 |
| **Requires** | WordPress 6.0+, PHP 7.4+ |
| **Tested up to** | WordPress 7.1 |
| **Author** | Arthur Sam Mahdi ([tsa3000](https://github.com/TSA3000)) |
| **Contributors** | tsa3000 |
| **Tags** | business hours, opening hours, holidays, schema, open now |
| **License** | GPL-2.0-or-later |

<img src=".wordpress-org/banner-1544x500.png" alt="HoursPilot" width="772">

## Features

- Multiple locations, each with its own time zone
- Several opening hours per day (lunch breaks), "open 24 hours", hours past midnight
- Holidays and special hours (single days or date ranges), optionally repeating every year
- Countdown to opening / closing, calendar month view, vertical or horizontal layout
- Two styles: clean (default) and **card**, with a coloured header band, today's date, an OPEN/CLOSED badge, custom open/closed messages and a header image
- Lunch and other breaks with labels ("Lunch"), shown in the hours but counted as closed
- Map below the location details (OpenStreetMap or Google Maps), loaded only after a click
- Everything set per location in wp-admin, in tabs: General, Hours, Holidays, Messages, Display, Appearance, Location; a bare `[hourspilot id="…"]` is enough
- Shared holidays for all locations (e.g. public holidays), with opt-out per location
- Closed on public holidays automatically: tick the box, pick a source (Nager.Date or OpenHolidays API) and a country
- Import / export of locations as JSON
- Day names in the site language automatically, week start from *Settings → General*
- Live "Open now / Closed now" badge, computed in the browser by a small Rust/WebAssembly engine, so page caches never show a stale status
- schema.org `OpeningHoursSpecification` + `specialOpeningHoursSpecification` JSON-LD
- Works anywhere shortcodes do: block editor, Elementor, widgets
- Translated into Swedish and Arabic (right-to-left layout, optional Arabic-Indic digits ٠١٢…)

## Screenshots

### Card style (`style="card"`)

| Status band, message, lunch break, map | Arabic | Monospace, Swedish |
|---|---|---|
| <img src="docs/screenshots/card-week.png" alt="Card style: green header band with date and OPEN badge, lunch break chip, location image beside the details, Show map button" width="280"> | <img src="docs/screenshots/card-ar.png" alt="Card style in Arabic, right-to-left" width="280"> | <img src="docs/screenshots/card-mono-sv.png" alt="Card style with monospace font in Swedish" width="280"> |

<img src="docs/screenshots/card-today.png" alt="Card style, today only" width="420"> <img src="docs/screenshots/card-image.png" alt="Card style with a header image, close countdown and holidays before the regular hours" width="420">

### Admin

<img src="docs/screenshots/admin-hours.png" alt="Hours tab: Open, Closed, Open 24 hours per day, time slots with label and break" width="430"> <img src="docs/screenshots/admin-display.png" alt="Display tab with sections" width="430">

<img src="docs/screenshots/admin-messages.png" alt="Messages tab" width="430"> <img src="docs/screenshots/admin-appearance.png" alt="Appearance tab" width="430">

<img src="docs/screenshots/admin-holidays.png" alt="Holidays tab: own holidays, shared holidays, Closed on public holidays with source, country and the next public holidays" width="430"> <img src="docs/screenshots/admin-hours-ar.png" alt="Hours tab in Arabic, right-to-left" width="430"> <img src="docs/screenshots/admin-menu.png" alt="Admin menu: Business hours with Locations, Add location, Shared holidays, Import / Export" width="150">

### Clean style (default)

Tuesday 16:35, 25 minutes before closing, with short holiday hours later in the week:

| English | Swedish | Arabic (`digits="arab"`) |
|---|---|---|
| <img src="docs/screenshots/week-en.png" alt="Weekly hours in English: open now, closing countdown, holiday hours" width="280"> | <img src="docs/screenshots/week-sv.png" alt="Weekly hours in Swedish" width="280"> | <img src="docs/screenshots/week-ar.png" alt="Weekly hours in Arabic, right-to-left with Arabic-Indic digits" width="280"> |

`layout="horizontal" holiday_hours="1"`

<img src="docs/screenshots/horizontal-en.png" alt="Horizontal layout: one card per day" width="860">

`layout="calendar"` (English and Arabic)

<img src="docs/screenshots/calendar-en.png" alt="Calendar month view with holidays and today marked" width="420"> <img src="docs/screenshots/calendar-ar.png" alt="Calendar month view in Arabic, right-to-left" width="420">

## Installation

Download `hourspilot-X.Y.Z.zip` from [Releases](https://github.com/TSA3000/hourspilot/releases) (not the "Source code" archives),
then *WordPress → Plugins → Add New → Upload Plugin*. Add locations under *Locations* in the admin menu.

## Usage

*Business hours* in the admin menu:

- **Locations / Add location**: one location per shop, office or branch, each with its settings in tabs:
  - **General**: time zone, business type (for search engines)
  - **Hours**: Open / Closed / Open 24 hours per day, several time slots per day, labels and breaks ("Lunch")
  - **Holidays**: holidays and special hours, every year or once; whether to also use the shared holidays; *Closed on public holidays* with source and country (your own holidays win on the same day, so add a day with hours to stay open)
  - **Messages**: every text visitors see (status, badges, open/closed messages, "Closed All Day", countdowns)
  - **Display**: what to show (week or today, layout, header, local time, grouping, hidden days, holidays, countdowns, formats, digits, details, structured data)
  - **Appearance**: style (clean or card), font, colours, header band and image, map
  - **Location**: address, phones, email, website, map link and map position
- **Shared holidays**: holidays every location uses
- **Import / Export**: all locations as one JSON file, or one location via *Export* in the list; imports become drafts

Then paste the shortcode from the location's sidebar into a page.

## Shortcode

```
[hourspilot id="123"]
```

Omit `id` to use the first location.

| Attribute | Default | Description |
|---|---|---|
| `view` | `week` | `week` or `today` |
| `style` | `default`¹ | `default` (clean) or `card` (header band with date and OPEN/CLOSED badge, dashed rows) |
| `font` | `theme`¹ | `theme` or `mono` |
| `band` | `accent`¹ | Card header colour: `accent`, `status` (green when open, red when closed) or `color` (see `band_color`) |
| `band_color` | ¹ | Card header colour with `band="color"`, e.g. `#3346b8` |
| `header_image` | ¹ | Image URL behind the card header (darkened for readability) |
| `header_date_format` | `l F jS` | PHP date format of the card header date ("Tuesday November 17th"); translated per language |
| `badge_open`, `badge_closed` | `Open`, `Closed` | Card badge texts |
| `open_message`, `closed_message` | | Card header line, e.g. "We are open! Come in." |
| `local_time_label` | `Current local time is` | Card: text before the local time |
| `layout` | `vertical` | `vertical`, `horizontal` (one card per day) or `calendar` (month view, 12 months ahead) |
| `title` | *(empty)* | Empty = location name, `0` = no title |
| `group` | `1` | Merge consecutive days with identical hours |
| `short_days` | `0` | Short day names |
| `hide_closed` | `0` | Hide closed days |
| `hide_days` | | ISO day numbers to hide, e.g. `6,7` |
| `show_header` | `1` | Title, status badge and local time row |
| `show_status` | `1` | Open/closed badge |
| `show_local_time` | `0` | Current time at the location |
| `show_holidays` | `1` | List of upcoming holidays |
| `holiday_days` | `60` | How far ahead to list holidays |
| `date_format` | `j M` | PHP date format for holidays |
| `time_format` | | e.g. `H:i` (24 h) or `g:i A` (12 h); empty = *Settings → General* |
| `holidays_position` | `bottom` | `top` = holidays before the regular hours |
| `hide_hours_on_holiday` | `0` | `1` = only the holiday notice while today is a holiday |
| `map` | ¹ | `osm` (needs the map position) or `google`: map below the location details |
| `map_consent` | `1` | `1` = visitors click "Show map" first (nothing is sent to the map service before); `0` = load directly |
| `digits` | `latn` | `latn` (0–9) or `arab` (Arabic-Indic ٠–٩); site-wide default via the `hourspilot_digits` filter |
| `holiday_hours` | `0` | `1` = holiday hours replace this week's regular hours (turns off grouping) |
| `show_info` | `1` | Address, phone, e-mail, website, directions |
| `show_image` | `1` | Featured image |
| `schema` | `1` | schema.org JSON-LD |
| `accent` | | Accent colour, e.g. `#0f766e` |
| `open_countdown` | `0` | Countdown to the next opening while closed |
| `close_countdown` | `0` | Countdown to closing |
| `countdown_minutes` | `60` | Show the close countdown this long before closing |
| `countdown_format` | `{h}:{mm}:{ss}` | Tokens `{d}` `{h}` `{hh}` `{m}` `{mm}` `{s}` `{ss}`; without `{d}` hours keep counting past 24 |
| `open_countdown_text` | `Opening in [time]` | `[time]` = the countdown |
| `close_countdown_text` | `Closing in [time]` | |
| `open_text`, `closed_text`, `closed_label`, `open24_label`, `holiday_title` | *(translated)* | Labels, e.g. `closed_label="Closed All Day"` |

Every attribute can also be set on the location (tabs Messages, Display, Appearance); the shortcode attribute overrides it.
¹ Usually set in the Appearance tab.

**Breaks:** in the weekly hours, give a time slot a label (e.g. "Lunch") and tick *Break*: it is shown with the label but counts as closed.

## How it works

```mermaid
flowchart LR
    subgraph wp["WordPress (PHP)"]
        admin["Admin: Locations<br/>weekly hours, holidays, info"] -->|post meta| db[("hourspilot_location")]
        db --> sc["Shortcode [hourspilot]"]
        lang["languages/<br/>sv_SE, ar"] -.->|labels, day + month names| sc
        sc --> ld["schema.org JSON-LD"]
    end
    subgraph browser["Browser"]
        page["Page: hours table<br/>+ data-hp JSON"]
        js["frontend.js"]
        wasm[["hourspilot.wasm<br/>Rust time engine"]]
        page --> js
        js -->|"config + local time<br/>in the location's time zone"| wasm
        wasm -->|"status, countdown,<br/>holiday rows, calendar"| js
        js -->|"updates every 30 s<br/>(1 s with countdowns)"| page
    end
    sc -->|server-rendered HTML| page
    cache["Page cache"] -.->|cached HTML stays correct| page
```

The server renders the hours once; everything that depends on *now* (open/closed, countdowns, today's holiday,
the calendar) is computed in the browser by the Rust engine. A cached page therefore never shows a stale status.

## External services

Only if *Closed on public holidays* is ticked for a location, the server (never the visitor's browser) fetches the
country list and that country's public holidays from the chosen source. Sent: the country code and the year. Results are
cached for a week. Nothing is fetched while the option is off.

- [Nager.Date](https://date.nager.at) (`date.nager.at`): [terms](https://date.nager.at/legal/termsofservice), [privacy](https://date.nager.at/legal/privacy)
- [OpenHolidays API](https://www.openholidaysapi.org) (`openholidaysapi.org`): [privacy](https://legal.stueber.de/en/privacy.php)

The map in the Location tab is loaded from OpenStreetMap or Google Maps only after the visitor clicks it.

## Translations

English (source), Swedish (`sv_SE`) and Arabic (`ar`) ship in `languages/`: `.po` (editable), `.mo` (WordPress 6.0–6.4)
and `.l10n.php` (WordPress 6.5+). The site language (*Settings → General*) picks them automatically; day and month names
come from WordPress itself. Arabic gets a right-to-left layout through direction-neutral CSS.

To add a language: copy `languages/hourspilot.pot` to `hourspilot-<locale>.po`, translate it (e.g. with Poedit), then
compile with `wp i18n make-mo languages/ && wp i18n make-php languages/`. After changing texts in the code, refresh the
template with `wp i18n make-pot . languages/hourspilot.pot --exclude=rust,.github,languages` and
`wp i18n update-po languages/hourspilot.pot languages/`.

## Styling

Most things are set in the Appearance box. For more, override CSS variables on `.hourspilot`:

`--hp-accent`, `--hp-open`, `--hp-soon`, `--hp-shut`, `--hp-line`, `--hp-radius`, `--hp-pad`, and for the card style
`--hp-band`, `--hp-band-open`, `--hp-band-closed`, `--hp-band-edge`, `--hp-dot`, `--hp-chip`.

## Developers

Filters: `hourspilot_schema( array $data, int $location_id )`, `hourspilot_digits( string $system )` (`latn` | `arab`)

Data is stored as post meta on the `hourspilot_location` post type:
`_hourspilot_hours`, `_hourspilot_holidays` (from, to, label, slots, recurring), `_hourspilot_info`, `_hourspilot_timezone`, `_hourspilot_schema_type`,
`_hourspilot_settings`, `_hourspilot_use_shared`, `_hourspilot_public_holidays` (source, country). Fetched public holidays are cached in transients `hourspilot_ph_*`.

Uninstalling the plugin does not delete your locations.

### Time engine

`rust/` (Rust, GPL-2.0-or-later) is compiled to `assets/hourspilot.wasm`; the compiler version is pinned in `rust/rust-toolchain.toml`.

```sh
cd rust
cargo test && cargo build --release --target wasm32-unknown-unknown
cp target/wasm32-unknown-unknown/release/hourspilot_core.wasm ../assets/hourspilot.wasm
```

### Releases

1. Bump `Version` and `HOURSPILOT_VERSION` in `hourspilot.php`, the version above, and add a `### X.Y.Z` entry under Changelog.
2. Commit, then `git tag -a vX.Y.Z -m "HoursPilot X.Y.Z" && git push origin master vX.Y.Z`.

`.github/workflows/release.yml` checks the versions, lints PHP 7.4 + 8.3, runs the Rust tests, builds the WebAssembly fresh
and publishes the installable zip as a GitHub release with this changelog entry as notes. Tags with a hyphen
(`v1.0.0-beta.1`) become pre-releases.

The zip gets a wordpress.org `readme.txt` generated from this file (`.github/scripts/readme-txt.py`); the **Contributors**
and **Tags** rows above exist for it.

## Changelog

### 0.7.0

- Unique `hourspilot_` prefix for the post type, post meta, transients, admin pages and actions (wordpress.org guidelines); data of earlier versions is moved automatically on update
- Plugin icon and banner
- Tested up to WordPress 7.1

### 0.6.0

- *Closed on public holidays* (Holidays tab): pick a source (Nager.Date, OpenHolidays API) and a country; nationwide public holidays are shown as closed, cached weekly, with a preview of the next ones
- Own and shared holidays override a public holiday on the same day
- Included in import / export

### 0.5.0

- Location editor with tabs (General, Hours, Holidays, Messages, Display, Appearance, Location) and HoursPilot's own icons; new admin menu icon
- Every shortcode option can be set per location; the shortcode only needs the id
- Hours: Open / Closed / Open 24 hours as buttons
- Shared holidays (Business hours → Shared holidays), opt-out per location
- Import / Export of locations as JSON (all, or one from the list)
- The 0.4.0 Appearance box settings carry over automatically
- All new texts in Swedish and Arabic

### 0.4.0

- Card style (`style="card"`): header band with today's date, OPEN/CLOSED badge, local time, open/closed messages, dashed rows, a dot before today, image beside the location details
- Header band colour from the accent, the status (green/red) or an own colour; header background image
- Monospace font option
- Breaks: time slots with a label ("Lunch") that count as closed; labels on any slot
- Map below the location details: OpenStreetMap or Google Maps, click to load (`map`, `map_consent`); map position field
- Second phone number
- Appearance box per location (style, colours, font, header image, map) as defaults for the shortcode
- `time_format`, `holidays_position`, `hide_hours_on_holiday`, `header_date_format`, `badge_open`/`badge_closed`, `open_message`/`closed_message`
- All new texts translated into Swedish and Arabic

### 0.3.1

- Horizontal layout: 12-hour times wrap inside the day cards instead of overlapping the next day
- Arabic calendar: the month arrows point the right way (the browser already mirrors them)
- Local time without a leading zero ("4:35 PM"), like WordPress's own times
- README screenshots (English, Swedish, Arabic)

### 0.3.0

- Swedish and Arabic translations; `.pot` template for more languages
- Right-to-left layout (direction-neutral CSS, mirrored calendar arrows)
- `digits="arab"`: Arabic-Indic digits in times, dates, countdowns and the calendar
- Translatable list separator (Arabic "،")
- Translations load on `init` (no "loaded too early" notice on WordPress 6.7+)

### 0.2.0

- Time engine rewritten in Rust, compiled to WebAssembly (source in `rust/`)
- Recurring holidays ("Every year")
- Countdown to opening and closing (`open_countdown`, `close_countdown`)
- Calendar month view and horizontal layout (`layout="calendar|horizontal"`)
- Holiday hours can replace this week's regular hours (`holiday_hours="1"`)
- Opening times more than a week away are shown with their date

### 0.1.0

- First release
