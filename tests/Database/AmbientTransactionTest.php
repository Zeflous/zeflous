<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\AmbientTransaction;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionManager;
use Zef\Test\Support\Database\RecordingPdo;

/**
 * The ambient transaction holder's boundary: the OPEN half of the
 * transaction story, pinned over the same real SQLite socket the
 * orchestration family rides -- the outermost around() opening the
 * real transaction, the nested around() JOINING as the savepoint the
 * nesting machine already spells (one BEGIN and one COMMIT for a
 * whole nested ambient tree, the inner throw rolling back only its
 * savepoint span, the outer throw the whole scope), the once-only
 * hook drain at the outermost boundary, and the isOpen() delegation
 * (the manager's own truth, raw connection transactions invisible to
 * it exactly as the orchestration contract spells). The closure
 * receives NOTHING: the connection is the noise the ambient
 * question exists to remove, so the pins write through the test's
 * own connection reference -- the ambient story itself.
 *
 * @internal
 */
#[CoversClass(AmbientTransaction::class)]
final class AmbientTransactionTest extends TestCase
{
    private ConnectionInterface $connection;

    private TransactionManager $transactionManager;

    private AmbientTransaction $ambientTransaction;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $this->connection->execute(SqlQuery::raw('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)'));

        $this->transactionManager = new TransactionManager($this->connection);
        $this->ambientTransaction = new AmbientTransaction($this->transactionManager);
    }

    // ------------------------------------------------------------------
    // The outermost bracket: open when none is open
    // ------------------------------------------------------------------

    public function testTheOutermostAroundAnswersItsBodysValueAndAppliesItsWrites(): void
    {
        // The zero-argument closure IS the arity pin: the connection
        // is the noise the ambient question exists to remove, so a
        // closure that demanded one would TypeError here.
        $result = $this->ambientTransaction->around(function (): int {
            $this->write('outer');

            return \count($this->names());
        });

        self::assertSame(1, $result, 'the scope answers whatever its body answers');
        self::assertSame(['outer'], $this->names(), 'the write applied at the commit');
        self::assertFalse($this->ambientTransaction->isOpen(), 'the outermost bracket closes at its boundary');
    }

    // ------------------------------------------------------------------
    // Joining IS nesting: one commit for the whole ambient tree
    // ------------------------------------------------------------------

    public function testNestedAroundsSpellOneCommitInTheVerbStream(): void
    {
        $recordingPdo = new RecordingPdo();
        $ambientTransaction = new AmbientTransaction(new TransactionManager($this->connectionOver($recordingPdo)));

        $ambientTransaction->around(static function () use ($ambientTransaction): void {
            $ambientTransaction->around(static function (): void {
            });
        });

        self::assertSame(
            [
                'BEGIN',
                'SAVEPOINT zef_sp2',
                'RELEASE SAVEPOINT zef_sp2',
                'COMMIT',
            ],
            $recordingPdo->verbs,
            'one BEGIN and one COMMIT: the nested around() joined the outermost scope as the'
                . ' savepoint the nesting machine already spells, never a second transaction',
        );
    }

    public function testTheInnerThrowRollsBackOnlyItsSavepointSpan(): void
    {
        $recordingPdo = new RecordingPdo();
        $connection = $this->connectionOver($recordingPdo);
        $ambientTransaction = new AmbientTransaction(new TransactionManager($connection));

        $ambientTransaction->around(static function () use ($connection, $ambientTransaction): void {
            $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('outer')"));

            try {
                $ambientTransaction->around(static function () use ($connection): never {
                    $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('inner')"));

                    throw new RuntimeException('inner fails');
                });
                // @phpstan-ignore-next-line (the scope always throws on this path)
                self::fail('Expected the inner failure to propagate.');
            } catch (RuntimeException) {
            }

            self::assertTrue($ambientTransaction->isOpen(), 'the inner bracket unwound to the still-open outer scope');
        });

        self::assertSame(
            [
                'BEGIN',
                'SAVEPOINT zef_sp2',
                'ROLLBACK TO SAVEPOINT zef_sp2',
                'COMMIT',
            ],
            $recordingPdo->verbs,
            'the inner throw rolled back to its savepoint span and the outermost boundary'
                . ' still committed',
        );
        self::assertSame(
            ['outer'],
            $this->namesOver($connection),
            "the outer writes survive the inner span's rollback",
        );
    }

    public function testTheOuterThrowRollsBackTheWholeScope(): void
    {
        $throwable = new RuntimeException('outer fails');

        try {
            $this->ambientTransaction->around(function () use ($throwable): never {
                $this->write('outer-doomed');

                $this->ambientTransaction->around(function (): void {
                    $this->write('inner-doomed');
                });

                throw $throwable;
            });
            // @phpstan-ignore-next-line (the scope always throws on this path)
            self::fail('Expected the outer failure to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($throwable, $runtimeException, 'the throwable propagates untouched, the same instance');
        }

        self::assertSame([], $this->names(), 'the whole scope rolled back, the nested span included');
        self::assertFalse($this->ambientTransaction->isOpen(), 'the outermost failure unwound every bracket');
    }

    public function testTheAmbientBracketJoinsAScopeTheExplicitHalfOpened(): void
    {
        $recordingPdo = new RecordingPdo();
        $connection = $this->connectionOver($recordingPdo);
        $transactionManager = new TransactionManager($connection);
        $ambientTransaction = new AmbientTransaction($transactionManager);

        $transactionManager->withTransaction(
            static function (ConnectionInterface $connection) use ($ambientTransaction): void {
                $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('explicit')"));

                $ambientTransaction->around(static function () use ($connection): void {
                    $connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('ambient')"));
                });
            },
        );

        self::assertSame(
            [
                'BEGIN',
                'SAVEPOINT zef_sp2',
                'RELEASE SAVEPOINT zef_sp2',
                'COMMIT',
            ],
            $recordingPdo->verbs,
            'the ambient bracket joined the scope the explicit half opened -- joining IS'
                . ' nesting, both halves over the one machine',
        );
        self::assertSame(['explicit', 'ambient'], $this->namesOver($connection));
    }

    // ------------------------------------------------------------------
    // The once-only hook drain at the outermost boundary
    // ------------------------------------------------------------------

    public function testHooksRegisteredInsideTheInnerScopeDrainAtTheOutermostCommitOnce(): void
    {
        $order = [];

        $this->ambientTransaction->around(function () use (&$order): void {
            $this->write('outer');

            $this->ambientTransaction->around(function () use (&$order): void {
                $this->write('inner');
                $this->queueHook($order, 'from-inner');
            });

            // The inner boundary released a savepoint, never the
            // transaction: the inner-registered hook must still be
            // waiting, exactly as the orchestration discipline spells.
            self::assertSame([], $order, 'no hook drains at an inner boundary');
            $this->queueHook($order, 'from-outer');
        });

        self::assertSame(['from-inner', 'from-outer'], $order, 'every hook drained at the outermost commit');
        self::assertSame(['outer', 'inner'], $this->names(), 'the whole tree committed once');
    }

    public function testTheDrainFiresOnceForTheNestedTree(): void
    {
        $fires = 0;

        $this->ambientTransaction->around(function () use (&$fires): void {
            $this->ambientTransaction->around(static function (): void {
            });
            $this->transactionManager->afterCommit(static function () use (&$fires): void {
                ++$fires;
            });
        });

        self::assertSame(1, $fires, 'the holder adds no second drain: once, at the outermost commit');
    }

    // ------------------------------------------------------------------
    // The isOpen() delegation: the manager's own truth
    // ------------------------------------------------------------------

    public function testIsOpenDelegatesToTheManagersOwnTruth(): void
    {
        self::assertFalse($this->ambientTransaction->isOpen(), 'no managed scope is open before any bracket');

        $this->ambientTransaction->around(function (): void {
            self::assertTrue($this->ambientTransaction->isOpen(), 'the outermost bracket is a managed scope');

            $this->ambientTransaction->around(static function (): void {
            });

            self::assertTrue($this->ambientTransaction->isOpen(), 'the inner bracket left the outer scope open');
        });

        self::assertFalse($this->ambientTransaction->isOpen(), 'the managed scope closed at the outermost boundary');
    }

    public function testRawConnectionTransactionsStayInvisibleToIsOpen(): void
    {
        // The raw half, opened directly on the connection, is NOT a
        // managed scope: isOpen() answers the manager's own truth,
        // which counts managed scopes only -- the delegation pin.
        $this->connection->beginTransaction();

        self::assertFalse($this->ambientTransaction->isOpen(), 'a raw connection transaction is not a managed scope');

        $this->connection->rollBack();
    }

    /**
     * @param list<string> $order
     */
    private function queueHook(array &$order, string $entry): void
    {
        $this->transactionManager->afterCommit(static function () use (&$order, $entry): void {
            $order[] = $entry;
        });
    }

    private function write(string $name): void
    {
        $this->connection->execute(SqlQuery::raw("INSERT INTO t (name) VALUES ('" . $name . "')"));
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return $this->namesOver($this->connection);
    }

    /**
     * @return list<string>
     */
    private function namesOver(ConnectionInterface $connection): array
    {
        $names = [];
        foreach ($connection->fetchAll(SqlQuery::raw('SELECT name FROM t ORDER BY id')) as $row) {
            self::assertIsString($row['name']);
            $names[] = $row['name'];
        }

        return $names;
    }

    private function connectionOver(RecordingPdo $recordingPdo): PdoConnection
    {
        $pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => ':memory:']),
            $recordingPdo,
        );
        $pdoConnection->execute(SqlQuery::raw('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)'));

        return $pdoConnection;
    }
}
