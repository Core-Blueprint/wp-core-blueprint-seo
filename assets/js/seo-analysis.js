/**
 * Core Blueprint SEO - on-demand live rendered-page analysis.
 */
(() => {
	'use strict';

	document.addEventListener('click', async (event) => {
		const button = event.target.closest('[data-cb-seo-analyze-button]');
		if (!button) {
			return;
		}

		const panel = button.closest('[data-cb-seo-analysis]');
		if (!panel || typeof window.ajaxurl !== 'string') {
			return;
		}

		const output = panel.querySelector('[data-cb-seo-analysis-output]');
		const focus = panel.querySelector('[data-cb-seo-focus-keyword]');
		if (!output || !focus) {
			return;
		}

		const originalLabel = button.textContent;
		button.disabled = true;
		button.textContent = button.dataset.analyzingLabel || 'Analyzing…';
		output.setAttribute('aria-busy', 'true');

		try {
			const body = new URLSearchParams({
				action: 'cb_seo_analyze_post',
				post_id: panel.dataset.postId || '',
				nonce: panel.dataset.nonce || '',
				focus_keyword: focus.value || '',
			});

			const response = await fetch(window.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			});
			const payload = await response.json();
			if (!payload.success) {
				throw new Error(payload?.data?.message || panel.dataset.errorLabel || 'Analysis failed.');
			}
			output.innerHTML = payload.data.html || '';
		} catch (error) {
			output.replaceChildren();
			const notice = document.createElement('div');
			notice.className = 'notice notice-error inline';
			const paragraph = document.createElement('p');
			paragraph.textContent = error instanceof Error ? error.message : (panel.dataset.errorLabel || 'Analysis failed.');
			notice.appendChild(paragraph);
			output.appendChild(notice);
		} finally {
			output.removeAttribute('aria-busy');
			button.disabled = false;
			button.textContent = originalLabel;
		}
	});
})();
