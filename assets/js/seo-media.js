(function () {
	'use strict';

	function bindField(field) {
		if (field.dataset.cbSeoImageBound === '1') {
			return;
		}
		field.dataset.cbSeoImageBound = '1';

		const idInput = field.querySelector('[data-cb-seo-image-id]');
		const preview = field.querySelector('[data-cb-seo-image-preview]');
		const select = field.querySelector('[data-cb-seo-image-select]');
		const remove = field.querySelector('[data-cb-seo-image-remove]');
		if (!idInput || !preview || !select || !window.wp || !wp.media) {
			return;
		}

		select.addEventListener('click', function () {
			const frame = wp.media({
				title: select.textContent,
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				const item = frame.state().get('selection').first().toJSON();
				const src = item.sizes && item.sizes.medium ? item.sizes.medium.url : item.url;
				idInput.value = item.id || '';
				preview.innerHTML = src ? '<img src="' + String(src).replace(/"/g, '&quot;') + '" alt="">' : '';
				preview.hidden = !src;
				if (remove) {
					remove.hidden = false;
				}
			});
			frame.open();
		});

		if (remove) {
			remove.addEventListener('click', function () {
				idInput.value = '';
				preview.innerHTML = '';
				preview.hidden = true;
				remove.hidden = true;
			});
		}
	}

	function boot() {
		document.querySelectorAll('[data-cb-seo-image-field]').forEach(bindField);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
