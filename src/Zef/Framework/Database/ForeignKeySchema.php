<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * One reflected foreign key: the referencing columns, the referenced
 * table, the referenced columns -- the triple the question owns --
 * plus the constraint's own name when the driver answers one.
 *
 * The name travels honestly: SQLite's `foreign_key_list` answers no
 * constraint name at all (only the ordinal id), so a reflected SQLite
 * foreign key carries null where MySQL's and PostgreSQL's
 * `information_schema` answers carry `CONSTRAINT_NAME` -- never a
 * fabricated `fk_0` the driver never spelled. The referencing and
 * referenced column lists pair one-to-one, and the value refuses any
 * list pair that does not: a foreign key maps each referencing column
 * onto exactly one referenced column, and a fold that answered
 * mismatched lengths would be answering a constraint no driver
 * declared.
 */
final readonly class ForeignKeySchema
{
    /** @var list<string> the referencing column names, in the driver's answer order */
    public array $columns;

    /** @var list<string> the referenced column names, paired one-to-one */
    public array $referencedColumns;

    /**
     * @param array<mixed> $columns           the referencing column names, in the driver's answer order
     * @param array<mixed> $referencedColumns the referenced column names, paired one-to-one
     *
     * @throws QueryException when the referenced table is empty, either column list is not a
     *                        non-empty list of non-empty strings, or the paired lengths differ
     */
    public function __construct(
        public ?string $name,
        array $columns,
        public string $referencedTable,
        array $referencedColumns,
    ) {
        if ($referencedTable === '') {
            throw new QueryException('The reflected foreign key references an empty table name.');
        }

        if ($columns === [] || $referencedColumns === []) {
            throw new QueryException(
                'The reflected foreign key columns must be a non-empty list.',
            );
        }

        if (!array_is_list($columns) || !array_is_list($referencedColumns)) {
            throw new QueryException(
                'The reflected foreign key column lists must be lists.',
            );
        }

        if (\count($columns) !== \count($referencedColumns)) {
            throw new QueryException(
                "The reflected foreign key on '{$referencedTable}' pairs "
                . \count($columns) . ' columns with ' . \count($referencedColumns)
                . ' referenced columns.',
            );
        }

        $this->columns = $this->nonBlankNames($columns, $referencedTable);
        $this->referencedColumns = $this->nonBlankNames($referencedColumns, $referencedTable);
    }

    /**
     * One side of the paired column lists, narrowed to the non-empty
     * string names a foreign key carries -- a blank name is no name,
     * and a non-string cell is a driver answering a shape the value
     * refuses to guess at. One fold spelled apart from the index
     * family's own loop, so the two value refusals share a doctrine
     * without sharing a token stream.
     *
     * @param array<mixed> $names one side of the paired column lists
     *
     * @return list<string> the narrowed names, answer order kept
     */
    private function nonBlankNames(array $names, string $referencedTable): array
    {
        $narrowed = [];

        foreach ($names as $name) {
            if (!\is_string($name) || $name === '') {
                throw new QueryException(
                    "The reflected foreign key on '{$referencedTable}' column names"
                    . ' must be non-empty strings.',
                );
            }

            $narrowed[] = $name;
        }

        return $narrowed;
    }
}
