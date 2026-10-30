<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The events family's knob census: the closed set of knobs the
 * `events.deferred` map may carry, refused with the known list
 * spelled out when a map carries anything else -- the lineage the
 * HTTP lane's own {@see Http\StrictKnobs} spelled first, extracted
 * here per-family because the composition lane reads this census,
 * not the HTTP lane.
 *
 * A typo'd key must never fall through to the silent posture
 * unnoticed: an `events.deferred.capcity` misspelling that fell
 * through would leave a deployment believing its bound is declared
 * while the store keeps the default -- the operator believes the
 * knob is live while the default's is, exactly the miss the strict
 * census exists to catch. The family's own declaration ({@see
 * EventDeliveryDeclaration}) reads every knob through its own seam
 * and consults this census first, so the two can never disagree
 * about what the family owns.
 */
final class EventDeliveryKnobs
{
    /**
     * The knobs the `events.deferred` family owns, in declaration
     * order -- the one list the census and the reading share.
     */
    private const array KNOBS = ['store', 'path', 'capacity'];

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
                "The events.deferred configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: {$spelled}).",
            );
        }
    }
}
