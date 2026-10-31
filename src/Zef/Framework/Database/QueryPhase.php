<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The builder's phase vocabulary: the four query types and the three
 * guards that keep a verb legal for the phase the builder is in --
 * select-side verbs refused on write queries, write types never mixed,
 * WHERE refused on an insert. Every message names the method and the
 * phase it collided with.
 *
 * @internal
 */
final class QueryPhase
{
    public const string SELECT = 'select';

    public const string INSERT = 'insert';

    public const string UPDATE = 'update';

    public const string DELETE = 'delete';

    /**
     * Guards one select-side verb against a write phase.
     */
    public static function assertSelect(string $type, string $method): void
    {
        if ($type !== self::SELECT) {
            throw new QueryException("{$method} is not available on a {$type} query.");
        }
    }

    /**
     * Guards one where-family verb against the insert phase.
     */
    public static function assertWhereAllowed(string $type): void
    {
        if ($type === self::INSERT) {
            throw new QueryException('WHERE clauses are not available on an insert query.');
        }
    }

    /**
     * Answers the phase a write verb moves the builder into, refusing
     * a mix with a different write type.
     */
    public static function marked(string $current, string $type, string $method): string
    {
        if ($current !== self::SELECT && $current !== $type) {
            throw new QueryException("{$method} cannot be mixed with an existing {$current} query.");
        }

        return $type;
    }
}
