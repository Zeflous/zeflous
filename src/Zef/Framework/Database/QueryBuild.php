<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The build dispatch: the one place the builder's phase meets its
 * collected snapshot and where-state, choosing the assembly that
 * answers the statement -- select, insert, update or delete.
 *
 * @internal
 */
final class QueryBuild
{
    public static function statement(string $type, QuerySnapshot $querySnapshot, QueryWhere $queryWhere): SqlQuery
    {
        return match ($type) {
            QueryPhase::SELECT => new QuerySelectAssembly()->compile($querySnapshot, $queryWhere),
            QueryPhase::INSERT => new QueryInsertAssembly()->compile($querySnapshot),
            QueryPhase::UPDATE => new QueryUpdateAssembly()->compileUpdate($querySnapshot, $queryWhere),
            QueryPhase::DELETE => new QueryUpdateAssembly()->compileDelete($querySnapshot, $queryWhere),
            default => throw new QueryException("Unknown query type '{$type}'."),
        };
    }
}
