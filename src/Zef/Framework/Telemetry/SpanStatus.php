<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The span status vocabulary's fold and refusal: the closed set
 * (`UNSET`, `OK`, `ERROR`) a lifecycle status must land in, read
 * through one named place -- the tracing core's own mirror of the
 * config system's vocabulary readers ({@see \Zef\Framework\Http\
 * StrictToggles}, the fold-and-refuse shape the whole repository
 * shares).
 *
 * The known list is spelled out in the refusal, exactly as every
 * knob census spells its own: an `ok` folds to `OK` and lands, a
 * `BOGUS` is refused with the three legitimate statuses named, and
 * an already-folded spelling passes through untouched.
 */
final class SpanStatus
{
    /**
     * The lifecycle statuses a span can carry, in vocabulary order.
     */
    private const array VALUES = ['UNSET', 'OK', 'ERROR'];

    /**
     * Folds one offered status into the vocabulary, refusing
     * everything that does not land in it.
     *
     * @throws InvalidArgumentException when the status folds to none
     *                                  of the three legitimate values
     */
    public static function fold(string $status): string
    {
        $folded = mb_strtoupper($status);
        if (!\in_array($folded, self::VALUES, true)) {
            throw new InvalidArgumentException(
                'Invalid span status: ' . $status . ' (known statuses: '
                . implode(', ', self::VALUES) . ').',
            );
        }

        return $folded;
    }
}
