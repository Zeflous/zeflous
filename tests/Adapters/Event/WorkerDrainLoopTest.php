<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Event\WorkerDrainLoop;
use Zef\Framework\Adapters\Event\WorkerDrainOutcome;
use Zef\Framework\Adapters\Event\WorkerDrainSentinel;
use Zef\Framework\Event\DurableEventDispatcher;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\RetryPolicy;
use Zef\Test\Event\DurableFailingListener;
use Zef\Test\Event\DurableRecordingListener;
use Zef\Test\Event\EventLog;
use Zef\Test\Event\FixedResolver;
use Zef\Test\Event\InMemoryDeliveryStore;

/**
 * Pins the worker drain loop's discipline end to end, over the
 * REAL durable facade composition a deployment rides (the drain
 * arm is never doubled -- the loop is the discipline around the
 * facade's own drain verb, so every test drains the real regime):
 * one pass per handed now, the schedule's own ending as one exit,
 * the sentinel read at every iteration boundary as the other --
 * armed before the first ask, armed while the schedule was
 * handing (a real now, and the schedule's own null ending), armed
 * mid-pass by a listener -- each observed by what actually
 * happened (the deliveries, the attempts, the asks, the store's
 * own pending count), never by an outcome count alone.
 *
 * @internal
 */
#[CoversClass(WorkerDrainLoop::class)]
#[CoversClass(WorkerDrainSentinel::class)]
#[CoversClass(WorkerDrainOutcome::class)]
final class WorkerDrainLoopTest extends TestCase
{
    public function testTheLoopDrainsOnePassPerHandedNowAndEndsWithTheSchedule(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $scriptedDrainSchedule = new ScriptedDrainSchedule([100, 200]);
        $workerDrainLoop = new WorkerDrainLoop($durable, new WorkerDrainSentinel());

        $outcome = $workerDrainLoop->run($scriptedDrainSchedule);

        self::assertSame(2, $outcome->passes());
        self::assertFalse($outcome->stoppedBySentinel());
        self::assertSame(2, $scriptedDrainSchedule->asks());
        self::assertSame(['audit'], $eventLog->tags());
        self::assertCount(1, $durableRecordingListener->received());
        self::assertNotNull($outcome->lastOutcome());
        self::assertSame(0, $outcome->lastOutcome()->delivered());
        self::assertSame(0, $outcome->lastOutcome()->pending());
    }

    public function testTheHandedNowsReachTheDrainsOwnArithmetic(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $durableFailingListener = new DurableFailingListener('app.orders.broken');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$durableFailingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $workerDrainLoop = new WorkerDrainLoop($durable, new WorkerDrainSentinel());

        $outcome = $workerDrainLoop->run(new ScriptedDrainSchedule([100, 105, 120]));

        self::assertSame(3, $outcome->passes());
        self::assertSame(2, $durableFailingListener->thrown());
        self::assertNotNull($outcome->lastOutcome());
        self::assertSame(0, $outcome->lastOutcome()->delivered());
        self::assertSame(1, $outcome->lastOutcome()->retried());
        self::assertSame(1, $outcome->lastOutcome()->pending());
    }

    public function testAnArmedSentinelBeforeTheRunNeverAsksTheSchedule(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $scriptedDrainSchedule = new ScriptedDrainSchedule([100, 200]);
        $workerDrainSentinel = new WorkerDrainSentinel();
        $workerDrainSentinel->arm();

        $outcome = new WorkerDrainLoop($durable, $workerDrainSentinel)->run($scriptedDrainSchedule);

        self::assertSame(0, $outcome->passes());
        self::assertTrue($outcome->stoppedBySentinel());
        self::assertNull($outcome->lastOutcome());
        self::assertSame(0, $scriptedDrainSchedule->asks());
        self::assertSame([], $eventLog->tags());
        self::assertSame(1, $inMemoryDeliveryStore->pendingCount());
    }

    public function testAStopArrivingDuringTheHandedAskIsObservedAtTheNextBoundary(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $workerDrainSentinel = new WorkerDrainSentinel();
        $sentinelArmingSchedule = new SentinelArmingSchedule($workerDrainSentinel, 100);

        $outcome = new WorkerDrainLoop($durable, $workerDrainSentinel)->run($sentinelArmingSchedule);

        self::assertSame(1, $outcome->passes());
        self::assertTrue($outcome->stoppedBySentinel());
        self::assertSame(1, $sentinelArmingSchedule->asks());
        self::assertSame(['audit'], $eventLog->tags());
        self::assertSame(0, $inMemoryDeliveryStore->pendingCount());
        self::assertNotNull($outcome->lastOutcome());
        self::assertSame(1, $outcome->lastOutcome()->delivered());
    }

    public function testAStopArrivingInsideTheSchedulesOwnEndingIsStillTheHostsStop(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $workerDrainSentinel = new WorkerDrainSentinel();
        $sentinelArmingSchedule = new SentinelArmingSchedule($workerDrainSentinel, null);

        $outcome = new WorkerDrainLoop($durable, $workerDrainSentinel)->run($sentinelArmingSchedule);

        self::assertSame(0, $outcome->passes());
        self::assertTrue($outcome->stoppedBySentinel());
        self::assertNull($outcome->lastOutcome());
        self::assertSame(1, $sentinelArmingSchedule->asks());
        self::assertSame([], $eventLog->tags());
        self::assertSame(1, $inMemoryDeliveryStore->pendingCount());
    }

    public function testAStopArmedMidPassEndsThePassNeverThePassItself(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $workerDrainSentinel = new WorkerDrainSentinel();
        $sentinelArmingListener = new SentinelArmingListener(
            $eventLog,
            $workerDrainSentinel,
            'arming',
            'app.orders.arming',
        );
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'recorder', 'app.orders.recorder');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$sentinelArmingListener, $durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $scriptedDrainSchedule = new ScriptedDrainSchedule([100, 200, 300]);

        $outcome = new WorkerDrainLoop($durable, $workerDrainSentinel)->run($scriptedDrainSchedule);

        self::assertSame(1, $outcome->passes());
        self::assertTrue($outcome->stoppedBySentinel());
        self::assertSame(1, $scriptedDrainSchedule->asks());

        $tags = $eventLog->tags();
        sort($tags);

        self::assertSame(['arming', 'recorder'], $tags);
        self::assertNotNull($outcome->lastOutcome());
        self::assertSame(2, $outcome->lastOutcome()->delivered());
        self::assertSame(0, $outcome->lastOutcome()->pending());
    }

    public function testAScheduleWithNothingToHandIsAZeroPassRun(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $outcome = new WorkerDrainLoop($durable, new WorkerDrainSentinel())
            ->run(new ScriptedDrainSchedule([]))
        ;

        self::assertSame(0, $outcome->passes());
        self::assertFalse($outcome->stoppedBySentinel());
        self::assertNull($outcome->lastOutcome());
        self::assertSame([], $eventLog->tags());
        self::assertSame(1, $inMemoryDeliveryStore->pendingCount());
    }

    public function testEachRunIsItsOwnDiscipline(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $durable = $this->durableDelivery($inMemoryDeliveryStore, [$durableRecordingListener]);
        $durable->defer(new EventName('order.paid'), ['amount' => 12]);

        $workerDrainLoop = new WorkerDrainLoop($durable, new WorkerDrainSentinel());

        $first = $workerDrainLoop->run(new ScriptedDrainSchedule([100]));
        $second = $workerDrainLoop->run(new ScriptedDrainSchedule([200]));

        self::assertSame(1, $first->passes());
        self::assertSame(['audit'], $eventLog->tags());
        self::assertSame(1, $second->passes());
        self::assertNotNull($second->lastOutcome());
        self::assertSame(0, $second->lastOutcome()->delivered());
    }

    /**
     * The durable facade a deployment holds: the dispatcher's own
     * one-way composition over the in-memory store stand-in, the
     * resolver table this test subscribed, the retry policy and
     * the quarantine threshold the existing durable suite pins.
     *
     * @param list<DurableEventListener> $listeners
     */
    private function durableDelivery(
        InMemoryDeliveryStore $inMemoryDeliveryStore,
        array $listeners,
    ): DurableEventDispatcher {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));

        $table = [];

        foreach ($listeners as $listener) {
            $eventDispatcher->subscribe(new EventName('order.paid'), $listener, new ListenerPriority(0));
            $table[$listener->listenerIdentity()->spelling()] = $listener;
        }

        return $eventDispatcher->durableDelivery(
            $inMemoryDeliveryStore,
            new FixedResolver($table),
            new RetryPolicy(3, 10),
            1,
        );
    }
}
