<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use LogicException;
use Override;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\RetryBackoff;
use Zef\Framework\Database\RetryCandidates;
use Zef\Framework\Database\RetryMessages;
use Zef\Framework\Database\RetryTaxonomy;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Framework\Database\UnitOfWork;
use Zef\Framework\Database\UnitOfWorkFlush;
use Zef\Framework\Database\UnitOfWorkQueue;
use Zef\Framework\Database\UnitOfWorkReplay;
use Zef\Framework\Database\UnitOfWorkRetryPolicy;
use Zef\Framework\Database\UnitOfWorkWait;
use Zef\Test\Support\Database\ArmedPdoException;

/**
 * The retrying flush: flushRetrying() preserves the queue across retry
 * attempts and re-runs the operations until either success or policy
 * exhaustion -- with the backoff wait read through the sleeper seam, so
 * the delays the policy spells are observed without the suite waiting
 * them.
 *
 * Uses real SQLite in-memory + closures that throw transient failures on
 * the first N attempts (deterministic, no fake connection needed).
 *
 * @internal
 */
#[CoversClass(UnitOfWork::class)]
#[CoversClass(UnitOfWorkQueue::class)]
#[CoversClass(UnitOfWorkFlush::class)]
#[CoversClass(UnitOfWorkReplay::class)]
#[CoversClass(UnitOfWorkWait::class)]
#[CoversClass(UnitOfWorkRetryPolicy::class)]
#[CoversClass(RetryTaxonomy::class)]
#[CoversClass(RetryCandidates::class)]
#[CoversClass(RetryMessages::class)]
#[CoversClass(RetryBackoff::class)]
#[CoversClass(TransactionException::class)]
final class UnitOfWorkFlushRetryingTest extends TestCase
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

    public function testFlushRetryingSucceedsOnFirstAttempt(): void
    {
        $calls = [];
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$calls): void {
            $calls[] = 'op-1';
            $connection->execute(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['first']));
        });
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$calls): void {
            $calls[] = 'op-2';
            $connection->execute(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['second']));
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 3, initialDelayMs: 0);
        $executed = $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);

        self::assertSame(2, $executed);
        self::assertSame(['op-1', 'op-2'], $calls);
        self::assertSame(0, $unitOfWork->pending(), 'queue cleared on success');
    }

    public function testFlushRetryingReplaysOperationsOnTransientFailure(): void
    {
        $calls = [];
        $attempt = 0;
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$calls, &$attempt): void {
            $calls[] = 'op-1';
            ++$attempt;
            if ($attempt < 2) {
                throw self::makePdoException('deadlock', '40P01');
            }
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 3, initialDelayMs: 0);
        $executed = $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);

        self::assertSame(1, $executed, 'final attempt succeeded');
        self::assertSame(['op-1', 'op-1'], $calls, 'operation replayed on retry');
        self::assertSame(0, $unitOfWork->pending(), 'queue cleared on success');
    }

    /**
     * The message lane's end-to-end answer: a lost connection that
     * arrives with nothing but its message (the code-0 wrapper — no
     * SQLSTATE slot, no driver code) is replayed like any code-carrying
     * transient failure. Before the lane, this failure escaped the
     * retrying flush as non-retryable.
     */
    public function testFlushRetryingReplaysTheCodelessLostConnection(): void
    {
        $attempt = 0;
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$attempt): void {
            ++$attempt;
            if ($attempt < 2) {
                throw new PDOException('MySQL server has gone away', 0);
            }

            $connection->execute(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['survivor']));
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 3, initialDelayMs: 0);
        $executed = $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);

        self::assertSame(1, $executed, 'the replayed attempt succeeded');
        self::assertSame(2, $attempt, 'the codeless lost connection was retried once');
        self::assertSame(
            [['name' => 'survivor']],
            $this->connection->fetchAll(SqlQuery::raw('SELECT name FROM t')),
            'the retried write landed',
        );
    }

    public function testFlushRetryingPropagatesNonRetryableThrowable(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection): never {
            throw new LogicException('not retryable');
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $this->expectException(LogicException::class);
        $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
    }

    public function testFlushRetryingExhaustsAttemptsAndPropagatesLast(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection): never {
            throw self::makePdoException('deadlock', '40P01');
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 2, initialDelayMs: 0);
        $this->expectException(PDOException::class);
        $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
    }

    public function testFlushRetryingRefusesReentry(): void
    {
        $unitOfWork = new UnitOfWork();
        $queueValue = new ReflectionProperty($unitOfWork, 'unitOfWorkQueue')->getValue($unitOfWork);
        self::assertInstanceOf(UnitOfWorkQueue::class, $queueValue);
        new ReflectionProperty(UnitOfWorkQueue::class, 'flushing')->setValue($queueValue, true);

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $this->expectException(TransactionException::class);
        $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
    }

    public function testFlushRetryingEmptyQueueReturnsZero(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        self::assertSame(0, $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy));
    }

    public function testFlushRetryingPreservesQueueAcrossRetries(): void
    {
        $attempt = 0;
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$attempt): void {
            ++$attempt;
            if ($attempt < 3) {
                throw self::makePdoException('deadlock', '40P01');
            }
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 5, initialDelayMs: 0);
        $executed = $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);

        self::assertSame(1, $executed);
        self::assertSame(3, $attempt, 'tried 3 times before success');
        self::assertSame(0, $unitOfWork->pending(), 'queue cleared on success');
    }

    public function testFlushRetryingLeavesQueuePopulatedOnExhaustion(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection): never {
            throw self::makePdoException('deadlock', '40P01');
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 2, initialDelayMs: 0);

        try {
            $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
            self::fail('Expected the exhausted policy to propagate the last throwable.');
        } catch (PDOException) {
        }

        self::assertSame(1, $unitOfWork->pending(), 'the stale queue stays for a potential caller replay');
    }

    public function testFlushRetryingLeavesQueuePopulatedOnNonRetryableFailure(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection): never {
            throw new LogicException('not retryable');
        });

        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();

        try {
            $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
            self::fail('Expected the non-retryable throwable to propagate.');
        } catch (LogicException) {
        }

        self::assertSame(1, $unitOfWork->pending(), 'the stale queue stays for a potential caller replay');
    }

    public function testFlushRetryingRefusesMidReplayRecording(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use ($unitOfWork): void {
            $unitOfWork->record(static function (ConnectionInterface $connection): void {
            });
        });

        try {
            $unitOfWork->flushRetrying($this->connection, new UnitOfWorkRetryPolicy());
            self::fail('Expected mid-replay recording to be refused.');
        } catch (TransactionException $transactionException) {
            self::assertSame(
                'Cannot record operations while the UnitOfWork is flushing.',
                $transactionException->getMessage(),
            );
        }
    }

    public function testAFailedReplayLeavesTheUnitUsable(): void
    {
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection): never {
            throw self::makePdoException('deadlock', '40P01');
        });
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 2, initialDelayMs: 0);

        try {
            $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
            self::fail('Expected the exhausted policy to propagate.');
        } catch (PDOException) {
        }

        // The replay preserved its queue, and the unit unwound its
        // flushing state: the caller may replay through the plain
        // flush, or discard and record fresh operations.
        self::assertSame(1, $unitOfWork->pending());
        self::assertSame(1, $unitOfWork->discard(), 'the preserved operation is discarded by the explicit abort');
        $unitOfWork->recordQuery(new SqlQuery('INSERT INTO t (name) VALUES (?)', ['fresh']));
        self::assertSame(1, $unitOfWork->flush($this->connection));
    }

    public function testTheDefaultSleeperWaitsThePolicyDelay(): void
    {
        $unitOfWork = new UnitOfWork();
        $attempt = 0;
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$attempt): void {
            ++$attempt;
            if ($attempt < 2) {
                throw self::makePdoException('deadlock', '40P01');
            }
        });

        $startedAt = (int) hrtime(true);
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 2, initialDelayMs: 50);
        $executed = $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
        $elapsedNs = (int) hrtime(true) - $startedAt;

        self::assertSame(1, $executed);
        self::assertSame(2, $attempt);
        self::assertGreaterThanOrEqual(
            50_000_000,
            $elapsedNs,
            "the default seam blocked for the policy's full 50 ms delay",
        );
    }

    public function testTheBackoffWaitsReadTheSleeperSeamWithThePolicyDelays(): void
    {
        $waits = [];
        $attempt = 0;
        $unitOfWork = new UnitOfWork(static function (int $delayMs) use (&$waits): void {
            $waits[] = $delayMs;
        });
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$attempt): never {
            ++$attempt;

            throw self::makePdoException('deadlock', '40P01');
        });

        // 100ms initial, doubling, capped at 250ms: 100, 200, 250 for
        // the three waits a five-attempt policy spells.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(
            maxAttempts: 5,
            initialDelayMs: 100,
            maxDelayMs: 250,
            multiplier: 2.0,
        );

        try {
            $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);
            self::fail('Expected the exhausted policy to propagate.');
        } catch (PDOException) {
        }

        self::assertSame(5, $attempt, 'five attempts');
        self::assertSame([100, 200, 250, 250], $waits, 'the backoff doubles and clamps at the cap');
    }

    public function testTheDefaultSleeperWaitsWithoutASeam(): void
    {
        $attempt = 0;
        $unitOfWork = new UnitOfWork();
        $unitOfWork->record(static function (ConnectionInterface $connection) use (&$attempt): void {
            ++$attempt;
            if ($attempt < 2) {
                throw self::makePdoException('deadlock', '40P01');
            }
        });

        // The default seam blocks for real: the one retry waits the
        // policy's 1ms -- short enough to keep the suite quick, real
        // enough to exercise the engine's own sleep.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 2, initialDelayMs: 1);
        $executed = $unitOfWork->flushRetrying($this->connection, $unitOfWorkRetryPolicy);

        self::assertSame(1, $executed);
        self::assertSame(2, $attempt, 'the default seam waited and the retry ran');
    }

    /**
     * Builds the armed driver-failure double: the SQLSTATE slots real
     * driver errors populate through PHP internals, which the public
     * constructor cannot reach.
     */
    private static function makePdoException(string $message, string $sqlState): ArmedPdoException
    {
        return new ArmedPdoException($message, 0, [$sqlState, 0, $message]);
    }
}
