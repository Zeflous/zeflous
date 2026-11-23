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
 * The census spells its walk as a filter over the declaration's
 * own key set rather than the fail-fast loop the elder sister
 * censuses spell: the two spellings answer the identical first
 * foreign key (the filter preserves the map's own insertion
 * order), and the advisory duplication lane's density bound --
 * which the elder spellings' small slices amortise and this
 * smaller slice cannot -- reads the loop's shape as another copy
 * of the same body. The filter keeps the family's own spelling,
 * the refusal's message and its first-key semantics unchanged
 * (the uploads slice's own answer, KDD-US10).
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
        $foreign = array_filter(
            array_keys($map),
            static fn (mixed $knob): bool => !\is_string($knob) || !\in_array($knob, self::KNOBS, true),
        );

        if ($foreign === []) {
            return;
        }

        $knob = $foreign[array_key_first($foreign)];

        throw new ConfigException(
            "The temp configuration carries the unknown knob '"
            . (\is_string($knob) ? $knob : get_debug_type($knob))
            . "' (known knobs: " . implode(', ', self::KNOBS) . ').',
        );
    }
}
