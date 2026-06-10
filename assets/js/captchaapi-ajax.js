/**
 * Generic gate for form plugins that submit over their own AJAX.
 *
 * These plugins bind their own submit handler and send the form before the
 * widget's submit mode can attach an response. A capture-phase listener
 * intercepts the first submit of any form matching a configured selector,
 * fetches a fresh response through window.captchaapi.solve(), writes it into
 * a hidden field, and re-submits. The second pass runs the plugin's own handler,
 * which serializes the form with the response included.
 *
 * Listening on the document covers forms inserted after load (popups, blocks,
 * multi-step forms). Selectors come from window.captchaapiAjax.selectors.
 */
(function () {
    var FALLBACK_MESSAGE = 'Verification is temporarily unavailable. Please try again.';

    function config() {
        return window.captchaapiAjax || {};
    }

    function message() {
        return config().unavailable || FALLBACK_MESSAGE;
    }

    function selector() {
        var selectors = config().selectors || [];

        return selectors.join(', ');
    }

    function setResponse(form, value) {
        var input = form.querySelector('input[name="captchaapi_response"]');

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'captchaapi_response';
            form.appendChild(input);
        }

        input.value = value;
    }

    function handle(form, event) {
        if (form.__captchaapiCleared) {
            form.__captchaapiCleared = false;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (form.__captchaapiSolving) {
            return;
        }

        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }

            return;
        }

        if (!window.captchaapi || typeof window.captchaapi.solve !== 'function') {
            window.alert(message());
            return;
        }

        form.__captchaapiSolving = true;
        var submitter = event.submitter || null;

        window.captchaapi.solve().then(function (response) {
            setResponse(form, response);
            form.__captchaapiCleared = true;
            form.requestSubmit(submitter || undefined);
        }).catch(function () {
            window.alert(message());
        }).finally(function () {
            form.__captchaapiSolving = false;
        });
    }

    document.addEventListener('submit', function (event) {
        var match = selector();
        var form = event.target;

        if (match && form && form.matches && form.matches(match)) {
            handle(form, event);
        }
    }, true);
})();
