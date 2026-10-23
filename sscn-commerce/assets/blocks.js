(function () {
    const {createElement} = window.wp.element;
    const {decodeEntities} = window.wp.htmlEntities;
    ['sscn_alipay', 'sscn_wechat'].forEach(function (name) {
        const settings = window.wc.wcSettings.getSetting(name + '_data', null);
        if (!settings) return;
        const title = decodeEntities(settings.title);
        const content = createElement('span', null, decodeEntities(settings.description));
        window.wc.wcBlocksRegistry.registerPaymentMethod({
            name, label: createElement('span', null, title), content, edit: content,
            canMakePayment: () => true, ariaLabel: title,
            supports: {features: settings.supports || ['products']}
        });
    });
}());
