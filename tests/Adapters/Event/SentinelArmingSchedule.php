<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use Override;
use Zef\Framework\Adapters\Event\WorkerDrainSchedule;
use Zef\Framework\Adapters\Event\WorkerDrainSentinel;

/**
 * The schedule stand-in for the armed-during-the-iteration window:
 * its ask arms the sentinel AND hands the now it was constructed
 * with -- the stop that arrives while the schedule was already
 * handing the pass's time (a SIGTERM landing inside the runtime's
 * idle wait). The now it hands is real on purpose: the honest test
 * must observe the loop draining exactly that one pass and then
 * observing the arm at the very next boundary -- never more passes
 * past an arm than one (the armed-success-must-be-a-real-success
 * lesson, KDD-DU13, replayed on a schedule). A nullable now spells
 * the window's other half: the stop arriving inside the schedule's
 * OWN ending, where the handed answer is that the handing is done
 * -- and the loop still names the sentinel's state at that exit.
 *
 * @internal
 */
final class SentinelArmingSchedule implements WorkerDrainSchedule
{
    private int $asks = 0;

    public function __construct(
        private readonly WorkerDrainSentinel $workerDrainSentinel,
        private readonly ?int $now,
    ) {
    }

    #[Override]
    public function nextNow(): ?int
    {
        ++$this->asks;
        $this->workerDrainSentinel->arm();

        return $this->now;
    }

    /**
     * How many times the loop asked this schedule for a now.
     */
    public function asks(): int
    {
        return $this->asks;
    }
}
