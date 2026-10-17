<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The counted total's own arithmetic: the reference's coercion made
 * explicit -- two integers stay integer, anything else joins as
 * float -- the unit the {@see CounterMeter}'s accumulation reads,
 * exactly the counts the engine always carried.
 */
final class MeterCount
{
    /**
     * The count advanced by one more amount: integers preserved
     * exactly, the mixed join answered as the float the reference's
     * own counts carried.
     */
    public static function advanced(float|int $count, float|int $value): float|int
    {
        if (\is_int($count)) {
            if (\is_int($value)) {
                return $count + $value;
            }
        }

        return (float) $count + (float) $value;
    }
}
