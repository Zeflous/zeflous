<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The uploads family's knob census: the closed set of knobs the
 * `uploads` map may carry, refused with the known list spelled out
 * when a map carries anything else -- the lineage the events
 * family's own {@see EventDeliveryKnobs} planted for the
 * composition-lane families, itself extracted from the HTTP lane's
 * {@see Http\StrictKnobs} seam.
 *
 * A typo'd key must never fall through to the silent posture
 * unnoticed: an `uploads.max_bytse` misspelling that fell through
 * would leave a deployment believing its ceiling is declared while
 * the writer keeps the family's own default -- the operator
 * believes the knob is live while the default's is, exactly the
 * miss the strict census exists to catch. The family's own
 * declaration ({@see UploadsDeclaration}) reads every knob through
 * its own seam and consults this census first, so the two can
 * never disagree about what the family owns.
 */
final class UploadsKnobs
{
    /**
     * The knobs the `uploads` family owns, in declaration order --
     * the one list the census and the reading share.
     */
    private const array KNOBS = ['path', 'max_bytes', 'types'];

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
                "The uploads configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: {$spelled}).",
            );
        }
    }
}
