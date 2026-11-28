<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Override;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\DatabaseException;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\SqlQuery;

/**
 * The PDO-backed outbox store's port boundary: the one seam every
 * fold's engine touch rides, where the database family's own typed
 * refusals ({@see DatabaseException}, the family the database
 * lane's rule 17 spelling owns) become the port's declared
 * {@see InvalidArgumentException} -- the adapter's translation
 * spelled once, every verb of the connection contract carried
 * through the same fold, so the store's verbs are plain delegation
 * and the caller's catch clause is the one the port's docblock
 * promised on every verb alike. The original refusal rides
 * chained; the port's own refusals (the capacity arithmetic, the
 * decode's fail-closed walk, the construction grammars -- the
 * folds that never touch the engine) already answer the port's
 * family and never pass through here -- the same division of
 * labour the delivery store's own boundary spells (KDD-DP5).
 */
final readonly class PdoOutboxBoundary implements ConnectionInterface
{
    public function __construct(private ConnectionInterface $connection)
    {
    }

    #[Override]
    public function driver(): string
    {
        return $this->connection->driver();
    }

    #[Override]
    public function execute(SqlQuery $sqlQuery): int
    {
        return $this->translated(fn (): int => $this->connection->execute($sqlQuery));
    }

    #[Override]
    public function fetchAll(SqlQuery $sqlQuery): array
    {
        return $this->translated(fn (): array => $this->connection->fetchAll($sqlQuery));
    }

    #[Override]
    public function fetchOne(SqlQuery $sqlQuery): ?array
    {
        return $this->translated(fn (): ?array => $this->connection->fetchOne($sqlQuery));
    }

    #[Override]
    public function lastInsertId(): ?string
    {
        return $this->translated(fn (): ?string => $this->connection->lastInsertId());
    }

    #[Override]
    public function beginTransaction(?IsolationLevel $isolationLevel = null): void
    {
        $this->translated(
            fn (): null => $this->connection->beginTransaction($isolationLevel),
        );
    }

    #[Override]
    public function commit(): void
    {
        $this->translated(fn (): null => $this->connection->commit());
    }

    #[Override]
    public function rollBack(): void
    {
        $this->translated(fn (): null => $this->connection->rollBack());
    }

    #[Override]
    public function transactionLevel(): int
    {
        return $this->translated(fn (): int => $this->connection->transactionLevel());
    }

    #[Override]
    public function transaction(callable $fn, ?IsolationLevel $isolationLevel = null): mixed
    {
        return $this->translated(
            fn (): mixed => $this->connection->transaction($fn, $isolationLevel),
        );
    }

    /**
     * Carries one engine verb through the translation: the answer
     * as the engine spelled it, or the refusal in the port's own
     * family with the original chained.
     *
     * @template TAnswer
     *
     * @param callable(): TAnswer $verb the engine verb, curried with its own arguments
     *
     * @return TAnswer
     *
     * @throws InvalidArgumentException when the engine refuses the
     *                                  verb
     */
    private function translated(callable $verb): mixed
    {
        try {
            return $verb();
        } catch (DatabaseException $databaseException) {
            throw new InvalidArgumentException(
                'The outbox store refused: ' . $databaseException->getMessage(),
                0,
                $databaseException,
            );
        }
    }
}
