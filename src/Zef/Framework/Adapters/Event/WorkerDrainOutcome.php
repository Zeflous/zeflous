<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Event\DrainOutcome;

/**
 * What one whole run of the worker drain loop answered with: the
 * honest ledger of the run's own three questions. How many passes
 * ran; why the run ended (the sentinel armed -- the host's
 * shutdown -- or the schedule ending its own handing); and the
 * last pass's own {@see DrainOutcome} -- the five counts the
 * durable regime always answers a pass with, carried as the
 * facade answered them, never re-derived here.
 *
 * The aggregate story -- every pass's counts summed, timestamped
 * and attributed -- is deliberately NOT this class's business:
 * traceability over drain outcomes is the named follow-up
 * KDD-EA12/KDD-DU12 both spell, and the counts are its seam. This
 * outcome hands the last pass's answer to the host because the
 * host holding the run's exit wants the store's freshest reading;
 * a run that never drained has no reading to lie with, and answers
 * null instead of inventing one.
 *
 * @api host applications and worker-runtime glue read this
 *      outcome from {@see WorkerDrainLoop::run()} to observe what
 *      their worker's run drained and why it stopped
 */
final readonly class WorkerDrainOutcome
{
    public function __construct(
        private int $passes,
        private bool $stoppedBySentinel,
        private ?DrainOutcome $drainOutcome,
    ) {
    }

    /**
     * How many drain passes ran -- the passes the schedule handed
     * a now for and the loop drained, zero for a run the sentinel
     * ended before its first ask.
     */
    public function passes(): int
    {
        return $this->passes;
    }

    /**
     * Why the run ended: true when the sentinel was armed (the
     * host's shutdown -- before the first ask, after an ask, or
     * between passes); false when the schedule itself ended its
     * handing, which is the run completing the host's own idling
     * rather than being told to stop.
     */
    public function stoppedBySentinel(): bool
    {
        return $this->stoppedBySentinel;
    }

    /**
     * The last pass's own answer -- the five counts the durable
     * facade answered that pass with (delivered, retried,
     * dead-lettered, quarantined, pending) -- or null when no pass
     * ran: a run that drained nothing has no store reading, and
     * the outcome says so instead of spelling a zero it never
     * read.
     */
    public function lastOutcome(): ?DrainOutcome
    {
        return $this->drainOutcome;
    }
}
