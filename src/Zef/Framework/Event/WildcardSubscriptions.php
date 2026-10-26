<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The wildcard book: one listener registry per distinct pattern, in
 * pattern-declaration order -- the same two-map shape the event book
 * keeps for its definitions, replayed for patterns. The
 * duplicate-standing refusal and the honest-boolean removal are the
 * exact-name registry's own rules replayed per pattern: a listener
 * standing twice under one pattern is the same double-delivery bug,
 * while the same listener under two different patterns (or under a
 * pattern and an exact name) is two subscriptions and two
 * deliveries, the wider world's own semantics, not a bug this book
 * refuses for the application.
 *
 * The match walk visits the patterns in the order they were declared,
 * so the subscriptions it collects seed the dispatch pool's stable
 * sort deterministically: equal priorities keep pattern-declaration
 * order, the same guarantee the exact registry spells for
 * registration order.
 */
final class WildcardSubscriptions
{
    /** @var array<string, EventPattern> */
    private array $patterns = [];

    /** @var array<string, ListenerRegistry> */
    private array $registries = [];

    /**
     * Adds a subscription under a pattern, opening the pattern's
     * registry on its first standing.
     *
     * @throws InvalidArgumentException when the listener object is
     *                                  already subscribed under this pattern
     */
    public function add(EventPattern $eventPattern, ListenerSubscription $listenerSubscription): void
    {
        $spelling = $eventPattern->spelling();

        // The pattern VO is spelling-keyed and value-equal for any two
        // instances of the same spelling (one grammar, one match
        // semantics), so the latest stands; the registry is the live
        // book and must never be reset by a later standing.
        $this->patterns[$spelling] = $eventPattern;
        $this->registries[$spelling] ??= new ListenerRegistry();

        $this->registries[$spelling]->add($listenerSubscription);
    }

    /**
     * Removes the listener's standing under a pattern, answering
     * whether one was removed -- the stale-book boolean the exact
     * registry's removal spelled, replayed per pattern (an
     * unsubscribe for a pattern nobody declared answers false the
     * same way an unknown event's does).
     */
    public function remove(EventPattern $eventPattern, EventListener $eventListener): bool
    {
        return ($this->registries[$eventPattern->spelling()] ?? null)?->remove($eventListener) ?? false;
    }

    /**
     * Every subscription whose pattern matches the name: the walk
     * itself lives in {@see WildcardMatches} -- the fold this book's
     * own difficulty ceiling forced out -- constructed over this
     * book's maps at the moment of the question, so the answer is
     * always the stands that exist right now.
     *
     * @return list<ListenerSubscription>
     */
    public function matching(EventName $eventName): array
    {
        return new WildcardMatches($this->patterns, $this->registries)->matchedBy($eventName);
    }

    /**
     * Whether any listener stands under any pattern -- the answer a
     * dispatch with no exact listeners and no matching wildcard
     * states through its zero-delivery outcome.
     */
    public function isEmpty(): bool
    {
        return $this->patterns === [];
    }
}
