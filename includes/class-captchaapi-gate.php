<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Runs the verification for a single request. Every protected form reads the
 * response the widget injected and asks the verifier whether captchaapi.eu
 * accepts it. Single-use enforcement is the server's job now, so there is no
 * local replay store.
 */
class Captchaapi_Gate
{
    const FIELD = 'captchaapi_response';

    private Captchaapi_Verifier $verifier;

    private bool $failsafe;

    /**
     * @var array<string, bool>
     */
    private array $memo = [];

    public function __construct(Captchaapi_Verifier $verifier, bool $failsafe = false)
    {
        $this->verifier = $verifier;
        $this->failsafe = $failsafe;
    }

    public function passes(): bool
    {
        return $this->check($this->posted_response());
    }

    /**
     * Same check as passes(), but for a response the integration already has in
     * hand. Fluent Forms (and similar) post the form fields nested inside a
     * single "data" parameter, so the response never lands in $_POST directly;
     * the integration reads it from the parsed form data and passes it here.
     */
    public function passes_for(string $response): bool
    {
        return $this->check($response);
    }

    /**
     * The response is single-use: the server consumes it on the first verify.
     * A form plugin that validates twice in one request would fail the second
     * call, so the verdict is memoized per response and the action fires once.
     */
    private function check(string $response): bool
    {
        if (! array_key_exists($response, $this->memo)) {
            $this->memo[$response] = $this->evaluate($response);

            /**
             * Fires once per protected form submission with the verification result.
             *
             * @param bool $passed Whether the submission cleared the gate.
             */
            do_action('captchaapi_verified', $this->memo[$response]);
        }

        return $this->memo[$response];
    }

    private function evaluate(string $response): bool
    {
        if ($response === '') {
            return false;
        }

        $status = $this->verifier->verify($response);

        if ($status === Captchaapi_Verifier::VERIFIED) {
            return true;
        }

        // Only an unreachable service or a 5xx defers to failsafe; a definitive
        // rejection always blocks.
        if ($status === Captchaapi_Verifier::UNAVAILABLE) {
            return $this->failsafe;
        }

        return false;
    }

    private function posted_response(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (empty($_POST[self::FIELD])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_POST[self::FIELD]));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}
