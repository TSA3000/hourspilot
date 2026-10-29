//! HoursPilot time engine.
//!
//! Everything that depends on "now" runs in the browser (compiled to WebAssembly), so full-page caches never
//! freeze a stale status. PHP embeds the location as JSON ([`Config`]); the JS loader passes the wall-clock
//! date and time in the location's time zone ([`Now`]) and applies the [`Update`] to the server-rendered markup.
//!
//! Days use ISO-8601 numbering (1 = Monday … 7 = Sunday). Times are "HH:MM"; a slot whose close is earlier
//! than its open runs past midnight. A holiday overrides the weekly hours; no slots = closed all day.
//! Recurring holidays repeat every year on the same dates (29 Feb -> 28 Feb in common years).

use serde::{Deserialize, Serialize};
use std::collections::HashMap;

pub mod date;
#[cfg(target_arch = "wasm32")]
mod wasm;

/// How far ahead to look for the next opening (and the open countdown).
const LOOKAHEAD_DAYS: i32 = 366;
/// The status badge turns amber this long before closing.
const SOON_SECS: i64 = 30 * 60;

/* ---------- Input ---------- */

#[derive(Deserialize, Clone, Copy, PartialEq, Eq, Debug, Default)]
pub enum Mode {
    #[serde(rename = "open")]
    Open,
    #[default]
    #[serde(rename = "closed")]
    Closed,
    #[serde(rename = "24h")]
    H24,
}

/// Opening slot: `o`/`c` = "HH:MM", `of`/`cf` = the same times formatted in the site's time format.
/// `l` = optional label ("Lunch"); `x` = break: shown in the hours but closed.
#[derive(Deserialize, Clone, Debug, Default)]
pub struct Slot {
    pub o: String,
    pub c: String,
    #[serde(default)]
    pub of: String,
    #[serde(default)]
    pub cf: String,
    #[serde(default)]
    pub l: String,
    #[serde(default)]
    pub x: bool,
}

impl Slot {
    /// (open, close) in minutes since midnight; None if malformed or a break.
    fn mins(&self) -> Option<(i64, i64)> {
        if self.x {
            return None;
        }
        Some((hhmm(&self.o)?, hhmm(&self.c)?))
    }
}

fn hhmm(t: &str) -> Option<i64> {
    let (h, m) = t.trim().split_once(':')?;
    let (h, m): (i64, i64) = (h.parse().ok()?, m.parse().ok()?);
    ((0..24).contains(&h) && (0..60).contains(&m)).then_some(h * 60 + m)
}

#[derive(Deserialize, Clone, Debug, Default)]
pub struct Day {
    #[serde(default)]
    pub m: Mode,
    #[serde(default)]
    pub s: Vec<Slot>,
}

/// Holiday or special hours from `f` to `t` (inclusive). `r` = every year.
#[derive(Deserialize, Clone, Debug, Default)]
pub struct Holiday {
    pub f: String,
    #[serde(default)]
    pub t: String,
    #[serde(default)]
    pub l: String,
    #[serde(default)]
    pub s: Vec<Slot>,
    #[serde(default)]
    pub r: bool,
}

impl Holiday {
    fn range(&self) -> Option<(i32, i32)> {
        let f = date::parse(&self.f)?;
        let t = date::parse(&self.t).filter(|&t| t >= f).unwrap_or(f);
        Some((f, t))
    }

    pub fn covers(&self, day: i32) -> bool {
        let Some((f, t)) = self.range() else { return false };
        if !self.r {
            return f <= day && day <= t;
        }
        // The occurrence starting this year, or last year's if it spans New Year.
        let years = date::civil_from_days(day).0 - date::civil_from_days(f).0;
        [years - 1, years]
            .into_iter()
            .any(|n| date::shift_years(f, n) <= day && day <= date::shift_years(t, n))
    }
}

#[derive(Deserialize, Debug, Default)]
#[serde(default, rename_all = "camelCase")]
pub struct I18n {
    pub open: String,
    pub closed: String,
    pub closed_label: String,
    pub open24: String,
    /// "closes %s"
    pub closes_at: String,
    /// "opens %s"
    pub opens_at: String,
    pub today: String,
    pub tomorrow: String,
    /// Short day names keyed "1".."7".
    pub days: HashMap<String, String>,
    /// Full day names keyed "1".."7" (header date).
    pub days_long: HashMap<String, String>,
    pub months: Vec<String>,
    pub months_short: Vec<String>,
    pub prev_month: String,
    pub next_month: String,
    /// List separator, ", " by default ("، " in Arabic): status + time, several opening slots.
    pub sep: String,
}

impl I18n {
    fn sep(&self) -> &str {
        if self.sep.is_empty() { ", " } else { &self.sep }
    }
}

/// Countdown messages; `[time]` in the texts is replaced by the duration in `format`
/// (tokens `{d}` `{h}` `{hh}` `{m}` `{mm}` `{s}` `{ss}`; without `{d}` hours keep counting past 24).
#[derive(Deserialize, Debug)]
#[serde(default, rename_all = "camelCase")]
pub struct Countdown {
    pub open: bool,
    pub close: bool,
    /// Show the close countdown this many minutes before closing.
    pub mins: i64,
    pub open_text: String,
    pub close_text: String,
    pub format: String,
}

impl Default for Countdown {
    fn default() -> Self {
        Self {
            open: false,
            close: false,
            mins: 60,
            open_text: "[time]".into(),
            close_text: "[time]".into(),
            format: "{h}:{mm}:{ss}".into(),
        }
    }
}

#[derive(Deserialize, Debug)]
pub struct Config {
    /// Keyed "1".."7".
    #[serde(default)]
    pub week: HashMap<String, Day>,
    #[serde(default)]
    pub hol: Vec<Holiday>,
    #[serde(default)]
    pub i18n: I18n,
    #[serde(default)]
    pub cd: Countdown,
    /// Show holiday hours in this week's rows instead of the regular hours.
    #[serde(default)]
    pub hh: bool,
    /// First day of the week, ISO.
    #[serde(default = "monday")]
    pub ws: u8,
    /// Digits in the output: "arab" = Arabic-Indic (٠١٢…), otherwise as given (Western).
    #[serde(default)]
    pub dg: String,
    /// Header date format, PHP date() tokens d j S D l N F M m n Y y (\\ escapes); empty = no header date.
    #[serde(default)]
    pub hdf: String,
}

impl Config {
    /// Applies the digit setting to text shown to visitors (idempotent).
    pub fn digits(&self, s: String) -> String {
        if self.dg != "arab" {
            return s;
        }
        s.chars().map(|c| c.to_digit(10).and_then(|d| char::from_u32(0x0660 + d)).unwrap_or(c)).collect()
    }
}

fn monday() -> u8 {
    1
}

/// Wall-clock now in the location's time zone.
#[derive(Deserialize, Debug)]
pub struct Now {
    /// "YYYY-MM-DD"
    pub date: String,
    /// Seconds since local midnight.
    pub sec: i64,
}

/* ---------- Schedule ---------- */

pub struct Sched<'a> {
    pub mode: Mode,
    pub slots: &'a [Slot],
    pub hol: Option<&'a Holiday>,
}

impl Config {
    pub fn holiday_on(&self, day: i32) -> Option<&Holiday> {
        self.hol.iter().find(|h| h.covers(day))
    }

    /// Hours for a date: a holiday overrides the weekly hours.
    pub fn schedule(&self, day: i32) -> Sched<'_> {
        if let Some(h) = self.holiday_on(day) {
            let mode = if h.s.is_empty() { Mode::Closed } else { Mode::Open };
            return Sched { mode, slots: &h.s, hol: Some(h) };
        }
        match self.week.get(&date::weekday(day).to_string()) {
            Some(d) if d.m == Mode::Open && d.s.iter().all(|s| s.x) => Sched { mode: Mode::Closed, slots: &[], hol: None },
            Some(d) => Sched { mode: d.m, slots: &d.s, hol: None },
            None => Sched { mode: Mode::Closed, slots: &[], hol: None },
        }
    }

    pub fn hours_text(&self, s: &Sched) -> String {
        match s.mode {
            Mode::H24 => self.i18n.open24.clone(),
            Mode::Open if s.slots.iter().any(|x| !x.x) => s
                .slots
                .iter()
                .filter(|x| !x.x)
                .map(|x| format!("{} – {}", x.of, x.cf))
                .collect::<Vec<_>>()
                .join(self.i18n.sep()),
            _ => self.i18n.closed_label.clone(),
        }
    }
}

/* ---------- Status ---------- */

#[derive(Serialize, Debug, Default, PartialEq)]
pub struct Status {
    pub open: bool,
    pub h24: bool,
    /// Formatted closing time while open.
    #[serde(skip_serializing_if = "Option::is_none")]
    pub until: Option<String>,
    /// Seconds until closing.
    #[serde(skip_serializing_if = "Option::is_none")]
    pub left: Option<i64>,
    /// Next opening: days from today, date, formatted time ("" = from midnight), seconds from now.
    #[serde(skip_serializing_if = "Option::is_none")]
    pub next_days: Option<i32>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub next_date: Option<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub next_time: Option<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub next_in: Option<i64>,
}

pub fn status(cfg: &Config, day: i32, sec: i64) -> Status {
    let today = cfg.schedule(day);
    if today.mode == Mode::H24 {
        return Status { open: true, h24: true, ..Default::default() };
    }
    let open_until = |cf: &str, end_min: i64| Status {
        open: true,
        until: Some(cf.to_string()),
        left: Some(end_min * 60 - sec),
        ..Default::default()
    };

    // A slot from yesterday that runs past midnight.
    let yesterday = cfg.schedule(day - 1);
    if yesterday.mode == Mode::Open {
        for s in yesterday.slots {
            if let Some((o, c)) = s.mins() {
                if c < o && sec < c * 60 {
                    return open_until(&s.cf, c);
                }
            }
        }
    }
    if today.mode == Mode::Open {
        for s in today.slots {
            if let Some((o, c)) = s.mins() {
                if sec >= o * 60 && (c < o || sec < c * 60) {
                    return open_until(&s.cf, if c < o { c + 1440 } else { c });
                }
            }
        }
    }

    // Next opening.
    for k in 0..=LOOKAHEAD_DAYS {
        let d = cfg.schedule(day + k);
        let next = |time: &str, min: i64| Status {
            next_days: Some(k),
            next_date: Some(date::format(day + k)),
            next_time: Some(time.to_string()),
            next_in: Some(i64::from(k) * 86_400 + min * 60 - sec),
            ..Default::default()
        };
        match d.mode {
            Mode::H24 if k > 0 => return next("", 0),
            Mode::Open => {
                for s in d.slots {
                    if let Some((o, _)) = s.mins() {
                        if k > 0 || o * 60 > sec {
                            return next(&s.of, o);
                        }
                    }
                }
            }
            _ => {}
        }
    }
    Status::default()
}

impl Config {
    fn day_name(&self, iso: u8) -> String {
        self.i18n.days.get(&iso.to_string()).cloned().unwrap_or_default()
    }

    fn date_short(&self, day: i32) -> String {
        let (_, m, d) = date::civil_from_days(day);
        match self.i18n.months_short.get(m as usize - 1) {
            Some(mon) => format!("{d} {mon}"),
            None => date::format(day),
        }
    }

    pub fn badge(&self, st: &Status) -> String {
        let t = &self.i18n;
        if st.open {
            return if st.h24 {
                t.open24.clone()
            } else {
                format!("{}{}{}", t.open, t.sep(), t.closes_at.replace("%s", st.until.as_deref().unwrap_or("")))
            };
        }
        let (Some(k), Some(nd)) = (st.next_days, st.next_date.as_deref().and_then(date::parse)) else {
            return t.closed.clone();
        };
        let time = st.next_time.as_deref().unwrap_or("");
        let when = match k {
            0 => time.to_string(),
            1 => format!("{} {time}", t.tomorrow),
            2..=6 => format!("{} {time}", self.day_name(date::weekday(nd))),
            _ => format!("{} {time}", self.date_short(nd)),
        };
        format!("{}{}{}", t.closed, t.sep(), t.opens_at.replace("%s", when.trim()))
    }

    pub fn countdown(&self, st: &Status) -> Option<String> {
        let cd = &self.cd;
        match (st.open, st.left, st.next_in) {
            (true, Some(left), _) if cd.close && left <= cd.mins * 60 => {
                Some(cd.close_text.replace("[time]", &duration(left, &cd.format)))
            }
            (false, _, Some(next)) if cd.open => Some(cd.open_text.replace("[time]", &duration(next, &cd.format))),
            _ => None,
        }
    }
}

impl Config {
    /// PHP date()-style formatting with the site's day and month names.
    pub fn format_date(&self, fmt: &str, day: i32) -> String {
        let (y, m, d) = date::civil_from_days(day);
        let wd = date::weekday(day);
        let t = &self.i18n;
        let month = |v: &Vec<String>| v.get(m as usize - 1).cloned().unwrap_or_else(|| m.to_string());
        let mut out = String::new();
        let mut chars = fmt.chars();
        while let Some(c) = chars.next() {
            match c {
                '\\' => out.extend(chars.next()),
                'd' => out += &format!("{d:02}"),
                'j' => out += &d.to_string(),
                'S' => out += match (d % 10, d % 100) {
                    (_, 11..=13) => "th",
                    (1, _) => "st",
                    (2, _) => "nd",
                    (3, _) => "rd",
                    _ => "th",
                },
                'D' => out += &self.day_name(wd),
                'l' => out += &t.days_long.get(&wd.to_string()).cloned().unwrap_or_else(|| self.day_name(wd)),
                'N' => out += &wd.to_string(),
                'F' => out += &month(&t.months),
                'M' => out += &month(&t.months_short),
                'm' => out += &format!("{m:02}"),
                'n' => out += &m.to_string(),
                'Y' => out += &y.to_string(),
                'y' => out += &format!("{:02}", y.rem_euclid(100)),
                _ => out.push(c),
            }
        }
        out
    }
}

pub fn duration(secs: i64, fmt: &str) -> String {
    let s = secs.max(0);
    let h = if fmt.contains("{d}") { s % 86_400 / 3600 } else { s / 3600 };
    let (m, sec) = (s % 3600 / 60, s % 60);
    fmt.replace("{d}", &(s / 86_400).to_string())
        .replace("{hh}", &format!("{h:02}"))
        .replace("{h}", &h.to_string())
        .replace("{mm}", &format!("{m:02}"))
        .replace("{m}", &m.to_string())
        .replace("{ss}", &format!("{sec:02}"))
        .replace("{s}", &sec.to_string())
}

/* ---------- Update (what the page shows now) ---------- */

/// Hours to show in the row for ISO day `d` instead of the regular hours.
#[derive(Serialize, Debug, PartialEq)]
pub struct RowOut {
    pub d: u8,
    pub text: String,
    pub label: String,
}

#[derive(Serialize, Debug)]
pub struct Update {
    pub date: String,
    /// Today in the header date format (empty if none).
    pub date_text: String,
    pub day: u8,
    pub open: bool,
    pub soon: bool,
    pub badge: String,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub countdown: Option<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub notice: Option<String>,
    /// Holiday hours: this week's days if `hh`, otherwise today only (for the "today" view).
    pub rows: Vec<RowOut>,
    pub status: Status,
}

pub fn update(cfg: &Config, now: &Now) -> Result<Update, String> {
    let day = date::parse(&now.date).ok_or_else(|| format!("bad date {:?}", now.date))?;
    let sec = now.sec.clamp(0, 86_399);
    let st = status(cfg, day, sec);
    let today = cfg.schedule(day);

    let notice = today.hol.map(|h| {
        let label = if h.l.is_empty() { String::new() } else { format!(" ({})", h.l) };
        format!("{}{label}: {}", cfg.i18n.today, cfg.hours_text(&today))
    });

    let week_start = day - i32::from((date::weekday(day) + 7 - cfg.ws.clamp(1, 7)) % 7);
    let days: Vec<i32> = if cfg.hh { (week_start..week_start + 7).collect() } else { vec![day] };
    let rows = days
        .into_iter()
        .filter_map(|d| {
            let s = cfg.schedule(d);
            let h = s.hol?;
            Some(RowOut { d: date::weekday(d), text: cfg.digits(cfg.hours_text(&s)), label: h.l.clone() })
        })
        .collect();

    Ok(Update {
        date: date::format(day),
        date_text: cfg.digits(cfg.format_date(&cfg.hdf, day)),
        day: date::weekday(day),
        open: st.open,
        soon: st.open && st.left.is_some_and(|l| l <= SOON_SECS),
        badge: cfg.digits(cfg.badge(&st)),
        countdown: cfg.countdown(&st).map(|c| cfg.digits(c)),
        notice: notice.map(|n| cfg.digits(n)),
        rows,
        status: st,
    })
}

/* ---------- Calendar ---------- */

/// Month view `offset` months from the month of `today`: a navigation header and a week-by-week table.
pub fn calendar(cfg: &Config, today: i32, offset: i32, max_offset: i32) -> String {
    let (y, m, _) = date::civil_from_days(today);
    let mi = y * 12 + (m as i32 - 1) + offset;
    let (cy, cm) = (mi.div_euclid(12), mi.rem_euclid(12) as u32 + 1);
    let first = date::days_from_civil(cy, cm, 1);
    let last = first + date::days_in_month(cy, cm) as i32 - 1;
    let ws = cfg.ws.clamp(1, 7);
    let start = first - i32::from((date::weekday(first) + 7 - ws) % 7);
    let end = last + i32::from((ws + 6 - date::weekday(last)) % 7);
    let t = &cfg.i18n;

    let month = t.months.get(cm as usize - 1).cloned().unwrap_or_else(|| format!("{cm:02}"));
    let nav = |dir: i32, label: &str, glyph: &str, off: bool| {
        format!(
            r#"<button type="button" class="hp-cal-nav" data-hp-nav="{dir}" aria-label="{}"{}>{glyph}</button>"#,
            esc(label),
            if off { " disabled" } else { "" }
        )
    };
    let mut h = String::with_capacity(4096);
    h += r#"<div class="hp-cal-head">"#;
    h += &nav(-1, &t.prev_month, "‹", offset <= 0);
    h += &format!(r#"<span class="hp-cal-title" tabindex="-1" aria-live="polite">{} {}</span>"#, esc(&month), cfg.digits(cy.to_string()));
    h += &nav(1, &t.next_month, "›", offset >= max_offset);
    h += r#"</div><table class="hp-cal"><thead><tr>"#;
    for i in 0..7u8 {
        h += &format!(r#"<th scope="col">{}</th>"#, esc(&cfg.day_name((ws - 1 + i) % 7 + 1)));
    }
    h += "</tr></thead><tbody>";
    for d in start..=end {
        if (d - start) % 7 == 0 {
            h += "<tr>";
        }
        if d < first || d > last {
            h += r#"<td class="hp-cal-out"></td>"#;
        } else {
            let s = cfg.schedule(d);
            let mut cls = String::from("hp-cal-day");
            cls += match s.mode {
                Mode::Open => " hp-cal-open",
                Mode::H24 => " hp-cal-24h",
                Mode::Closed => " hp-cal-closed",
            };
            if s.hol.is_some() {
                cls += " hp-cal-hol";
            }
            if d == today {
                cls += " hp-cal-today";
            } else if d < today {
                cls += " hp-cal-past";
            }
            let text = cfg.hours_text(&s);
            let title = match s.hol {
                Some(hol) if !hol.l.is_empty() => format!("{}: {text}", hol.l),
                _ => text.clone(),
            };
            let short = match s.mode {
                Mode::Open => s.slots.iter().filter(|x| !x.x).map(|x| format!("{}–{}", esc(&x.of), esc(&x.cf))).collect::<Vec<_>>().join("<br>"),
                _ => esc(&text),
            };
            h += &format!(
                r#"<td class="{cls}" title="{}"{}><span class="hp-cal-n">{}</span><span class="hp-cal-h">{short}</span></td>"#,
                esc(&title),
                if d == today { r#" aria-current="date""# } else { "" },
                cfg.digits(date::civil_from_days(d).2.to_string()),
            );
        }
        if (d - start) % 7 == 6 {
            h += "</tr>";
        }
    }
    h += "</tbody></table>";
    h
}

pub fn esc(s: &str) -> String {
    let mut o = String::with_capacity(s.len());
    for c in s.chars() {
        match c {
            '&' => o += "&amp;",
            '<' => o += "&lt;",
            '>' => o += "&gt;",
            '"' => o += "&quot;",
            '\'' => o += "&#39;",
            _ => o.push(c),
        }
    }
    o
}

#[cfg(test)]
mod tests;
