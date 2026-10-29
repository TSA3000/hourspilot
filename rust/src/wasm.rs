//! Raw WebAssembly ABI (no wasm-bindgen): JSON strings in, JSON/HTML strings out.
//! JS: p = hp_alloc(n), write UTF-8 bytes, call, read hp_out_len()… bytes at hp_out_ptr(), hp_free(p, n).

use crate::{Config, Now, calendar, date, update};
use std::cell::RefCell;

thread_local! {
    static OUT: RefCell<Vec<u8>> = const { RefCell::new(Vec::new()) };
}

#[unsafe(no_mangle)]
pub extern "C" fn hp_alloc(len: usize) -> *mut u8 {
    let mut v = Vec::<u8>::with_capacity(len.max(1));
    let p = v.as_mut_ptr();
    std::mem::forget(v);
    p
}

/// # Safety
/// `p`/`len` must come from `hp_alloc`.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn hp_free(p: *mut u8, len: usize) {
    drop(unsafe { Vec::from_raw_parts(p, 0, len.max(1)) });
}

#[unsafe(no_mangle)]
pub extern "C" fn hp_out_ptr() -> *const u8 {
    OUT.with(|o| o.borrow().as_ptr())
}

fn out(s: String) -> usize {
    OUT.with(|o| {
        *o.borrow_mut() = s.into_bytes();
        o.borrow().len()
    })
}

unsafe fn input<'a>(p: *const u8, len: usize) -> &'a [u8] {
    if len == 0 { &[] } else { unsafe { std::slice::from_raw_parts(p, len) } }
}

fn parse(cfg: &[u8], now: &[u8]) -> Result<(Config, Now), String> {
    let cfg = serde_json::from_slice(cfg).map_err(|e| format!("config: {e}"))?;
    let now = serde_json::from_slice(now).map_err(|e| format!("now: {e}"))?;
    Ok((cfg, now))
}

fn error(e: String) -> String {
    format!(r#"{{"error":{}}}"#, serde_json::to_string(&e).unwrap_or_default())
}

/// Status, badge, countdown, notice and holiday rows as JSON; `{"error": …}` on bad input.
/// # Safety
/// Pointers must reference `len` readable bytes (from `hp_alloc`).
#[unsafe(no_mangle)]
pub unsafe extern "C" fn hp_update(cp: *const u8, cl: usize, np: *const u8, nl: usize) -> usize {
    let r = parse(unsafe { input(cp, cl) }, unsafe { input(np, nl) })
        .and_then(|(cfg, now)| update(&cfg, &now))
        .and_then(|u| serde_json::to_string(&u).map_err(|e| e.to_string()));
    out(r.unwrap_or_else(error))
}

/// Month calendar HTML `offset` months from now (0..=max_offset); empty string on bad input.
/// # Safety
/// As `hp_update`.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn hp_calendar(cp: *const u8, cl: usize, np: *const u8, nl: usize, offset: i32, max_offset: i32) -> usize {
    let html = parse(unsafe { input(cp, cl) }, unsafe { input(np, nl) })
        .ok()
        .and_then(|(cfg, now)| Some(calendar(&cfg, date::parse(&now.date)?, offset, max_offset)))
        .unwrap_or_default();
    out(html)
}
