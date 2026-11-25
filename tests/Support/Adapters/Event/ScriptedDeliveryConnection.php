<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Event;

use Override;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;

/**
 * The scripted-connection double the PDO delivery store's
 * scripted tier rides: a {@see ConnectionInterface} whose driver
 * word the test spells, whose statements are recorded for the
 * assertions that pin spellings, and whose fetch answers arrive
 * from a script -- one scripted answer per call, in order -- and
 * whose every verb refuses on demand, so a hostile row shape (the
 * engine contract the narrowing folds enforce), a dialect's exact
 * DDL and the port boundary's translated refusal are all
 * observable without the engines CI's socket never reaches. The
 * scripting doctrine is the Redis answer's own: the double answers
 * from the SERVER's side of the contract, never by replaying what
 * the fold under test would have said.
 *
 * @internal
 */
final class ScriptedDeliveryConnection implements ConnectionInterface
{
    /** @var list<string> every statement the connection executed, in order */
    public array $executedStatements = [];

    /** @var list<SqlQuery> every statement with its ordered params, in order */
    public array $executed = [];

    private int $fetchOneCursor = 0;

    private int $fetchAllCursor = 0;

    /**
     * @param list<null|array<string, null|bool|float|int|string>>  $fetchOneScript
     * @param list<list<array<string, null|bool|float|int|string>>> $fetchAllScript
     * @param bool                                                  $refusesOperations when true, every verb
     *                                                                                 refuses with the engine's own typed
     *                                                                                 family -- the port boundary's
     *                                                                                 translation observable per verb
     */
    public function __construct(
        private readonly string $driver,
        private array $fetchOneScript = [],
        private array $fetchAllScript = [],
        private readonly bool $refusesOperations = false,
    ) {
    }

    #[Override]
    public function driver(): string
    {
        return $this->driver;
    }

    #[Override]
    public function execute(SqlQuery $sqlQuery): int
    {
        $this->refuseWhenAsked();

        $this->executed[] = $sqlQuery;
        $this->executedStatements[] = $sqlQuery->sql;

        return 1;
    }

    #[Override]
    public function fetchAll(SqlQuery $sqlQuery): array
    {
        $this->refuseWhenAsked();

        $answer = $this->fetchAllScript[$this->fetchAllCursor] ?? [];

        ++$this->fetchAllCursor;

        return $answer;
    }

    #[Override]
    public function fetchOne(SqlQuery $sqlQuery): ?array
    {
        $this->refuseWhenAsked();

        if (!\array_key_exists($this->fetchOneCursor, $this->fetchOneScript)) {
            return null;
        }

        $answer = $this->fetchOneScript[$this->fetchOneCursor];

        ++$this->fetchOneCursor;

        return $answer;
    }

    #[Override]
    public function lastInsertId(): ?string
    {
        $this->refuseWhenAsked();

        return null;
    }

    #[Override]
    public function beginTransaction(?IsolationLevel $isolationLevel = null): void
    {
        $this->refuseWhenAsked();
    }

    #[Override]
    public function commit(): void
    {
        $this->refuseWhenAsked();
    }

    #[Override]
    public function rollBack(): void
    {
        $this->refuseWhenAsked();
    }

    #[Override]
    public function transactionLevel(): int
    {
        $this->refuseWhenAsked();

        return 0;
    }

    #[Override]
    public function transaction(callable $fn, ?IsolationLevel $isolationLevel = null): mixed
    {
        $this->refuseWhenAsked();

        return $fn($this);
    }

    /**
     * The on-demand refusal: the engine family's own typed
     * vocabulary, exactly what a refused statement arrives as.
     */
    private function refuseWhenAsked(): void
    {
        if ($this->refusesOperations) {
            throw new QueryException('The scripted connection refuses.');
        }
    }
}
