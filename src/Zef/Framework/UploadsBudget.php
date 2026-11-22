<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The uploads family's byte-budget seam: the strict integer reading
 * the `uploads.max_bytes` knob answers -- at least one byte, the
 * integer itself or the all-digit string an environment overlay
 * writes, refused with the knob named otherwise -- the
 * {@see EventDeliveryCapacity} lineage replayed for the size
 * ceiling the lane's first writer enforces.
 *
 * The digit-string fold is the environment layering's own honesty:
 * `EnvSource` writes its values as the raw strings the environment
 * carries, so `APP_UPLOADS__MAX_BYTES=5242880` must fold to the
 * integer it spells rather than refusing a deployment that spelled
 * its ceiling the only way an environment can.
 *
 * The silent family's default the seam never answers: the
 * declaration owns it ({@see UploadsDeclaration::DEFAULT_MAX_BYTES}),
 * spelled where the framework profile and the shipped file can read
 * the same constant and so cannot drift apart.
 */
final class UploadsBudget
{
    /**
     * Reads the declared ceiling, refusing every shape the knob
     * cannot answer as an integer of at least one byte.
     *
     * @param mixed $value the `uploads.max_bytes` knob's value as the
     *                     frozen repository carries it
     *
     * @return positive-int the declared ceiling
     *
     * @throws ConfigException when the value is not an integer or an
     *                         all-digit string, or is below one byte
     */
    public static function read(mixed $value): int
    {
        if (\is_int($value)) {
            $ceiling = $value;
        } elseif (\is_string($value) && preg_match('/^\d+$/D', $value) === 1) {
            $ceiling = (int) $value;
        } else {
            throw new ConfigException(
                'uploads.max_bytes must be an integer of at least one byte, '
                . get_debug_type($value) . ' given.',
            );
        }

        if ($ceiling < 1) {
            throw new ConfigException(
                'uploads.max_bytes must be an integer of at least one byte.',
            );
        }

        return $ceiling;
    }
}
