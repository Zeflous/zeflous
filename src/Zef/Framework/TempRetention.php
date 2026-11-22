<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The temp family's retention seam: the strict integer reading the
 * `temp.ttl` knob answers -- at least one second, the integer
 * itself or the all-digit string an environment overlay writes,
 * refused with the knob named otherwise -- the {@see
 * UploadsBudget} lineage replayed for the retention the lane's
 * writer judges its sweeps against.
 *
 * The digit-string fold is the environment layering's own
 * honesty ({@see UploadsBudget} spells it first): `EnvSource`
 * writes its values as the raw strings the environment carries,
 * so `APP_TEMP__TTL=3600` must fold to the integer it spells
 * rather than refusing a deployment that spelled its retention
 * the only way an environment can.
 *
 * The silent family's default the seam never answers: the temp
 * family carries none -- the TTL is itself the opt-in word, so
 * an absent knob is the silent posture the declaration owns, and
 * a writer armed without a retention is a question the wiring
 * refuses, never a default the seam invents.
 */
final class TempRetention
{
    /**
     * Reads the declared retention, refusing every shape the knob
     * cannot answer as an integer of at least one second.
     *
     * @param mixed $value the `temp.ttl` knob's value as the frozen
     *                    repository carries it
     *
     * @return positive-int the declared retention, in seconds
     *
     * @throws ConfigException when the value is not an integer or an
     *                         all-digit string, or is below one second
     */
    public static function read(mixed $value): int
    {
        if (\is_int($value)) {
            $seconds = $value;
        } elseif (\is_string($value) && preg_match('/^\d+$/D', $value) === 1) {
            $seconds = (int) $value;
        } else {
            throw new ConfigException(
                'temp.ttl must be an integer of at least one second, '
                . get_debug_type($value) . ' given.',
            );
        }

        if ($seconds < 1) {
            throw new ConfigException(
                'temp.ttl must be an integer of at least one second.',
            );
        }

        return $seconds;
    }
}
