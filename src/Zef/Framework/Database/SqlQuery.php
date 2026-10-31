<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * Immutable value object pairing a SQL statement with its ordered
 * positional parameters.
 *
 * `params` MUST be a list -- named placeholders are deliberately not
 * supported so parameter order is always explicit and testable, and a
 * map-shaped argument is refused at construction. Use
 * {@see SqlQuery::raw()} for statements that carry no parameters
 * (typically DDL).
 *
 * The `unbounded` flag marks an UPDATE/DELETE that was deliberately
 * built without a WHERE clause (the builder's {@see QueryBuilder::allowUnbounded()}
 * confirmation); consumers may audit such statements.
 */
final readonly class SqlQuery
{
    /** @var list<mixed> positional values in statement order */
    public array $params;

    /**
     * @param array<mixed> $params positional values in statement order
     */
    public function __construct(
        public string $sql,
        array $params = [],
        public bool $unbounded = false,
    ) {
        if ($sql === '') {
            throw new QueryException('SQL statement must not be empty.');
        }

        if (!array_is_list($params)) {
            throw new QueryException('SqlQuery params must be a list of positional values.');
        }

        $this->params = $params;
    }

    /**
     * Raw statement without parameters (DDL, admin statements).
     */
    public static function raw(string $sql): self
    {
        return new self($sql, []);
    }
}
