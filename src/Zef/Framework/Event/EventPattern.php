<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The wildcard subscription's address: a pattern a listener subscribes
 * by, the last segment spelled `*` -- `user.*`, `Order.Created.*` --
 * matching every defined event whose name continues past the prefix
 * (`user.created`, `user.updated.late`; never `user` itself, which is
 * not "past" the dot).
 *
 * The pattern grammar is the event name's own grammar plus one
 * reserved character, and exactly one position for it: the star the
 * core slice's name grammar refuses (ED10) is spent here, in the final
 * segment alone, so a wildcard matcher can never meet a name that
 * spells a star of its own and a pattern can never bury one mid-name
 * where its meaning (`user.*.created` -- which events?) would be a
 * guess the framework refuses to make. A pattern is not defined and
 * not definable: the name book's define verb refuses the star by the
 * shared grammar, and a wildcard subscription is its own contract --
 * the events it matches are the events the application defines, each
 * carrying each definition's own validated shape.
 */
final readonly class EventPattern
{
    private const int MAXIMUM_LENGTH = 64;

    private string $pattern;

    /**
     * @throws InvalidArgumentException when the spelling is not a
     *                                  name-prefixed trailing-star pattern within the grammar's
     *                                  bounds
     */
    public function __construct(string $pattern)
    {
        if ($pattern === '' || mb_strlen($pattern) > self::MAXIMUM_LENGTH) {
            throw new InvalidArgumentException(
                'An event pattern must be a non-empty string of at most '
                . self::MAXIMUM_LENGTH . ' characters, got a length of '
                . mb_strlen($pattern) . '.',
            );
        }

        if (!str_ends_with($pattern, '.*')) {
            throw new InvalidArgumentException(
                "An event pattern must end in '.*' (the wildcard star owns the"
                . " final segment alone), got '{$pattern}'.",
            );
        }

        $prefix = mb_substr($pattern, 0, -2);

        if (str_ends_with($prefix, '.')) {
            throw new InvalidArgumentException(
                "An event pattern's prefix must name the segment the star stands"
                . " behind, got '{$prefix}.' -- an empty segment matches no event.",
            );
        }

        if ($prefix === '' || preg_match('/^[A-Za-z][A-Za-z0-9._-]*$/', $prefix) !== 1) {
            throw new InvalidArgumentException(
                "An event pattern's prefix must start with a letter and continue"
                . " with letters, digits, dots, dashes or underscores, got '{$prefix}'.",
            );
        }

        $this->pattern = $pattern;
    }

    /**
     * The canonical spelling every participant addresses the pattern
     * by -- the one string a wildcard subscription records and an
     * unsubscribe reads back, so equality stays byte-identical.
     */
    public function spelling(): string
    {
        return $this->pattern;
    }

    /**
     * Whether a defined event's name falls under the pattern: the
     * name must continue past the prefix (`user.*` addresses
     * `user.created` and `user.updated.late`, never `user` itself --
     * the dot is the boundary the star stands behind).
     */
    public function matches(EventName $eventName): bool
    {
        return str_starts_with($eventName->spelling(), $this->prefix());
    }

    /**
     * The prefix the star stands behind, dot included -- the string a
     * match is a `str_starts_with` against.
     */
    private function prefix(): string
    {
        return mb_substr($this->pattern, 0, -1);
    }
}
