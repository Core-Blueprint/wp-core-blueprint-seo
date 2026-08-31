(() => {
	'use strict';

	const initIndexingPolicy = () => {
		document.querySelectorAll('[data-cb-seo-policy-row]').forEach((row) => {
			const index = row.querySelector('[data-cb-seo-policy-index]');
			const sitemap = row.querySelector('[data-cb-seo-policy-sitemap]');
			if (!index || !sitemap) return;

			const sync = () => {
				const disabled = !index.checked;
				sitemap.disabled = disabled;
				row.classList.toggle('is-index-disabled', disabled);
			};

			index.addEventListener('change', sync);
			sync();
		});
	};

	const initTabs = () => {
		const root = document.querySelector('.cb-core-seo-wrap');
		const tabs = Array.from(document.querySelectorAll('[data-cb-seo-tab]'));
		const panels = Array.from(document.querySelectorAll('[data-cb-seo-panel]'));
		const sectionInput = document.querySelector('input[name="cb_seo_section"]');

		if (!root || tabs.length === 0 || panels.length === 0) {
			return;
		}

		const available = new Set(tabs.map((tab) => tab.dataset.section));
		let active = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.section || 'post-types';

		if (window.location.hash) {
			const hashPanel = panels.find((panel) => `#${panel.id}` === window.location.hash);
			if (hashPanel?.dataset.section && available.has(hashPanel.dataset.section)) {
				active = hashPanel.dataset.section;
			}
		}

		const activate = (section, updateHash = false) => {
			if (!available.has(section)) {
				return;
			}

			tabs.forEach((tab) => {
				const selected = tab.dataset.section === section;
				tab.classList.toggle('nav-tab-active', selected);
				tab.setAttribute('aria-selected', selected ? 'true' : 'false');
				tab.tabIndex = selected ? 0 : -1;
			});

			panels.forEach((panel) => {
				panel.hidden = panel.dataset.section !== section;
			});

			if (sectionInput) {
				sectionInput.value = section;
			}

			if (updateHash) {
				const panel = panels.find((item) => item.dataset.section === section);
				if (panel) {
					history.replaceState(null, '', `#${panel.id}`);
				}
			}
		};

		root.classList.add('cb-seo-tabs-enhanced');
		activate(active);

		tabs.forEach((tab, index) => {
			tab.addEventListener('click', (event) => {
				event.preventDefault();
				activate(tab.dataset.section, true);
			});

			tab.addEventListener('keydown', (event) => {
				if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
					return;
				}

				event.preventDefault();
				let targetIndex = index;
				if (event.key === 'ArrowRight') targetIndex = (index + 1) % tabs.length;
				if (event.key === 'ArrowLeft') targetIndex = (index - 1 + tabs.length) % tabs.length;
				if (event.key === 'Home') targetIndex = 0;
				if (event.key === 'End') targetIndex = tabs.length - 1;

				const target = tabs[targetIndex];
				activate(target.dataset.section, true);
				target.focus();
			});
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => { initTabs(); initIndexingPolicy(); }, { once: true });
	} else {
		initTabs();
		initIndexingPolicy();
	}
})();
