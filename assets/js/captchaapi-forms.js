/**
 * Tags the configured forms with data-captcha so the widget picks them up.
 *
 * WordPress renders the login, registration, and comment forms itself and offers
 * no hook to add an attribute to those <form> tags, so we add it here. This
 * script is a dependency of the widget, which means it runs first and its
 * DOMContentLoaded handler is registered before the widget's form discovery.
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mark);
    } else {
        mark();
    }
})();
