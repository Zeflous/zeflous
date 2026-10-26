<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPattern;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerRegistry;
use Zef\Framework\Event\ListenerSubscription;
use Zef\Framework\Event\WildcardMatches;

/**
 * @internal
 */
#[CoversClass(WildcardMatches::class)]
final class WildcardMatchesTest extends TestCase
{
    public function testTheWalkCollectsEveryMatchingPatternsSubscriptionsInItsOwnOrder(): void
    {
        $userStar = new ListenerRegistry();
        $userStar->add(
            new ListenerSubscription(new RecordingListener(new EventLog(), 'user-low'), new ListenerPriority(0)),
        );
        $userStar->add(
            new ListenerSubscription(new RecordingListener(new EventLog(), 'user-high'), new ListenerPriority(10)),
        );

        $orderStar = new ListenerRegistry();
        $orderStar->add(
            new ListenerSubscription(new RecordingListener(new EventLog(), 'order'), new ListenerPriority(0)),
        );

        $wildcardMatches = new WildcardMatches(
            ['user.*' => new EventPattern('user.*'), 'order.*' => new EventPattern('order.*')],
            ['user.*' => $userStar, 'order.*' => $orderStar],
        );

        $matched = $wildcardMatches->matchedBy(new EventName('user.created'));

        self::assertCount(2, $matched);
        self::assertSame(10, $matched[0]->priority()->standing());
        self::assertSame(0, $matched[1]->priority()->standing());
    }

    public function testTheWalkVisitsPatternsInDeclarationOrderAmongEquals(): void
    {
        $first = new ListenerRegistry();
        $first->add(new ListenerSubscription(new RecordingListener(new EventLog(), 'first'), new ListenerPriority(0)));

        $second = new ListenerRegistry();
        $second->add(
            new ListenerSubscription(new RecordingListener(new EventLog(), 'second'), new ListenerPriority(0)),
        );

        $wildcardMatches = new WildcardMatches(
            ['user.*' => new EventPattern('user.*'), 'user.created.*' => new EventPattern('user.created.*')],
            ['user.*' => $first, 'user.created.*' => $second],
        );

        $matched = $wildcardMatches->matchedBy(new EventName('user.created.late'));

        self::assertSame(0, $matched[0]->priority()->standing());
        self::assertSame(0, $matched[1]->priority()->standing());
        self::assertSame($first->ordered()[0]->listener(), $matched[0]->listener());
        self::assertSame($second->ordered()[0]->listener(), $matched[1]->listener());
    }

    public function testTheWalkSkipsPatternsTheNameDoesNotContinuePast(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $listenerRegistry->add(
            new ListenerSubscription(new RecordingListener(new EventLog(), 'user'), new ListenerPriority(0)),
        );

        $wildcardMatches = new WildcardMatches(
            ['user.*' => new EventPattern('user.*'), 'order.*' => new EventPattern('order.*')],
            ['user.*' => $listenerRegistry, 'order.*' => new ListenerRegistry()],
        );

        self::assertCount(0, $wildcardMatches->matchedBy(new EventName('order.paid')));
        self::assertCount(1, $wildcardMatches->matchedBy(new EventName('user.updated')));
        self::assertCount(0, $wildcardMatches->matchedBy(new EventName('user')));
    }

    public function testAnEmptyBookWalksToNothing(): void
    {
        $wildcardMatches = new WildcardMatches([], []);

        self::assertSame([], $wildcardMatches->matchedBy(new EventName('user.created')));
    }
}
