/**
 * Tags the configured forms with data-captcha so the widget picks them up.
 *
 * WordPress renders the login, registration, and comment forms itself and offers
 * no hook to add an attribute to those <form> tags, so we add it here. This
 * script is a dependency of the widget, which means it runs first and its
 * DOMContentLoaded handler is registered before the widget's form discovery.
 *
 * It also preserves the submit button's name/value. The widget submits in submit
 * mode via HTMLFormElement.prototype.submit(), which never includes the button
 * that triggered the submit. Forms that key their handler on that name - every
 * WooCommerce account form does (login, register, wc_reset_password) - would
 * otherwise never run. A capture-phase submit listener copies the active
 * submitter into a hidden input before the widget takes over.
 */
(function () {
    function mark() {
        var forms = window.captchaapiForms || [];

        for (var i = 0; i < forms.length; i++) {
            var config = forms[i];
            var nodes = document.querySelectorAll(config.selector);

            for (var j = 0; j < nodes.length; j++) {
                nodes[j].setAttribute('data-captcha', '');

                if (config.mode) {
                    nodes[j].setAttribute('data-captcha-mode', config.mode);
                }
            }
        }
    }

    function preserveSubmitter(event) {
        var form = event.target;

        if (!form || !form.hasAttribute || !form.hasAttribute('data-captcha')) {
            return;
        }

        var prior = form.querySelectorAll('input[data-captcha-submitter]');
        for (var i = 0; i < prior.length; i++) {
            prior[i].parentNode.removeChild(prior[i]);
        }

        var submitter = event.submitter;
        if (!submitter || !submitter.name) {
            return;
        }

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = submitter.name;
        input.value = submitter.value || '';
        input.setAttribute('data-captcha-submitter', '');
        form.appendChild(input);
    }

    // Capture phase so this runs before the widget's own submit handler.
    document.addEventListener('submit', preserveSubmitter, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mark);
    } else {
        mark();
    }
})();
