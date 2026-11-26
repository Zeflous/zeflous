<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The view family's knob census: the closed set of knobs the `views`
 * map may carry, refused with the known list spelled out when a map
 * carries anything else -- the lineage the events family's own
 * {@see EventDeliveryKnobs} planted for the composition-lane
 * families, carried one family further the way the uploads
 * ({@see UploadsKnobs}) and temp ({@see TempKnobs}) slices carried
 * it.
 *
 * A typo'd key must never fall through to the silent posture
 * unnoticed: a `views.engien` misspelling that fell through would
 * leave a deployment believing its wired engine is declared while
 * the service resolution keeps refusing at the silent word -- the
 * operator believes the knob is live while the halt keeps firing,
 * exactly the miss the strict census exists to catch. The family's
 * own declaration ({@see ViewDeclaration}) reads every knob through
 * its own seam and consults this census first, so the two can never
 * disagree about what the family owns.
 *
 * The census spells its walk as the eldest sisters' positive-guard
 * loop ({@see EventDeliveryKnobs} and the database family's own
 * private fold), refusing on the first foreign key the walk meets:
 * the views slice takes the loop shape again because the refusal's
 * own message is the family's -- the message's own tokens keep the
 * run under both duplication lanes' floors (phpcpd over `src/`, the
 * advisory cloud lane over new code), the same accounting that let
 * the eldest sisters share the loop before it (KDD-US10's doctrine,
 * carried).
 */
final class ViewKnobs
{
    /**
     * The knobs the `views` family owns, in declaration order -- the
     * one list the census and the reading share.
     */
    private const array KNOBS = ['engine', 'path'];

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
        $spelled = implode(', ', self::KNOBS);

        foreach (array_keys($map) as $knob) {
            if (\is_string($knob) && \in_array($knob, self::KNOBS, true)) {
                continue;
            }

            throw new ConfigException(
                "The views configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: {$spelled}).",
            );
        }
    }
}
