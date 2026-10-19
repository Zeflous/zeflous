<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The data-only literal rendering of the artifact boundary: the one
 * exportability verdict and the one {@see var_export()} spelling every
 * rendered value passes through.
 *
 * The artifact is a plain PHP file whose array literal carries live
 * closures ({@see AotClosures}) -- and a closure's body can only embed
 * values PHP can spell as code. `var_export()` would *silently* emit
 * `ClassName::__set_state(...)` for an object and a callable-shaped
 * string for a closure: code that parses, then fatals the moment the
 * artifact is required. So the walk refuses what cannot honestly cross
 * the boundary -- an object, closure or resource anywhere at any depth --
 * at dump time, with the position and the way out in the message; arrays
 * and scalars (the whole data language the configuration layer's own
 * cache already declares) export verbatim.
 *
 * The digest draws the same line for the same reason
 * ({@see AotDigest}): the two verdicts agree by construction, so an
 * artifact whose digest folds is an artifact whose literals render.
 */
final readonly class AotLiterals
{
    /**
     * Renders one data value as PHP code, refusing what the boundary
     * cannot carry.
     *
     * @param string $serviceId the definition the value belongs to
     * @param string $path      the dotted position, for the refusal
     */
    public static function export(mixed $value, string $serviceId, string $path): string
    {
        self::assertExportable($value, $serviceId, $path);

        return var_export($value, true);
    }

    /**
     * The exportability verdict over a whole value: data folds, anything
     * else refuses with the position named.
     *
     * @param string $serviceId the definition the value belongs to
     * @param string $path      the dotted position, for the refusal
     */
    public static function assertExportable(mixed $value, string $serviceId, string $path): void
    {
        if ($value === null || \is_scalar($value)) {
            return;
        }

        if (\is_array($value)) {
            foreach ($value as $key => $entry) {
                self::assertExportable($entry, $serviceId, $path . '.' . $key);
            }

            return;
        }

        throw AotRefusals::notExportable($serviceId, $path, $value);
    }
}
