(function () {
    async function poll() {
        if (Date.now() / 1000 > window.sscnPayment.expires) return;
        try {
            const body = new URLSearchParams({action: 'sscn_payment_status',
                order_id: window.sscnPayment.order_id, key: window.sscnPayment.key});
            const response = await fetch(window.sscnPayment.url, {method: 'POST', body,
                credentials: 'same-origin', cache: 'no-store'});
            const result = await response.json();
            if (result.success && result.data.paid) {
                const redirect = new URL(result.data.redirect, window.location.href);
                if (redirect.origin === window.location.origin) window.location.assign(redirect.href);
                return;
            }
        } catch (error) { /* Retry transient network failures without displaying internal details. */ }
        window.setTimeout(poll, 10000);
    }
    window.setTimeout(poll, 3000);
}());
