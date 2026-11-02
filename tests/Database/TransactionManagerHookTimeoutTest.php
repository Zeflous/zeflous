<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\HookDrain;
use Zef\Framework\Database\HookDuration;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionHooks;
use Zef\Framework\Database\TransactionManager;
use Zef\Test\Support\Log\RecordingLogger;

/**
 * The cooperative slow-hook guard: when the TransactionManager receives
 * a non-null hook-duration threshold, each afterCommit hook is timed
 * and a debug-level log entry is emitted via the PSR-3 logger when the
 * threshold is exceeded. The threshold judgement itself
 * ({@see HookDuration}) is pinned at its exact boundaries by its own
 * test; this one pins the wiring around it.
 *
 * @internal
 */
#[CoversClass(TransactionManager::class)]
#[CoversClass(TransactionHooks::class)]
#[CoversClass(HookDrain::class)]
#[CoversClass(HookDuration::class)]
final class TransactionManagerHookTimeoutTest extends TestCase
{
    private ConnectionInterface $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $this->connection->execute(SqlQuery::raw('CREATE TABLE t (id INTEGER PRIMARY KEY)'));
    }

    public function testNoThresholdNoTimingAndNoLogging(): void
    {
        $recordingLogger = new RecordingLogger();
        $transactionManager = new TransactionManager($this->connection, null, $recordingLogger);

        $transactionManager->withTransaction(static function (ConnectionInterface $connection): void {
            $connection->execute(SqlQuery::raw('INSERT INTO t DEFAULT VALUES'));
        });
        // No afterCommit hooks registered -> no log entries, and no
        // timing path taken at all.
        self::assertSame([], $recordingLogger->calls);
    }

    public function testSlowHookLogsDebugEntry(): void
    {
        $recordingLogger = new RecordingLogger();
        $transactionManager = new TransactionManager($this->connection, 10, $recordingLogger); // 10 ms threshold

        $transactionManager->withTransaction(
            static function (ConnectionInterface $connection) use ($transactionManager): void {
                $connection->execute(SqlQuery::raw('INSERT INTO t DEFAULT VALUES'));
                $transactionManager->afterCommit(static function (): void {
                    usleep(50_000); // 50 ms -- well above the 10 ms threshold
                });
            },
        );

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('debug', $recordingLogger->calls[0][0]);
        self::assertStringContainsString(
            'slow-hook threshold',
            $recordingLogger->calls[0][1],
        );
        self::assertArrayHasKey('elapsed_ms', $recordingLogger->calls[0][2]);
        self::assertArrayHasKey('threshold_ms', $recordingLogger->calls[0][2]);
        self::assertSame(10, $recordingLogger->calls[0][2]['threshold_ms']);
        self::assertGreaterThanOrEqual(50, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testFastHookUnderThresholdDoesNotLog(): void
    {
        $recordingLogger = new RecordingLogger();
        $transactionManager = new TransactionManager($this->connection, 1_000, $recordingLogger); // 1 s threshold

        $transactionManager->withTransaction(
            static function (ConnectionInterface $connection) use ($transactionManager): void {
                $connection->execute(SqlQuery::raw('INSERT INTO t DEFAULT VALUES'));
                $transactionManager->afterCommit(static function (): void {
                    // No sleep -- well under 1 s
                });
            },
        );

        self::assertSame([], $recordingLogger->calls);
    }

    public function testNullLoggerIsSilentByDefault(): void
    {
        $transactionManager = new TransactionManager($this->connection, 1); // no logger arg -> silent

        $transactionManager->withTransaction(
            static function (ConnectionInterface $connection) use ($transactionManager): void {
                $connection->execute(SqlQuery::raw('INSERT INTO t DEFAULT VALUES'));
                $transactionManager->afterCommit(static function (): void {
                    usleep(50_000); // 50 ms -- well above 1 ms
                });
            },
        );

        // No assertion needed -- the silent default must not throw.
        $this->expectNotToPerformAssertions();
    }

    public function testEveryHookIsTimedIndependently(): void
    {
        $recordingLogger = new RecordingLogger();
        $transactionManager = new TransactionManager($this->connection, 10, $recordingLogger);

        $transactionManager->withTransaction(
            static function (ConnectionInterface $connection) use ($transactionManager): void {
                $transactionManager->afterCommit(static function (): void {
                    // Fast hook first.
                });
                $transactionManager->afterCommit(static function (): void {
                    usleep(50_000); // slow hook second
                });
            },
        );

        self::assertCount(1, $recordingLogger->calls, 'only the slow hook logs');
    }
}
