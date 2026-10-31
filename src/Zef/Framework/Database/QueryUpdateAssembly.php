<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The UPDATE and DELETE statements' assembly fold: the SET pairs plus
 * the where suffix (with the unbounded-write refusal in front) and the
 * WHERE-qualified delete -- each answering a {@see SqlQuery} whose
 * `unbounded` flag records a WHERE-less write the caller explicitly
 * confirmed.
 *
 * @internal
 */
final readonly class QueryUpdateAssembly
{
    public function __construct(
        private SqlIdentifier $sqlIdentifier = new SqlIdentifier(),
    ) {
    }

    public function compileUpdate(QuerySnapshot $querySnapshot, QueryWhere $queryWhere): SqlQuery
    {
        if ($querySnapshot->table === null) {
            throw new QueryException('Update query requires a table (call QueryBuilder::table() first).');
        }

        if ($querySnapshot->updatePairs === []) {
            throw new QueryException('Update query requires data (call update() first).');
        }

        if ($queryWhere->isEmpty() && !$querySnapshot->unboundedAllowed) {
            throw new QueryException(
                'Refusing unbounded UPDATE without WHERE; call allowUnbounded() to confirm a full-table update.',
            );
        }

        $sets = [];

        /** @var list<mixed> $params the positional values the SET pairs spell */
        $params = [];

        /** @var mixed $value one pair's value, an expression or a bound value */
        foreach ($querySnapshot->updatePairs as $column => $value) {
            if ($value instanceof SqlExpression) {
                $sets[] = $this->sqlIdentifier->quote($column, 'column') . ' = ' . $value;
            } else {
                $sets[] = $this->sqlIdentifier->quote($column, 'column') . ' = ?';
                /** @var null|bool|int|float|string $value the bound spelling of a validated pair */
                $params[] = $value;
            }
        }

        $sql = 'UPDATE ' . $querySnapshot->table . ' SET ' . implode(', ', $sets) . $queryWhere->suffix();

        return new SqlQuery(
            $sql,
            array_merge($params, $queryWhere->params()),
            $queryWhere->isEmpty(), // only reached when allowUnbounded() was set
        );
    }

    public function compileDelete(QuerySnapshot $querySnapshot, QueryWhere $queryWhere): SqlQuery
    {
        if ($querySnapshot->table === null) {
            throw new QueryException('Delete query requires a table (call QueryBuilder::table() first).');
        }

        if ($queryWhere->isEmpty() && !$querySnapshot->unboundedAllowed) {
            throw new QueryException(
                'Refusing unbounded DELETE without WHERE; call allowUnbounded() to confirm a full-table delete.',
            );
        }

        $sql = 'DELETE FROM ' . $querySnapshot->table . $queryWhere->suffix();

        return new SqlQuery(
            $sql,
            $queryWhere->params(),
            $queryWhere->isEmpty(), // only reached when allowUnbounded() was set
        );
    }
}
