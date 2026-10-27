<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DurableEventDispatcher;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the durable facade end to end, through the composition
 * seam the dispatcher owns: the defer/drain pair over the spelled
 * store and resolver, the pool snapshot (a late subscriber never
 * joins a deferred delivery), the producer's boundary refusals,
 * and the drain-snapshot doctrine (an entry saved during a drain
 * is next-pass material).
 *
 * @internal
 */
#[CoversClass(DurableEventDispatcher::class)]
#[CoversClass(EventDispatcher::class)]
final class DurableEventDispatcherTest extends TestCase
{
    public function testADeferredEventIsDrainedToItsDurableListener(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        [, $dispatcher] = $this->dispatcher($inMemoryDeliveryStore, [$durableRecordingListener]);
        $outcome = $dispatcher->defer(new EventName('order.paid'), ['amount' => 12]);

        self::assertSame('order.paid', $outcome->name()->spelling());
        self::assertSame(1, $outcome->admitted());

        $drainOutcome = $dispatcher->drain(100);

        self::assertSame(1, $drainOutcome->delivered());
        self::assertSame(['audit'], $eventLog->tags());
        self::assertCount(1, $durableRecordingListener->received());
        self::assertSame(12, $durableRecordingListener->received()[0]->payload()->value('amount'));
    }

    public function testALateSubscriberNeverJoinsADeferredDelivery(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $audit = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');
        $latecomer = new DurableRecordingListener($eventLog, 'late', 'app.orders.late');

        [$dispatcher, $durable] = $this->dispatcher($inMemoryDeliveryStore, [$audit, $latecomer]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $dispatcher->subscribe(
            new EventName('order.paid'),
            new DurableRecordingListener($eventLog, 'newcomer', 'app.orders.newcomer'),
            new ListenerPriority(0),
        );

        $outcome = $durable->drain(100);

        self::assertSame(2, $outcome->delivered());
        $tags = $eventLog->tags();
        sort($tags);
        self::assertSame(['audit', 'late'], $tags);
    }

    public function testAnUnsubscribeAfterTheDeferDoesNotRetractIt(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        [$dispatcher, $durable] = $this->dispatcher($inMemoryDeliveryStore, [$durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $dispatcher->unsubscribe(new EventName('order.paid'), $durableRecordingListener);

        $outcome = $durable->drain(100);

        self::assertSame(1, $outcome->delivered());
        self::assertSame(['audit'], $eventLog->tags());
    }

    public function testAPoolListenerWithoutADurableIdentityIsRefusedAtTheDefer(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));
        $eventDispatcher->subscribe(
            new EventName('order.paid'),
            new RecordingListener($eventLog, 'anonymous'),
            new ListenerPriority(0),
        );

        $durable = $eventDispatcher->durableDelivery(
            $inMemoryDeliveryStore,
            new FixedResolver([]),
            new RetryPolicy(3, 10),
            1,
        );

        try {
            $durable->defer(new EventName('order.paid'), ['amount' => 12]);
            self::fail('The anonymous pool listener was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery pool carries a listener without a durable'
                . ' identity: Zef\Test\Event\RecordingListener -- defer it'
                . ' in-process, or spell its identity, but a durable defer'
                . ' can never promise a delivery no later process could'
                . ' resolve.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $inMemoryDeliveryStore->pendingCount());
    }

    public function testAMisShapedPayloadIsRefusedAtTheDefer(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        [, $dispatcher] = $this->dispatcher($inMemoryDeliveryStore, [$durableRecordingListener]);

        try {
            $dispatcher->defer(new EventName('order.paid'), ['amount' => 'twelve']);
            self::fail('The mis-shaped payload was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload field 'amount' must answer int, got string.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $inMemoryDeliveryStore->pendingCount());
    }

    public function testAnUndefinedEventIsRefusedAtTheDefer(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        [, $dispatcher] = $this->dispatcher($inMemoryDeliveryStore, []);

        try {
            $dispatcher->defer(new EventName('order.unknown'), ['amount' => 12]);
            self::fail('The undefined event was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'order.unknown' is not defined; dispatch refuses an"
                . ' undefined name so a typo cannot silently deliver nothing.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEntrySavedDuringADrainIsNextPassMaterial(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $storeWritingListener = new StoreWritingListener(
            'app.orders.feeding',
            $inMemoryDeliveryStore,
            'app.orders.audit',
        );
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        [, $dispatcher] = $this->dispatcher($inMemoryDeliveryStore, [$storeWritingListener, $durableRecordingListener]);
        $dispatcher->defer(new EventName('order.paid'), ['amount' => 12]);

        $firstPass = $dispatcher->drain(100);

        self::assertSame(2, $firstPass->delivered());
        self::assertSame(1, $firstPass->pending());

        $secondPass = $dispatcher->drain(100);

        self::assertSame(1, $secondPass->delivered());
        self::assertSame(0, $secondPass->pending());

        self::assertSame(['audit', 'audit'], $eventLog->tags());
    }

    public function testTheBackpressureRefusalNamesTheCounts(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore(1);
        $eventLog = new EventLog();
        $audit = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');
        $billing = new DurableRecordingListener($eventLog, 'billing', 'app.orders.billing');

        [, $dispatcher] = $this->dispatcher($inMemoryDeliveryStore, [$audit, $billing]);

        try {
            $dispatcher->defer(new EventName('order.paid'), ['amount' => 12]);
            self::fail('The overflowing batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store is full: capacity 1, occupied 0, demanded'
                . ' 2 -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    /**
     * @param list<DurableEventListener> $listeners
     *
     * @return array{EventDispatcher, DurableEventDispatcher}
     */
    private function dispatcher(
        DeferredDeliveryStore $deferredDeliveryStore,
        array $listeners,
    ): array {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));

        $table = [];

        foreach ($listeners as $listener) {
            $eventDispatcher->subscribe(new EventName('order.paid'), $listener, new ListenerPriority(0));
            $table[$listener->listenerIdentity()->spelling()] = $listener;
        }

        return [
            $eventDispatcher,
            $eventDispatcher->durableDelivery(
                $deferredDeliveryStore,
                new FixedResolver($table),
                new RetryPolicy(3, 10),
                1,
            ),
        ];
    }
}
