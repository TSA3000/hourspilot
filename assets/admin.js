/* HoursPilot admin – hour slot and holiday repeaters. | v0.4.0 | Arthur Sam Mahdi */
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
		row.querySelector('.hp-mode').value = mode;
		row.className = row.className.replace(/\bhp-mode-\S+/, 'hp-mode-' + mode);
	}

	function copyMonday() {
		var src = document.querySelector('.hp-day[data-day="1"]');
		if (!src) { return; }
		var mode = src.querySelector('.hp-mode').value;
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
})();
