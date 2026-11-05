<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * One reflected column: the driver's own answer about a column of a
 * table -- the name, the declared type AS THE DRIVER SPELLS IT, the
 * nullability, the declared default in the driver's own literal
 * spelling, and the 1-based position within the primary key when the
 * column is a member.
 *
 * The value is pure over the answered row: it owns no driver
 * knowledge and reads no rows -- the adapter folds narrow the
 * dialect's answered rows into these constructor arguments, so every
 * fact a reflected column carries is a fact the driver spelled.
 * Cross-driver type unification (`INTEGER` vs `int(11)` vs the
 * PostgreSQL `information_schema` spelling) is deliberately absent:
 * that is the type-mapping slice's question, and this slice's
 * honesty is carrying the driver's own spelling untouched -- the
 * declared default included, in the driver's own literal form
 * (`'hi'` stays quoted, `NULL` stays the word, an expression default
 * stays its own SQL).
 */
final readonly class ColumnSchema
{
    /**
     * @param bool                       $nullable           whether the column answers NULL
     * @param null|bool|float|int|string $default            the declared default in the
     *                                                       driver's own literal spelling, or null
     *                                                       when the driver answered none
     * @param null|int                   $primaryKeyPosition the 1-based position within the
     *                                                       primary key, or null when the column is
     *                                                       no member
     *
     * @throws QueryException when the name is empty or the primary-key position is below one
     */
    public function __construct(
        public string $name,
        public string $declaredType,
        public bool $nullable,
        public bool|float|int|string|null $default,
        public ?int $primaryKeyPosition,
    ) {
        if ($name === '') {
            throw new QueryException('The reflected column name must not be empty.');
        }

        if ($primaryKeyPosition !== null && $primaryKeyPosition < 1) {
            throw new QueryException(
                "The reflected column '{$name}' answers a primary-key position below one.",
            );
        }
    }
}
