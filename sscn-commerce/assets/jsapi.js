(function () {
    const button = document.getElementById('sscn-jsapi-pay');
    const message = document.getElementById('sscn-payment-message');
    if (!button || !message || !window.sscnJsapi) return;
    button.addEventListener('click', function () {
        if (!window.WeixinJSBridge) { message.textContent = '请在微信中打开此页面。'; return; }
        button.disabled = true;
        window.WeixinJSBridge.invoke('getBrandWCPayRequest', window.sscnJsapi, function (result) {
            button.disabled = false;
            message.textContent = result.err_msg === 'get_brand_wcpay_request:ok'
                ? '正在向支付平台确认结果，请稍候。'
                : '支付未完成，您可以重试。';
            // The browser result never changes the order. Signed server confirmation does.
        });
    });
}());
