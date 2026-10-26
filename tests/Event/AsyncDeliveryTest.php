<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\AsyncDelivery;
use Zef\Framework\Event\DeferralOutcome;
use Zef\Framework\Event\DeliveryAdmission;
use Zef\Framework\Event\DeliveryAttempt;
use Zef\Framework\Event\DeliveryFailure;
use Zef\Framework\Event\DrainOutcome;
use Zef\Framework\Event\DrainPass;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;
use Zef\Framework\Event\FailureVerdict;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerSubscription;
use Zef\Framework\Event\PreparedDelivery;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the deferred regime's orchestrator: admission (the
 * quarantine filter with its honest skip count), the drain fold's
 * per-delivery failure isolation, the retry requeue with its
 * backoff ready-at, the dead letter on exhaustion, the
 * forward-looking quarantine, and the entries deferred during a
 * drain being next-pass material.
 *
 * @internal
 */
#[CoversClass(AsyncDelivery::class)]
#[CoversClass(DeliveryAdmission::class)]
#[CoversClass(DeliveryAttempt::class)]
#[CoversClass(DeliveryFailure::class)]
#[CoversClass(DrainPass::class)]
#[CoversClass(FailureVerdict::class)]
#[CoversClass(DeferralOutcome::class)]
#[CoversClass(DrainOutcome::class)]
final class AsyncDeliveryTest extends TestCase
{
    public function testAQuarantineThresholdBelowOneIsRefused(): void
    {
        try {
            new AsyncDelivery(new RetryPolicy(3, 10), 5, 0);
            self::fail('The zero threshold was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A quarantine threshold is at least one dead letter, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testDeferAdmitsOneEntryPerPoolListenerAndAnswersTheCount(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(3, 10), 5, 3);
        $event = $this->event();

        $outcome = $asyncDelivery->defer(new PreparedDelivery($event, [
            new ListenerSubscription(new RecordingListener(new EventLog(), 'a'), new ListenerPriority(0)),
            new ListenerSubscription(new RecordingListener(new EventLog(), 'b'), new ListenerPriority(0)),
        ]));

        self::assertSame('user.created', $outcome->name()->spelling());
        self::assertSame(2, $outcome->admitted());
        self::assertSame(0, $outcome->skippedQuarantined());
        self::assertSame(2, $asyncDelivery->pendingCount());
    }

    public function testDrainDeliversTheEntriesInTheOrderThePoolWasHanded(): void
    {
        // The engine preserves the pool order verbatim; sorting the pool is
        // the dispatcher's own fold, pinned end-to-end by the facade test.
        $eventLog = new EventLog();
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(3, 10), 5, 3);
        $event = $this->event();

        $asyncDelivery->defer(new PreparedDelivery($event, [
            new ListenerSubscription(new RecordingListener($eventLog, 'late'), new ListenerPriority(0)),
            new ListenerSubscription(new RecordingListener($eventLog, 'early'), new ListenerPriority(10)),
        ]));

        $outcome = $asyncDelivery->drain(1_000);

        self::assertSame(['late', 'early'], $eventLog->tags());
        self::assertSame(2, $outcome->delivered());
        self::assertSame(0, $outcome->retried());
        self::assertSame(0, $outcome->deadLettered());
        self::assertSame(0, $outcome->quarantined());
        self::assertSame(0, $outcome->pending());
    }

    public function testAFailedDeliveryIsRequeuedWithTheBackoffReadyAt(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(3, 10), 5, 3);
        $failingListener = new FailingListener();

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($failingListener, new ListenerPriority(0)),
        ]));

        $first = $asyncDelivery->drain(1_000);
        self::assertSame(0, $first->delivered());
        self::assertSame(1, $first->retried());
        self::assertSame(0, $first->deadLettered());
        self::assertSame(1, $first->pending());
        self::assertSame(1, $failingListener->thrown());

        $tooEarly = $asyncDelivery->drain(1_009);
        self::assertSame(0, $tooEarly->delivered());
        self::assertSame(0, $tooEarly->retried());
        self::assertSame(1, $tooEarly->pending());
        self::assertSame(1, $failingListener->thrown());

        $second = $asyncDelivery->drain(1_010);
        self::assertSame(0, $second->delivered());
        self::assertSame(1, $second->retried());
        self::assertSame(2, $failingListener->thrown());

        $tooEarlyAgain = $asyncDelivery->drain(1_029);
        self::assertSame(0, $tooEarlyAgain->delivered());
        self::assertSame(1, $tooEarlyAgain->pending());
        self::assertSame(2, $failingListener->thrown());

        $third = $asyncDelivery->drain(1_030);
        self::assertSame(0, $third->delivered());
        self::assertSame(0, $third->retried());
        self::assertSame(1, $third->deadLettered());
        self::assertSame(0, $third->pending());
        self::assertSame(3, $failingListener->thrown());
    }

    public function testARetriedEntryNeverStopsTheEntriesBehindIt(): void
    {
        $eventLog = new EventLog();
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(3, 10), 5, 3);

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription(new FailingListener(), new ListenerPriority(10)),
            new ListenerSubscription(new RecordingListener($eventLog, 'survivor'), new ListenerPriority(0)),
        ]));

        $outcome = $asyncDelivery->drain(1_000);

        self::assertSame(1, $outcome->delivered());
        self::assertSame(1, $outcome->retried());
        self::assertSame(1, $outcome->pending());
        self::assertSame(['survivor'], $eventLog->tags());
    }

    public function testOneListenersFailureNeverStopsItsPeers(): void
    {
        $eventLog = new EventLog();
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(1, 10), 5, 3);

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription(new FailingListener(), new ListenerPriority(10)),
            new ListenerSubscription(new RecordingListener($eventLog, 'survivor'), new ListenerPriority(0)),
        ]));

        $outcome = $asyncDelivery->drain(1_000);

        self::assertSame(['survivor'], $eventLog->tags());
        self::assertSame(1, $outcome->delivered());
        self::assertSame(1, $outcome->deadLettered());
        self::assertSame(0, $outcome->pending());
    }

    public function testExhaustionWritesTheWholeDeadLetter(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(2, 10), 5, 3);
        $failingListener = new FailingListener('the listener failed');

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($failingListener, new ListenerPriority(0)),
        ]));
        $asyncDelivery->drain(1_000);

        $outcome = $asyncDelivery->drain(1_010);

        self::assertSame(0, $outcome->retried());
        self::assertSame(1, $outcome->deadLettered());

        $letters = $asyncDelivery->deadLetters();
        self::assertCount(1, $letters);
        self::assertSame('user.created', $letters[0]->eventName()->spelling());
        self::assertSame($failingListener, $letters[0]->eventListener());
        self::assertSame(2, $letters[0]->attempts());
        self::assertSame('RuntimeException', $letters[0]->failureClass());
        self::assertSame('the listener failed', $letters[0]->failureMessage());
        self::assertSame(1_010, $letters[0]->deadAt());
    }

    public function testThePoisonListenerIsQuarantinedAtTheThreshold(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(1, 0), 5, 2);
        $poison = new FailingListener();
        $other = new FailingListener();

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($poison, new ListenerPriority(0)),
        ]));
        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($other, new ListenerPriority(0)),
        ]));
        $firstPass = $asyncDelivery->drain(1_000);

        self::assertSame(2, $firstPass->deadLettered());
        self::assertSame(0, $firstPass->quarantined());
        self::assertFalse($asyncDelivery->isListenerQuarantined($poison));
        self::assertFalse($asyncDelivery->isListenerQuarantined($other));

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($poison, new ListenerPriority(0)),
        ]));
        $secondPass = $asyncDelivery->drain(2_000);

        self::assertSame(1, $secondPass->deadLettered());
        self::assertSame(1, $secondPass->quarantined());
        self::assertTrue($asyncDelivery->isListenerQuarantined($poison));
        self::assertFalse($asyncDelivery->isListenerQuarantined($other));
    }

    public function testAQuarantinedListenerIsSkippedByFutureDefersAndNamedInTheOutcome(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(1, 0), 5, 1);
        $failingListener = new FailingListener();
        $recordingListener = new RecordingListener(new EventLog(), 'healthy');

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($failingListener, new ListenerPriority(0)),
        ]));
        $asyncDelivery->drain(1_000);
        self::assertTrue($asyncDelivery->isListenerQuarantined($failingListener));

        $outcome = $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($failingListener, new ListenerPriority(10)),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        ]));

        self::assertSame(1, $outcome->admitted());
        self::assertSame(1, $outcome->skippedQuarantined());
        self::assertSame(1, $asyncDelivery->pendingCount());
    }

    public function testAlreadyAdmittedEntriesOfAQuarantinedListenerStillRun(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(1, 0), 5, 1);
        $eventLog = new EventLog();
        $failingListener = new FailingListener();
        $recordingListener = new RecordingListener($eventLog, 'recovers');

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($failingListener, new ListenerPriority(0)),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        ]));
        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($failingListener, new ListenerPriority(0)),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        ]));

        $firstPass = $asyncDelivery->drain(1_000);

        self::assertSame(2, $firstPass->delivered());
        self::assertSame(2, $firstPass->deadLettered());
        self::assertSame(1, $firstPass->quarantined());
        self::assertSame(2, $failingListener->thrown());
        self::assertSame(['recovers', 'recovers'], $eventLog->tags());
        self::assertTrue($asyncDelivery->isListenerQuarantined($failingListener));

        $secondPass = $asyncDelivery->drain(2_000);
        self::assertSame(0, $secondPass->delivered());
        self::assertSame(0, $secondPass->pending());
    }

    public function testEntriesDeferredDuringADrainAreNextPassMaterial(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(1, 0), 5, 3);
        $selfDeferringListener = new SelfDeferringListener($asyncDelivery);

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription($selfDeferringListener, new ListenerPriority(0)),
        ]));

        $firstPass = $asyncDelivery->drain(1_000);

        self::assertSame(1, $firstPass->delivered());
        self::assertSame(0, $firstPass->retried());
        self::assertSame(1, $firstPass->pending());

        $secondPass = $asyncDelivery->drain(2_000);
        self::assertSame(1, $secondPass->delivered());
        self::assertSame(0, $secondPass->pending());
    }

    public function testAFreshAdmissionIsDueAtTheVeryFirstDrain(): void
    {
        $eventLog = new EventLog();
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(3, 10), 5, 3);

        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription(new RecordingListener($eventLog, 'x'), new ListenerPriority(0)),
        ]));

        $outcome = $asyncDelivery->drain(0);

        self::assertSame(1, $outcome->delivered());
        self::assertSame(['x'], $eventLog->tags());
    }

    public function testAnEmptyDrainAnswersZeroEverywhere(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(3, 10), 5, 3);

        $outcome = $asyncDelivery->drain(1_000);

        self::assertSame(0, $outcome->delivered());
        self::assertSame(0, $outcome->retried());
        self::assertSame(0, $outcome->deadLettered());
        self::assertSame(0, $outcome->quarantined());
        self::assertSame(0, $outcome->pending());
        self::assertSame([], $asyncDelivery->deadLetters());
    }

    public function testABatchRefusedByCapacityLeavesNoEntriesBehind(): void
    {
        $asyncDelivery = new AsyncDelivery(new RetryPolicy(3, 10), 1, 3);
        $asyncDelivery->defer($this->prepared([
            new ListenerSubscription(new RecordingListener(new EventLog(), 'x'), new ListenerPriority(0)),
        ]));

        try {
            $asyncDelivery->defer($this->prepared([
                new ListenerSubscription(new RecordingListener(new EventLog(), 'y'), new ListenerPriority(0)),
            ]));
            self::fail('The overflowing defer was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery queue is full: capacity 1, occupied 1, demanded'
                . ' 1 -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(1, $asyncDelivery->pendingCount());
    }

    private function event(): Event
    {
        return new Event(new EventName('user.created'), new EventPayload(['userId' => 7]));
    }

    /**
     * @param list<ListenerSubscription> $pool
     */
    private function prepared(array $pool): PreparedDelivery
    {
        return new PreparedDelivery($this->event(), $pool);
    }
}
