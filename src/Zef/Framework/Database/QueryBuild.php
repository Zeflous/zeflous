<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The build dispatch: the one place the builder's phase meets its
 * collected snapshot and where-state, choosing the assembly that
 * answers the statement -- select, insert, update or delete -- over
 * the builder's own {@see SqlIdentifier} so the declared
 * {@see IdentifierQuoting} spells every assembly's identifiers
 * (the alias and the write-payload columns the assemblies quote
 * themselves).
 *
 * @internal
 */
final class QueryBuild
{
    public static function statement(
        string $type,
        QuerySnapshot $querySnapshot,
        QueryWhere $queryWhere,
        SqlIdentifier $sqlIdentifier,
    ): SqlQuery {
        return match ($type) {
            QueryPhase::SELECT => new QuerySelectAssembly($sqlIdentifier)->compile($querySnapshot, $queryWhere),
            QueryPhase::INSERT => new QueryInsertAssembly($sqlIdentifier)->compile($querySnapshot),
            QueryPhase::UPDATE => new QueryUpdateAssembly($sqlIdentifier)->compileUpdate($querySnapshot, $queryWhere),
            QueryPhase::DELETE => new QueryUpdateAssembly($sqlIdentifier)->compileDelete($querySnapshot, $queryWhere),
            default => throw new QueryException("Unknown query type '{$type}'."),
        };
    }
}
