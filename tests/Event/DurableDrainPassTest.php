<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DeadLetterBook;
use Zef\Framework\Event\DeliveryAttempt;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryFailure;
use Zef\Framework\Event\DurableDrainPass;
use Zef\Framework\Event\DurableEntrySoundness;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\DurableSettlement;
use Zef\Framework\Event\DurableVerdictAnswer;
use Zef\Framework\Event\EventBook;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Framework\Event\ListenerQuarantine;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the durable drain pass: the delivery and settle, the
 * retry's write-the-answer-then-settle-the-question ORDER, the
 * dead letter's three causes (listener failure with the real
 * class, the unresolved identity, the stale shape), the
 * quarantine act, the due ordering, and the counts the outcome
 * answers with.
 *
 * @internal
 */
#[CoversClass(DurableDrainPass::class)]
final class DurableDrainPassTest extends TestCase
{
    public function testADueEnvelopeIsDeliveredAndSettled(): void
    {
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$durableRecordingListener]);
        $inMemoryDeliveryStore->save($this->envelope('app.orders.audit', ['amount' => 12]));

        $outcome = $pass->run(100);

        self::assertSame(1, $outcome->delivered());
        self::assertSame(0, $outcome->retried());
        self::assertSame(0, $outcome->deadLettered());
        self::assertSame(0, $outcome->quarantined());
        self::assertSame(0, $outcome->pending());
        self::assertSame(['audit'], $eventLog->tags());
        self::assertCount(1, $durableRecordingListener->received());
        self::assertSame(12, $durableRecordingListener->received()[0]->payload()->value('amount'));
    }

    public function testANotYetDueEnvelopeIsNotTaken(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, []);
        $inMemoryDeliveryStore->save($this->envelope('app.orders.audit', ['amount' => 12], 1, 200));

        $outcome = $pass->run(100);

        self::assertSame(0, $outcome->delivered());
        self::assertSame(1, $outcome->pending());
    }

    public function testAFailedDeliveryIsRequeuedBeforeTheSpentEntrySettles(): void
    {
        $durableFailingListener = new DurableFailingListener('app.orders.audit');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$durableFailingListener]);
        $envelope = $this->envelope('app.orders.audit', ['amount' => 12]);
        $inMemoryDeliveryStore->save($envelope);

        $outcome = $pass->run(100);

        self::assertSame(0, $outcome->delivered());
        self::assertSame(1, $outcome->retried());
        self::assertSame(1, $outcome->pending());
        self::assertSame(1, $durableFailingListener->thrown());

        $retry = array_values($inMemoryDeliveryStore->pending())[0];

        self::assertSame(2, $retry->attempt());
        self::assertSame(110, $retry->readyAt());
        self::assertNotSame($envelope->entryId()->spelling(), $retry->entryId()->spelling());

        $requeueIndex = $this->actionIndex($inMemoryDeliveryStore, 'requeue:');
        $settleIndex = $this->actionIndex($inMemoryDeliveryStore, 'settle:' . $envelope->entryId()->spelling());

        self::assertGreaterThan($requeueIndex, $settleIndex);
    }

    public function testAnExhaustedDeliveryIsDeadLetteredWithTheRealClassAndSettled(): void
    {
        $durableFailingListener = new DurableFailingListener('app.orders.audit', 'the audit exploded');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$durableFailingListener], new RetryPolicy(1, 10));
        $envelope = $this->envelope('app.orders.audit', ['amount' => 12]);
        $inMemoryDeliveryStore->save($envelope);

        $outcome = $pass->run(100);

        self::assertSame(0, $outcome->retried());
        self::assertSame(1, $outcome->deadLettered());
        self::assertSame(1, $outcome->quarantined());
        self::assertSame(0, $outcome->pending());

        $deadIndex = $this->actionIndex(
            $inMemoryDeliveryStore,
            'dead:' . $envelope->entryId()->spelling()
                . ':RuntimeException:the audit exploded:100',
        );
        $settleIndex = $this->actionIndex($inMemoryDeliveryStore, 'settle:' . $envelope->entryId()->spelling());

        self::assertGreaterThan($deadIndex, $settleIndex);
    }

    public function testAnIdentityNoListenerResolvesToIsDeadLetteredUnresolvedAndSettled(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, []);
        $envelope = $this->envelope('app.orders.retired', ['amount' => 12]);
        $inMemoryDeliveryStore->save($envelope);

        $outcome = $pass->run(100);

        self::assertSame(0, $outcome->delivered());
        self::assertSame(1, $outcome->deadLettered());
        self::assertSame(0, $outcome->quarantined());
        self::assertSame(0, $outcome->pending());

        self::assertContains('dead:' . $envelope->entryId()->spelling()
            . ':unresolved-listener:No listener answers the listener'
            . " identity 'app.orders.retired'.:100", $inMemoryDeliveryStore->actions());
    }

    public function testARecordWhoseEventIsNoLongerDefinedIsDeadLetteredStale(): void
    {
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$durableRecordingListener]);
        $envelope = $this->envelope('app.orders.audit', ['amount' => 12], 1, 0, 'order.cancelled');
        $inMemoryDeliveryStore->save($envelope);

        $outcome = $pass->run(100);

        self::assertSame(0, $outcome->delivered());
        self::assertSame(1, $outcome->deadLettered());
        self::assertSame(0, $outcome->quarantined());
        self::assertSame(0, $outcome->pending());
        self::assertSame([], $eventLog->tags());

        self::assertContains('dead:' . $envelope->entryId()->spelling()
            . ":stale-shape:The event 'order.cancelled' is not defined;"
            . ' dispatch refuses an undefined name so a typo cannot'
            . ' silently deliver nothing.:100', $inMemoryDeliveryStore->actions());
    }

    public function testARecordWhosePayloadNoLongerAnswersTheShapeIsDeadLetteredStale(): void
    {
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$durableRecordingListener]);
        $envelope = $this->envelope('app.orders.audit', ['amount' => 'twelve']);
        $inMemoryDeliveryStore->save($envelope);

        $outcome = $pass->run(100);

        self::assertSame(0, $outcome->delivered());
        self::assertSame(1, $outcome->deadLettered());
        self::assertSame(0, $outcome->quarantined());
        self::assertSame([], $eventLog->tags());

        self::assertContains('dead:' . $envelope->entryId()->spelling()
            . ":stale-shape:The payload field 'amount' must answer int,"
            . ' got string.:100', $inMemoryDeliveryStore->actions());
    }

    public function testDueEnvelopesAreDeliveredInReadyAtOrder(): void
    {
        $eventLog = new EventLog();
        $early = new DurableRecordingListener($eventLog, 'early', 'app.orders.early');
        $late = new DurableRecordingListener($eventLog, 'late', 'app.orders.late');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$early, $late]);
        $inMemoryDeliveryStore->save($this->envelope('app.orders.late', ['amount' => 12], 1, 50));
        $inMemoryDeliveryStore->save($this->envelope('app.orders.early', ['amount' => 12], 1, 10));

        $outcome = $pass->run(100);

        self::assertSame(2, $outcome->delivered());
        self::assertSame(['early', 'late'], $eventLog->tags());
    }

    public function testAnUnsoundEntryDoesNotStopTheEntriesBehindIt(): void
    {
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$durableRecordingListener]);
        $inMemoryDeliveryStore->save($this->envelope('app.orders.retired', ['amount' => 1], 1, 0));
        $inMemoryDeliveryStore->save($this->envelope('app.orders.audit', ['amount' => 2], 1, 1));

        $outcome = $pass->run(2);

        self::assertSame(1, $outcome->delivered());
        self::assertSame(1, $outcome->deadLettered());
        self::assertSame(0, $outcome->pending());
        self::assertSame(['audit'], $eventLog->tags());
    }

    public function testARetriedEntryDoesNotStopTheEntriesBehindIt(): void
    {
        $eventLog = new EventLog();
        $durableFailingListener = new DurableFailingListener('app.orders.broken');
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $pass = $this->pass($inMemoryDeliveryStore, [$durableFailingListener, $durableRecordingListener]);
        $inMemoryDeliveryStore->save($this->envelope('app.orders.broken', ['amount' => 1], 1, 0));
        $inMemoryDeliveryStore->save($this->envelope('app.orders.audit', ['amount' => 2], 1, 1));

        $outcome = $pass->run(2);

        self::assertSame(1, $outcome->retried());
        self::assertSame(1, $outcome->delivered());
        self::assertSame(1, $outcome->pending());
        self::assertSame(['audit'], $eventLog->tags());
    }

    public function testAnEntryTakenButNotSettledIsReTakenByTheNextPass(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $inMemoryDeliveryStore->save($this->envelope('app.orders.audit', ['amount' => 12]));

        $due = $inMemoryDeliveryStore->takeDue(100);
        $dueAgain = $inMemoryDeliveryStore->takeDue(100);

        self::assertSame($due, $dueAgain);
    }

    /**
     * @param list<DurableEventListener> $listeners
     */
    private function pass(
        InMemoryDeliveryStore $inMemoryDeliveryStore,
        array $listeners,
        ?RetryPolicy $retryPolicy = null,
    ): DurableDrainPass {
        $eventBook = new EventBook();
        $eventBook->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));

        $deadLetterBook = new DeadLetterBook();
        $listenerQuarantine = new ListenerQuarantine();

        $table = [];

        foreach ($listeners as $listener) {
            $table[$listener->listenerIdentity()->spelling()] = $listener;
        }

        $durableSettlement = new DurableSettlement($inMemoryDeliveryStore);

        return new DurableDrainPass(
            $inMemoryDeliveryStore,
            new DurableEntrySoundness(new FixedResolver($table), $eventBook, $durableSettlement),
            new DeliveryAttempt(),
            new DeliveryFailure(
                $retryPolicy ?? new RetryPolicy(3, 10),
                $deadLetterBook,
                $listenerQuarantine,
                1,
            ),
            new DurableVerdictAnswer($deadLetterBook, $listenerQuarantine, $durableSettlement),
            $durableSettlement,
        );
    }

    private function actionIndex(InMemoryDeliveryStore $inMemoryDeliveryStore, string $prefix): int
    {
        foreach ($inMemoryDeliveryStore->actions() as $index => $action) {
            if (str_starts_with($action, $prefix)) {
                return $index;
            }
        }

        self::fail("The action '{$prefix}' was never recorded.");
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     */
    private function envelope(
        string $identity,
        array $values,
        int $attempt = 1,
        int $readyAt = 0,
        string $eventName = 'order.paid',
    ): DeliveryEnvelope {
        return new DeliveryEnvelope(
            DeliveryEntryId::mint(),
            new EventName($eventName),
            $values,
            new ListenerIdentity($identity),
            $attempt,
            $readyAt,
        );
    }
}
