<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * A listener's standing in the dispatch order: the integer the
 * registry sorts subscriptions by, higher standing first. The
 * number is unbounded on purpose -- the roadmap's "logging before
 * error handling" doctrine is a policy the application spells,
 * not a bound the framework guesses -- but it is validated to be
 * a real integer, because a boolean priority (PHP's most silent
 * integer impersonator) would order listeners by accident.
 */
final readonly class ListenerPriority
{
    private int $priority;

    /**
     * @throws InvalidArgumentException when the priority is not an
     *                                  integer (a boolean is explicitly refused -- it is the
     *                                  one value PHP would otherwise silently coerce)
     */
    public function __construct(mixed $priority)
    {
        if (!\is_int($priority)) {
            throw new InvalidArgumentException(
                'A listener priority must be an integer, got '
                . get_debug_type($priority) . '.',
            );
        }

        $this->priority = $priority;
    }

    /**
     * The standing's own integer -- the value the registry's
     * descending order compares.
     */
    public function standing(): int
    {
        return $this->priority;
    }
}
