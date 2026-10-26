<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\AsyncDeliveryQueue;
use Zef\Framework\Event\DeferredDelivery;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;

/**
 * Pins the bounded book: the atomic admission with its
 * backpressure refusal (counts spelled), the due-selection fold's
 * partition and order, the requeue-at-tail discipline, and the
 * capacity construction refusal.
 *
 * @internal
 */
#[CoversClass(AsyncDeliveryQueue::class)]
final class AsyncDeliveryQueueTest extends TestCase
{
    public function testACapacityBelowOneIsRefused(): void
    {
        try {
            new AsyncDeliveryQueue(0);
            self::fail('The zero capacity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A delivery queue's capacity must be at least one, got 0.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAdmitAppendsInBatchOrderAndCounts(): void
    {
        $asyncDeliveryQueue = new AsyncDeliveryQueue(5);
        $first = $this->delivery(0);
        $second = $this->delivery(0);

        $asyncDeliveryQueue->admit([$first, $second]);

        self::assertSame(2, $asyncDeliveryQueue->count());
        self::assertSame([$first, $second], $asyncDeliveryQueue->takeDue(0));
        self::assertSame(0, $asyncDeliveryQueue->count());
    }

    public function testACapacityOfOneHoldsExactlyOneEntry(): void
    {
        $asyncDeliveryQueue = new AsyncDeliveryQueue(1);
        $only = $this->delivery(0);

        $asyncDeliveryQueue->admit([$only]);

        self::assertSame(1, $asyncDeliveryQueue->count());
        self::assertSame([$only], $asyncDeliveryQueue->takeDue(0));
    }

    public function testABatchThatWouldExceedTheCapacityIsRefusedWhole(): void
    {
        $asyncDeliveryQueue = new AsyncDeliveryQueue(2);
        $asyncDeliveryQueue->admit([$this->delivery(0), $this->delivery(0)]);

        try {
            $asyncDeliveryQueue->admit([$this->delivery(0), $this->delivery(0)]);
            self::fail('The overflowing batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery queue is full: capacity 2, occupied 2, demanded'
                . ' 2 -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(2, $asyncDeliveryQueue->count());
    }

    public function testTakeDueAnswersOnlyTheArrivedEntriesAndKeepsTheRestInOrder(): void
    {
        $asyncDeliveryQueue = new AsyncDeliveryQueue(5);
        $ready = $this->delivery(100);
        $early = $this->delivery(50);
        $late = $this->delivery(5_000);

        $asyncDeliveryQueue->admit([$ready, $early, $late]);

        self::assertSame([$ready, $early], $asyncDeliveryQueue->takeDue(100));
        self::assertSame([], $asyncDeliveryQueue->takeDue(200));
        self::assertSame(1, $asyncDeliveryQueue->count());
        self::assertSame([$late], $asyncDeliveryQueue->takeDue(5_000));
        self::assertSame(0, $asyncDeliveryQueue->count());
    }

    public function testTheBoundaryMomentItselfIsDue(): void
    {
        $asyncDeliveryQueue = new AsyncDeliveryQueue(2);
        $boundary = $this->delivery(100);

        $asyncDeliveryQueue->admit([$boundary]);

        self::assertSame([$boundary], $asyncDeliveryQueue->takeDue(100));
    }

    public function testARequeueJoinsTheTailBehindTheSurvivors(): void
    {
        $asyncDeliveryQueue = new AsyncDeliveryQueue(3);
        $survivor = $this->delivery(0);
        $asyncDeliveryQueue->admit([$survivor]);

        $retried = $this->delivery(0);
        $asyncDeliveryQueue->requeue($retried);

        self::assertSame([$survivor, $retried], $asyncDeliveryQueue->takeDue(0));
        self::assertSame(0, $asyncDeliveryQueue->count());
    }

    public function testARequeuedRetryIsNotDueBeforeItsOwnReadyAt(): void
    {
        $asyncDeliveryQueue = new AsyncDeliveryQueue(3);
        $asyncDeliveryQueue->requeue($this->delivery(300));

        self::assertSame([], $asyncDeliveryQueue->takeDue(200));
        self::assertSame(1, $asyncDeliveryQueue->count());
        self::assertSame([], $asyncDeliveryQueue->takeDue(299));
        self::assertSame(1, $asyncDeliveryQueue->count());
    }

    private function delivery(int $readyAt): DeferredDelivery
    {
        return new DeferredDelivery(
            new Event(new EventName('user.created'), new EventPayload(['userId' => 7])),
            new RecordingListener(new EventLog(), 'x'),
            1,
            $readyAt,
        );
    }
}
