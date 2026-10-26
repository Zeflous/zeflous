<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\AsyncDelivery;
use Zef\Framework\Event\DeferralOutcome;
use Zef\Framework\Event\DrainOutcome;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPattern;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the deferred pair end-to-end over the dispatcher's own
 * facade: the same fail-closed boundaries dispatch answers
 * (undefined name, mis-shaped payload -- refused at the CALL, not
 * at a drain), the pool snapshot semantics (a late subscriber
 * never joins, an unsubscribe never retracts), the wildcard pool
 * admitted by defer, and the sync-only construction's honest
 * refusal of the deferred verbs.
 *
 * @internal
 */
#[CoversClass(EventDispatcher::class)]
#[CoversClass(DeferralOutcome::class)]
#[CoversClass(DrainOutcome::class)]
final class EventDispatcherAsyncTest extends TestCase
{
    public function testDeferAdmitsThePoolAndADrainDeliversIt(): void
    {
        $eventLog = new EventLog();
        $eventDispatcher = $this->asyncDispatcher();
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

        $deferralOutcome = $eventDispatcher->defer($eventName, ['userId' => 7]);
        self::assertSame('user.created', $deferralOutcome->name()->spelling());
        self::assertSame(2, $deferralOutcome->admitted());
        self::assertSame(0, $deferralOutcome->skippedQuarantined());
        self::assertSame([], $eventLog->tags());

        $drainOutcome = $eventDispatcher->drain(1_000);
        self::assertSame(2, $drainOutcome->delivered());
        self::assertSame(['early', 'late'], $eventLog->tags());
    }

    public function testDeferringAnUndefinedEventIsRefusedAtTheCall(): void
    {
        $eventDispatcher = $this->asyncDispatcher();

        try {
            $eventDispatcher->defer(new EventName('user.created'), ['userId' => 7]);
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
        $eventDispatcher = $this->asyncDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'x'),
            new ListenerPriority(0),
        );

        try {
            $eventDispatcher->defer($eventName, ['userId' => 'seven']);
            self::fail('The mis-shaped payload was not refused at defer.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload field 'userId' must answer int, got string.",
                $invalidArgumentException->getMessage(),
            );
        }

        $drainOutcome = $eventDispatcher->drain(1_000);
        self::assertSame(0, $drainOutcome->delivered());
        self::assertSame(0, $drainOutcome->pending());
        self::assertSame([], $eventLog->tags());
    }

    public function testThePoolSnapshotIsTheMessage(): void
    {
        $eventLog = new EventLog();
        $eventDispatcher = $this->asyncDispatcher();
        $eventName = new EventName('user.created');
        $subscribed = new RecordingListener($eventLog, 'subscribed');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, $subscribed, new ListenerPriority(0));

        $eventDispatcher->defer($eventName, ['userId' => 7]);

        $late = new RecordingListener($eventLog, 'late');
        $eventDispatcher->subscribe($eventName, $late, new ListenerPriority(0));
        $eventDispatcher->unsubscribe($eventName, $subscribed);

        $drainOutcome = $eventDispatcher->drain(1_000);

        self::assertSame(1, $drainOutcome->delivered());
        self::assertSame(['subscribed'], $eventLog->tags());
    }

    public function testAWildcardListenerIsAdmittedByDeferAndDelivered(): void
    {
        $eventLog = new EventLog();
        $eventDispatcher = $this->asyncDispatcher();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'wildcard'),
            new ListenerPriority(0),
        );

        $eventDispatcher->defer($eventName, ['userId' => 7]);

        $drainOutcome = $eventDispatcher->drain(1_000);

        self::assertSame(1, $drainOutcome->delivered());
        self::assertSame(['wildcard'], $eventLog->tags());
    }

    public function testASyncOnlyDispatcherRefusesTheDeferredVerbs(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventName = new EventName('user.created');
        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        try {
            $eventDispatcher->defer($eventName, ['userId' => 7]);
            self::fail('The sync-only defer was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'defer needs a deferred-delivery engine; construct the'
                . ' dispatcher with one (new EventDispatcher(new'
                . ' AsyncDelivery(...))) -- a dispatcher built without one'
                . ' is the legal sync-only shape.',
                $invalidArgumentException->getMessage(),
            );
        }

        try {
            $eventDispatcher->drain(1_000);
            self::fail('The sync-only drain was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'drain needs a deferred-delivery engine; construct the'
                . ' dispatcher with one (new EventDispatcher(new'
                . ' AsyncDelivery(...))) -- a dispatcher built without one'
                . ' is the legal sync-only shape.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASyncDispatchIsUnchangedBesideTheDeferredArm(): void
    {
        $eventLog = new EventLog();
        $eventDispatcher = $this->asyncDispatcher();
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
        self::assertSame(0, $eventDispatcher->drain(1_000)->pending());
    }

    public function testTheFullRegimeRunsThroughTheFacade(): void
    {
        $eventLog = new EventLog();
        $eventDispatcher = $this->asyncDispatcher();
        $eventName = new EventName('user.created');
        $failingListener = new FailingListener();

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, $failingListener, new ListenerPriority(0));
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'healthy'),
            new ListenerPriority(0),
        );

        $eventDispatcher->defer($eventName, ['userId' => 7]);

        $firstPass = $eventDispatcher->drain(1_000);
        self::assertSame(1, $firstPass->delivered());
        self::assertSame(1, $firstPass->retried());
        self::assertSame(1, $firstPass->pending());

        $secondPass = $eventDispatcher->drain(1_010);
        self::assertSame(1, $secondPass->retried());

        $thirdPass = $eventDispatcher->drain(1_030);
        self::assertSame(1, $thirdPass->deadLettered());
        self::assertSame(0, $thirdPass->pending());
        self::assertSame(3, $failingListener->thrown());
        self::assertSame(['healthy'], $eventLog->tags());
    }

    private function asyncDispatcher(): EventDispatcher
    {
        return new EventDispatcher(new AsyncDelivery(new RetryPolicy(3, 10), 100, 3));
    }
}
