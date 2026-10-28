<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Event\WorkerDrainSentinel;

/**
 * Pins the shutdown sentinel's own contract: silent by default,
 * armed by the host's one-way verb, and the same stop however
 * many times the shutdown path fires it -- the arm-once
 * discipline the drain loop reads between passes (KDD-DW2).
 *
 * @internal
 */
#[CoversClass(WorkerDrainSentinel::class)]
final class WorkerDrainSentinelTest extends TestCase
{
    public function testASilentSentinelIsNotArmed(): void
    {
        self::assertFalse(new WorkerDrainSentinel()->isArmed());
    }

    public function testArmingIsTheOneWayStopSignal(): void
    {
        $workerDrainSentinel = new WorkerDrainSentinel();

        $workerDrainSentinel->arm();

        self::assertTrue($workerDrainSentinel->isArmed());
    }

    public function testArmingTwiceIsTheSameStop(): void
    {
        $workerDrainSentinel = new WorkerDrainSentinel();

        $workerDrainSentinel->arm();
        $workerDrainSentinel->arm();

        self::assertTrue($workerDrainSentinel->isArmed());
    }
}
