<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Closure;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\MigrationAcquire;
use Zef\Framework\Database\MigrationBookkeeping;
use Zef\Framework\Database\MigrationException;
use Zef\Framework\Database\MigrationInterface;
use Zef\Framework\Database\MigrationLock;
use Zef\Framework\Database\MigrationLockAge;
use Zef\Framework\Database\MigrationLockRow;
use Zef\Framework\Database\MigrationPending;
use Zef\Framework\Database\MigrationRegistration;
use Zef\Framework\Database\MigrationRollback;
use Zef\Framework\Database\MigrationRun;
use Zef\Framework\Database\MigrationStep;
use Zef\Framework\Database\MigrationTtl;
use Zef\Framework\Database\Migrator;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Test\Support\Database\ScriptedMigration;

/**
 * Migration lock TTL hardening through the runner itself: per-migration
 * getLockTtl() override, heartbeat renewal before every step, and steal
 * decisions that honour the TTL recorded on the lock row.
 * Deterministic via injected wall-clock on real SQLite in-memory.
 *
 * @internal
 */
#[CoversClass(Migrator::class)]
#[CoversClass(MigrationException::class)]
#[CoversClass(MigrationRegistration::class)]
#[CoversClass(MigrationBookkeeping::class)]
#[CoversClass(MigrationPending::class)]
#[CoversClass(MigrationStep::class)]
#[CoversClass(MigrationRun::class)]
#[CoversClass(MigrationLock::class)]
#[CoversClass(MigrationLockRow::class)]
#[CoversClass(MigrationLockAge::class)]
#[CoversClass(MigrationAcquire::class)]
#[CoversClass(MigrationTtl::class)]
#[CoversClass(MigrationRollback::class)]
#[CoversClass(TransactionException::class)]
final class MigratorTtlTest extends TestCase
{
    private const int T0 = 1_700_000_000;

    private PdoConnection $pdoConnection;

    private int $now = self::T0;

    #[Override]
    protected function setUp(): void
    {
        $this->pdoConnection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $this->now = self::T0;
    }

    public function testOverrideTtlIsHeartbeatedOntoTheLockRow(): void
    {
        /** @var list<array<string, mixed>> $captured */
        $captured = [];
        $m = $this->migrator();
        $m->register($this->migration(
            '20260101000001',
            900.0,
            static function (ConnectionInterface $connection) use (&$captured): void {
                $captured = $connection->fetchAll(
                    QueryBuilder::table(MigrationLockRow::LOCK_TABLE)->select('locked_at', 'ttl')->build(),
                );
            },
        ));

        self::assertSame(['20260101000001'], $m->migrate());
        self::assertCount(1, $captured, 'up() must observe the heartbeat-renewed lock row');
        self::assertSame(self::T0, $captured[0]['locked_at']);
        self::assertSame(900.0, $captured[0]['ttl']);
    }

    public function testNullOverrideFallsBackToMigratorDefault(): void
    {
        /** @var list<array<string, mixed>> $captured */
        $captured = [];
        $m = $this->migrator(123.5);
        $m->register($this->migration(
            '20260101000001',
            null,
            static function (ConnectionInterface $connection) use (&$captured): void {
                $captured = $connection->fetchAll(
                    QueryBuilder::table(MigrationLockRow::LOCK_TABLE)->select('locked_at', 'ttl')->build(),
                );
            },
        ));

        self::assertSame(['20260101000001'], $m->migrate());
        self::assertSame(123.5, $captured[0]['ttl'], 'null override must renew with the Migrator default TTL');
    }

    public function testNonPositiveOverrideRejected(): void
    {
        $m = $this->migrator();
        $m->register($this->migration('20260101000001', 0.0));

        try {
            $m->migrate();
            self::fail('non-positive TTL override must be rejected');
        } catch (MigrationException $migrationException) {
            self::assertStringContainsString(
                "'20260101000001' lock TTL override must be greater than zero",
                $migrationException->getMessage(),
            );
        }
    }

    public function testRowTtlGuardsTheStealDecision(): void
    {
        // A stale-looking holder (age 400s) declared ttl 900s: NOT stale.
        $this->seedLockRow(self::T0 - 400, 900.0);
        $m = $this->migrator(300.0);
        $m->register($this->migration('20260101000001'));

        try {
            $m->migrate();
            self::fail('a lock within its own row TTL must not be stolen');
        } catch (TransactionException $transactionException) {
            self::assertStringContainsString('already held (age 400s, ttl 900s)', $transactionException->getMessage());
        }

        self::assertNotNull($this->lockRow(), 'the declared holder must keep its lock');
    }

    public function testExpiredRowTtlIsStolen(): void
    {
        // Same age (400s) but the row only declared ttl 100s: stale, steal.
        $this->seedLockRow(self::T0 - 400, 100.0);
        $m = $this->migrator(300.0);
        $m->register($this->migration('20260101000001'));

        self::assertSame(['20260101000001'], $m->migrate(), 'an expired row TTL must be stealable');
        self::assertNull($this->lockRow(), 'the stealer releases the lock when done');
    }

    public function testHeartbeatWithHighOverridePreventsMidRunSteal(): void
    {
        // The audit scenario: a legitimate 400-second step would
        // exceed the default 300s TTL, so the migration declares 900s;
        // a second runner trying to steal mid-step must be refused.
        $m = $this->migrator(300.0);
        $m->register($this->migration('20260101000001', 900.0, function (ConnectionInterface $connection): void {
            $this->now = self::T0 + 400; // the step is 400s in -- past the default TTL
            $migrator = new Migrator($connection, fn (): int => $this->now, 300.0);
            $migrator->register($this->migration('20260101000002'));

            try {
                $migrator->migrate();
                self::fail('a runner must not steal a heartbeat-protected lock mid-step');
            } catch (TransactionException $transactionException) {
                self::assertStringContainsString(
                    'already held (age 400s, ttl 900s)',
                    $transactionException->getMessage(),
                );
            }
        }));

        self::assertSame(['20260101000001'], $m->migrate());
    }

    public function testHeartbeatUsesOverriddenTtlDuringRollbackToo(): void
    {
        /** @var list<array<string, mixed>> $captured */
        $captured = [];
        $m = $this->migrator();
        $m->register($this->migration(
            '20260101000001',
            600.0,
            null,
            static function (ConnectionInterface $connection) use (&$captured): void {
                $captured = $connection->fetchAll(
                    QueryBuilder::table(MigrationLockRow::LOCK_TABLE)->select('locked_at', 'ttl')->build(),
                );
            },
        ));
        $m->migrate();

        $this->now = self::T0 + 50;
        self::assertSame(['20260101000001'], $m->rollback());
        self::assertCount(1, $captured, 'down() must observe the heartbeat-renewed lock row');
        self::assertSame(self::T0 + 50, $captured[0]['locked_at'], 'rollback must renew locked_at to the current time');
        self::assertSame(600.0, $captured[0]['ttl'], 'rollback must honour the per-migration TTL override');
    }

    // ---------------------------------------------------------- fixtures

    /**
     * @param null|Closure(ConnectionInterface): void $up
     * @param null|Closure(ConnectionInterface): void $down
     */
    private function migration(
        string $version,
        ?float $ttl = null,
        ?Closure $up = null,
        ?Closure $down = null,
    ): MigrationInterface {
        return new ScriptedMigration(
            $version,
            'm' . $version,
            ['SELECT 1'],
            ['SELECT 1'],
            $up,
            $down,
            $ttl,
        );
    }

    private function migrator(float $ttl = 300.0): Migrator
    {
        return new Migrator($this->pdoConnection, fn (): int => $this->now, $ttl);
    }

    /** @return null|array<string, mixed> */
    private function lockRow(): ?array
    {
        return $this->pdoConnection->fetchOne(
            QueryBuilder::table(MigrationLockRow::LOCK_TABLE)->select('locked_at', 'ttl')->where('id', '=', 1)->build(),
        );
    }

    private function seedLockRow(int $lockedAt, float $ttl): void
    {
        $this->pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE IF NOT EXISTS "' . MigrationLockRow::LOCK_TABLE . '" ("id" INTEGER NOT NULL PRIMARY KEY, '
            . '"locked_at" INTEGER NOT NULL, "ttl" REAL NOT NULL)',
        ));
        $this->pdoConnection->execute(
            QueryBuilder::table(MigrationLockRow::LOCK_TABLE)
                ->insertRows([['id' => 1, 'locked_at' => $lockedAt, 'ttl' => $ttl]])->build(),
        );
    }
}
