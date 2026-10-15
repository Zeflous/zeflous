<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The strict-mode knob census the security families share: the map
 * may carry only the knobs its family owns, and an unknown key halts
 * the boot with the family's own list spelled out -- the lineage the
 * rate-limit, CSRF and auth families each followed in their own
 * knob classes, extracted here now that the third family made the
 * shape shared property rather than a coincidence.
 *
 * The known list is spelled out in the rejection: a typo'd key must
 * never fall through to the default posture unnoticed -- the operator
 * believes their knob is live while the default's is.
 */
final class StrictKnobs
{
    /**
     * Rejects the map's unknown keys with the known list spelled out.
     *
     * @param array<array-key, mixed> $map    the family's knob map
     * @param string                  $family the family's own name, as
     *                                        the rejection spells it
     * @param list<string>            $known  the knobs the family owns,
     *                                        in declaration order
     *
     * @throws HttpException when the map carries an unknown key
     */
    public static function rejectUnknown(array $map, string $family, array $known): void
    {
        $spelled = implode(', ', $known);

        foreach (array_keys($map) as $knob) {
            if (\is_string($knob) && \in_array($knob, $known, true)) {
                continue;
            }

            throw new HttpException(
                "The security.{$family} configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: {$spelled}).",
            );
        }
    }
}
