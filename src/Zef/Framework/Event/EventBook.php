<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The book of defined events: one entry per event name, each entry
 * the payload contract (the shape) and the listener registry that
 * name's subscribers live in. Every undefined-name refusal the
 * dispatcher's public verbs spell is answered here, at the one
 * place that actually knows whether a name was defined -- so the
 * dispatcher stays a thin vocabulary and the book stays the
 * authority.
 *
 * This is the fold the dispatcher's own difficulty ceiling forced
 * out (the same split the storage root's document-root fold
 * spelled): the entry-keeping concern and the dispatch
 * orchestration concern are two questions, two seams.
 */
final class EventBook
{
    /** @var array<string, EventShape> */
    private array $shapes = [];

    /** @var array<string, ListenerRegistry> */
    private array $registries = [];

    /**
     * Writes an event's entry: the shape and a fresh, empty
     * listener registry. Writing a name twice is refused -- the
     * second definition is near-always a plugin re-registering an
     * event it did not own, and the shape the first definition
     * spelled is the contract the existing subscribers already
     * reasoned about.
     *
     * @throws InvalidArgumentException when the name is already
     *                                  defined
     */
    public function define(EventName $eventName, EventShape $eventShape): void
    {
        $spelling = $eventName->spelling();

        if (\array_key_exists($spelling, $this->shapes)) {
            throw new InvalidArgumentException(
                "The event '{$spelling}' is already defined; a second"
                . ' definition would silently re-spell the contract its'
                . ' existing subscribers reasoned about.',
            );
        }

        $this->shapes[$spelling] = $eventShape;
        $this->registries[$spelling] = new ListenerRegistry();
    }

    /**
     * The event's payload contract -- the shape a dispatch
     * validates its payload against.
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined; dispatch refuses an undefined name so a typo
     *                                  cannot silently deliver nothing
     */
    public function shapeFor(EventName $eventName): EventShape
    {
        $spelling = $eventName->spelling();

        if (!\array_key_exists($spelling, $this->shapes)) {
            throw new InvalidArgumentException(
                "The event '{$spelling}' is not defined; dispatch refuses an"
                . ' undefined name so a typo cannot silently deliver nothing.',
            );
        }

        return $this->shapes[$spelling];
    }

    /**
     * The event's listener registry -- the book the subscribe and
     * unsubscribe verbs read.
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined; subscribe and unsubscribe both refuse an
     *                                  undefined name so a typo cannot silently register a
     *                                  listener nobody dispatches to
     */
    public function registryFor(EventName $eventName): ListenerRegistry
    {
        $spelling = $eventName->spelling();

        if (!\array_key_exists($spelling, $this->registries)) {
            throw new InvalidArgumentException(
                "The event '{$spelling}' is not defined; subscribe and"
                . ' unsubscribe both refuse an undefined name so a typo'
                . ' cannot silently register a listener nobody dispatches to.',
            );
        }

        return $this->registries[$spelling];
    }
}
