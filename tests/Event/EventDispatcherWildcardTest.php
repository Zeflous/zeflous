<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPattern;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;

/**
 * Pins the wildcard arm of the dispatcher's public vocabulary
 * end-to-end: the pattern-as-its-own-contract doctrine (a wildcard
 * subscription needs no definition, and an undefined event is still
 * refused at dispatch), the matched event's own validated payload,
 * the one-pool delivery order (priority first; among equals, the
 * exact-name listener before the wildcard), the honest-boolean
 * unsubscribe, and the bubbling failure regime a wildcard listener
 * shares with the exact ones.
 *
 * @internal
 */
#[CoversClass(EventDispatcher::class)]
final class EventDispatcherWildcardTest extends TestCase
{
    public function testAWildcardListenerReceivesEveryEventPastItsPrefix(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();

        $eventDispatcher->define(new EventName('user.created'), new EventShape(['userId' => 'int']));
        $eventDispatcher->define(new EventName('user.updated.late'), new EventShape(['userId' => 'int']));
        $eventDispatcher->define(new EventName('order.paid'), new EventShape(['orderId' => 'int']));

        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'audit'),
            new ListenerPriority(0),
        );

        $eventDispatcher->dispatch(new EventName('user.created'), ['userId' => 1]);
        $eventDispatcher->dispatch(new EventName('user.updated.late'), ['userId' => 2]);
        $eventDispatcher->dispatch(new EventName('order.paid'), ['orderId' => 3]);

        self::assertSame(['audit', 'audit'], $eventLog->tags());
    }

    public function testAWildcardListenerReceivesTheMatchedEventsOwnValidatedPayload(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $recordingListener = new RecordingListener($eventLog, 'audit');

        $eventDispatcher->define(new EventName('user.created'), new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            $recordingListener,
            new ListenerPriority(0),
        );

        $outcome = $eventDispatcher->dispatch(new EventName('user.created'), ['userId' => 7]);

        self::assertSame(1, $outcome->deliveredTo());
        self::assertSame('user.created', $recordingListener->received()[0]->name()->spelling());
        self::assertSame(7, $recordingListener->received()[0]->payload()->value('userId'));
    }

    public function testAMisShapedPayloadNeverReachesAWildcardListener(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();

        $eventDispatcher->define(new EventName('user.created'), new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'audit'),
            new ListenerPriority(0),
        );

        try {
            $eventDispatcher->dispatch(new EventName('user.created'), ['userId' => 'seven']);
            self::fail('A mis-shaped payload must be refused before any listener runs.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('userId', $invalidArgumentException->getMessage());
        }

        self::assertSame([], $eventLog->tags());
    }

    public function testThePoolSortsExactAndWildcardListenersByPriority(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'exact-low'),
            new ListenerPriority(0),
        );
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'wildcard-high'),
            new ListenerPriority(10),
        );

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 1]);

        self::assertSame(['wildcard-high', 'exact-low'], $eventLog->tags());
        self::assertSame(2, $outcome->deliveredTo());
    }

    public function testEqualPrioritiesStandExactFirstThenWildcard(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'wildcard'),
            new ListenerPriority(0),
        );
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'exact'),
            new ListenerPriority(0),
        );

        $eventDispatcher->dispatch($eventName, ['userId' => 1]);

        self::assertSame(['exact', 'wildcard'], $eventLog->tags());
    }

    public function testTheOutcomeCountsExactAndWildcardDeliveriesTogether(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'exact'),
            new ListenerPriority(0),
        );
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'wildcard-one'),
            new ListenerPriority(0),
        );
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.created.*'),
            new RecordingListener($eventLog, 'wildcard-two'),
            new ListenerPriority(0),
        );

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 1]);

        // 'user.created.*' does not match 'user.created' itself: the star
        // stands BEHIND the dot, and the name does not continue past it.
        self::assertSame(['exact', 'wildcard-one'], $eventLog->tags());
        self::assertSame(2, $outcome->deliveredTo());
    }

    public function testTheSameListenerUnderAnExactNameAndAPatternDeliversTwice(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');
        $recordingListener = new RecordingListener($eventLog, 'both');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribe($eventName, $recordingListener, new ListenerPriority(0));
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            $recordingListener,
            new ListenerPriority(0),
        );

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 1]);

        self::assertSame(['both', 'both'], $eventLog->tags());
        self::assertSame(2, $outcome->deliveredTo());
    }

    public function testAWildcardExceptionBubblesAndTheLaterPoolDoesNotRun(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new class implements EventListener {
                #[Override]
                public function handle(Event $event): void
                {
                    throw new RuntimeException('listener exploded');
                }
            },
            new ListenerPriority(10),
        );
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'never-runs'),
            new ListenerPriority(0),
        );

        try {
            $eventDispatcher->dispatch($eventName, ['userId' => 1]);
            self::fail('A listener exception must bubble to the dispatch caller.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('listener exploded', $runtimeException->getMessage());
        }

        self::assertSame([], $eventLog->tags());
    }

    public function testUnsubscribingAWildcardListenerStopsItsDeliveries(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');
        $eventPattern = new EventPattern('user.*');
        $recordingListener = new RecordingListener($eventLog, 'audit');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribeWildcard($eventPattern, $recordingListener, new ListenerPriority(0));
        self::assertTrue($eventDispatcher->unsubscribeWildcard($eventPattern, $recordingListener));
        self::assertFalse($eventDispatcher->unsubscribeWildcard($eventPattern, $recordingListener));

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 1]);

        self::assertSame([], $eventLog->tags());
        self::assertSame(0, $outcome->deliveredTo());
    }

    public function testAWildcardSubscriptionNeedsNoDefinition(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();

        // No define() call at all: the pattern is its own contract, and
        // an event nobody defined is still refused at dispatch.
        $eventDispatcher->subscribeWildcard(
            new EventPattern('user.*'),
            new RecordingListener($eventLog, 'audit'),
            new ListenerPriority(0),
        );

        try {
            $eventDispatcher->dispatch(new EventName('user.created'), ['userId' => 1]);
            self::fail('Dispatch must still refuse an undefined event.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('not defined', $invalidArgumentException->getMessage());
        }

        self::assertSame([], $eventLog->tags());
    }

    /**
     * The wildcard arm's own registration-order guarantee among equal
     * priorities under one pattern: the same guarantee the exact
     * registry spells, replayed per pattern.
     */
    public function testEqualWildcardPrioritiesKeepRegistrationOrder(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');
        $eventPattern = new EventPattern('user.*');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribeWildcard(
            $eventPattern,
            new RecordingListener($eventLog, 'first'),
            new ListenerPriority(0),
        );
        $eventDispatcher->subscribeWildcard(
            $eventPattern,
            new RecordingListener($eventLog, 'second'),
            new ListenerPriority(0),
        );

        $eventDispatcher->dispatch($eventName, ['userId' => 1]);

        self::assertSame(['first', 'second'], $eventLog->tags());
    }
}
