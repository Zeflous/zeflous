<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPattern;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerSubscription;
use Zef\Framework\Event\WildcardSubscriptions;

/**
 * @internal
 */
#[CoversClass(WildcardSubscriptions::class)]
final class WildcardSubscriptionsTest extends TestCase
{
    public function testASubscriptionIsAddedAndMatched(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $recordingListener = new RecordingListener(new EventLog(), 'audit');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        );

        self::assertFalse($wildcardSubscriptions->isEmpty());
        self::assertCount(1, $wildcardSubscriptions->matching(new EventName('user.created')));
        self::assertCount(1, $wildcardSubscriptions->matching(new EventName('user.updated.late')));
        self::assertCount(0, $wildcardSubscriptions->matching(new EventName('order.paid')));
    }

    public function testASecondStandingUnderTheSamePatternIsRefused(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $recordingListener = new RecordingListener(new EventLog(), 'audit');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('already subscribed');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($recordingListener, new ListenerPriority(10)),
        );
    }

    public function testTheSameListenerMayStandUnderTwoPatterns(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $recordingListener = new RecordingListener(new EventLog(), 'audit');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        );
        $wildcardSubscriptions->add(
            new EventPattern('order.*'),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        );

        self::assertCount(1, $wildcardSubscriptions->matching(new EventName('user.created')));
        self::assertCount(1, $wildcardSubscriptions->matching(new EventName('order.paid')));
    }

    public function testMatchingAnswersEachRegistrysOwnPriorityOrder(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $low = new RecordingListener(new EventLog(), 'low');
        $high = new RecordingListener(new EventLog(), 'high');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($low, new ListenerPriority(0)),
        );
        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($high, new ListenerPriority(10)),
        );

        $matched = $wildcardSubscriptions->matching(new EventName('user.created'));

        self::assertSame([$high, $low], $this->listenersOf($matched));
    }

    public function testEqualPrioritiesAcrossPatternsKeepPatternDeclarationOrder(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $underUser = new RecordingListener(new EventLog(), 'user-star');
        $underDeeper = new RecordingListener(new EventLog(), 'deeper-star');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($underUser, new ListenerPriority(0)),
        );
        $wildcardSubscriptions->add(
            new EventPattern('user.created.*'),
            new ListenerSubscription($underDeeper, new ListenerPriority(0)),
        );

        $matched = $wildcardSubscriptions->matching(new EventName('user.created.late'));

        self::assertSame([$underUser, $underDeeper], $this->listenersOf($matched));
    }

    public function testTwoMatchingPatternsBothDeliverTheirListeners(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $underUser = new RecordingListener(new EventLog(), 'user-star');
        $underDeeper = new RecordingListener(new EventLog(), 'deeper-star');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($underUser, new ListenerPriority(0)),
        );
        $wildcardSubscriptions->add(
            new EventPattern('user.created.*'),
            new ListenerSubscription($underDeeper, new ListenerPriority(0)),
        );

        self::assertCount(2, $wildcardSubscriptions->matching(new EventName('user.created.late')));
        self::assertCount(1, $wildcardSubscriptions->matching(new EventName('user.updated')));
    }

    public function testRemovalAnswersWhetherAStoodExisted(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $recordingListener = new RecordingListener(new EventLog(), 'audit');
        $unrelatedListener = new RecordingListener(new EventLog(), 'other');

        $wildcardSubscriptions->add(
            new EventPattern('user.*'),
            new ListenerSubscription($recordingListener, new ListenerPriority(0)),
        );

        self::assertFalse($wildcardSubscriptions->remove(new EventPattern('user.*'), $unrelatedListener));
        self::assertTrue($wildcardSubscriptions->remove(new EventPattern('user.*'), $recordingListener));
        self::assertFalse($wildcardSubscriptions->remove(new EventPattern('user.*'), $recordingListener));
        self::assertCount(0, $wildcardSubscriptions->matching(new EventName('user.created')));
    }

    public function testRemovalUnderAnUndeclaredPatternAnswersFalse(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();
        $recordingListener = new RecordingListener(new EventLog(), 'audit');

        self::assertFalse(
            $wildcardSubscriptions->remove(new EventPattern('user.*'), $recordingListener),
        );
    }

    public function testAnEmptyBookMatchesNothing(): void
    {
        $wildcardSubscriptions = new WildcardSubscriptions();

        self::assertTrue($wildcardSubscriptions->isEmpty());
        self::assertCount(0, $wildcardSubscriptions->matching(new EventName('user.created')));
    }

    /**
     * @param list<ListenerSubscription> $matched
     *
     * @return list<EventListener>
     */
    private function listenersOf(array $matched): array
    {
        return array_map(
            static fn (ListenerSubscription $listenerSubscription): EventListener => $listenerSubscription->listener(),
            $matched,
        );
    }
}
