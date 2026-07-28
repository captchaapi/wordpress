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
        var first = null;

        for (var i = 0; i < forms.length; i++) {
            var config = forms[i];
            var nodes = document.querySelectorAll(config.selector);

            for (var j = 0; j < nodes.length; j++) {
                nodes[j].setAttribute('data-captcha', '');

                if (config.mode) {
                    nodes[j].setAttribute('data-captcha-mode', config.mode);
                }

                if (!first) {
                    first = nodes[j];
                }
            }
        }

        if (first) {
            addBadge(first);
        }
    }

    /**
     * The widget fills any [data-captcha-status] it finds inside a form, so the
     * badge has to live in the form rather than after it.
     *
     * Only the first protected form on the page gets one. A WooCommerce account
     * page carries a login and a registration form side by side, and a page can
     * hold several contact forms; one status line per page says everything
     * several would, without turning the page into a wall of them.
     */
    function addBadge(form) {
        var config = window.captchaapiBadge;

        if (!config || !config.mode || config.mode === 'none') {
            return;
        }

        if (document.querySelector('.captchaapi-badge')) {
            return;
        }

        // The widget reports into the first [data-captcha-status] it finds in
        // the form. If something else already put one there, adding a second
        // would leave ours permanently blank.
        if (form.querySelector('[data-captcha-status]')) {
            return;
        }

        var badge = document.createElement('div');
        badge.className = 'captchaapi-badge captchaapi-badge--' + config.mode;

        // The outer element clears the theme's floats; the inner one carries the
        // border. Clearance eats a top margin, so the gap above lives on the
        // outer box as padding and must not sit inside the border.
        var inner = document.createElement('div');
        inner.className = 'captchaapi-badge__inner';
        badge.appendChild(inner);

        if (config.mode === 'branded') {
            var link = document.createElement('a');
            link.className = 'captchaapi-badge__link';
            link.href = config.href;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';

            if (config.logo) {
                var logo = document.createElement('img');
                logo.className = 'captchaapi-badge__logo';
                logo.src = config.logo;
                logo.alt = '';
                logo.setAttribute('aria-hidden', 'true');
                link.appendChild(logo);
            }

            var label = document.createElement('span');
            label.textContent = config.label || '';
            link.appendChild(label);

            var separator = document.createElement('span');
            separator.className = 'captchaapi-badge__separator';
            separator.setAttribute('aria-hidden', 'true');

            inner.appendChild(link);
            inner.appendChild(separator);
        }

        var status = document.createElement('span');
        status.className = 'captchaapi-badge__status';
        status.setAttribute('data-captcha-status', '');
        status.setAttribute('aria-live', 'polite');
        inner.appendChild(status);

        form.appendChild(badge);
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
