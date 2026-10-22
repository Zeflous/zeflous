<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use UnitEnum;

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
 * One object shape does fold as code: an enum case (`Status::ACTIVE`)
 * is a constant expression -- the case is a process-wide singleton, so
 * the rendered `\Status::ACTIVE` answers the identical instance the
 * full lane's baked plan holds, at any depth of a nested array. This
 * is the expression lane's line only ({@see export()}, the closure
 * bodies): the *data* verdict ({@see assertExportable()}) still
 * refuses enums, because a build entry's arguments cross as plain data
 * the artifact file's own array literal would have to `var_export()`
 * whole.
 *
 * The digest draws the same line for the same reason
 * ({@see AotDigest}): the two verdicts agree by construction, so an
 * artifact whose digest folds is an artifact whose literals render.
 */
final readonly class AotLiterals
{
    /**
     * Renders one data value or enum case as PHP code, refusing what
     * the boundary cannot carry ({@see AotExportability} keeps the
     * data verdict the build entries' plain-data arguments answer).
     *
     * @param string $serviceId the definition the value belongs to
     * @param string $path      the dotted position, for the refusal
     */
    public static function export(mixed $value, string $serviceId, string $path): string
    {
        if ($value === null || \is_scalar($value)) {
            return var_export($value, true);
        }

        if ($value instanceof UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }

        if (\is_array($value)) {
            return self::arrayExpression($value, $serviceId, $path);
        }

        throw AotRefusals::notExportable($serviceId, $path, $value);
    }

    /**
     * Renders an array as its bracket literal, each entry through the
     * same expression rendering (a nested enum case folds as the
     * constant expression it is; a nested object still refuses).
     *
     * @param array<array-key, mixed> $value     the array to render
     * @param string                  $serviceId the definition the value belongs to
     * @param string                  $path      the dotted position, for the refusal
     */
    private static function arrayExpression(array $value, string $serviceId, string $path): string
    {
        $rendered = [];

        foreach ($value as $key => $entry) {
            $rendered[] = var_export($key, true) . ' => ' . self::export($entry, $serviceId, $path . '.' . $key);
        }

        return '[' . implode(', ', $rendered) . ']';
    }
}
