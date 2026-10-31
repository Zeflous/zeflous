<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\TransactionException;

/**
 * The nested-transaction machine behind {@see PdoConnection}: explicit
 * SAVEPOINTs (`zef_sp2`, `zef_sp3`, ...) issued per verb -- PDO::inTransaction()
 * cannot distinguish nesting and is never consulted. The depth counter,
 * its ceiling and the level-zero guard live in {@see PdoTransactionDepth};
 * the isolation-request rules and the administrative statements live in
 * {@see PdoIsolation}; the driver primitives' failure mapping lives in
 * {@see PdoPrimitive}.
 *
 * @internal
 */
final readonly class PdoTransactions
{
    private PdoTransactionDepth $pdoTransactionDepth;

    public function __construct()
    {
        $this->pdoTransactionDepth = new PdoTransactionDepth();
    }

    /**
     * @throws TransactionException when nested and $isolation requested, the
     *                              nesting ceiling exceeded, or the driver refuses
     * @throws QueryException       when the driver refuses an administrative statement
     */
    public function begin(PDO $pdo, string $driver, ?IsolationLevel $isolationLevel): void
    {
        PdoIsolation::apply($pdo, $driver, $isolationLevel, $this->pdoTransactionDepth->level());
        if ($this->pdoTransactionDepth->level() === 0) {
            PdoPrimitive::run($pdo->beginTransaction(...), 'begin transaction');
            $this->pdoTransactionDepth->enter();

            return;
        }

        $this->pdoTransactionDepth->assertRoomForOneMore();
        PdoIsolation::savepoint($pdo, $this->pdoTransactionDepth->level());
        $this->pdoTransactionDepth->enter();
    }

    /**
     * @throws TransactionException when no transaction is open, or the driver refuses
     * @throws QueryException       when the driver refuses the savepoint release
     */
    public function commit(PDO $pdo): void
    {
        $this->pdoTransactionDepth->assertOpen('commit()');
        if ($this->pdoTransactionDepth->level() === 1) {
            PdoPrimitive::run($pdo->commit(...), 'commit transaction');
            $this->pdoTransactionDepth->leave();

            return;
        }

        PdoIsolation::release($pdo, $this->pdoTransactionDepth->level());
        $this->pdoTransactionDepth->leave();
    }

    /**
     * @throws TransactionException when no transaction is open, or the driver refuses
     * @throws QueryException       when the driver refuses the savepoint restore
     */
    public function rollBack(PDO $pdo): void
    {
        $this->pdoTransactionDepth->assertOpen('rollBack()');
        if ($this->pdoTransactionDepth->level() === 1) {
            PdoPrimitive::run($pdo->rollBack(...), 'roll back transaction');
            $this->pdoTransactionDepth->leave();

            return;
        }

        PdoIsolation::rollbackTo($pdo, $this->pdoTransactionDepth->level());
        $this->pdoTransactionDepth->leave();
    }

    /**
     * Current nesting depth: 0 = no transaction, 1 = real transaction,
     * 2+ = inside savepoints.
     */
    public function level(): int
    {
        return $this->pdoTransactionDepth->level();
    }
}
