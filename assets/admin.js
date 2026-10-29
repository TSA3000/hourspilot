/* HoursPilot admin – hour slot and holiday repeaters. | v0.5.0 | Arthur Sam Mahdi */
(function () {
	'use strict';

	var uid = Date.now();

	function fromTpl(id, map) {
		var html = document.getElementById(id).innerHTML;
		Object.keys(map).forEach(function (k) { html = html.split(k).join(map[k]); });
		var t = document.createElement('template');
		t.innerHTML = html.trim();
		return t.content.firstElementChild;
	}

	function addSlot(row, open, close, label, brk) {
		var slot = fromTpl('hp-tpl-slot', {
			__DAY__: row.getAttribute('data-day'),
			__IDX__: String(uid++),
			__OPEN__: open || '09:00',
			__CLOSE__: close || '17:00'
		});
		slot.querySelector('.hp-slot-label').value = label || '';
		slot.querySelector('.hp-slot-break input').checked = !!brk;
		row.querySelector('.hp-slots').appendChild(slot);
	}

	function setMode(row, mode) {
		var radio = row.querySelector('.hp-mode[value="' + mode + '"]');
		if (radio) { radio.checked = true; }
		row.className = row.className.replace(/\bhp-mode-\S+/, 'hp-mode-' + mode);
	}

	function copyMonday() {
		var src = document.querySelector('.hp-day[data-day="1"]');
		if (!src) { return; }
		var mode = (src.querySelector('.hp-mode:checked') || {}).value || 'closed';
		var slots = Array.prototype.map.call(src.querySelectorAll('.hp-slot'), function (s) {
			var i = s.querySelectorAll('input[type="time"]');
			return [i[0].value, i[1].value, s.querySelector('.hp-slot-label').value, s.querySelector('.hp-slot-break input').checked];
		});
		[2, 3, 4, 5].forEach(function (d) {
			var row = document.querySelector('.hp-day[data-day="' + d + '"]');
			row.querySelectorAll('.hp-slot').forEach(function (s) { s.remove(); });
			slots.forEach(function (s) { addSlot(row, s[0], s[1], s[2], s[3]); });
			setMode(row, mode);
		});
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (t.closest('.hp-add-slot')) {
			e.preventDefault();
			addSlot(t.closest('.hp-day'));
		} else if (t.closest('.hp-remove')) {
			e.preventDefault();
			t.closest('.hp-slot, .hp-holiday').remove();
		} else if (t.closest('.hp-add-holiday')) {
			e.preventDefault();
			var row = fromTpl('hp-tpl-holiday', { __IDX__: String(uid++) });
			document.querySelector('.hp-holiday-rows').appendChild(row);
			row.querySelector('input').focus();
		} else if (t.closest('.hp-copy-weekdays')) {
			e.preventDefault();
			copyMonday();
		}
	});

	document.addEventListener('change', function (e) {
		if (!e.target.classList.contains('hp-mode')) { return; }
		var row = e.target.closest('.hp-day');
		setMode(row, e.target.value);
		if (e.target.value === 'open' && !row.querySelector('.hp-slot')) { addSlot(row); }
	});

	/* ---------- Tabs (location settings) ---------- */
	var tabs = document.querySelectorAll('.hp-tab');
	function selectTab(key, focus) {
		tabs.forEach(function (t) {
			var on = t.getAttribute('data-tab') === key;
			t.setAttribute('aria-selected', on ? 'true' : 'false');
			t.tabIndex = on ? 0 : -1;
			document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
			if (on && focus) { t.focus(); }
		});
		try { sessionStorage.setItem('hpTab', key); } catch (e) { /* private mode */ }
	}
	if (tabs.length) {
		var keys = Array.prototype.map.call(tabs, function (t) { return t.getAttribute('data-tab'); });
		var start = (location.hash || '').replace('#hp-', '');
		try { start = keys.indexOf(start) !== -1 ? start : sessionStorage.getItem('hpTab'); } catch (e) { /* ignore */ }
		if (keys.indexOf(start) !== -1) { selectTab(start); }
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { selectTab(keys[i]); });
			t.addEventListener('keydown', function (e) {
				var n = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
				if (e.key === 'Home') { n = -i; } else if (e.key === 'End') { n = keys.length - 1 - i; }
				if (n === undefined) { return; }
				e.preventDefault();
				if (document.documentElement.dir === 'rtl' && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) { n = -n; }
				selectTab(keys[(i + n + keys.length) % keys.length], true);
			});
		});
		// Required fields in a hidden tab: show that tab instead of failing silently.
		document.addEventListener('invalid', function (e) {
			var panel = e.target.closest && e.target.closest('.hp-panel');
			if (panel && panel.hidden) { selectTab(panel.id.replace('hp-panel-', '')); }
		}, true);
	}

	/* ---------- Colour pickers and image picker ---------- */
	if (window.jQuery && jQuery.fn.wpColorPicker) {
		jQuery('.hp-color').wpColorPicker();
	}
	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('.hp-pick-image');
		if (!btn || !window.wp || !wp.media) { return; }
		e.preventDefault();
		var frame = wp.media({ library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			document.getElementById(btn.getAttribute('data-target')).value = a.url;
		});
		frame.open();
	});
})();
