<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\MigrationAcquire;
use Zef\Framework\Database\MigrationLock;
use Zef\Framework\Database\MigrationLockAge;
use Zef\Framework\Database\MigrationLockRow;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Test\Support\Database\ScriptedConnection;

/**
 * The lock machine in isolation (real SQLite + a scripted connection):
 * the acquire/steal/release walk, the reentrancy guard, the row-TTL
 * steal decision with its numeric arms and malformed-row refusals, and
 * the heartbeat renewal's no-op guards.
 *
 * @internal
 */
#[CoversClass(MigrationLock::class)]
#[CoversClass(MigrationLockRow::class)]
#[CoversClass(MigrationLockAge::class)]
#[CoversClass(MigrationAcquire::class)]
#[CoversClass(TransactionException::class)]
#[CoversClass(QueryException::class)]
final class MigrationLockTest extends TestCase
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

    public function testAcquireCreatesTheLockRowAndReleaseDeletesIt(): void
    {
        $lock = $this->lock();

        $lock->acquire();
        self::assertNotNull($this->lockRow(), 'the lock row exists while held');

        $lock->release();
        self::assertNull($this->lockRow(), 'the lock row is gone after release');
    }

    public function testReleaseAndRenewAreNoOpsWhenTheLockIsNotHeld(): void
    {
        $lock = $this->lock();
        $this->createLockTable();

        $lock->renew(123.0);
        $lock->release();

        self::assertNull($this->lockRow(), 'a renew or release without acquire touches nothing');
    }

    public function testReentrancyIsRefused(): void
    {
        $lock = $this->lock();
        $lock->acquire();

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessageIs('Migration lock is already held by this Migrator instance.');
        $lock->acquire();
    }

    public function testAFreshHoldersLockIsNotStolen(): void
    {
        $this->seedLockRow(self::T0 - 100, 300.0);

        try {
            $this->lock()->acquire();
            self::fail('a lock inside its own row TTL must not be stolen');
        } catch (TransactionException $transactionException) {
            self::assertSame(
                'Migration lock is already held (age 100s, ttl 300s).',
                $transactionException->getMessage(),
            );
        }

        self::assertNotNull($this->lockRow(), 'the declared holder must keep its lock');
    }

    public function testAnExpiredRowTtlIsStolen(): void
    {
        $this->seedLockRow(self::T0 - 400, 100.0);

        $lock = $this->lock();
        $lock->acquire();

        $row = $this->lockRow();
        self::assertNotNull($row);
        self::assertIsInt($row['locked_at']);
        self::assertSame(self::T0, $row['locked_at'], 'the stealer rewrites the row with its own now');
        self::assertIsFloat($row['ttl']);
        self::assertSame(300.0, $row['ttl'], 'the stealer rewrites the row with its own default TTL');
    }

    public function testTheAgeIsFoldedToWholeSecondsBeforeTheTtlComparison(): void
    {
        // locked_at 299.5s ago: the int fold reads a full 300s age, so
        // the strict comparison lets the steal happen -- the documented
        // (int) coercion, pinned at the boundary where it decides.
        $this->seedLockRow(self::T0 - 299.5, 300.0);

        $lock = $this->lock();
        $lock->acquire();

        self::assertNotNull($this->lockRow(), 'the int-folded age reached the TTL and the steal ran');
    }

    public function testTheStealBoundaryIsStrictAtEqualAgeAndTtl(): void
    {
        // age 300s against ttl 300s: exactly on the bar, stealable.
        $this->seedLockRow(self::T0 - 300, 300.0);

        $lock = $this->lock();
        $lock->acquire();

        self::assertNotNull($this->lockRow(), 'an age equal to the TTL is already stealable');
    }

    public function testAMalformedLockedAtIsRefused(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->executeScript = [0, new QueryException('the insert refused')];
        $scriptedConnection->fetchAllScript = [['locked_at' => null, 'ttl' => 300.0]];

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('Migration lock row is malformed.');
        new MigrationLock($scriptedConnection, static fn (): int => self::T0, 300.0)->acquire();
    }

    public function testAMalformedTtlAloneIsStillRefused(): void
    {
        // One healthy field and one malformed one: the row as a whole
        // is a corruption the runner halts on.
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->executeScript = [0, new QueryException('the insert refused')];
        $scriptedConnection->fetchAllScript = [['locked_at' => self::T0 - 100, 'ttl' => null]];

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('Migration lock row is malformed.');
        new MigrationLock($scriptedConnection, static fn (): int => self::T0, 300.0)->acquire();
    }

    public function testARefusedInsertWithNoReadableRowIsMalformed(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->executeScript = [0, new QueryException('the insert refused')];
        $scriptedConnection->fetchAllScript = [];

        $migrationLock = new MigrationLock($scriptedConnection, static fn (): int => self::T0, 300.0);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('Migration lock row is malformed.');
        $migrationLock->acquire();
    }

    public function testTheNoOpGuardsTouchNoStatement(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $migrationLock = new MigrationLock($scriptedConnection, static fn (): int => self::T0, 300.0);

        $migrationLock->renew(123.0);
        $migrationLock->release();

        self::assertSame([], $scriptedConnection->executed, 'a renew or release without acquire issues no statement');
    }

    public function testAcquirePinsTheExactSchemaAndClaimStatements(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->executeScript = [0, 0];

        new MigrationLock($scriptedConnection, static fn (): int => self::T0, 300.0)->acquire();

        self::assertSame([
            'CREATE TABLE IF NOT EXISTS "zef_migrations_lock" ('
            . '"id" INTEGER NOT NULL PRIMARY KEY, '
            . '"locked_at" INTEGER NOT NULL, '
            . '"ttl" REAL NOT NULL)',
            'INSERT INTO "zef_migrations_lock" ("id", "locked_at", "ttl") VALUES (?, ?, ?)',
        ], $scriptedConnection->executed);
    }

    public function testANonNumericStringTtlFoldsToZeroAndIsStolen(): void
    {
        // The row declares garbage: the engine's own (float) coercion
        // folds it to zero, so a holder that declared nothing numeric
        // holds nothing at all — the steal proceeds.
        $this->seedLockRow(self::T0 - 400, 'abc');

        $lock = $this->lock();
        $lock->acquire();

        self::assertNotNull($this->lockRow(), 'the garbage-TTL holder is stealable');
    }

    public function testRenewRewritesTheRowWithTheEffectiveTtl(): void
    {
        $lock = $this->lock();
        $lock->acquire();

        $this->now = self::T0 + 50;
        $lock->renew(900.0);

        $row = $this->lockRow();
        self::assertNotNull($row);
        self::assertIsInt($row['locked_at']);
        self::assertSame(self::T0 + 50, $row['locked_at'], 'the heartbeat rewrites locked_at');
        self::assertIsFloat($row['ttl']);
        self::assertSame(900.0, $row['ttl'], 'the heartbeat rewrites the effective TTL');
    }

    private function lock(): MigrationLock
    {
        return new MigrationLock($this->pdoConnection, fn (): int => $this->now, 300.0);
    }

    /** @return null|array<string, mixed> */
    private function lockRow(): ?array
    {
        return $this->pdoConnection->fetchOne(
            QueryBuilder::table(MigrationLockRow::LOCK_TABLE)->select('locked_at', 'ttl')->where('id', '=', 1)->build(),
        );
    }

    private function createLockTable(): void
    {
        $this->pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE IF NOT EXISTS "' . MigrationLockRow::LOCK_TABLE . '" ("id" INTEGER NOT NULL PRIMARY KEY, '
            . '"locked_at" INTEGER NOT NULL, "ttl" REAL NOT NULL)',
        ));
    }

    private function seedLockRow(float|int|string|null $lockedAt, float|int|string|null $ttl): void
    {
        $this->createLockTable();
        $this->pdoConnection->execute(
            QueryBuilder::table(MigrationLockRow::LOCK_TABLE)
                ->insertRows([['id' => 1, 'locked_at' => $lockedAt, 'ttl' => $ttl]])->build(),
        );
    }
}
