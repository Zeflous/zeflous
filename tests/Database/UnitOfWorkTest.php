<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Framework\Database\UnitOfWork;
use Zef\Framework\Database\UnitOfWorkFlush;
use Zef\Framework\Database\UnitOfWorkQueue;

/**
 * UoW-lite: the deferred-write queue with transactional flush semantics
 * (real SQLite integration + order-capturing fakes) -- FIFO execution,
 * the clear-before-execute failure story, the re-entry and mid-flush
 * recording refusals, and the discard abort.
 *
 * @internal
 */
#[CoversClass(UnitOfWork::class)]
#[CoversClass(UnitOfWorkQueue::class)]
#[CoversClass(UnitOfWorkFlush::class)]
#[CoversClass(TransactionException::class)]
#[CoversClass(QueryException::class)]
final class UnitOfWorkTest extends TestCase
{
    private ConnectionInterface $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $this->connection->execute(SqlQuery::raw('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)'));
    }

    public function testFlushExecutesRecordedQueriesFifo(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['first']));
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['second']));
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['third']));

        self::assertSame(3, $unitOfWork->pending());
        self::assertSame(3, $unitOfWork->flush($this->connection));
        self::assertSame(['first', 'second', 'third'], $this->names(), 'operations must run FIFO');
        self::assertSame(0, $unitOfWork->pending(), 'queue must be empty after a successful flush');
    }

    public function testFlushRunsArbitraryOperationsWithConnection(): void
    {
        $unitOfWork = new UnitOfWork();
        $seen = [];
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$seen): void {
            $seen[] = $connection::class;
        });
        $unitOfWork->record(static function (ConnectionInterface $connection): void {
            $connection->execute(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['from-closure']));
        });

        $unitOfWork->flush($this->connection);

        self::assertSame([PdoConnection::class], $seen, 'operations receive the flush connection');
        self::assertSame(['from-closure'], $this->names());
    }

    public function testFlushWithEmptyQueueReturnsZero(): void
    {
        $unitOfWork = new UnitOfWork();
        self::assertSame(0, $unitOfWork->pending());
        self::assertSame(0, $unitOfWork->flush($this->connection));
        self::assertSame([], $this->names());
    }

    public function testFlushFailureLeavesQueueEmptyAndPropagates(): void
    {
        $unitOfWork = new UnitOfWork();
        $ranSecond = false;
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['ok']));
        $unitOfWork->record(static function (ConnectionInterface $connection): void {
            $connection->execute(SqlQuery::raw('INSERT INTO t (missing_column) VALUES (1)'));
        });
        $unitOfWork->record(static function () use (&$ranSecond): void {
            $ranSecond = true;
        });

        self::assertSame(3, $unitOfWork->pending());

        try {
            $unitOfWork->flush($this->connection);
            self::fail('Expected the query failure to propagate.');
        } catch (QueryException) {
        }

        self::assertSame(0, $unitOfWork->pending(), 'queue is cleared BEFORE execution -- no partial replay');
        self::assertFalse($ranSecond, 'execution stops at the first failure');
        self::assertSame(['ok'], $this->names(), 'autocommit mode keeps already-applied operations');
    }

    public function testFlushInsideTransactionIsAtomic(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['kept']));

        $this->connection->beginTransaction();
        $unitOfWork->flush($this->connection);
        self::assertSame(['kept'], $this->names(), 'visible inside the open transaction');
        $this->connection->rollBack();
        self::assertSame([], $this->names(), 'transaction rollback reverts flushed writes');
    }

    public function testReentrantFlushIsRefused(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use ($unitOfWork): void {
            $unitOfWork->flush($connection);
        });

        try {
            $unitOfWork->flush($this->connection);
            self::fail('Expected the re-entrant flush to be refused.');
        } catch (TransactionException $transactionException) {
            self::assertSame('UnitOfWork::flush() re-entered while flushing.', $transactionException->getMessage());
        }
    }

    public function testRecordingDuringFlushIsRefused(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use ($unitOfWork): void {
            $unitOfWork->record(static function (ConnectionInterface $connection): void {
                $connection->execute(SqlQuery::raw('SELECT 1'));
            });
        });

        try {
            $unitOfWork->flush($this->connection);
            self::fail('Expected mid-flush recording to be refused.');
        } catch (TransactionException $transactionException) {
            self::assertSame(
                'Cannot record operations while the UnitOfWork is flushing.',
                $transactionException->getMessage(),
            );
        }
    }

    public function testDiscardDropsPendingOperations(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['one']));
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['two']));

        self::assertSame(2, $unitOfWork->discard());
        self::assertSame(0, $unitOfWork->pending());
        self::assertSame(0, $unitOfWork->flush($this->connection), 'flush after discard executes nothing');
        self::assertSame([], $this->names());
    }

    public function testQueueAcceptsNewOperationsAfterFailedFlush(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection): void {
            $connection->execute(SqlQuery::raw('INSERT INTO t (missing_column) VALUES (1)'));
        });

        try {
            $unitOfWork->flush($this->connection);
            self::fail('Expected the query failure to propagate.');
        } catch (QueryException) {
        }

        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['fresh']));
        self::assertSame(1, $unitOfWork->pending(), 'queue must accept operations after a failed flush');
        self::assertSame(1, $unitOfWork->flush($this->connection));
        self::assertSame(['fresh'], $this->names());
    }

    public function testQueueAcceptsNewOperationsAfterSuccessfulFlush(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['first']));
        self::assertSame(1, $unitOfWork->flush($this->connection));

        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['second']));
        self::assertSame(1, $unitOfWork->pending(), 'queue must accept operations after a successful flush');
        self::assertSame(1, $unitOfWork->flush($this->connection));
        self::assertSame(['first', 'second'], $this->names());
    }

    /** @return list<string> */
    private function names(): array
    {
        $names = [];
        foreach ($this->connection->fetchAll(SqlQuery::raw('SELECT name FROM t ORDER BY id')) as $row) {
            self::assertIsString($row['name']);
            $names[] = $row['name'];
        }

        return $names;
    }
}
