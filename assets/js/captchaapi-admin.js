/**
 * Drives the "Test API response" button on the settings screen. It posts the
 * current keys to the server, which checks that the service is reachable and
 * that the keys verify locally, then shows the result next to the button.
 */
(function () {
    var config = window.captchaapiAdmin || {};

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var button = document.getElementById('captchaapi-test');
        var result = document.getElementById('captchaapi-test-result');

        if (!button || !result) {
            return;
        }

        button.addEventListener('click', function () {
            button.disabled = true;
            result.className = 'captchaapi-test-result';
            result.textContent = config.testing || 'Testing...';

            var body = new URLSearchParams();
            body.set('action', 'captchaapi_test');
            body.set('nonce', config.nonce || '');

            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (response) {
                return response.json();
            }).then(function (json) {
                var ok = json && json.success;
                var message = json && json.data && json.data.message;
                result.className = 'captchaapi-test-result ' + (ok ? 'is-ok' : 'is-error');
                result.textContent = message || (ok ? 'OK' : (config.failure || 'Test failed.'));
            }).catch(function () {
                result.className = 'captchaapi-test-result is-error';
                result.textContent = config.failure || 'Test failed.';
            }).finally(function () {
                button.disabled = false;
            });
        });
    });
})();
