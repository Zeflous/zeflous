<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The per-event listener book: the ordered set of subscriptions a
 * dispatch walks. Two rules hold the order honest.
 *
 * First, a higher priority stands earlier -- the roadmap's
 * "listener prioritization" doctrine, so logging outranks
 * processing because the application said so, not because of the
 * order the code happened to register in.
 *
 * Second, equal priorities keep their registration order. The
 * sort is stable, the append is at the end, and a removal never
 * re-shuffles the survivors: two listeners that never asked to be
 * ordered against each other observe exactly the order they
 * subscribed in, which is the only order they could have reasoned
 * about.
 */
final class ListenerRegistry
{
    /** @var array<int, ListenerSubscription> */
    private array $subscriptions = [];

    /** @var array<int, EventListener> */
    private array $listenerIdentities = [];

    /**
     * Adds a subscription. A listener that is already subscribed to
     * this registry's event is refused -- a second standing for the
     * same object is near-always a plugin's double-registration
     * bug, and the fix (unsubscribe, then re-subscribe at the new
     * priority) is one honest call, not a silent double delivery.
     *
     * @throws InvalidArgumentException when the listener object is
     *                                  already subscribed to this event
     */
    public function add(ListenerSubscription $listenerSubscription): void
    {
        $identity = spl_object_id($listenerSubscription->listener());

        if (\array_key_exists($identity, $this->listenerIdentities)) {
            throw new InvalidArgumentException(
                'The listener is already subscribed to this event; a'
                . ' second standing for the same object would deliver the'
                . ' event twice -- unsubscribe first, then re-subscribe.',
            );
        }

        $this->subscriptions[] = $listenerSubscription;
        $this->listenerIdentities[$identity] = $listenerSubscription->listener();
    }

    /**
     * Removes the listener's subscription, answering whether a
     * subscription was actually removed -- an unsubscribe for a
     * listener nobody registered is the caller's own stale book,
     * and a boolean is more honest than a refusal the caller
     * cannot act on.
     *
     * The book is rebuilt rather than holed: the kept subscriptions
     * append into a fresh list, so the registration order the
     * survivors keep is dense by construction, never a re-indexed
     * afterthought a later append would have to reason about.
     */
    public function remove(EventListener $eventListener): bool
    {
        $identity = spl_object_id($eventListener);

        if (!\array_key_exists($identity, $this->listenerIdentities)) {
            return false;
        }

        $kept = [];

        foreach ($this->subscriptions as $subscription) {
            if ($subscription->listener() === $eventListener) {
                continue;
            }

            $kept[] = $subscription;
        }

        $this->subscriptions = $kept;
        unset($this->listenerIdentities[$identity]);

        return true;
    }

    /**
     * The dispatch order: subscriptions sorted by priority
     * descending, equal priorities keeping registration order.
     *
     * @return list<ListenerSubscription>
     */
    public function ordered(): array
    {
        $ordered = $this->subscriptions;

        usort(
            $ordered,
            static fn (
                ListenerSubscription $left,
                ListenerSubscription $right,
            ): int => $right->priority()->standing() <=> $left->priority()->standing(),
        );

        return $ordered;
    }

    /**
     * Whether any listener is subscribed -- the answer an
     * event's dispatch outcome's zero-delivery case states.
     */
    public function isEmpty(): bool
    {
        return $this->subscriptions === [];
    }
}
