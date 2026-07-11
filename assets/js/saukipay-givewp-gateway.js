(function () {
	'use strict';

	function registerSaukiPayGateway() {
		if (!window.givewp || !window.givewp.gateways || typeof window.givewp.gateways.register !== 'function') {
			return;
		}

		var createElement = window.wp && window.wp.element && window.wp.element.createElement;
		var saukiPayGateway = {
			id: 'saukipay',
			Fields: function () {
				var config = window.saukiPayGiveWP || {};
				var message = saukiPayGateway.settings && saukiPayGateway.settings.message
					? saukiPayGateway.settings.message
					: 'You will be redirected to Sauki Pay secure checkout to complete your donation.';

				if (!createElement) {
					return null;
				}

				return createElement(
					'div',
					{
						className: 'saukipay-givewp-panel',
					},
					createElement(
						'div',
						{
							className: 'saukipay-givewp-brand',
						},
						createElement(
							'span',
							{
								className: 'saukipay-givewp-icon',
							},
							config.iconUrl
								? createElement('img', {
									src: config.iconUrl,
									alt: '',
								})
								: null
						),
						createElement(
							'span',
							{
								className: 'saukipay-givewp-wordmark',
							},
							createElement('span', null, 'Sauki'),
							createElement('strong', null, 'PAY')
						),
						createElement(
							'em',
							{
								className: 'saukipay-givewp-secure',
							},
							config.secureText || 'Secure checkout'
						)
					),
					createElement(
						'p',
						{
							className: 'saukipay-givewp-message',
						},
						message
					)
				);
			},
		};

		window.givewp.gateways.register(saukiPayGateway);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', registerSaukiPayGateway);
	} else {
		registerSaukiPayGateway();
	}
}());
