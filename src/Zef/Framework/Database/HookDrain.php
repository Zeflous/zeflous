<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Psr\Log\LoggerInterface;

/**
 * The hook queue's drain walk: every queued hook, FIFO, with the
 * cooperative slow-hook guard timing each one against the threshold
 * when one is configured ({@see HookDuration} owns the judgement) and
 * the debug entry emitted through the logger when it is exceeded.
 *
 * @internal
 */
final class HookDrain
{
    /**
     * Runs the pending hooks, in queue order. Observability-only: a
     * slow hook is allowed to complete, timed and reported, never
     * interrupted.
     *
     * @param list<callable(): void> $pending
     * @param null|int               $thresholdMs the slow-hook bar; null
     *                                            runs every hook untimed
     */
    public static function run(array $pending, ?int $thresholdMs, ?LoggerInterface $logger): void
    {
        foreach ($pending as $hook) {
            if ($thresholdMs === null) {
                $hook();

                continue;
            }

            // The casts are the engine signature's own narrowing:
            // hrtime(true) answers int|float, and the true-argument's
            // int is not a narrowing the analyser can carry through
            // the call, so the landings are explicit.
            $start = (int) hrtime(true);
            $hook();
            $elapsedNs = (int) hrtime(true) - $start;
            $elapsedMs = HookDuration::milliseconds($elapsedNs);
            if (!HookDuration::exceeded($elapsedMs, $thresholdMs)) {
                continue;
            }

            $logger?->debug(
                'afterCommit hook exceeded slow-hook threshold',
                [
                    'elapsed_ms' => $elapsedMs,
                    'threshold_ms' => $thresholdMs,
                ],
            );
        }
    }
}
