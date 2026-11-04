<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * One reflected index: the name the driver answers, the columns it
 * covers in the driver's own order, and its uniqueness verdict.
 *
 * The triple is carried as spelled, filtered by nothing: SQLite
 * answers its own implicit `sqlite_autoindex_*` guards (a UNIQUE
 * constraint's, a WITHOUT ROWID table's) with the indexes the
 * deployment declared, and the two `information_schema` dialects
 * their constraint-backed ones -- a fold that silently dropped the
 * driver's own answers would answer a shape the socket never
 * spelled, which is exactly the dishonesty this family refuses.
 */
final readonly class IndexSchema
{
    /** @var list<string> the covered column names, in the driver's answer order */
    public array $columns;

    /**
     * @param array<mixed> $columns the covered column names, in the driver's answer order
     *
     * @throws QueryException when the name is empty, the column list is not a list of
     *                        non-empty strings, or it covers no columns
     */
    public function __construct(
        public string $name,
        array $columns,
        public bool $unique,
    ) {
        if ($name === '') {
            throw new QueryException('The reflected index name must not be empty.');
        }

        if ($columns === []) {
            throw new QueryException("The reflected index '{$name}' covers no columns.");
        }

        if (!array_is_list($columns)) {
            throw new QueryException("The reflected index '{$name}' columns must be a list.");
        }

        $covered = [];

        foreach ($columns as $column) {
            if (!\is_string($column) || $column === '') {
                throw new QueryException(
                    "The reflected index '{$name}' column names must be non-empty strings.",
                );
            }

            $covered[] = $column;
        }

        $this->columns = $covered;
    }
}
