/* Prepare the official Google button on account/checkout sign-in panels. */
(function () {
	'use strict';
	const config = window.pcGoogleLogin;
	if (!config) return;
	let library;
	async function request(action, values = {}) {
		const controller = new AbortController();
		const timeout = setTimeout(() => controller.abort(), 15000);
		try {
			const response = await fetch(config.url, {
				method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action, ...values })
			});
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result.data?.message || config.error);
			return result.data;
		} finally {
			clearTimeout(timeout);
		}
	}
	function loadGoogle() {
		if (!library) library = new Promise((resolve, reject) => {
			const script = document.createElement('script');
			let settled = false;
			const finish = failed => {
				if (settled) return;
				settled = true;
				clearTimeout(timeout);
				if (failed) {
					library = null;
					script.remove();
					reject(new Error(config.error));
				} else resolve();
			};
			const timeout = setTimeout(() => finish(true), 15000);
			script.src = 'https://accounts.google.com/gsi/client';
			script.async = true;
			script.onload = () => finish(!window.google?.accounts?.id);
			script.onerror = () => finish(true);
			document.head.appendChild(script);
		});
		return library;
	}
	const panels = Array.from(document.querySelectorAll('.pc-google-load'), button => {
		const panel = button.closest('.pc-google-login');
		return { button, panel, status: panel.querySelector('.pc-google-status'), target: panel.querySelector('.pc-google-button') };
	});
	let preparing = false;
	let expiryTimer;
	let expiresAt = 0;
	function retry(message) {
		clearTimeout(expiryTimer);
		expiresAt = 0;
		panels.forEach(({ button, status, target }) => {
			target.replaceChildren();
			target.hidden = false;
			button.hidden = false;
			button.disabled = false;
			button.querySelector('span').textContent = config.retry;
			status.textContent = message;
		});
	}
	async function prepare() {
		if (preparing) return;
		preparing = true;
		panels.forEach(({ button, status }) => { button.disabled = true; status.textContent = config.loading; });
		try {
			await loadGoogle();
			// All panels on a page share one browser-bound challenge and initialization.
			const challenge = await request('pc_google_start');
			google.accounts.id.initialize({
				client_id: challenge.clientId, nonce: challenge.nonce,
				auto_select: false, ux_mode: 'popup',
				callback: async credential => {
					clearTimeout(expiryTimer);
					expiresAt = 0;
					panels.forEach(({ target, status }) => { target.hidden = true; status.textContent = config.loading; });
					try {
						await request('pc_google_finish', { credential: credential.credential });
						// Preserve the exact local URL, cart and referral context.
						window.location.reload();
					} catch (error) {
						retry(error.name === 'AbortError' ? config.error : error.message || config.error);
					}
				}
			});
			panels.forEach(({ button, panel, status, target }) => {
				google.accounts.id.renderButton(target, {
					theme: 'outline', size: 'large', text: 'signin_with',
					width: Math.min(300, Math.floor(panel.getBoundingClientRect().width))
				});
				button.hidden = true;
				status.textContent = '';
			});
			// Retire an idle button before the server's five-minute challenge expires.
			expiresAt = Date.now() + 240000;
			expiryTimer = setTimeout(() => retry(config.expired), 240000);
		} catch (error) {
			retry(error.name === 'AbortError' ? config.error : error.message || config.error);
		} finally {
			preparing = false;
		}
	}
	panels.forEach(({ button }) => button.addEventListener('click', prepare));
	if (panels.length) {
		document.addEventListener('visibilitychange', () => {
			if (!document.hidden && expiresAt && Date.now() >= expiresAt) retry(config.expired);
		});
		prepare();
	}
	document.querySelectorAll('.pc-google-disconnect').forEach(button => {
		button.addEventListener('click', async () => {
			button.disabled = true;
			const status = button.parentElement.querySelector('.pc-google-status');
			try {
				const result = await request('pc_google_disconnect', { _ajax_nonce: config.disconnectNonce });
				status.textContent = result.message;
				button.hidden = true;
			} catch (error) {
				status.textContent = error.name === 'AbortError' ? config.error : error.message || config.error;
				button.disabled = false;
			}
		});
	});
}());
