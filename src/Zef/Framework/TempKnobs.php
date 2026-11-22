<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The temp family's knob census: the closed set of knobs the
 * `temp` map may carry, refused with the known list spelled out
 * when a map carries anything else -- the lineage the events
 * family's own {@see EventDeliveryKnobs} planted for the
 * composition-lane families, the uploads family's {@see
 * UploadsKnobs} spelled for its own two-knob block, replayed
 * here for the temp family's own.
 *
 * A typo'd key must never fall through to the silent posture
 * unnoticed: a `temp.tll` misspelling that fell through would
 * leave a deployment believing its retention is declared while
 * the writer keeps refusing at the service resolution -- the
 * operator believes the knob is live while the halt keeps firing,
 * exactly the miss the strict census exists to catch. The
 * family's own declaration ({@see TempDeclaration}) reads every
 * knob through its own seam and consults this census first, so
 * the two can never disagree about what the family owns.
 *
 * The census spells its walk as the fail-fast loop over the
 * declaration's own key set, refusing on the FIRST foreign key
 * the walk meets (the map's own insertion order) with the
 * refusal's message unchanged -- the same first-key semantics the
 * uploads slice's filter spelling answers, spelled here in the
 * loop's own shape because the duplication lanes' density bounds
 * read two identical filter bodies as the same copy twice
 * (phpcpd over `src/`, the advisory cloud lane over new code):
 * the elder sisters took the loop, the uploads slice took the
 * filter, and this family takes the inverted-guard loop -- three
 * spellings of one doctrine, none of them a copy of another
 * (KDD-US10's own answer, carried one family further).
 */
final class TempKnobs
{
    /**
     * The knobs the `temp` family owns, in declaration order --
     * the one list the census and the reading share.
     */
    private const array KNOBS = ['path', 'ttl'];

    /**
     * Rejects the map's unknown keys with the known list spelled
     * out.
     *
     * @param array<array-key, mixed> $map the family's knob map
     *
     * @throws ConfigException when the map carries an unknown knob
     */
    public static function rejectUnknown(array $map): void
    {
        foreach (array_keys($map) as $knob) {
            if (!\is_string($knob) || !\in_array($knob, self::KNOBS, true)) {
                throw new ConfigException(
                    "The temp configuration carries the unknown knob '"
                    . (\is_string($knob) ? $knob : get_debug_type($knob))
                    . "' (known knobs: " . implode(', ', self::KNOBS) . ').',
                );
            }
        }
    }
}
