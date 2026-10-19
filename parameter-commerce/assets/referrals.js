/* Progressive clipboard enhancement. The readonly link works without JavaScript. */
(() => {
  'use strict';
  document.querySelectorAll('.pc-referral-copy-field').forEach((panel) => {
    const button = panel.querySelector('.pc-referral-copy');
    const input = panel.querySelector('input, textarea');
    const status = panel.querySelector('.pc-referral-copy-status');
    if (!button || !input || !status) return;
    button.hidden = false;
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(input.value);
        status.textContent = button.dataset.copied;
      } catch (_) {
        input.focus();
        input.select();
        status.textContent = button.dataset.fallback;
      }
    });
  });
})();
