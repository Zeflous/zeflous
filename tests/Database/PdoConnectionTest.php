<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;

/**
 * The PDO adapter's behaviour over real SQLite in-memory: the four data
 * verbs (execute/fetchAll/fetchOne/lastInsertId with its no-answer
 * spellings), the nested-transaction savepoint walk with its exact level
 * transitions, the isolation refusal SQLite answers by concept, the
 * driver-error mapping onto the typed family (connect, prepare, execute
 * phases each named), and the transaction() closure's rollback-on-throw
 * with nesting.
 *
 * @internal
 */
#[CoversClass(PdoConnection::class)]
#[CoversClass(QueryException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(TransactionException::class)]
final class PdoConnectionTest extends TestCase
{
    private ConnectionInterface $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $this->connection->execute(SqlQuery::raw(
            'CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER)',
        ));
    }

    public function testInsertExecuteAnswersAffectedRows(): void
    {
        $affected = $this->connection->execute(
            QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build(),
        );

        self::assertSame(1, $affected);
    }

    public function testUpdateExecuteAnswersAffectedRows(): void
    {
        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());

        $affected = $this->connection->execute(
            QueryBuilder::table('t')->update(['age' => 21])->where('name', '=', 'Ari')->build(),
        );

        self::assertSame(1, $affected);
    }

    public function testFetchAllAnswersStringKeyedRows(): void
    {
        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());
        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Budi', 'age' => 17]])->build());

        $rows = $this->connection->fetchAll(
            QueryBuilder::table('t')->select('id', 'name', 'age')->orderBy('id')->build(),
        );

        self::assertSame([
            ['id' => 1, 'name' => 'Ari', 'age' => 20],
            ['id' => 2, 'name' => 'Budi', 'age' => 17],
        ], $rows);
    }

    public function testFetchOneAnswersTheFirstRowOrNull(): void
    {
        self::assertNull($this->connection->fetchOne(QueryBuilder::table('t')->select('id')->build()));

        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());
        $row = $this->connection->fetchOne(QueryBuilder::table('t')->select('name')->build());

        self::assertSame(['name' => 'Ari'], $row);
    }

    public function testLastInsertIdAnswersTheAutoIncrementValue(): void
    {
        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());
        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Budi', 'age' => 17]])->build());

        self::assertSame('2', $this->connection->lastInsertId());
    }

    public function testLastInsertIdAnswersNullBeforeAnyInsert(): void
    {
        // SQLite answers '0' until the first insert: the adapter folds
        // that spelling onto the null the port promises.
        self::assertNull($this->connection->lastInsertId());
    }

    // ------------------------------------------------------------------
    // Driver-error mapping
    // ------------------------------------------------------------------

    public function testPreparationFailureIsMappedOntoQueryException(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Preparation failed');

        $this->connection->execute(new SqlQuery('SELECT no_such_table_column FROM t'));
    }

    public function testExecutionFailureIsMappedOntoQueryExceptionWithTheSql(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Execution failed');
        $this->expectExceptionMessageIsOrContains('sql: INSERT INTO t (id, name, age) VALUES (1, ?, ?)');

        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());
        // The unique primary key collides on the second identical insert.
        $this->connection->execute(new SqlQuery('INSERT INTO t (id, name, age) VALUES (1, ?, ?)', ['Ari', 20]));
    }

    public function testFetchAllPreparationFailureIsMappedOntoQueryException(): void
    {
        // SQLite answers a missing table at PREPARE time: the mapping
        // names the phase the driver actually refused.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Preparation failed');

        $this->connection->fetchAll(new SqlQuery('SELECT * FROM missing_table'));
    }

    public function testTheMappedExceptionChainsTheOriginalDriverFailure(): void
    {
        try {
            $this->connection->execute(new SqlQuery('SELECT * FROM missing_table'));
            self::fail('The failing statement must map onto the typed family.');
        } catch (QueryException $queryException) {
            self::assertNotNull($queryException->getPrevious());
            self::assertInstanceOf(PDOException::class, $queryException->getPrevious());
        }
    }

    public function testConnectFailureIsMappedOntoConnectionException(): void
    {
        $pdoConnection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => '/nonexistent-directory-under-root/app.sqlite',
        ]));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Connection failed (sqlite)');

        $pdoConnection->execute(SqlQuery::raw('SELECT 1'));
    }

    // ------------------------------------------------------------------
    // Transaction primitives
    // ------------------------------------------------------------------

    public function testCommitOutsideATransactionIsRefused(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessageIsOrContains('commit() called outside a transaction.');

        $this->connection->commit();
    }

    public function testRollbackOutsideATransactionIsRefused(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessageIsOrContains('rollBack() called outside a transaction.');

        $this->connection->rollBack();
    }

    public function testSavepointLevelTransitionsAreExact(): void
    {
        $this->connection->beginTransaction();
        self::assertSame(1, $this->connection->transactionLevel());

        $this->connection->beginTransaction();
        self::assertSame(2, $this->connection->transactionLevel());

        $this->connection->beginTransaction();
        self::assertSame(3, $this->connection->transactionLevel());

        $this->connection->rollBack();
        self::assertSame(2, $this->connection->transactionLevel());

        $this->connection->commit();
        self::assertSame(1, $this->connection->transactionLevel());

        $this->connection->commit();
        self::assertSame(0, $this->connection->transactionLevel());
    }

    public function testNestedCommitReleasesTheSavepointWithoutCommitting(): void
    {
        $this->connection->beginTransaction();
        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());
        $this->connection->beginTransaction();

        $this->connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Budi', 'age' => 17]])->build());
        $this->connection->rollBack();
        $this->connection->commit();

        // The savepoint insert rolled back; the outer insert committed.
        self::assertSame(1, $this->countRows());
    }

    public function testTheNestingCeilingIsRefused(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessageIsOrContains('Transaction nesting limit of 16 exceeded.');

        for ($i = 0; $i < 17; ++$i) {
            $this->connection->beginTransaction();
        }
    }

    public function testIsolationOnSqliteIsRefusedByConcept(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            'SQLite does not support isolation levels; pass null instead of SERIALIZABLE.',
        );

        $this->connection->beginTransaction(IsolationLevel::Serializable);
    }

    public function testIsolationOnANestedSavepointIsRefused(): void
    {
        $this->connection->beginTransaction();

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessageIsOrContains(
            'Isolation level may only be requested on the outermost transaction (current level: 1).',
        );

        $this->connection->beginTransaction(IsolationLevel::ReadCommitted);
    }

    // ------------------------------------------------------------------
    // transaction() closure
    // ------------------------------------------------------------------

    public function testTheClosureCommitsItsAnswer(): void
    {
        $answer = $this->connection->transaction(
            static function (ConnectionInterface $connection): int {
                $connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());

                return \count($connection->fetchAll(QueryBuilder::table('t')->select('id')->build()));
            },
        );

        self::assertSame(1, $answer);
        self::assertSame(0, $this->connection->transactionLevel());
        self::assertSame(1, $this->countRows());
    }

    public function testTheClosureRollsBackOnThrow(): void
    {
        try {
            $this->connection->transaction(static function (ConnectionInterface $connection): never {
                $connection->execute(QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build());

                throw new RuntimeException('handler failed');
            });
        } catch (RuntimeException $runtimeException) {
            self::assertSame('handler failed', $runtimeException->getMessage());
        }

        self::assertSame(0, $this->connection->transactionLevel());
        self::assertSame(0, $this->countRows());
    }

    public function testNestedClosuresBecomeSavepointsAndTheOuterRollbackWins(): void
    {
        try {
            $this->connection->transaction(
                static function (ConnectionInterface $connection): never {
                    $connection->execute(
                        QueryBuilder::table('t')->insertRows([['name' => 'Ari', 'age' => 20]])->build(),
                    );
                    $connection->transaction(
                        static function (ConnectionInterface $connection): void {
                            $connection->execute(
                                QueryBuilder::table('t')->insertRows([['name' => 'Budi', 'age' => 17]])->build(),
                            );
                        },
                    );

                    throw new RuntimeException('outer failed');
                },
            );
        } catch (RuntimeException $runtimeException) {
            self::assertSame('outer failed', $runtimeException->getMessage());
        }

        self::assertSame(0, $this->connection->transactionLevel());
        self::assertSame(0, $this->countRows());
    }

    public function testTheNestedClosureDropsItsIsolationArgumentSilently(): void
    {
        // A nested transaction() call cannot apply isolation (SQLite
        // would refuse it on the outermost begin); the port's contract
        // drops the argument silently rather than throwing.
        $this->connection->beginTransaction();

        $answer = $this->connection->transaction(
            static fn (ConnectionInterface $connection): string => $connection->transactionLevel() > 0
                ? 'kept'
                : 'dropped',
            IsolationLevel::Serializable,
        );

        self::assertSame('kept', $answer);
        $this->connection->commit();
    }

    public function testThePresetHandleIsUsedAsGiven(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE preset (id INTEGER)');

        $pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => ':memory:']),
            $pdo,
        );

        $pdoConnection->execute(new SqlQuery('INSERT INTO preset (id) VALUES (1)'));

        $rows = $pdoConnection->fetchAll(new SqlQuery('SELECT id FROM preset'));

        self::assertSame([['id' => 1]], $rows);
    }

    // ------------------------------------------------------------------
    // Data verbs
    // ------------------------------------------------------------------

    /**
     * The count() aggregate over the fixture table, narrowed to an int
     * the assertions read.
     */
    private function countRows(): int
    {
        $row = $this->connection->fetchOne(QueryBuilder::table('t')->count()->build());
        self::assertIsArray($row);
        $aggregate = $row['aggregate'] ?? null;
        if (!\is_int($aggregate) && !\is_float($aggregate) && !\is_string($aggregate)) {
            self::fail('count() returned no aggregate value.');
        }

        return (int) $aggregate;
    }
}
