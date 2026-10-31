<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The paging guards: a non-negative limit, and an offset that refuses
 * to stand without one.
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
     * Answers the validated offset, requiring the limit it pages
     * against.
     */
    public static function offset(int $offset, ?int $limit): int
    {
        if ($offset < 0) {
            throw new QueryException('offset() must be >= 0.');
        }

        if ($limit === null) {
            throw new QueryException('offset() requires limit() to be set first.');
        }

        return $offset;
    }
}
