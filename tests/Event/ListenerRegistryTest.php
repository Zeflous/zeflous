<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerRegistry;
use Zef\Framework\Event\ListenerSubscription;

/**
 * Pins the registry's two order rules and its removal contract:
 * a higher priority stands earlier, equal priorities keep their
 * registration order, and a removal never re-shuffles the
 * survivors. The duplicate-subscription refusal is the registry's
 * own contribution to the fail-closed doctrine -- a second
 * standing for the same object would deliver the event twice.
 *
 * @internal
 */
#[CoversClass(ListenerRegistry::class)]
#[CoversClass(ListenerSubscription::class)]
final class ListenerRegistryTest extends TestCase
{
    public function testAHigherPriorityStandsEarlier(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $low = $this->listener();
        $high = $this->listener();

        $listenerRegistry->add(new ListenerSubscription($low, new ListenerPriority(10)));
        $listenerRegistry->add(new ListenerSubscription($high, new ListenerPriority(20)));

        self::assertSame([$high, $low], $this->orderedListeners($listenerRegistry));
    }

    public function testEqualPrioritiesKeepTheirRegistrationOrder(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $first = $this->listener();
        $second = $this->listener();
        $third = $this->listener();

        $listenerRegistry->add(new ListenerSubscription($first, new ListenerPriority(0)));
        $listenerRegistry->add(new ListenerSubscription($second, new ListenerPriority(0)));
        $listenerRegistry->add(new ListenerSubscription($third, new ListenerPriority(0)));

        self::assertSame([$first, $second, $third], $this->orderedListeners($listenerRegistry));
    }

    public function testTheOrderHoldsAcrossAMixedRegistration(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $logging = $this->listener();
        $processing = $this->listener();
        $auditing = $this->listener();
        $tracing = $this->listener();

        $listenerRegistry->add(new ListenerSubscription($logging, new ListenerPriority(100)));
        $listenerRegistry->add(new ListenerSubscription($processing, new ListenerPriority(50)));
        $listenerRegistry->add(new ListenerSubscription($tracing, new ListenerPriority(100)));
        $listenerRegistry->add(new ListenerSubscription($auditing, new ListenerPriority(-1)));

        self::assertSame(
            [$logging, $tracing, $processing, $auditing],
            $this->orderedListeners($listenerRegistry),
        );
    }

    public function testTheSubscriptionCarriesItsOwnPriority(): void
    {
        $listenerSubscription = new ListenerSubscription($this->listener(), new ListenerPriority(25));

        self::assertSame(25, $listenerSubscription->priority()->standing());
    }

    public function testADuplicateListenerIsRefused(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $listener = $this->listener();

        $listenerRegistry->add(new ListenerSubscription($listener, new ListenerPriority(0)));

        try {
            $listenerRegistry->add(new ListenerSubscription($listener, new ListenerPriority(10)));
            self::fail('The duplicate standing was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The listener is already subscribed to this event; a'
                . ' second standing for the same object would deliver the'
                . ' event twice -- unsubscribe first, then re-subscribe.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRemovalAnswersTrueAndClearsTheStanding(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $listener = $this->listener();

        $listenerRegistry->add(new ListenerSubscription($listener, new ListenerPriority(0)));
        self::assertFalse($listenerRegistry->isEmpty());

        self::assertTrue($listenerRegistry->remove($listener));
        self::assertTrue($listenerRegistry->isEmpty());
        self::assertSame([], $listenerRegistry->ordered());
    }

    public function testRemovalNeverReshufflesTheSurvivors(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $first = $this->listener();
        $second = $this->listener();
        $third = $this->listener();

        $listenerRegistry->add(new ListenerSubscription($first, new ListenerPriority(0)));
        $listenerRegistry->add(new ListenerSubscription($second, new ListenerPriority(0)));
        $listenerRegistry->add(new ListenerSubscription($third, new ListenerPriority(0)));

        self::assertTrue($listenerRegistry->remove($second));

        self::assertSame([$first, $third], $this->orderedListeners($listenerRegistry));
    }

    public function testRemovingAnUnregisteredListenerAnswersFalse(): void
    {
        $listenerRegistry = new ListenerRegistry();

        self::assertFalse($listenerRegistry->remove($this->listener()));
    }

    public function testARemovedListenerMaySubscribeAgain(): void
    {
        $listenerRegistry = new ListenerRegistry();
        $listener = $this->listener();

        $listenerRegistry->add(new ListenerSubscription($listener, new ListenerPriority(0)));
        self::assertTrue($listenerRegistry->remove($listener));

        $listenerRegistry->add(new ListenerSubscription($listener, new ListenerPriority(10)));

        $ordered = $listenerRegistry->ordered();

        self::assertCount(1, $ordered);
        self::assertSame(10, $ordered[0]->priority()->standing());
    }

    public function testAFreshRegistryIsEmpty(): void
    {
        self::assertTrue(new ListenerRegistry()->isEmpty());
    }

    /**
     * A listener that records nothing and answers nothing -- the
     * registry orders listeners, it never runs them, so the test's
     * listener is an identity the book can hold.
     */
    private function listener(): EventListener
    {
        return new class implements EventListener {
            #[Override]
            public function handle(Event $event): void
            {
                // Intentionally empty: the registry never invokes this.
            }
        };
    }

    /**
     * The registry's dispatch order spelled as the listener objects
     * themselves, in the order a dispatch would hand the event to
     * them.
     *
     * @return list<EventListener>
     */
    private function orderedListeners(ListenerRegistry $listenerRegistry): array
    {
        return array_map(
            static fn (ListenerSubscription $listenerSubscription): EventListener => $listenerSubscription->listener(),
            $listenerRegistry->ordered(),
        );
    }
}
