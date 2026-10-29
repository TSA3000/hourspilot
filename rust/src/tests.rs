use super::*;

/// The sample location from tools/wp-dev: Mon lunch break, Fri late slot past midnight, Sat 24h, Sun closed.
fn cfg(extra: &str) -> Config {
    let slot = |o: &str, c: &str| format!(r#"{{"o":"{o}","c":"{c}","of":"{o}","cf":"{c}"}}"#);
    let json = format!(
        r#"{{
        "week": {{
            "1": {{"m":"open","s":[{},{}]}},
            "2": {{"m":"open","s":[{}]}}, "3": {{"m":"open","s":[{}]}}, "4": {{"m":"open","s":[{}]}},
            "5": {{"m":"open","s":[{},{}]}},
            "6": {{"m":"24h","s":[]}}, "7": {{"m":"closed","s":[]}}
        }},
        "i18n": {{"open":"Open now","closed":"Closed now","closedLabel":"Closed","open24":"Open 24 hours",
                  "closesAt":"closes %s","opensAt":"opens %s","today":"Today","tomorrow":"tomorrow",
                  "days":{{"1":"Mon","2":"Tue","3":"Wed","4":"Thu","5":"Fri","6":"Sat","7":"Sun"}},
                  "months":["January","February","March","April","May","June","July","August","September","October","November","December"],
                  "monthsShort":["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"]}}
        {extra}
    }}"#,
        slot("08:00", "12:00"),
        slot("13:00", "17:00"),
        slot("08:00", "17:00"),
        slot("08:00", "17:00"),
        slot("08:00", "19:00"),
        slot("08:00", "12:00"),
        slot("20:00", "02:00"),
    );
    serde_json::from_str(&json).unwrap()
}

fn d(s: &str) -> i32 {
    date::parse(s).unwrap()
}

fn at(h: i64, m: i64) -> i64 {
    h * 3600 + m * 60
}

// 2026-10-26 = Monday … 2026-11-01 = Sunday.

#[test]
fn open_in_slot() {
    let c = cfg("");
    let st = status(&c, d("2026-10-26"), at(9, 30));
    assert!(st.open);
    assert_eq!(st.until.as_deref(), Some("12:00"));
    assert_eq!(st.left, Some(at(2, 30)));
    assert_eq!(c.badge(&st), "Open now, closes 12:00");
}

#[test]
fn lunch_break_opens_today() {
    let c = cfg("");
    let st = status(&c, d("2026-10-26"), at(12, 15));
    assert!(!st.open);
    assert_eq!((st.next_days, st.next_time.as_deref(), st.next_in), (Some(0), Some("13:00"), Some(at(0, 45))));
    assert_eq!(c.badge(&st), "Closed now, opens 13:00");
}

#[test]
fn closing_minute_is_closed() {
    let st = status(&cfg(""), d("2026-10-27"), at(17, 0));
    assert!(!st.open);
    assert_eq!(st.next_days, Some(1));
}

#[test]
fn past_midnight_from_yesterday() {
    // Saturday 01:30 is inside Friday's 20:00–02:00 slot, even though Saturday itself is 24h.
    let c = cfg("");
    let fri = status(&c, d("2026-10-30"), at(23, 0));
    assert_eq!((fri.open, fri.left), (true, Some(at(3, 0))));
    // Thursday night -> Friday 01:00: Thursday has no late slot, so closed until 08:00.
    let st = status(&c, d("2026-10-30"), at(1, 0));
    assert!(!st.open);
    assert_eq!(st.next_time.as_deref(), Some("08:00"));
}

#[test]
fn h24_and_sunday() {
    let c = cfg("");
    assert!(status(&c, d("2026-10-31"), at(15, 0)).h24);
    let sun = status(&c, d("2026-11-01"), at(10, 0));
    assert!(!sun.open);
    assert_eq!(c.badge(&sun), "Closed now, opens tomorrow 08:00");
    // Friday lunch: the morning slot is over, the evening slot opens at 20:00.
    let fri = status(&c, d("2026-10-30"), at(12, 30));
    assert_eq!((fri.next_days, fri.next_time.as_deref()), (Some(0), Some("20:00")));
}

#[test]
fn holiday_overrides_and_notice() {
    let c = cfg(r#","hol":[{"f":"2026-10-27","t":"2026-10-28","l":"Staff days","s":[]},
                          {"f":"2026-10-29","l":"Short","s":[{"o":"10:00","c":"14:00","of":"10:00","cf":"14:00"}]}]"#);
    let st = status(&c, d("2026-10-27"), at(9, 0));
    assert!(!st.open);
    assert_eq!(c.badge(&st), "Closed now, opens Thu 10:00");
    let u = update(&c, &Now { date: "2026-10-29".into(), sec: at(11, 0) }).unwrap();
    assert!(u.open);
    assert_eq!(u.notice.as_deref(), Some("Today (Short): 10:00 – 14:00"));
    assert_eq!(u.rows, vec![RowOut { d: 4, text: "10:00 – 14:00".into(), label: "Short".into() }]);
}

#[test]
fn holiday_hours_for_the_week() {
    let c = cfg(r#","hh":true,"hol":[{"f":"2026-10-27","t":"2026-10-28","l":"Staff","s":[]}]"#);
    let u = update(&c, &Now { date: "2026-11-01".into(), sec: 0 }).unwrap();
    assert_eq!(u.rows.iter().map(|r| r.d).collect::<Vec<_>>(), vec![2, 3]);
    assert_eq!(u.rows[0].text, "Closed");
    // Week starting Sunday: 2026-11-01 starts a new week, the holiday is last week.
    let c = cfg(r#","hh":true,"ws":7,"hol":[{"f":"2026-10-27","t":"2026-10-28","s":[]}]"#);
    assert!(update(&c, &Now { date: "2026-11-01".into(), sec: 0 }).unwrap().rows.is_empty());
}

#[test]
fn recurring_holidays() {
    let h = |f: &str, t: &str| Holiday { f: f.into(), t: t.into(), r: true, ..Default::default() };
    let xmas = h("2020-12-24", "2020-12-26");
    assert!(xmas.covers(d("2026-12-25")));
    assert!(!xmas.covers(d("2026-12-27")));
    let ny = h("2024-12-31", "2025-01-01");
    assert!(ny.covers(d("2027-01-01")));
    assert!(ny.covers(d("2026-12-31")));
    assert!(!ny.covers(d("2027-01-02")));
    let leap = h("2024-02-29", "");
    assert!(leap.covers(d("2027-02-28")));
    assert!(leap.covers(d("2028-02-29")));
    assert!(!leap.covers(d("2028-02-28")));
    let once = Holiday { r: false, ..xmas.clone() };
    assert!(!once.covers(d("2026-12-25")));
}

#[test]
fn long_closure_uses_date() {
    let c = cfg(r#","hol":[{"f":"2026-10-26","t":"2026-11-15","l":"Renovation","s":[]}]"#);
    let st = status(&c, d("2026-10-26"), at(9, 0));
    assert_eq!(st.next_days, Some(21));
    assert_eq!(c.badge(&st), "Closed now, opens 16 Nov 08:00");
}

#[test]
fn countdowns() {
    let c = cfg(r#","cd":{"open":true,"close":true,"mins":60,"openText":"Opening in [time]","closeText":"Closing in [time]"}"#);
    let near = status(&c, d("2026-10-27"), at(16, 10) + 5);
    assert_eq!(c.countdown(&near).as_deref(), Some("Closing in 0:49:55"));
    assert_eq!(c.countdown(&status(&c, d("2026-10-27"), at(15, 0))), None);
    let sun = status(&c, d("2026-11-01"), at(22, 0));
    assert_eq!(c.countdown(&sun).as_deref(), Some("Opening in 10:00:00"));
    assert_eq!(duration(93_784, "{d}d {hh}h {mm}m {ss}s"), "1d 02h 03m 04s");
    assert_eq!(duration(-5, "{h}:{mm}:{ss}"), "0:00:00");
}

#[test]
fn soon_flag() {
    let c = cfg("");
    assert!(update(&c, &Now { date: "2026-10-27".into(), sec: at(16, 45) }).unwrap().soon);
    assert!(!update(&c, &Now { date: "2026-10-27".into(), sec: at(15, 0) }).unwrap().soon);
}

#[test]
fn calendar_month() {
    let c = cfg(r#","hol":[{"f":"2026-12-24","t":"2026-12-26","l":"Christmas <3","s":[],"r":true}]"#);
    let today = d("2026-10-29");
    let oct = calendar(&c, today, 0, 11);
    assert!(oct.contains("October 2026"));
    assert_eq!(oct.matches(r#"class="hp-cal-day"#).count(), 31);
    // Oct 2026 starts on Thursday: 3 leading blanks with a Monday week start, 1 trailing (Sun 1 Nov).
    assert_eq!(oct.matches("hp-cal-out").count(), 4);
    assert!(oct.contains(r#"hp-cal-today" title="08:00 – 19:00" aria-current="date""#));
    assert!(oct.contains(r#"data-hp-nav="-1" aria-label="" disabled"#));
    let dec = calendar(&c, today, 2, 11);
    assert!(dec.contains("December 2026"));
    assert_eq!(dec.matches("hp-cal-hol").count(), 3);
    assert!(dec.contains("Christmas &lt;3: Closed"));
    assert!(calendar(&c, today, 11, 11).contains(r#"data-hp-nav="1" aria-label="" disabled"#));
}

#[test]
fn bad_input() {
    assert!(update(&cfg(""), &Now { date: "2026-02-30".into(), sec: 0 }).is_err());
    let c: Config = serde_json::from_str("{}").unwrap();
    let u = update(&c, &Now { date: "2026-10-29".into(), sec: 0 }).unwrap();
    assert!(!u.open && u.status.next_days.is_none());
}

#[test]
fn arabic_digits() {
    let c = cfg(r#","dg":"arab","cd":{"close":true,"mins":600,"closeText":"[time]"}"#);
    let u = update(&c, &Now { date: "2026-10-27".into(), sec: at(9, 0) }).unwrap();
    assert_eq!(u.badge, "Open now, closes ١٧:٠٠");
    assert_eq!(u.countdown.as_deref(), Some("٨:٠٠:٠٠"));
    assert_eq!(c.digits("٠٨:٣٠".into()), "٠٨:٣٠");
    let cal = calendar(&c, d("2026-10-29"), 0, 11);
    assert!(cal.contains("October ٢٠٢٦"));
    assert!(cal.contains(r#"<span class="hp-cal-n">٢٩</span>"#));
    assert!(cal.contains(r#"data-hp-nav="1""#)); // attributes keep Western digits
    assert_eq!(cfg("").digits("17:00".into()), "17:00");
}

#[test]
fn arabic_separator() {
    let mut c = cfg("");
    c.i18n.sep = "، ".into();
    c.i18n.open = "مفتوح الآن".into();
    c.i18n.closes_at = "يغلق %s".into();
    assert_eq!(c.badge(&status(&c, d("2026-10-27"), at(9, 0))), "مفتوح الآن، يغلق 17:00");
    assert_eq!(c.hours_text(&c.schedule(d("2026-10-26"))), "08:00 – 12:00، 13:00 – 17:00");
}

#[test]
fn breaks_and_labels() {
    // Tuesday 08-12, lunch break 12-13 (closed, labelled), 13-17.
    let c: Config = serde_json::from_str(r#"{"week":{"2":{"m":"open","s":[
        {"o":"08:00","c":"12:00","of":"08:00","cf":"12:00"},
        {"o":"12:00","c":"13:00","of":"12:00","cf":"13:00","l":"Lunch","x":true},
        {"o":"13:00","c":"17:00","of":"13:00","cf":"17:00"}]},
        "3":{"m":"open","s":[{"o":"12:00","c":"13:00","of":"12:00","cf":"13:00","x":true}]}},
        "i18n":{"closedLabel":"Closed"}}"#).unwrap();
    let tue = d("2026-10-27");
    let lunch = status(&c, tue, at(12, 30));
    assert!(!lunch.open);
    assert_eq!(lunch.next_time.as_deref(), Some("13:00"));
    assert_eq!(status(&c, tue, at(11, 0)).left, Some(at(1, 0)));
    assert_eq!(c.hours_text(&c.schedule(tue)), "08:00 – 12:00, 13:00 – 17:00");
    // A day with only a break counts as closed.
    assert_eq!(c.schedule(tue + 1).mode, Mode::Closed);
    assert_eq!(c.hours_text(&c.schedule(tue + 1)), "Closed");
}

#[test]
fn header_date() {
    let mut c = cfg(r##","hdf":"l F jS""##);
    c.i18n.days_long = (1..=7).map(|i| (i.to_string(), ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"][i - 1].to_string())).collect();
    assert_eq!(c.format_date("l F jS", d("2021-11-29")), "Monday November 29th");
    assert_eq!(c.format_date("D j M Y", d("2026-11-01")), "Sun 1 Nov 2026");
    assert_eq!(c.format_date("jS jS jS jS", d("2026-11-02")), "2nd 2nd 2nd 2nd");
    assert_eq!(c.format_date("jS", d("2026-11-11")), "11th");
    assert_eq!(c.format_date("jS", d("2026-11-23")), "23rd");
    assert_eq!(c.format_date("d.m.y \\o\\f N", d("2026-03-05")), "05.03.26 of 4");
    let u = update(&c, &Now { date: "2026-10-27".into(), sec: 0 }).unwrap();
    assert_eq!(u.date_text, "Tuesday October 27th");
    assert_eq!(update(&cfg(""), &Now { date: "2026-10-27".into(), sec: 0 }).unwrap().date_text, "");
}
