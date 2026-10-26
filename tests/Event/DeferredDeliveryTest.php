<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DeferredDelivery;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;

/**
 * Pins the queue's own entry: the readers, the retry's
 * new-entry-with-next-attempt shape, and the two construction
 * refusals (an attempt below one, a negative ready-at) that keep
 * the entry's own arithmetic honest.
 *
 * @internal
 */
#[CoversClass(DeferredDelivery::class)]
final class DeferredDeliveryTest extends TestCase
{
    public function testTheEntryReadsBackWhatItWasBuiltFrom(): void
    {
        $event = new Event(new EventName('user.created'), new EventPayload(['userId' => 7]));
        $recordingListener = new RecordingListener(new EventLog(), 'x');

        $deferredDelivery = new DeferredDelivery($event, $recordingListener, 2, 1_000);

        self::assertSame($event, $deferredDelivery->event());
        self::assertSame($recordingListener, $deferredDelivery->listener());
        self::assertSame(2, $deferredDelivery->attempt());
        self::assertSame(1_000, $deferredDelivery->readyAt());
    }

    public function testTheNextAttemptIsANewEntryOverTheSameEventAndListener(): void
    {
        $event = new Event(new EventName('user.created'), new EventPayload(['userId' => 7]));
        $recordingListener = new RecordingListener(new EventLog(), 'x');
        $deferredDelivery = new DeferredDelivery($event, $recordingListener, 1, 0);

        $retried = $deferredDelivery->withNextAttempt(2, 500);

        self::assertSame($event, $retried->event());
        self::assertSame($recordingListener, $retried->listener());
        self::assertSame(2, $retried->attempt());
        self::assertSame(500, $retried->readyAt());
        self::assertSame(1, $deferredDelivery->attempt());
        self::assertSame(0, $deferredDelivery->readyAt());
    }

    public function testAnAttemptBelowOneIsRefused(): void
    {
        try {
            new DeferredDelivery(
                new Event(new EventName('user.created'), new EventPayload(['userId' => 7])),
                new RecordingListener(new EventLog(), 'x'),
                0,
                0,
            );
            self::fail('The zero attempt was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A deferred delivery stands at an attempt number of at least'
                . ' one, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANegativeReadyAtIsRefused(): void
    {
        try {
            new DeferredDelivery(
                new Event(new EventName('user.created'), new EventPayload(['userId' => 7])),
                new RecordingListener(new EventLog(), 'x'),
                1,
                -1,
            );
            self::fail('The negative ready-at was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A deferred delivery's ready-at moment cannot be negative,"
                . ' got -1.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheFrozenPayloadTravelsWithTheEntry(): void
    {
        $deferredDelivery = new DeferredDelivery(
            new Event(new EventName('user.created'), new EventPayload(['userId' => 7])),
            new RecordingListener(new EventLog(), 'x'),
            1,
            0,
        );

        self::assertSame('user.created', $deferredDelivery->event()->name()->spelling());
        self::assertSame(7, $deferredDelivery->event()->payload()->value('userId'));
    }
}
