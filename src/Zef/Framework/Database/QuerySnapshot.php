<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The builder's collected state at build time: the one readonly
 * snapshot the compilers read. The facade assembles it after its own
 * verbs have validated and rendered every part, so the assemblies stay
 * branch-pure over the snapshot -- the only questions left are which
 * clauses carry content.
 *
 * @internal
 */
final readonly class QuerySnapshot
{
    /**
     * @param list<string>               $columns     the rendered select columns
     * @param list<string>               $joins       the rendered join clauses
     * @param list<string>               $groupBy     the rendered group-by columns
     * @param list<string>               $orderBy     the rendered order-by clauses
     * @param list<array<string, mixed>> $insertRows  the validated insert rows
     * @param array<string, mixed>       $updatePairs the validated update pairs
     */
    public function __construct(
        public ?string $table,
        public ?string $tableAlias,
        public array $columns,
        public bool $distinct,
        public array $joins,
        public array $groupBy,
        public QueryHaving $having,
        public array $orderBy,
        public ?int $limit,
        public ?int $offset,
        public array $insertRows,
        public array $updatePairs,
        public bool $unboundedAllowed,
    ) {
    }
}
