<?php

declare(strict_types=1);

namespace Zef\Test\Support\Database;

use Override;
use RuntimeException;
use Throwable;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\SqlQuery;

/**
 * Scripted connection double: answers the fetch verbs from a queue of
 * scripted rows, so a consumer's decode guards can be driven through the
 * exact row shapes a healthy driver never produces (a missing aggregate
 * cell, a non-string bookkeeping column, an empty lock-row select) --
 * the fail-closed paths the recording PDO double cannot spell.
 *
 * Every verb a script did not answer is refused loudly: the double
 * never invents data.
 */
final class ScriptedConnection implements ConnectionInterface
{
    /** @var list<array<string, mixed>> rows the next fetchAll answers */
    public array $fetchAllScript = [];

    /** @var list<null|array<string, mixed>> rows the next fetchOne answers, in order; null answers no row */
    public array $fetchOneScript = [];

    /** @var list<int|Throwable> answers the next execute calls pop, in order */
    public array $executeScript = [];

    /** @var list<string> the SQL statements execute accepted, in order */
    public array $executed = [];

    #[Override]
    public function execute(SqlQuery $sqlQuery): int
    {
        $this->executed[] = $sqlQuery->sql;
        if ($this->executeScript === []) {
            return 0;
        }

        $answer = array_shift($this->executeScript);
        if ($answer instanceof Throwable) {
            throw $answer;
        }

        return $answer;
    }

    #[Override]
    public function fetchAll(SqlQuery $sqlQuery): array
    {
        $this->executed[] = $sqlQuery->sql;

        return $this->fetchAllScript;
    }

    #[Override]
    public function fetchOne(SqlQuery $sqlQuery): ?array
    {
        $this->executed[] = $sqlQuery->sql;
        if ($this->fetchOneScript === []) {
            return null;
        }

        return array_shift($this->fetchOneScript);
    }

    #[Override]
    public function lastInsertId(): ?string
    {
        throw new RuntimeException('ScriptedConnection has no script for lastInsertId().');
    }

    #[Override]
    public function beginTransaction(?IsolationLevel $isolationLevel = null): void
    {
        throw new RuntimeException('ScriptedConnection has no script for beginTransaction().');
    }

    #[Override]
    public function commit(): void
    {
        throw new RuntimeException('ScriptedConnection has no script for commit().');
    }

    #[Override]
    public function rollBack(): void
    {
        throw new RuntimeException('ScriptedConnection has no script for rollBack().');
    }

    #[Override]
    public function transactionLevel(): int
    {
        return 0;
    }

    #[Override]
    public function transaction(callable $fn, ?IsolationLevel $isolationLevel = null): mixed
    {
        throw new RuntimeException('ScriptedConnection has no script for transaction().');
    }
}
