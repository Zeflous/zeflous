<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Closure;
use PDO;
use PDOException;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\TransactionException;

/**
 * The nested-transaction machine behind {@see PdoConnection}: explicit
 * SAVEPOINTs (`zef_sp2`, `zef_sp3`, ...) tracked with an internal depth
 * counter -- PDO::inTransaction() cannot distinguish nesting and is
 * never consulted -- plus the nesting ceiling. The isolation-request
 * rules and the administrative statements live in {@see PdoIsolation}.
 *
 * @internal
 */
final class PdoTransactions
{
    private const int MAX_NESTING = 16;

    private int $level = 0;

    /**
     * @throws TransactionException when nested and $isolation requested, or the nesting ceiling exceeded
     */
    public function begin(PDO $pdo, string $driver, ?IsolationLevel $isolationLevel): void
    {
        PdoIsolation::apply($pdo, $driver, $isolationLevel, $this->level);
        if ($this->level === 0) {
            $this->attempt($pdo->beginTransaction(...), 'begin transaction');
            ++$this->level;

            return;
        }

        // The level only ever moves one step per verb, so the exact
        // ceiling spelling and the comparison agree on every reachable
        // state -- the 17th nested begin meets level 16.
        if ($this->level === self::MAX_NESTING) {
            throw new TransactionException(
                'Transaction nesting limit of ' . self::MAX_NESTING . ' exceeded.',
            );
        }

        PdoIsolation::savepoint($pdo, $this->level);
        ++$this->level;
    }

    /**
     * @throws TransactionException when no transaction is open, or the driver refuses
     */
    public function commit(PDO $pdo): void
    {
        $this->assertOpen('commit()');
        if ($this->level === 1) {
            $this->attempt($pdo->commit(...), 'commit transaction');
            --$this->level;

            return;
        }

        PdoIsolation::release($pdo, $this->level);
        --$this->level;
    }

    /**
     * @throws TransactionException when no transaction is open, or the driver refuses
     */
    public function rollBack(PDO $pdo): void
    {
        $this->assertOpen('rollBack()');
        if ($this->level === 1) {
            $this->attempt($pdo->rollBack(...), 'roll back transaction');
            --$this->level;

            return;
        }

        PdoIsolation::rollbackTo($pdo, $this->level);
        --$this->level;
    }

    /**
     * Current nesting depth: 0 = no transaction, 1 = real transaction,
     * 2+ = inside savepoints.
     */
    public function level(): int
    {
        return $this->level;
    }

    /**
     * Guards a close verb against the level-zero misuse.
     */
    private function assertOpen(string $verb): void
    {
        if ($this->level === 0) {
            throw new TransactionException($verb . ' called outside a transaction.');
        }
    }

    /**
     * One driver primitive (beginTransaction, commit, rollBack), its
     * failure mapped onto the typed family with the verb named.
     */
    /**
     * One driver primitive (the begin/commit/rollback callable), its
     * failure mapped onto the typed family with the verb named.
     *
     * @param Closure(): bool $action
     */
    private function attempt(Closure $action, string $failure): void
    {
        try {
            $action();
        } catch (PDOException $pdoException) {
            throw new TransactionException(
                'Failed to ' . $failure . ': ' . $pdoException->getMessage(),
                0,
                $pdoException,
            );
        }
    }
}
