<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * One rendered predicate: the SQL fragment and its ordered bound
 * values, the pair every where-family fold answers.
 *
 * @internal
 */
final readonly class QueryFragment
{
    /**
     * @param list<mixed> $params
     */
    public function __construct(
        public string $fragment,
        public array $params = [],
    ) {
    }
}
