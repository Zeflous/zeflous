<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use Override;
use Zef\Framework\Adapters\Event\WorkerDrainSchedule;

/**
 * The schedule stand-in: the nows a test scripts, handed one ask
 * at a time in order, null once the list is spent -- the host's
 * idle cadence spelled as a plain list, with the ask count kept so
 * a test can pin HOW OFTEN the loop asked (the loop-top sentinel
 * check's own observable: an armed loop must not ask at all, and
 * a stop between passes must not ask for the passes it will not
 * run).
 *
 * @internal
 */
final class ScriptedDrainSchedule implements WorkerDrainSchedule
{
    private int $asks = 0;

    /** @param list<int> $nows the nows this schedule hands, in order */
    public function __construct(private readonly array $nows)
    {
    }

    #[Override]
    public function nextNow(): ?int
    {
        if ($this->asks >= \count($this->nows)) {
            return null;
        }

        $now = $this->nows[$this->asks];
        ++$this->asks;

        return $now;
    }

    /**
     * How many times the loop asked this schedule for a now -- the
     * ask count that pins the loop's own asking discipline.
     */
    public function asks(): int
    {
        return $this->asks;
    }
}
