//! Proleptic Gregorian calendar dates as day numbers (0 = 1970-01-01).
//! Algorithms from Howard Hinnant's `chrono`-compatible date library (public domain).

pub fn days_from_civil(y: i32, m: u32, d: u32) -> i32 {
    let y = if m <= 2 { y - 1 } else { y };
    let era = y.div_euclid(400);
    let yoe = y - era * 400;
    let mp = (m + 9) % 12;
    let doy = ((153 * mp + 2) / 5 + d - 1) as i32;
    let doe = yoe * 365 + yoe / 4 - yoe / 100 + doy;
    era * 146_097 + doe - 719_468
}

pub fn civil_from_days(z: i32) -> (i32, u32, u32) {
    let z = z + 719_468;
    let era = z.div_euclid(146_097);
    let doe = z - era * 146_097;
    let yoe = (doe - doe / 1460 + doe / 36_524 - doe / 146_096) / 365;
    let doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
    let mp = (5 * doy + 2) / 153;
    let d = (doy - (153 * mp + 2) / 5 + 1) as u32;
    let m = (if mp < 10 { mp + 3 } else { mp - 9 }) as u32;
    let y = yoe + era * 400;
    (if m <= 2 { y + 1 } else { y }, m, d)
}

/// ISO weekday: 1 = Monday … 7 = Sunday.
pub fn weekday(z: i32) -> u8 {
    ((z + 3).rem_euclid(7) + 1) as u8
}

pub fn days_in_month(y: i32, m: u32) -> u32 {
    match m {
        2 if (y % 4 == 0 && y % 100 != 0) || y % 400 == 0 => 29,
        2 => 28,
        4 | 6 | 9 | 11 => 30,
        _ => 31,
    }
}

/// "YYYY-MM-DD" -> day number.
pub fn parse(s: &str) -> Option<i32> {
    let mut it = s.trim().splitn(3, '-');
    let y: i32 = it.next()?.parse().ok()?;
    let m: u32 = it.next()?.parse().ok()?;
    let d: u32 = it.next()?.parse().ok()?;
    ((1..=12).contains(&m) && d >= 1 && d <= days_in_month(y, m)).then(|| days_from_civil(y, m, d))
}

pub fn format(z: i32) -> String {
    let (y, m, d) = civil_from_days(z);
    format!("{y:04}-{m:02}-{d:02}")
}

/// Same month and day `n` years later; 29 Feb becomes 28 Feb in common years.
pub fn shift_years(z: i32, n: i32) -> i32 {
    let (y, m, d) = civil_from_days(z);
    let y = y + n;
    days_from_civil(y, m, d.min(days_in_month(y, m)))
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn roundtrip_and_weekday() {
        for z in -800_000..800_000 {
            let (y, m, d) = civil_from_days(z);
            assert_eq!(days_from_civil(y, m, d), z);
        }
        assert_eq!(parse("1970-01-01"), Some(0));
        assert_eq!(weekday(parse("2024-01-01").unwrap()), 1);
        assert_eq!(weekday(parse("2026-10-29").unwrap()), 4);
        assert_eq!(format(parse("2028-02-29").unwrap()), "2028-02-29");
        assert_eq!(parse("2027-02-29"), None);
        assert_eq!(parse("2026-13-01"), None);
    }

    #[test]
    fn shift() {
        assert_eq!(format(shift_years(parse("2028-02-29").unwrap(), -1)), "2027-02-28");
        assert_eq!(format(shift_years(parse("2025-12-24").unwrap(), 2)), "2027-12-24");
    }
}
