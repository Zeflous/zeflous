<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The exportability verdict over a whole value -- the *data* verdict
 * the build entries' plain-data arguments answer before they cross
 * ({@see AotLiterals} keeps the expression lane's own rendering).
 *
 * Data folds, anything else refuses with the position named: enums
 * included, an object the data array cannot carry (the expression
 * lane folds them as code; {@see AotLiterals::export()}). The verdict
 * walks arrays to any depth, naming every position it refuses -- the
 * digest draws the same line for the same reason ({@see AotDigest}):
 * the two verdicts agree by construction, so an artifact whose digest
 * folds is an artifact whose literals render.
 */
final readonly class AotExportability
{
    /**
     * The exportability verdict over a whole value: data folds,
     * anything else refuses with the position named.
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
            foreach (array_keys($value) as $key) {
                self::assertExportable($value[$key], $serviceId, $path . '.' . $key);
            }

            return;
        }

        throw AotRefusals::notExportable($serviceId, $path, $value);
    }
}
