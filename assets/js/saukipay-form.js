(function () {
	function ready(callback) {
		if (document.readyState !== 'loading') {
			callback();
			return;
		}

		document.addEventListener('DOMContentLoaded', callback);
	}

	ready(function () {
		var forms = document.querySelectorAll('.saukipay-form');

		forms.forEach(function (form) {
			var amountPicker = form.querySelector('.saukipay-amount-picker');

			if (amountPicker) {
				var amountInput = amountPicker.querySelector('input[name="amount"]');
				var customInput = amountPicker.querySelector('input[name="custom_amount"]');
				var options = amountPicker.querySelectorAll('.saukipay-amount-option');

				options.forEach(function (option) {
					option.addEventListener('click', function () {
						options.forEach(function (item) {
							item.classList.remove('is-selected');
						});

						option.classList.add('is-selected');

						if (amountInput) {
							amountInput.value = option.getAttribute('data-amount') || '';
						}

						if (customInput) {
							customInput.value = '';
						}
					});
				});

				if (customInput) {
					customInput.addEventListener('input', function () {
						if (customInput.value) {
							options.forEach(function (item) {
								item.classList.remove('is-selected');
							});

							if (amountInput) {
								amountInput.value = customInput.value;
							}
						}
					});
				}
			}

			form.addEventListener('submit', function () {
				var button = form.querySelector('button[type="submit"]');

				if (button) {
					button.disabled = true;
					button.setAttribute('aria-busy', 'true');
				}
			});
		});
	});
}());
