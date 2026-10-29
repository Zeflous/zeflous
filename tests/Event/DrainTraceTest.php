<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DrainOutcome;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\DrainTraceEncoder;

/**
 * Pins the drain trace's own shape: the outcome it timestamps, the
 * six getters it answers with, and the encoder's JSON spelling --
 * the record the store's journal holds, the same bytes either side
 * of the store choice.
 *
 * @internal
 */
#[CoversClass(DrainTrace::class)]
#[CoversClass(DrainTraceEncoder::class)]
final class DrainTraceTest extends TestCase
{
    public function testATraceReadsBackTheOutcomeItTimestamps(): void
    {
        $drainOutcome = new DrainOutcome(2, 1, 3, 1, 4);
        $drainTrace = DrainTrace::fromOutcome(1_000, $drainOutcome);

        self::assertSame(1_000, $drainTrace->drainedAt());
        self::assertSame(2, $drainTrace->delivered());
        self::assertSame(1, $drainTrace->retried());
        self::assertSame(3, $drainTrace->deadLettered());
        self::assertSame(1, $drainTrace->quarantined());
        self::assertSame(4, $drainTrace->pending());
    }

    public function testTheEncoderSpellsTheSixKeyRecord(): void
    {
        $drainTrace = DrainTrace::fromOutcome(1_000, new DrainOutcome(2, 1, 3, 1, 4));

        self::assertSame(
            '{"drained_at":1000,"delivered":2,"retried":1,"dead_lettered":3,"quarantined":1,"pending":4}',
            new DrainTraceEncoder()->encode($drainTrace),
        );
    }

    public function testTheEmptyPassEncodesItsOwnZeroes(): void
    {
        $drainTrace = DrainTrace::fromOutcome(0, new DrainOutcome(0, 0, 0, 0, 0));

        self::assertSame(
            '{"drained_at":0,"delivered":0,"retried":0,"dead_lettered":0,"quarantined":0,"pending":0}',
            new DrainTraceEncoder()->encode($drainTrace),
        );
    }
}
