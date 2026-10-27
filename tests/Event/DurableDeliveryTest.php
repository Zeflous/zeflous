<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DurableDelivery;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventBook;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerSubscription;
use Zef\Framework\Event\PreparedDelivery;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the durable engine's own seams: the threshold refusal, the
 * composition of defer and drain over the store and resolver the
 * host spelled, the in-process dead-letter book and quarantine the
 * judgment keeps, and the pending-count seam over the store.
 *
 * @internal
 */
#[CoversClass(DurableDelivery::class)]
final class DurableDeliveryTest extends TestCase
{
    public function testAQuarantineThresholdBelowOneIsRefused(): void
    {
        try {
            $this->engine(new InMemoryDeliveryStore(), [], 0);
            self::fail('The zero threshold was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A quarantine threshold is at least one dead letter, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testDeferringAndDrainingOverTheSpelledStoreAndResolver(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $engine = $this->engine($inMemoryDeliveryStore, [$durableRecordingListener]);
        $engine->defer($this->prepared(['amount' => 12], [$durableRecordingListener]));

        self::assertSame(1, $engine->pendingCount());

        $outcome = $engine->drain(100);

        self::assertSame(1, $outcome->delivered());
        self::assertSame(0, $engine->pendingCount());
        self::assertSame([], $engine->deadLetters());
        self::assertSame(['audit'], $eventLog->tags());
    }

    public function testAListenersDeathsAreKeptInTheInProcessBookAndQuarantine(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $durableFailingListener = new DurableFailingListener('app.orders.audit', 'the audit exploded');

        $engine = $this->engine($inMemoryDeliveryStore, [$durableFailingListener], 1, new RetryPolicy(1, 10));
        $engine->defer($this->prepared(['amount' => 12], [$durableFailingListener]));
        $engine->drain(100);

        $deadLetters = $engine->deadLetters();

        self::assertCount(1, $deadLetters);
        self::assertSame('the audit exploded', $deadLetters[0]->failureMessage());
        self::assertSame($durableFailingListener, $deadLetters[0]->eventListener());
        self::assertTrue($engine->isListenerQuarantined($durableFailingListener));
    }

    /**
     * @param list<DurableEventListener> $listeners
     */
    private function engine(
        InMemoryDeliveryStore $inMemoryDeliveryStore,
        array $listeners,
        int $quarantineThreshold = 1,
        ?RetryPolicy $retryPolicy = null,
    ): DurableDelivery {
        $eventBook = new EventBook();
        $eventBook->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));

        $table = [];

        foreach ($listeners as $listener) {
            $table[$listener->listenerIdentity()->spelling()] = $listener;
        }

        return new DurableDelivery(
            $inMemoryDeliveryStore,
            new FixedResolver($table),
            $eventBook,
            $retryPolicy ?? new RetryPolicy(3, 10),
            $quarantineThreshold,
        );
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     * @param list<EventListener>                                    $listeners
     */
    private function prepared(array $values, array $listeners): PreparedDelivery
    {
        $pool = [];

        foreach ($listeners as $listener) {
            $pool[] = new ListenerSubscription(
                $listener,
                new ListenerPriority(0),
            );
        }

        return new PreparedDelivery(
            new Event(
                new EventName('order.paid'),
                new EventPayload($values),
            ),
            $pool,
        );
    }
}
