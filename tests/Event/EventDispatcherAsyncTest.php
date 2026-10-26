<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\AsyncDelivery;
use Zef\Framework\Event\DeferralOutcome;
use Zef\Framework\Event\DeferredEventDispatcher;
use Zef\Framework\Event\DeliveryPlan;
use Zef\Framework\Event\DrainOutcome;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPattern;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\PreparedDelivery;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the deferred vocabulary end-to-end over the composed facade:
 * the same fail-closed boundaries dispatch answers (undefined name,
 * mis-shaped payload -- refused at the CALL, not at a drain), the
 * pool snapshot semantics (a late subscriber never joins, an
 * unsubscribe never retracts), the wildcard pool admitted by defer,
 * the one-way composition (a dispatcher that was never asked for
 * the deferred pair has no deferred verbs to call), and the sync
 * dispatch staying byte-identical beside the deferred arm.
 *
 * @internal
 */
#[CoversClass(EventDispatcher::class)]
#[CoversClass(DeferredEventDispatcher::class)]
#[CoversClass(DeliveryPlan::class)]
#[CoversClass(PreparedDelivery::class)]
#[CoversClass(DeferralOutcome::class)]
#[CoversClass(DrainOutcome::class)]
final class EventDispatcherAsyncTest extends TestCase
{
    public function testDeferAdmitsThePoolAndADrainDeliversIt(): void
    {
        [$deferredEventDispatcher, $eventDispatcher] = $this->asyncFacade();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'late'),
            new ListenerPriority(0),
        );
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'early'),
            new ListenerPriority(10),
        );

        $deferralOutcome = $deferredEventDispatcher->defer($eventName, ['userId' => 7]);
        self::assertSame('user.created', $deferralOutcome->name()->spelling());
        self::assertSame(2, $deferralOutcome->admitted());
        self::assertSame(0, $deferralOutcome->skippedQuarantined());
        self::assertSame([], $eventLog->tags());

        $drainOutcome = $deferredEventDispatcher->drain(1_000);
        self::assertSame(2, $drainOutcome->delivered());
        self::assertSame(['early', 'late'], $eventLog->tags());
    }

    public function testDeferringAnUndefinedEventIsRefusedAtTheCall(): void
    {
        [$deferredEventDispatcher] = $this->asyncFacade();

        try {
            $deferredEventDispatcher->defer(new EventName('user.created'), ['userId' => 7]);
            self::fail('The undefined-name defer was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'user.created' is not defined; dispatch refuses an"
                . ' undefined name so a typo cannot silently deliver nothing.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMisShapedPayloadIsRefusedAtTheDeferBoundary(): void
    {
        [$deferredEventDispatcher, $eventDispatcher] = $this->asyncFacade();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'x'),
            new ListenerPriority(0),
        );

        try {
            $deferredEventDispatcher->defer($eventName, ['userId' => 'seven']);
            self::fail('The mis-shaped payload was not refused at defer.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload field 'userId' must answer int, got string.",
                $invalidArgumentException->getMessage(),
            );
        }

        $drainOutcome = $deferredEventDispatcher->drain(1_000);
        self::assertSame(0, $drainOutcome->delivered());
        self::assertSame(0, $drainOutcome->pending());
        self::assertSame([], $eventLog->tags());
    }

    public function testThePoolSnapshotIsTheMessage(): void
    {
        [$deferredEventDispatcher, $eventDispatcher] = $this->asyncFacade();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');
        $recordingListener = new RecordingListener($eventLog, 'subscribed');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, $recordingListener, new ListenerPriority(0));

        $deferredEventDispatcher->defer($eventName, ['userId' => 7]);

        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'late'),
            new ListenerPriority(0),
        );
        $eventDispatcher->unsubscribe($eventName, $recordingListener);

        $drainOutcome = $deferredEventDispatcher->drain(1_000);

        self::assertSame(1, $drainOutcome->delivered());
        self::assertSame(['subscribed'], $eventLog->tags());
    }

    public function testThePoolSortsExactAndWildcardTogetherByPriority(): void
    {
        // The registry's own ordered() walk already sorts the exact
        // subscriptions; the plan's sort is the MERGE over the union --
        // observable only when an exact and a wildcard listener
        // interleave, so the pin crosses the two books on purpose.
        [$deferredEventDispatcher, $eventDispatcher] = $this->asyncFacade();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'wildcard-observer'),
            new ListenerPriority(10),
        );
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'exact-target'),
            new ListenerPriority(0),
        );

        $deferredEventDispatcher->defer($eventName, ['userId' => 7]);
        $deferredEventDispatcher->drain(1_000);

        self::assertSame(['wildcard-observer', 'exact-target'], $eventLog->tags());
    }

    public function testAWildcardListenerIsAdmittedByDeferAndDelivered(): void
    {
        [$deferredEventDispatcher, $eventDispatcher] = $this->asyncFacade();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'wildcard'),
            new ListenerPriority(0),
        );

        $deferredEventDispatcher->defer($eventName, ['userId' => 7]);
        $drainOutcome = $deferredEventDispatcher->drain(1_000);

        self::assertSame(1, $drainOutcome->delivered());
        self::assertSame(['wildcard'], $eventLog->tags());
    }

    public function testASyncOnlyDispatcherHasNoDeferredVerbsToCall(): void
    {
        // The sync-only shape is structural: the deferred vocabulary
        // exists only as the composed facade a host asks for with an
        // engine, so a plain dispatcher's own surface is the six sync
        // verbs -- nothing to refuse, nothing to fail closed.
        $eventDispatcher = new EventDispatcher();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $vocabulary = get_class_methods($eventDispatcher);

        self::assertNotContains('defer', $vocabulary);
        self::assertNotContains('drain', $vocabulary);
        self::assertContains('deferredDelivery', $vocabulary);
    }

    public function testTheComposedFacadeSharesTheDispatcherPlan(): void
    {
        [$firstFacade, $eventDispatcher] = $this->asyncFacade();
        $secondFacade = $eventDispatcher->deferredDelivery(
            new AsyncDelivery(new RetryPolicy(1, 0), 100, 3),
        );
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'shared'),
            new ListenerPriority(0),
        );

        $firstFacade->defer($eventName, ['userId' => 7]);
        $secondFacade->defer($eventName, ['userId' => 8]);

        self::assertSame(1, $firstFacade->drain(1_000)->delivered());
        self::assertSame(1, $secondFacade->drain(1_000)->delivered());
        self::assertSame(['shared', 'shared'], $eventLog->tags());
    }

    public function testASyncDispatchIsUnchangedBesideTheDeferredArm(): void
    {
        [$deferredEventDispatcher, $eventDispatcher] = $this->asyncFacade();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'sync'),
            new ListenerPriority(0),
        );

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 7]);

        self::assertSame(1, $outcome->deliveredTo());
        self::assertSame(['sync'], $eventLog->tags());
        self::assertSame(0, $deferredEventDispatcher->drain(1_000)->pending());
    }

    public function testTheFullRegimeRunsThroughTheFacade(): void
    {
        [$deferredEventDispatcher, $eventDispatcher] = $this->asyncFacade();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');
        $failingListener = new FailingListener();

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, $failingListener, new ListenerPriority(0));
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'healthy'),
            new ListenerPriority(0),
        );

        $deferredEventDispatcher->defer($eventName, ['userId' => 7]);

        $firstPass = $deferredEventDispatcher->drain(1_000);
        self::assertSame(1, $firstPass->delivered());
        self::assertSame(1, $firstPass->retried());
        self::assertSame(1, $firstPass->pending());

        $secondPass = $deferredEventDispatcher->drain(1_010);
        self::assertSame(1, $secondPass->retried());

        $thirdPass = $deferredEventDispatcher->drain(1_030);
        self::assertSame(1, $thirdPass->deadLettered());
        self::assertSame(0, $thirdPass->pending());
        self::assertSame(3, $failingListener->thrown());
        self::assertSame(['healthy'], $eventLog->tags());
    }

    /**
     * @return array{DeferredEventDispatcher, EventDispatcher}
     */
    private function asyncFacade(): array
    {
        $eventDispatcher = new EventDispatcher();
        $deferredEventDispatcher = $eventDispatcher->deferredDelivery(
            new AsyncDelivery(new RetryPolicy(3, 10), 100, 3),
        );

        return [$deferredEventDispatcher, $eventDispatcher];
    }
}
