<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The events family's claim-timeout seam: the strict integer
 * reading the `events.deferred.claim_timeout` knob passes through
 * -- the database word's visibility timeout, the seconds a crashed
 * drainer's claims survive before the next drainer hands the
 * claimed entries on, read through the family's declaration
 * ({@see EventDeliveryDeclaration}) so the census and the reading
 * can never disagree.
 *
 * A native int passes through untouched (a file or the default
 * layer declared it); a string -- the raw spelling an environment
 * overlay writes, `EnvSource` passing values through as the
 * environment carries them -- folds to its integer when it is all
 * digits, and everything else is rejected with the knob named: a
 * float, a bool, a negative sign or a spaced number halts the ask
 * rather than silently reading as zero, the same one-way fold
 * discipline the capacity seam spells ({@see
 * EventDeliveryCapacity}), reached from the database word alone
 * because the file answer owns no claim to time out.
 */
final class EventDeliveryClaimTimeout
{
    /**
     * Reads the claim-timeout knob's value: native int passthrough,
     * all-digit string fold, rejection with the knob named
     * otherwise, and the at-least-one-second bound the visibility
     * arithmetic demands.
     *
     * @throws ConfigException when the value is not an integer or
     *                         all-digit string, or is below one
     */
    public static function read(mixed $claimTimeout): int
    {
        if (\is_int($claimTimeout)) {
            $folded = $claimTimeout;
        } elseif (\is_string($claimTimeout) && ctype_digit($claimTimeout)) {
            $folded = (int) $claimTimeout;
        } else {
            throw new ConfigException(
                'The events.deferred.claim_timeout knob must be an integer'
                . ' or an all-digit string, ' . get_debug_type($claimTimeout)
                . ' given.',
            );
        }

        if ($folded < 1) {
            throw new ConfigException(
                'The events.deferred.claim_timeout knob must be at least one'
                . ' second, got ' . $folded . ' -- a visibility timeout of'
                . " zero would hand a live drainer's claims to the next"
                . ' drainer mid-drain.',
            );
        }

        return $folded;
    }
}
