/* HoursPilot – live open/closed status computed in the browser (cache-safe). | v0.1.0 | Arthur Sam Mahdi */
(function () {
	'use strict';

	var WEEKDAY = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };

	/** Current date, ISO weekday and minutes-since-midnight in the location's time zone. */
	function nowIn(tz) {
		var p = {};
		new Intl.DateTimeFormat('en-US', {
			timeZone: tz, weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit',
			hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
		}).formatToParts(new Date()).forEach(function (x) { p[x.type] = x.value; });
		return {
			date: p.year + '-' + p.month + '-' + p.day,
			day: WEEKDAY[p.weekday],
			min: (parseInt(p.hour, 10) % 24) * 60 + parseInt(p.minute, 10)
		};
	}

	function addDays(date, n) {
		var d = new Date(date + 'T12:00:00Z');
		d.setUTCDate(d.getUTCDate() + n);
		return d.toISOString().slice(0, 10);
	}

	function isoAdd(day, n) { return ((day - 1 + n) % 7 + 7) % 7 + 1; }

	function toMin(t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1]); }

	/** Schedule for a date: a holiday overrides the weekly hours. */
	function scheduleFor(cfg, date, day) {
		for (var i = 0; i < cfg.hol.length; i++) {
			var h = cfg.hol[i];
			if (date >= h.f && date <= h.t) {
				return { m: h.s.length ? 'open' : 'closed', s: h.s, hol: h };
			}
		}
		return cfg.week[day] || { m: 'closed', s: [] };
	}

	function status(cfg, now) {
		var today = scheduleFor(cfg, now.date, now.day);
		if (today.m === '24h') { return { open: true, h24: true }; }

		// A slot from yesterday that runs past midnight.
		var y = scheduleFor(cfg, addDays(now.date, -1), isoAdd(now.day, -1));
		if (y.m === 'open') {
			for (var i = 0; i < y.s.length; i++) {
				var yo = toMin(y.s[i].o), yc = toMin(y.s[i].c);
				if (yc < yo && now.min < yc) { return { open: true, until: y.s[i].cf, left: yc - now.min }; }
			}
		}

		if (today.m === 'open') {
			for (var j = 0; j < today.s.length; j++) {
				var o = toMin(today.s[j].o), c = toMin(today.s[j].c);
				if (now.min >= o && (c < o || now.min < c)) {
					return { open: true, until: today.s[j].cf, left: (c < o ? c + 1440 : c) - now.min };
				}
			}
		}

		// Next opening within a week.
		for (var k = 0; k <= 7; k++) {
			var d = scheduleFor(cfg, addDays(now.date, k), isoAdd(now.day, k));
			if (d.m === '24h' && k > 0) { return { open: false, inDays: k, day: isoAdd(now.day, k), time: '' }; }
			if (d.m !== 'open') { continue; }
			for (var m = 0; m < d.s.length; m++) {
				if (k > 0 || toMin(d.s[m].o) > now.min) {
					return { open: false, inDays: k, day: isoAdd(now.day, k), time: d.s[m].of };
				}
			}
		}
		return { open: false };
	}

	function statusText(cfg, st) {
		var t = cfg.i18n;
		if (st.open) { return st.h24 ? t.open24 : t.open + ', ' + t.closesAt.replace('%s', st.until); }
		if (st.inDays === undefined) { return t.closed; }
		var when = st.inDays === 0 ? st.time
			: (st.inDays === 1 ? t.tomorrow : t.days[st.day]) + ' ' + st.time;
		return t.closed + ', ' + t.opensAt.replace('%s', when.trim());
	}

	function hoursText(cfg, sched) {
		if (sched.m === '24h') { return cfg.i18n.open24; }
		if (sched.m !== 'open' || !sched.s.length) { return cfg.i18n.closedLabel; }
		return sched.s.map(function (s) { return s.of + ' – ' + s.cf; }).join(', ');
	}

	function update(el, cfg) {
		var now = nowIn(cfg.tz);
		var st = status(cfg, now);
		var today = scheduleFor(cfg, now.date, now.day);

		var badge = el.querySelector('.hp-status');
		if (badge) {
			badge.textContent = statusText(cfg, st);
			badge.className = 'hp-status ' + (st.open ? 'hp-open' : 'hp-shut') +
				(st.open && st.left !== undefined && st.left <= 30 ? ' hp-soon' : '');
			badge.hidden = false;
		}

		el.querySelectorAll('.hp-row').forEach(function (row) {
			var days = row.getAttribute('data-days').split(',').map(Number);
			var isToday = days.indexOf(now.day) !== -1;
			row.classList.toggle('hp-is-today', isToday);

			// "Today" view: show the holiday's hours instead of the weekly ones.
			if (cfg.view === 'today') {
				var cell = row.querySelector('td');
				if (row.dataset.orig === undefined) { row.dataset.orig = cell.innerHTML; }
				if (isToday && today.hol) { cell.textContent = hoursText(cfg, today); }
				else { cell.innerHTML = row.dataset.orig; }
			}
		});

		var notice = el.querySelector('.hp-notice');
		if (notice) {
			if (today.hol) {
				notice.textContent = cfg.i18n.today + (today.hol.l ? ' (' + today.hol.l + ')' : '') + ': ' + hoursText(cfg, today);
				notice.hidden = false;
			} else {
				notice.hidden = true;
			}
		}

		// Drop holidays that ended since the page was cached.
		var list = el.querySelector('.hp-holidays');
		if (list) {
			var visible = 0;
			list.querySelectorAll('li[data-to]').forEach(function (li) {
				li.hidden = li.getAttribute('data-to') < now.date;
				if (!li.hidden) { visible++; }
			});
			list.hidden = visible === 0;
		}

		var lt = el.querySelector('.hp-localtime');
		if (lt) {
			var opts = { timeZone: cfg.tz, hour: '2-digit', minute: '2-digit' };
			try { lt.textContent = new Intl.DateTimeFormat(document.documentElement.lang || undefined, opts).format(new Date()); }
			catch (e) { lt.textContent = new Intl.DateTimeFormat(undefined, opts).format(new Date()); }
		}
	}

	function init() {
		document.querySelectorAll('.hourspilot[data-hp]').forEach(function (el) {
			if (el.dataset.hpReady) { return; }
			var cfg;
			try { cfg = JSON.parse(el.getAttribute('data-hp')); } catch (e) { return; }
			cfg.hol = cfg.hol || [];
			el.dataset.hpReady = '1';
			update(el, cfg);
			setInterval(function () { update(el, cfg); }, 30000);
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
