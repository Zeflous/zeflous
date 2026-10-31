<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The insert payload's validation fold: the shape rules a row set must
 * satisfy before the builder accepts it -- non-empty, string-keyed,
 * column sets identical across rows, and every value inside the bound
 * scalar answer set ({@see QueryValues::assertScalar()}). The rows are
 * rebuilt cell-by-cell as they validate, so the answer's type is the
 * validation's own record -- the analyser reads what the walk proved.
 *
 * @internal
 */
final class QueryInsertRows
{
    /**
     * Validates and accepts a multi-row insert payload.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, null|bool|float|int|SqlExpression|string>> the validated rows
     */
    public static function accept(SqlIdentifier $sqlIdentifier, array $rows): array
    {
        if ($rows === []) {
            throw new QueryException('insertRows() requires at least one row.');
        }

        $expected = null;
        $validatedRows = [];
        foreach ($rows as $i => $row) {
            if ($row === []) {
                throw new QueryException("insertRows() row {$i} must be a non-empty column => value map.");
            }

            if ($expected === null) {
                $expected = array_keys($row);
            } elseif (array_keys($row) !== $expected) {
                throw new QueryException("insertRows() row {$i} column set differs from the first row.");
            }

            $validatedRow = [];

            /** @var mixed $value one cell of the row, judged by the scalar guard below */
            foreach ($row as $column => $value) {
                $sqlIdentifier->quote($column, 'column');
                QueryValues::assertScalar($value, "insertRows() row {$i} value for '{$column}'");
                $validatedRow[$column] = $value;
            }

            $validatedRows[] = $validatedRow;
        }

        return $validatedRows;
    }
}
