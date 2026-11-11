<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
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
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Test\Support\Database\ScriptedConnection;
use Zef\Test\Support\Database\ScriptedMigration;

/**
 * The migration runner's edge matrix (real SQLite + injected wall-clock
 * + a scripted connection for the decode guards): ordering, bookkeeping,
 * rollback, lock acquire/steal/release through migrate() and rollback(),
 * failure atomicity, and the strict registration grammar -- the
 * refusals typed onto the migration phase's own family member.
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
#[CoversClass(QueryException::class)]
final class MigratorTest extends TestCase
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

    public function testRegistrationGrammar(): void
    {
        $m = $this->migrator();

        try {
            $m->register($this->migration('20260101', ['SELECT 1']));
            self::fail('short version must be rejected');
        } catch (MigrationException $migrationException) {
            self::assertSame(
                "Migration version '20260101' must be a 14-digit timestamp (YYYYmmddHHMMSS).",
                $migrationException->getMessage(),
            );
        }

        try {
            $m->register($this->migration('2026010100000a', ['SELECT 1']));
            self::fail('non-digit version must be rejected');
        } catch (MigrationException $migrationException) {
            self::assertSame(
                "Migration version '2026010100000a' must be a 14-digit timestamp (YYYYmmddHHMMSS).",
                $migrationException->getMessage(),
            );
        }

        try {
            $m->register($this->migration('20260101000001', ['SELECT 1'], name: str_repeat('x', 129)));
            self::fail('129-byte name must be rejected');
        } catch (MigrationException $migrationException) {
            self::assertSame('Migration name must be a string of 1..128 bytes.', $migrationException->getMessage());
        }

        try {
            $m->register($this->migration('20260101000001', ['SELECT 1'], name: ''));
            self::fail('empty name must be rejected');
        } catch (MigrationException $migrationException) {
            self::assertSame('Migration name must be a string of 1..128 bytes.', $migrationException->getMessage());
        }

        $m->register($this->migration('20260101000001', ['SELECT 1']));
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIs("Migration version '20260101000001' is already registered.");
        $m->register($this->migration('20260101000001', ['SELECT 2']));
    }

    /**
     * The name corridor counts BYTES, not characters: a 65-character
     * two-byte name sits at 130 bytes and must be refused, while the
     * 64-character twin lands exactly on the 128-byte ceiling and is
     * the widest name the runner books.
     */
    public function testTheNameCorridorCountsBytesNotCharacters(): void
    {
        $m = $this->migrator();

        $m->register($this->migration('20260101000001', ['SELECT 1'], name: str_repeat("\u{0101}", 64)));
        self::assertSame(['20260101000001'], $m->plan(), 'a 128-byte multibyte name is registered');

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIs('Migration name must be a string of 1..128 bytes.');
        $m->register($this->migration('20260101000002', ['SELECT 1'], name: str_repeat("\u{0101}", 65)));
    }

    public function testAppliedPinsTheExactBookkeepingSchemaStatement(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchAllScript = [];

        // The schema fold is called directly: its visibility is part of
        // the lane's surface (a protected spelling would refuse this
        // very call), and the statement is pinned verbatim so a
        // reordered assembly cannot pass.
        MigrationBookkeeping::ensureTable($scriptedConnection);
        $schemaStatement = 'CREATE TABLE IF NOT EXISTS "zef_migrations" ('
            . '"version" VARCHAR(14) NOT NULL PRIMARY KEY, '
            . '"name" VARCHAR(128) NOT NULL, '
            . '"applied_at" INTEGER NOT NULL)';

        self::assertSame([], new Migrator($scriptedConnection)->applied());
        self::assertSame(
            [$schemaStatement, $schemaStatement, 'SELECT "version" FROM "zef_migrations" ORDER BY "version" ASC'],
            $scriptedConnection->executed,
            'the direct call, the read and the version select walk their statements verbatim',
        );
    }

    public function testTheMysqlDriverWordSpellsTheBookkeepingInBackticks(): void
    {
        // The issue #778 dialect: the bookkeeping family's schema, read
        // and select over the mysql driver word -- the same statements,
        // every identifier backticked, so stock MySQL parses what the
        // builder emits instead of reading each name as a string
        // literal.
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->driver = 'mysql';
        $scriptedConnection->fetchAllScript = [];

        MigrationBookkeeping::ensureTable($scriptedConnection);
        $schemaStatement = 'CREATE TABLE IF NOT EXISTS `zef_migrations` ('
            . '`version` VARCHAR(14) NOT NULL PRIMARY KEY, '
            . '`name` VARCHAR(128) NOT NULL, '
            . '`applied_at` INTEGER NOT NULL)';

        self::assertSame([], new Migrator($scriptedConnection)->applied());
        self::assertSame(
            [$schemaStatement, $schemaStatement, 'SELECT `version` FROM `zef_migrations` ORDER BY `version` ASC'],
            $scriptedConnection->executed,
        );
    }

    public function testTheVersionsFoldSpellsThePlanOrder(): void
    {
        $first = new ScriptedMigration('20260101000001');
        $second = new ScriptedMigration('20260101000002');

        // The fold is called directly: its visibility is part of the
        // lane's surface.
        self::assertSame(
            ['20260101000001', '20260101000002'],
            MigrationPending::versions([$first, $second]),
        );
    }

    public function testRollbackOfTwoStepsAnswersBothVersionsNewestFirst(): void
    {
        $m = $this->migrator();
        $m->register($this->migration('20260101000001', ['CREATE TABLE a (x)']));
        $m->register($this->migration('20260101000002', ['CREATE TABLE b (x)']));
        $m->migrate();

        self::assertSame(['20260101000002', '20260101000001'], $m->rollback(2));
        self::assertSame([], $m->applied());
    }

    public function testLockTtlMustBePositive(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIs('Migration lock TTL must be greater than zero.');
        new Migrator($this->pdoConnection, null, 0.0);
    }

    public function testMigrateAppliesPendingInOrderAndRecords(): void
    {
        $m = $this->migrator();
        $m->register($this->migration('20260101000002', ['CREATE TABLE b (x)'], name: 'b'));
        $m->register($this->migration('20260101000001', ['CREATE TABLE a (x)'], name: 'a'));

        self::assertSame(['20260101000001', '20260101000002'], $m->plan());
        self::assertSame(['20260101000001', '20260101000002'], $m->migrate());
        self::assertSame([], $m->plan());
        self::assertSame(['20260101000001', '20260101000002'], $m->applied());
        // Table existence proves up() really ran.
        $this->pdoConnection->execute(SqlQuery::raw('INSERT INTO a (x) VALUES (1)'));
        $this->pdoConnection->execute(SqlQuery::raw('INSERT INTO b (x) VALUES (2)'));
    }

    public function testRollbackDescendingAndBookkeeping(): void
    {
        $m = $this->migrator();
        $m->register($this->migration('20260101000001', ['CREATE TABLE a (x)'], ['DROP TABLE a']));
        $m->register($this->migration('20260101000002', ['CREATE TABLE b (x)'], ['DROP TABLE b']));
        $m->migrate();

        self::assertSame(['20260101000002'], $m->rollback());
        self::assertSame(['20260101000001'], $m->applied());
        self::assertSame(['20260101000002'], $m->plan());

        self::assertSame(['20260101000001'], $m->rollback());
        self::assertSame([], $m->applied());
        self::assertSame(['20260101000001', '20260101000002'], $m->plan());
    }

    public function testRollbackGuards(): void
    {
        $m = $this->migrator();

        try {
            $m->rollback(0);
            self::fail('rollback(0) must be rejected');
        } catch (MigrationException $migrationException) {
            self::assertSame('Rollback steps must be >= 1.', $migrationException->getMessage());
        }

        $m->register($this->migration('20260101000001', ['CREATE TABLE a (x)']));
        $m->migrate();

        try {
            $m->rollback(2);
            self::fail('rollback beyond applied must be rejected');
        } catch (MigrationException $migrationException) {
            self::assertSame(
                'Requested 2 rollback step(s) but only 1 applied migration(s) exist.',
                $migrationException->getMessage(),
            );
        }

        // Unregistered-but-applied version cannot roll back.
        $this->pdoConnection->execute(
            QueryBuilder::table(MigrationBookkeeping::MIGRATIONS_TABLE)
                ->insertRows([['version' => '20250101000000', 'name' => 'ghost', 'applied_at' => 1]])->build(),
        );

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageIs("Applied migration '20250101000000' is not registered; cannot roll back.");
        $m->rollback(2);
    }

    public function testFailedUpRollsBackAndRecordsNothing(): void
    {
        $m = $this->migrator();
        $m->register($this->migration('20260101000001', [
            'CREATE TABLE ok (x)',
            'CREATE TABLE boom (x',
        ]));
        $this->now = self::T0;

        try {
            $m->migrate();
            self::fail('broken migration must fail');
        } catch (QueryException) {
        }

        self::assertSame([], $m->applied());
        self::assertSame([], $this->pdoConnection->fetchAll(
            QueryBuilder::table(MigrationBookkeeping::MIGRATIONS_TABLE)->select('version')->build(),
        ));
        // Lock must be released even after failure.
        self::assertSame([], $this->pdoConnection->fetchAll(
            QueryBuilder::table(MigrationLockRow::LOCK_TABLE)->select('id')->build(),
        ));
    }

    public function testLockBlocksSecondRunnerUntilTtlExpiry(): void
    {
        $first = $this->migrator(300.0);
        // Acquire the lock manually through a migrate of an empty registry.
        $first->migrate();

        // The lock row was released, so a second runner may acquire.
        $second = $this->migrator(300.0);
        $second->migrate();

        // Simulate a crashed runner: insert a stale lock row directly.
        $this->pdoConnection->execute(
            QueryBuilder::table(MigrationLockRow::LOCK_TABLE)
                ->insertRows([['id' => 1, 'locked_at' => $this->now - 100, 'ttl' => 300.0]])->build(),
        );

        $third = $this->migrator(300.0);

        try {
            $third->migrate();
            self::fail('fresh lock must block');
        } catch (TransactionException $transactionException) {
            self::assertSame(
                'Migration lock is already held (age 100s, ttl 300s).',
                $transactionException->getMessage(),
            );
        }

        // After the ROW's declared ttl expires the lock is stolen -- the
        // row ttl (300s, set by the crashed holder) is authoritative,
        // not the recovering runner's default (50s). Push past it.
        $this->now += 200; // age 300s = row ttl 300s
        $stale = $this->migrator(50.0);
        $stale->migrate();
        self::assertSame([], $this->pdoConnection->fetchAll(
            QueryBuilder::table(MigrationLockRow::LOCK_TABLE)->select('id')->build(),
        ));
    }

    public function testPlanDoesNotRequireAppliedData(): void
    {
        $m = $this->migrator();
        $m->register($this->migration('20260101000001', ['SELECT 1']));
        // Fresh database: plan() must still work (schema auto-ensured).
        self::assertSame(['20260101000001'], $m->plan());
        self::assertSame([], $m->applied());
    }

    public function testAppliedAtUsesInjectedClock(): void
    {
        $m = $this->migrator();
        $m->register($this->migration('20260101000001', ['SELECT 1']));

        $this->now = 1_234_567_890;
        $m->migrate();

        $row = $this->pdoConnection->fetchOne(
            QueryBuilder::table(MigrationBookkeeping::MIGRATIONS_TABLE)->select('applied_at', 'name')->build(),
        );
        self::assertNotNull($row);
        $appliedAt = $row['applied_at'];
        self::assertTrue(\is_int($appliedAt) || \is_string($appliedAt) || \is_float($appliedAt));
        self::assertSame(1_234_567_890, (int) $appliedAt);
        self::assertSame('m', $row['name']);
    }

    public function testTheDefaultClockAnswersWallClockSeconds(): void
    {
        $migrator = new Migrator($this->pdoConnection);
        $migrator->register($this->migration('20260101000001', ['SELECT 1']));

        $before = time();
        $migrator->migrate();

        $row = $this->pdoConnection->fetchOne(
            QueryBuilder::table(MigrationBookkeeping::MIGRATIONS_TABLE)->select('applied_at')->build(),
        );
        self::assertNotNull($row);
        self::assertIsInt($row['applied_at']);
        self::assertGreaterThanOrEqual($before, $row['applied_at'], 'the default clock answers real seconds');
    }

    public function testAppliedRefusesAMalformedBookkeepingRow(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchAllScript = [['version' => 12345]];

        $migrator = new Migrator($scriptedConnection);

        try {
            $migrator->applied();
            self::fail('a non-string version column must halt the read');
        } catch (QueryException $queryException) {
            self::assertSame('Migration bookkeeping row has a non-string version.', $queryException->getMessage());
        }
    }

    public function testAppliedRefusesARowWithoutAVersion(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchAllScript = [['name' => 'ghost']];

        $migrator = new Migrator($scriptedConnection);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('Migration bookkeeping row has a non-string version.');
        $migrator->applied();
    }

    private function migrator(float $ttl = 300.0): Migrator
    {
        return new Migrator($this->pdoConnection, fn (): int => $this->now, $ttl);
    }

    /**
     * @param list<string> $up
     * @param list<string> $down
     */
    private function migration(
        string $version,
        array $up,
        array $down = ['SELECT 1'],
        string $name = 'm',
    ): MigrationInterface {
        return new ScriptedMigration($version, $name, $up, $down);
    }
}
