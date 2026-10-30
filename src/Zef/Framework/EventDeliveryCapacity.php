<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The events family's capacity seam: the strict integer reading
 * the `events.deferred.capacity` knob passes through -- the
 * pending bound's own fold, read through the family's declaration
 * ({@see EventDeliveryDeclaration}) so the census and the reading
 * can never disagree.
 *
 * A native int passes through untouched (a file or the default
 * layer declared it); a string -- the raw spelling an environment
 * overlay writes, `EnvSource` passing values through as the
 * environment carries them -- folds to its integer when it is all
 * digits, and everything else is rejected with the knob named: a
 * float, a bool, a negative sign or a spaced number halts the ask
 * rather than silently reading as zero, because a silently-mistyped
 * budget is a limit the operator believes is set while it is not.
 * The range is the family's own invariant -- this fold owns the
 * spelling.
 *
 * This is the integer sibling of the HTTP lane's {@see
 * Http\StrictIntegers} reading, spelled in the events family's own
 * words because the composition lane reads this knob, not the HTTP
 * lane -- the same one-way fold discipline every family's numeric
 * knobs follow.
 */
final class EventDeliveryCapacity
{
    /**
     * Reads the capacity knob's value: native int passthrough,
     * all-digit string fold, rejection with the knob named
     * otherwise, and the at-least-one bound the admission
     * arithmetic demands.
     *
     * @throws ConfigException when the value is not an integer or
     *                         all-digit string, or is below one
     */
    public static function read(mixed $capacity): int
    {
        if (\is_int($capacity)) {
            $folded = $capacity;
        } elseif (\is_string($capacity) && ctype_digit($capacity)) {
            $folded = (int) $capacity;
        } else {
            throw new ConfigException(
                'The events.deferred.capacity knob must be an integer or an'
                . ' all-digit string, ' . get_debug_type($capacity) . ' given.',
            );
        }

        if ($folded < 1) {
            throw new ConfigException(
                'The events.deferred.capacity knob must be at least one, got '
                . $folded . ' -- a capacity of zero would refuse every'
                . ' admission the wired store is asked to hold.',
            );
        }

        return $folded;
    }
}
