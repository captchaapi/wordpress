/**
 * Puts a status line under each protected form.
 *
 * One per form, not one per page: the WooCommerce account page shows a login
 * and a registration form side by side, and a single line under one of them
 * reads as though only that one is protected. The widget tracks each form
 * separately anyway, so a line per form is what it can actually report on.
 *
 * Two kinds of form, filled two different ways:
 *
 *   Forms the widget drives itself (data-captcha) get their badge here, before
 *   the widget runs - this script is one of its dependencies - because the
 *   widget reports into the first [data-captcha-status] it finds and has to
 *   find it already in place.
 *
 *   Forms whose integration owns the submit (Contact Form 7, the checkout, the
 *   form plugins) are reported on through solve({ form }), so their badge is
 *   attached by those scripts once the widget has loaded. They call attach()
 *   below rather than building their own markup.
 */
(function () {
    function addBadge() {
        var config = window.captchaapiBadge;

        if (!config || !config.mode || config.mode === 'none') {
            return;
        }

        var selectors = config.selectors || [];

        if (!selectors.length) {
            return;
        }

        var forms = document.querySelectorAll(selectors.join(','));

        for (var i = 0; i < forms.length; i++) {
            attach(forms[i]);
        }
    }

    /**
     * Appends a badge to one form. Returns false when it declined, so a caller
     * that also wants to seed the standby state knows whether there is anything
     * to seed it into.
     */
    function attach(form) {
        var config = window.captchaapiBadge;

        if (!form || !config || !config.mode || config.mode === 'none') {
            return false;
        }

        // If something else already put a status element in this form, adding a
        // second would leave ours permanently blank.
        if (form.querySelector('[data-captcha-status]')) {
            return false;
        }

        form.appendChild(build(config));

        return true;
    }

    function build(config) {
        var badge = document.createElement('div');
        badge.className = 'captchaapi-badge captchaapi-badge--' + config.mode;

        // The outer element clears the floats a theme may leave beside the
        // submit button; clearance eats a top margin, so the gap above is
        // padding on that element and the border belongs to the inner one.
        var inner = document.createElement('div');
        inner.className = 'captchaapi-badge__inner';
        badge.appendChild(inner);

        if (config.mode === 'branded') {
            inner.appendChild(buildLink(config));

            var separator = document.createElement('span');
            separator.className = 'captchaapi-badge__separator';
            separator.setAttribute('aria-hidden', 'true');
            inner.appendChild(separator);
        }

        var status = document.createElement('span');
        status.className = 'captchaapi-badge__status';
        status.setAttribute('data-captcha-status', '');
        status.setAttribute('aria-live', 'polite');
        inner.appendChild(status);

        return badge;
    }

    function buildLink(config) {
        var link = document.createElement('a');
        link.className = 'captchaapi-badge__link';
        link.href = config.href;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';

        // The visible text is "Powered by" beside a decorative logo, which on
        // its own tells a screen reader nothing about where the link goes.
        if (config.aria) {
            link.setAttribute('aria-label', config.aria);
        }

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

        return link;
    }

    /**
     * Attaches a badge and puts it into standby, for an integration that drives
     * its own submit. Skipped entirely against a widget without status(): an
     * element nothing ever writes to is worse than no element.
     */
    function attachAndWait(form) {
        if (!window.captchaapi || typeof window.captchaapi.status !== 'function') {
            return;
        }

        if (attach(form)) {
            window.captchaapi.status(form, 'waiting');
        }
    }

    /**
     * Attaches to everything matching now, and to anything that shows up later.
     *
     * A contact form can arrive after the page does - pulled into a popup, or
     * rendered on demand by a page builder - and a one-off pass at load would
     * miss it. Submission is already safe there, because those listeners are
     * delegated on the document; this is the badge catching up. A delegated
     * focus listener rather than a MutationObserver: nobody submits a form they
     * have not touched, and it costs nothing until they do.
     */
    function watch(selector) {
        if (!selector) {
            return;
        }

        attachAll(selector);

        document.addEventListener('focusin', function (event) {
            var form = event.target && event.target.closest ? event.target.closest('form') : null;

            if (form && form.matches && form.matches(selector)) {
                attachAndWait(form);
            }
        }, true);
    }

    function attachAll(selector) {
        var forms = document.querySelectorAll(selector);

        for (var i = 0; i < forms.length; i++) {
            attachAndWait(forms[i]);
        }
    }

    // Published on the config object the plugin already puts here, so the
    // integration scripts have one place to go for both.
    window.captchaapiBadge = window.captchaapiBadge || {};
    window.captchaapiBadge.attach = attach;
    window.captchaapiBadge.attachAndWait = attachAndWait;
    window.captchaapiBadge.watch = watch;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addBadge);
    } else {
        addBadge();
    }
})();
