<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The paging guards: a non-negative limit, and a non-negative offset
 * riding limit's parameter -- an offset cannot stand without one, so
 * the pair travels as one verb and the ordering precondition is an
 * invariant of the signature instead of a runtime refusal.
 *
 * @internal
 */
final class QueryPaging
{
    /**
     * Answers the validated limit.
     */
    public static function limit(int $limit): int
    {
        if ($limit < 0) {
            throw new QueryException('limit() must be >= 0.');
        }

        return $limit;
    }

    /**
     * Answers the validated offset.
     */
    public static function offset(int $offset): int
    {
        if ($offset < 0) {
            throw new QueryException('offset() must be >= 0.');
        }

        return $offset;
    }
}
