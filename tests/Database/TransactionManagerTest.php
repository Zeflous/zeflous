<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\HookDrain;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Framework\Database\TransactionHooks;
use Zef\Framework\Database\TransactionManager;
use Zef\Framework\Database\TransactionManagerInterface;

/**
 * Transaction orchestration: TransactionManager over the connection
 * primitives (real SQLite integration + controlled fakes for
 * hook-lifecycle edges) -- the managed-scope bookkeeping, the FIFO hook
 * queue across the nested scope tree, the discard-on-rollback and
 * inline-during-drain semantics, and the hook failure that propagates
 * only after the commit it follows has been applied.
 *
 * @internal
 */
#[CoversClass(TransactionManager::class)]
#[CoversClass(TransactionHooks::class)]
#[CoversClass(HookDrain::class)]
#[CoversClass(TransactionException::class)]
#[CoversClass(ConnectionException::class)]
final class TransactionManagerTest extends TestCase
{
    private ConnectionInterface $connection;

    private TransactionManagerInterface $transactionManager;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $this->connection->execute(SqlQuery::raw('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)'));

        $this->transactionManager = new TransactionManager($this->connection);
    }

    // ------------------------------------------------------------------
    // Commit / rollback / nesting
    // ------------------------------------------------------------------

    public function testWithTransactionCommitsOnSuccess(): void
    {
        $result = $this->transactionManager->withTransaction(static function (ConnectionInterface $connection): int {
            $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('inside')"));

            return \count($connection->fetchAll(SqlQuery::raw('SELECT name FROM t')));
        });

        self::assertSame(1, $result);
        self::assertSame(['inside'], $this->names());
        self::assertSame(0, $this->transactionManager->level());
        self::assertFalse($this->transactionManager->inTransaction());
    }

    public function testWithTransactionRollsBackOnThrowable(): void
    {
        try {
            $this->transactionManager->withTransaction(static function (ConnectionInterface $connection): never {
                $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('doomed')"));

                throw new RuntimeException('boom');
            });
            // @phpstan-ignore-next-line (the scope always throws on this path)
            self::fail('Expected the runtime exception to propagate.');
        } catch (RuntimeException) {
        }

        self::assertSame([], $this->names());
        self::assertSame(0, $this->transactionManager->level(), 'scope depth must unwind on failure');
    }

    public function testNestedScopeRollbackKeepsOuterWrites(): void
    {
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection): void {
            $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('outer')"));

            try {
                $this->transactionManager->withTransaction(static function (ConnectionInterface $connection): never {
                    $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('inner')"));

                    throw new RuntimeException('inner fails');
                });
                // @phpstan-ignore-next-line (the scope always throws on this path)
                self::fail('Expected inner failure to propagate.');
            } catch (RuntimeException) {
            }

            self::assertSame(1, $this->transactionManager->level(), 'inner scope must have unwound');
        });

        self::assertSame(['outer'], $this->names(), 'savepoint rollback keeps outer writes');
    }

    public function testIsolationRejectedBySqliteSurfacesAsConnectionException(): void
    {
        try {
            $this->transactionManager->withTransaction(
                static fn (ConnectionInterface $connection): null => null,
                IsolationLevel::Serializable,
            );
            self::fail('SQLite must reject isolation levels.');
        } catch (ConnectionException) {
            self::assertSame([], $this->names(), 'failed begin must not leave writes');
        }
    }

    // ------------------------------------------------------------------
    // afterCommit scheduling
    // ------------------------------------------------------------------

    public function testAfterCommitRunsImmediatelyOutsideManagedScope(): void
    {
        $calls = [];
        $this->transactionManager->afterCommit(static function () use (&$calls): void {
            $calls[] = 'immediate';
        });

        self::assertSame(['immediate'], $calls);
        self::assertFalse($this->transactionManager->inTransaction());
    }

    public function testHooksFlushFifoAfterOutermostCommit(): void
    {
        /** @var list<string> $order */
        $order = [];
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$order): void {
            $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('row')"));
            $this->transactionManager->afterCommit(static function () use (&$order): void {
                $order[] = 'first';
            });
            $this->transactionManager->afterCommit(static function () use (&$order): void {
                $order[] = 'second';
            });
            self::assertSame([], $order, 'hooks must not run before the commit');
        });

        self::assertSame(['first', 'second'], $order);
        self::assertSame(['row'], $this->names());
    }

    public function testHooksFromNestedScopesFlushAfterOutermostCommit(): void
    {
        $order = [];
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$order): void {
            $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('a')"));
            $this->transactionManager->afterCommit(static function () use (&$order): void {
                $order[] = 'registered-before-inner';
            });
            $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$order): void {
                $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('b')"));
                $this->transactionManager->afterCommit(static function () use (&$order): void {
                    $order[] = 'from-inner';
                });
            });
            // The inner scope's commit released a SAVEPOINT, not the
            // transaction: every hook registered so far -- including
            // the inner one -- must still be waiting for the outermost
            // commit, never flushed at the inner boundary.
            self::assertSame([], $order, 'no hook flushes at an inner scope boundary');
            $this->transactionManager->afterCommit(static function () use (&$order): void {
                $order[] = 'from-outer';
            });
        });

        self::assertSame(['registered-before-inner', 'from-inner', 'from-outer'], $order);
        self::assertSame(['a', 'b'], $this->names());
    }

    public function testOutermostRollbackDiscardsAllHooks(): void
    {
        $fired = [];

        try {
            $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$fired): never {
                $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('x')"));
                $this->transactionManager->afterCommit(static function () use (&$fired): void {
                    $fired[] = 'never';
                });

                throw new RuntimeException('rollback path');
            });
            // @phpstan-ignore-next-line (the scope always throws on this path)
            self::fail('Expected failure.');
        } catch (RuntimeException) {
        }

        self::assertSame([], $fired, 'rolled-back scope must discard every hook');
        self::assertSame([], $this->names());
    }

    public function testHookRegisteredDuringFlushRunsInline(): void
    {
        $order = [];
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$order): void {
            $this->transactionManager->afterCommit(function () use (&$order): void {
                $order[] = 'outer-hook';
                $this->transactionManager->afterCommit(static function () use (&$order): void {
                    $order[] = 'inline-child';
                });
                $order[] = 'outer-hook-end';
            });
        });

        self::assertSame(['outer-hook', 'inline-child', 'outer-hook-end'], $order);
    }

    public function testInnerScopeFailurePreservesHooksQueuedByOuterScope(): void
    {
        $order = [];
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$order): void {
            $this->transactionManager->afterCommit(static function () use (&$order): void {
                $order[] = 'outer-hook';
            });

            try {
                // The never-closure guarantees the inner scope always
                // throws; the interesting assertion is that the outer
                // hook survives the savepoint rollback below.
                $this->transactionManager->withTransaction(static function (ConnectionInterface $connection): never {
                    throw new RuntimeException('inner fails');
                });
            } catch (RuntimeException) {
            }
        });

        self::assertSame(['outer-hook'], $order, 'inner failure must not discard outer hooks');
    }

    public function testImmediateHookIsNotRequeuedForNextScope(): void
    {
        $fires = 0;
        $this->transactionManager->afterCommit(static function () use (&$fires): void {
            ++$fires;
        });

        $this->transactionManager->withTransaction(static fn (ConnectionInterface $connection): null => null);

        // Exactly one: the immediate execution itself, never a second
        // firing at the scope's commit.
        self::assertSame(1, $fires, 'immediate hook must not fire again at the next commit');
    }

    public function testHookOpeningItsOwnScopeSchedulesInlineDuringDrain(): void
    {
        $order = [];
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$order): void {
            $this->transactionManager->afterCommit(function () use (&$order): void {
                $order[] = 'hook-start';
                $this->transactionManager->withTransaction(
                    function (ConnectionInterface $connection) use (&$order): void {
                        $this->transactionManager->afterCommit(static function () use (&$order): void {
                            $order[] = 'nested-inline';
                        });
                        $order[] = 'scope-body';
                    }
                );
                $order[] = 'hook-end';
            });
        });

        self::assertSame(['hook-start', 'nested-inline', 'scope-body', 'hook-end'], $order);
    }

    public function testFlushingFlagResetsAfterDrainFailure(): void
    {
        $hookThrew = false;

        try {
            $this->transactionManager->withTransaction(function (ConnectionInterface $connection): void {
                $this->transactionManager->afterCommit(static function (): never {
                    throw new TransactionException('hook fail');
                });
            });
        } catch (TransactionException) {
            $hookThrew = true;
        }

        self::assertTrue($hookThrew, 'Expected the hook failure to surface.');

        $order = [];
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection) use (&$order): void {
            $this->transactionManager->afterCommit(static function () use (&$order): void {
                $order[] = 'second';
            });
            $order[] = 'scope-body';
        });

        self::assertSame(['scope-body', 'second'], $order, 'a failed drain must not leave scheduling inline');
    }

    public function testHookThrowingPropagatesAfterCommitApplied(): void
    {
        try {
            $this->transactionManager->withTransaction(function (ConnectionInterface $connection): void {
                $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('persisted')"));
                $this->transactionManager->afterCommit(static function (): never {
                    throw new TransactionException('hook failed');
                });
            });
            self::fail('Expected the hook failure to surface.');
        } catch (TransactionException) {
        }

        self::assertSame(['persisted'], $this->names(), 'commit is applied even when a hook fails');
    }

    public function testScopeDepthAndInTransactionTracking(): void
    {
        self::assertFalse($this->transactionManager->inTransaction());
        self::assertSame(0, $this->transactionManager->level());
        $this->transactionManager->withTransaction(function (ConnectionInterface $connection): void {
            self::assertTrue($this->transactionManager->inTransaction());
            self::assertSame(1, $this->transactionManager->level());
            $this->transactionManager->withTransaction(static function (ConnectionInterface $connection): void {
            });
            self::assertSame(1, $this->transactionManager->level());
        });
        self::assertSame(0, $this->transactionManager->level());
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
