<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;

/**
 * The positional value's PDO parameter type: the one fold the
 * statement run binds through, so a PHP value keeps its engine-side
 * spelling -- `false` answers a boolean binding (the engine's own
 * zero, never the empty string the array-lane binding stringifies it
 * into, silently corrupting every bool column it writes or compares),
 * an `int` an integer binding, `null` the NULL binding, and the
 * string and float families the string binding they share.
 *
 * The mapping is pure over the value's own type; the scalar grammar
 * upstream ({@see \Zef\Framework\Database\QueryValues::assertScalar()})
 * has already refused everything a {@see \Zef\Framework\Database\SqlQuery}
 * list can carry, so the default arm answers exactly those two
 * families.
 *
 * @internal
 */
final class PdoParamTypes
{
    /**
     * The PDO binding type one positional value travels as.
     */
    public static function of(mixed $value): int
    {
        return match (true) {
            \is_bool($value) => PDO::PARAM_BOOL,
            \is_int($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
