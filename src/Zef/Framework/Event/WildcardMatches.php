<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The wildcard match walk: the one pass that asks every declared
 * pattern whether a dispatched event's name continues past its
 * prefix, and collects the subscriptions of every pattern that says
 * yes -- each registry's own priority order kept, the patterns
 * visited in declaration order.
 *
 * This is the fold the wildcard book's own difficulty ceiling forced
 * out (the same split the event book's entry-keeping fold spelled):
 * entry-keeping and match-walking are two questions, two seams. The
 * walk is constructed per call over the book's own two maps -- the
 * arrays are copied at construction, but the registries they carry
 * are the live objects, so a dispatch always sees the subscriptions
 * that stand at the moment it asks.
 */
final readonly class WildcardMatches
{
    /**
     * @param array<string, EventPattern>     $patterns   the declared patterns, declaration order
     * @param array<string, ListenerRegistry> $registries the pattern spellings' listener registries
     */
    public function __construct(
        private array $patterns,
        private array $registries,
    ) {
    }

    /**
     * Every subscription whose pattern matches the name -- the seed
     * the dispatch pool's stable sort orders equal priorities by.
     *
     * @return list<ListenerSubscription>
     */
    public function matchedBy(EventName $eventName): array
    {
        $matched = [];

        foreach ($this->patterns as $spelling => $eventPattern) {
            if (!$eventPattern->matches($eventName)) {
                continue;
            }

            $matched = array_merge($matched, $this->registries[$spelling]->ordered());
        }

        return $matched;
    }
}
