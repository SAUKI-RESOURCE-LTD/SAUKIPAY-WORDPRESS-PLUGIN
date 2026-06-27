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
