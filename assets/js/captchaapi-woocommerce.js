/**
 * WooCommerce checkout gate.
 *
 * WooCommerce binds its own submit handler to form.checkout and sends the order
 * over AJAX, so the widget's submit mode can't win the race. A capture-phase
 * listener intercepts the first submit, fetches a fresh response through
 * window.captchaapi.solve(), writes it into a hidden field, and re-submits. The
 * second pass is let through so WooCommerce serializes the form, response
 * included, and places the order.
 */
(function () {
    var FALLBACK_MESSAGE = 'Verification is temporarily unavailable. Please try again.';
    var SELECTOR = 'form.checkout, form.woocommerce-checkout';

    function message() {
        return (window.captchaapiWoo && window.captchaapiWoo.unavailable) || FALLBACK_MESSAGE;
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

        if (!window.captchaapi || typeof window.captchaapi.solve !== 'function') {
            window.alert(message());
            return;
        }

        form.__captchaapiSolving = true;
        var submitter = event.submitter || null;

        window.captchaapi.solve({ form: form }).then(function (response) {
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
        var form = event.target;

        if (form && form.matches && form.matches(SELECTOR)) {
            handle(form, event);
        }
    }, true);

    // This form is never marked with data-captcha, so the widget does not find
    // it on its own - the badge is attached here and put into standby, and
    // every state after that arrives through solve({ form }).
    function badge() {
        if (!window.captchaapiBadge || typeof window.captchaapiBadge.attachAndWait !== 'function') {
            return;
        }

        var forms = document.querySelectorAll(SELECTOR);

        for (var i = 0; i < forms.length; i++) {
            window.captchaapiBadge.attachAndWait(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', badge);
    } else {
        badge();
    }
})();
