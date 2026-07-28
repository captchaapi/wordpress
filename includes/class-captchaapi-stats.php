<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Counts what the gate decided, so the settings screen can prove the plugin is
 * doing something.
 *
 * This is the half of the numbers that only this site knows. The service can
 * say how much of the quota is gone; it cannot say how many submissions were
 * turned away here, because a rejected one never reaches it.
 *
 * One row per day, thirty days kept, and the option is not autoloaded - it is
 * read on one admin screen and nowhere else. A write happens per protected
 * submission, which is the one place it cannot be avoided: a blocked bot is
 * exactly the event being counted.
 *
 * The read-modify-write is not atomic, so two submissions landing together can
 * cost one increment. That is accepted: these numbers exist to answer "is this
 * working", and an answer that is off by one under a burst still answers it.
 * Anything stronger would mean a table and a schema for a figure nobody bills
 * against.
 */
class Captchaapi_Stats
{
    const OPTION = 'captchaapi_stats';

    const KEEP_DAYS = 30;

    public function boot(): void
    {
        add_action('captchaapi_verified', [$this, 'record'], 10, 2);
    }

    /**
     * @param bool   $passed
     * @param string $surface
     */
    public function record($passed, $surface = ''): void
    {
        $stats = $this->all();
        $today = gmdate('Ymd');

        // Rebuilt rather than trusted: anything else in that slot came from
        // somewhere this class did not write, and incrementing into it would
        // warn and drop the count.
        if (! isset($stats[$today]) || ! is_array($stats[$today])) {
            $stats[$today] = ['passed' => 0, 'blocked' => 0];
        }

        $stats[$today][$passed ? 'passed' : 'blocked']++;
        $stats['last'] = time();

        update_option(self::OPTION, $this->prune($stats), false);
    }

    /**
     * Totals over the last $days days, today included.
     *
     * @return array{passed: int, blocked: int}
     */
    public function totals(int $days): array
    {
        $stats  = $this->all();
        $from   = gmdate('Ymd', time() - (($days - 1) * DAY_IN_SECONDS));
        $totals = ['passed' => 0, 'blocked' => 0];

        foreach ($stats as $day => $row) {
            if ($day === 'last' || ! is_array($row) || $day < $from) {
                continue;
            }

            $totals['passed']  += (int) ($row['passed'] ?? 0);
            $totals['blocked'] += (int) ($row['blocked'] ?? 0);
        }

        return $totals;
    }

    /**
     * When the gate last decided anything, or 0 when it never has. This is the
     * one number that answers "is this thing running at all".
     */
    public function last_seen(): int
    {
        return (int) ($this->all()['last'] ?? 0);
    }

    /**
     * Whether there is anything worth showing. Tied to the same window the rows
     * are kept for: once the last daily row has aged out, a site that saw one
     * submission eight months ago would otherwise keep a panel of zeros next to
     * a note about how long ago that was.
     */
    public function has_data(): bool
    {
        $last = $this->last_seen();

        return $last > 0 && (time() - $last) < (self::KEEP_DAYS * DAY_IN_SECONDS);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        $stats = get_option(self::OPTION, []);

        return is_array($stats) ? $stats : [];
    }

    /**
     * @param array<string, mixed> $stats
     *
     * @return array<string, mixed>
     */
    private function prune(array $stats): array
    {
        // Same window totals() reads, so the oldest kept row is one that can
        // still appear in a total rather than one waiting to be dropped.
        $cutoff = gmdate('Ymd', time() - ((self::KEEP_DAYS - 1) * DAY_IN_SECONDS));

        foreach (array_keys($stats) as $day) {
            if ($day !== 'last' && $day < $cutoff) {
                unset($stats[$day]);
            }
        }

        return $stats;
    }
}
