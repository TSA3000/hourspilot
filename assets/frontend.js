/* HoursPilot – live status, countdowns, holiday hours and calendar, computed in the browser by the
 * Rust engine (assets/hourspilot.wasm, source in rust/), so page caches never show a stale status. | v0.3.0 | Arthur Sam Mahdi */
(function () {
	'use strict';

	var enc = new TextEncoder();
	var dec = new TextDecoder();
	var engine = null;

	function load() {
		if (!engine) {
			engine = fetch((window.hourspilot || {}).wasm)
				.then(function (r) {
					if (!r.ok) { throw new Error('HTTP ' + r.status); }
					return r.arrayBuffer();
				})
				.then(function (b) { return WebAssembly.instantiate(b, {}); })
				.then(function (m) { return m.instance.exports; });
		}
		return engine;
	}

	/** Call an engine export with two JSON strings (+ extra integer args); returns its output string. */
	function call(ex, fn, a, b, extra) {
		var ab = enc.encode(a), bb = enc.encode(b);
		var pa = ex.hp_alloc(ab.length), pb = ex.hp_alloc(bb.length);
		// Views after both allocations: an allocation can grow (and detach) the memory buffer.
		new Uint8Array(ex.memory.buffer, pa, ab.length).set(ab);
		new Uint8Array(ex.memory.buffer, pb, bb.length).set(bb);
		var len = ex[fn].apply(null, [pa, ab.length, pb, bb.length].concat(extra || []));
		var out = dec.decode(new Uint8Array(ex.memory.buffer, ex.hp_out_ptr(), len));
		ex.hp_free(pa, ab.length);
		ex.hp_free(pb, bb.length);
		return out;
	}

	/** Wall-clock date and seconds since midnight in the location's time zone. */
	function nowIn(tz) {
		var p = {};
		new Intl.DateTimeFormat('en-US', {
			timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
			hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
		}).formatToParts(new Date()).forEach(function (x) { p[x.type] = x.value; });
		return {
			date: p.year + '-' + p.month + '-' + p.day,
			sec: (parseInt(p.hour, 10) % 24) * 3600 + parseInt(p.minute, 10) * 60 + parseInt(p.second, 10)
		};
	}

	function show(node, text) {
		if (!node) { return; }
		node.hidden = !text;
		if (text) { node.textContent = text; }
	}

	function setHolidayCell(cell, r) {
		cell.textContent = '';
		var h = document.createElement('span');
		h.className = 'hp-hol-hours';
		h.textContent = r.text;
		cell.appendChild(h);
		if (r.label) {
			var l = document.createElement('span');
			l.className = 'hp-hol-label';
			l.textContent = r.label;
			cell.appendChild(l);
		}
	}

	function renderCalendar(st) {
		var cal = st.el.querySelector('.hp-calendar');
		if (!cal) { return; }
		cal.innerHTML = call(st.ex, 'hp_calendar', st.raw, JSON.stringify(st.now), [st.month, 11]); // Escaped by the engine.
		cal.hidden = !cal.innerHTML;
		var table = st.el.querySelector('.hp-table');
		if (table) { table.hidden = !cal.hidden; }
	}

	function update(st) {
		var el = st.el, cfg = st.cfg;
		st.now = nowIn(cfg.tz);
		var u = JSON.parse(call(st.ex, 'hp_update', st.raw, JSON.stringify(st.now)));
		if (u.error) { window.console && console.warn('HoursPilot:', u.error); return; }

		var badge = el.querySelector('.hp-status');
		if (badge) {
			badge.textContent = u.badge;
			badge.className = 'hp-status ' + (u.open ? 'hp-open' : 'hp-shut') + (u.soon ? ' hp-soon' : '');
			badge.hidden = false;
		}

		var cd = el.querySelector('.hp-countdown');
		show(cd, u.countdown);
		if (cd) { cd.className = 'hp-countdown ' + (u.open ? 'hp-cd-close' : 'hp-cd-open'); }

		// Holiday hours replace the regular hours (whole week with holiday_hours="1", else today in the "today" view).
		var rows = {};
		if (cfg.hh || cfg.view === 'today') { u.rows.forEach(function (r) { rows[r.d] = r; }); }
		el.querySelectorAll('.hp-row').forEach(function (row) {
			var days = row.getAttribute('data-days').split(',').map(Number);
			row.classList.toggle('hp-is-today', days.indexOf(u.day) !== -1);
			var cell = row.querySelector('td');
			if (row.dataset.orig === undefined) { row.dataset.orig = cell.innerHTML; }
			var r = days.length === 1 ? rows[days[0]] : null;
			row.classList.toggle('hp-has-holiday', !!r);
			if (r) { setHolidayCell(cell, r); } else if (cell.innerHTML !== row.dataset.orig) { cell.innerHTML = row.dataset.orig; }
		});

		show(el.querySelector('.hp-notice'), u.notice);

		// Drop holidays that ended since the page was cached.
		var list = el.querySelector('.hp-holidays');
		if (list) {
			var visible = 0;
			list.querySelectorAll('li[data-to]').forEach(function (li) {
				li.hidden = li.getAttribute('data-to') < u.date;
				if (!li.hidden) { visible++; }
			});
			list.hidden = visible === 0;
		}

		var lt = el.querySelector('.hp-localtime');
		if (lt) {
			var opts = { timeZone: cfg.tz, hour: 'numeric', minute: '2-digit', numberingSystem: cfg.dg === 'arab' ? 'arab' : 'latn' };
			try { lt.textContent = new Intl.DateTimeFormat(document.documentElement.lang || undefined, opts).format(new Date()); }
			catch (e) { lt.textContent = new Intl.DateTimeFormat(undefined, opts).format(new Date()); }
		}

		if (cfg.layout === 'calendar' && st.calDate !== u.date) {
			st.calDate = u.date;
			renderCalendar(st);
		}
	}

	function start(el, ex) {
		var raw = el.getAttribute('data-hp'), cfg;
		try { cfg = JSON.parse(raw); } catch (e) { return; }
		var st = { el: el, ex: ex, raw: raw, cfg: cfg, month: 0 };
		el.addEventListener('click', function (e) {
			var b = e.target.closest('[data-hp-nav]');
			if (!b || b.disabled) { return; }
			st.month = Math.max(0, Math.min(11, st.month + Number(b.getAttribute('data-hp-nav'))));
			renderCalendar(st);
			var again = el.querySelector('[data-hp-nav="' + b.getAttribute('data-hp-nav') + '"]');
			(again && !again.disabled ? again : el.querySelector('.hp-cal-title')).focus();
		});
		update(st);
		var cdOn = cfg.cd && (cfg.cd.open || cfg.cd.close);
		setInterval(function () { update(st); }, cdOn ? 1000 : 30000);
	}

	function init() {
		var els = Array.prototype.filter.call(document.querySelectorAll('.hourspilot[data-hp]'), function (el) {
			if (el.dataset.hpReady) { return false; }
			el.dataset.hpReady = '1';
			return true;
		});
		if (!els.length) { return; }
		load().then(function (ex) {
			els.forEach(function (el) { start(el, ex); });
		}).catch(function (e) {
			window.console && console.warn('HoursPilot: engine failed to load', e);
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
	else { init(); }

	// Elementor editor re-renders widgets without a page load.
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', init);
		}
	});
})();
