/**
 * Contact Form 7 gate.
 *
 * CF7 attaches its own submit listener that fires its AJAX request right away,
 * so the widget's submit mode can't win the race. A capture-phase listener on
 * the document intercepts the first submit of any CF7 form, fetches a fresh
 * attestation through window.captchaapi.solve(), drops it into the form, and
 * re-submits. The second pass is let through to CF7's own listener, which sends
 * the form with the attestation included.
 *
 * Listening on the document (not per form) means forms inserted after load -
 * modals, page-builder popups, AJAX-rendered blocks - are covered too.
 */
(function () {
    var FALLBACK_MESSAGE = 'Verification is temporarily unavailable. Please try again.';

    function message() {
        return (window.captchaapiCf7 && window.captchaapiCf7.unavailable) || FALLBACK_MESSAGE;
    }

    function showMessage(form) {
        var output = form.querySelector('.wpcf7-response-output');

        if (!output) {
            return;
        }

        // CF7 keeps the response output hidden while the form is in its initial
        // state; switching to a non-init error state lets CF7's own styling
        // reveal it. CF7 replaces this class on its next submission.
        form.classList.remove('init');
        form.classList.add('aborted');

        output.setAttribute('role', 'alert');
        output.setAttribute('aria-hidden', 'false');
        output.textContent = message();
    }

    function setAttestation(form, value) {
        var input = form.querySelector('input[name="captcha_attestation"]');

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'captcha_attestation';
            form.appendChild(input);
        }

        input.value = value;
    }

    function handle(form, event) {
        // The re-fired submit already carries a fresh attestation: let CF7 send it.
        if (form.__captchaapiCleared) {
            form.__captchaapiCleared = false;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (form.__captchaapiSolving) {
            return;
        }

        // Let the browser show native validation without spending a solve. Keeping
        // the solve for a form that would pass validation also guarantees the
        // re-fired requestSubmit() actually dispatches a submit event.
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }

            return;
        }

        if (!window.captchaapi || typeof window.captchaapi.solve !== 'function') {
            showMessage(form);

            return;
        }

        form.__captchaapiSolving = true;
        var submitter = event.submitter || null;

        window.captchaapi.solve().then(function (attestation) {
            setAttestation(form, attestation);
            form.__captchaapiCleared = true;
            form.requestSubmit(submitter || undefined);
        }).catch(function () {
            showMessage(form);
        }).finally(function () {
            form.__captchaapiSolving = false;
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (form && form.matches && form.matches('form.wpcf7-form')) {
            handle(form, event);
        }
    }, true);
})();
