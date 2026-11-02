<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Database\MigrationException;
use Zef\Test\Support\Tooling\MigrationsBoot;
use Zef\Test\Support\Tooling\MigrationsRunText;
use Zef\Test\Support\Tooling\MigrationsStatusJson;
use Zef\Test\Support\Tooling\MigrationsStatusText;

/**
 * Pins the migrations commands' family end-to-end at the fold level:
 * the boot ({@see MigrationsBoot}) -- a declared configuration
 * directory and the operator's migrations directory become the
 * registered runner, over the real SQLite socket the wiring composes,
 * the durable bookkeeping answered by a SECOND boot -- and the
 * presentation units ({@see MigrationsStatusText}, {@see
 * MigrationsStatusJson}, {@see MigrationsRunText}) over the plain
 * data those questions answer.
 *
 * The seam-ownership doctrine rides the refusals: the missing
 * migrations directory and the duplicate version refuse at the
 * discovery fold's own words, the bad version stamp refuses at the
 * register verb's own corridor (the fold owns the directory's shape,
 * never the migration's), and the silent database profile refuses at
 * the host's first use -- the first register call -- never at the
 * boot itself.
 *
 * @internal
 */
#[CoversClass(MigrationsBoot::class)]
#[CoversClass(MigrationsStatusText::class)]
#[CoversClass(MigrationsStatusJson::class)]
#[CoversClass(MigrationsRunText::class)]
final class MigrationsBootTest extends TestCase
{
    private const string FIRST_VERSION = '20261102120000';

    private const string SECOND_VERSION = '20261102130000';

    private string $root = '';

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-migrations-boot-' . uniqid();

        mkdir($this->configs(), 0o777, true);
        mkdir($this->migrations(), 0o777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->root === '' || !is_dir($this->root)) {
            return;
        }

        $root = $this->root;

        foreach ([$this->configs() . '/*.php', $this->migrations() . '/*.php', $root . '/*.sqlite'] as $pattern) {
            $entries = glob($pattern);
            foreach (\is_array($entries) ? $entries : [] as $entry) {
                if (!is_file($entry)) {
                    continue;
                }

                unlink($entry);
            }
        }

        rmdir($this->configs());
        rmdir($this->migrations());
        rmdir($root);
    }

    public function testTheBootAnswersTheDeclaredNamesAndAnEmptyRecord(): void
    {
        $this->writeDeclaredConfiguration();
        $this->writeCreateUsersMigration();
        $this->writeCreateSessionsMigration();

        $boot = MigrationsBoot::booted($this->configs(), $this->migrations());

        $declared = [];
        foreach ($boot->names() as $version => $name) {
            $declared[] = $version . ' ' . $name;
        }

        self::assertSame(
            ['20261102120000 create users', '20261102130000 create sessions'],
            $declared,
            'the declared migrations answer as the names map keyed by version',
        );
        self::assertSame([], $boot->applied(), 'a fresh database answers no applied versions');
        self::assertSame(
            [self::FIRST_VERSION, self::SECOND_VERSION],
            $boot->plan(),
            'every declared migration is pending on a fresh database',
        );
    }

    public function testRunAppliesEveryPendingMigrationOverTheRealSocket(): void
    {
        $this->writeDeclaredConfiguration();
        $this->writeCreateUsersMigration();
        $this->writeCreateSessionsMigration();

        $boot = MigrationsBoot::booted($this->configs(), $this->migrations());
        $applied = $boot->migrate();

        self::assertSame(
            [self::FIRST_VERSION, self::SECOND_VERSION],
            $applied,
            'the run answers the versions it applied, ascending',
        );
        self::assertSame($applied, $boot->applied(), 'the record now carries every applied version');
        self::assertSame([], $boot->plan(), 'a fully migrated database plans nothing');

        $pdo = new PDO('sqlite:' . $this->databaseFile());
        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('users', 'sessions')",
        );
        self::assertNotFalse($tables);

        $landed = [];
        foreach ($tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
            self::assertIsString($table);
            $landed[] = $table;
        }

        self::assertSame(
            ['users', 'sessions'],
            $landed,
            "the migration bodies ran over the wiring's own socket: both tables landed in apply order",
        );
    }

    public function testASecondBootAnswersTheDurableRecord(): void
    {
        $this->writeDeclaredConfiguration();
        $this->writeCreateUsersMigration();
        $this->writeCreateSessionsMigration();

        MigrationsBoot::booted($this->configs(), $this->migrations())->migrate();

        $second = MigrationsBoot::booted($this->configs(), $this->migrations());

        self::assertSame(
            [self::FIRST_VERSION, self::SECOND_VERSION],
            $second->applied(),
            'the bookkeeping table persisted beside the schema: the record survives the process',
        );
        self::assertSame([], $second->plan(), 'the second boot plans nothing over the durable record');
    }

    public function testRollbackRevertsTheStepsMostRecentAndReappliable(): void
    {
        $this->writeDeclaredConfiguration();
        $this->writeCreateUsersMigration();
        $this->writeCreateSessionsMigration();

        $boot = MigrationsBoot::booted($this->configs(), $this->migrations());
        $boot->migrate();

        self::assertSame(
            [self::SECOND_VERSION],
            $boot->rollback(1),
            'the rollback answers the newest applied version',
        );
        self::assertSame(
            [self::FIRST_VERSION],
            $boot->applied(),
            'only the rolled-back version left the record',
        );
        self::assertSame(
            [self::SECOND_VERSION],
            $boot->plan(),
            'the rolled-back version is pending again: the operations are re-appliable',
        );
    }

    public function testTheSilentProfileRefusesAtTheHostsFirstUse(): void
    {
        $this->writeCreateUsersMigration();

        $this->expectExceptionObject(new ConfigException(
            'The database.migrator wired default is not declared: spell the migration'
            . " runner's connection (database.migrations or database.default over"
            . ' database.connections) or compose the runner over your own ConnectionInterface.',
        ));

        MigrationsBoot::booted(__DIR__ . '/../../app/configs', $this->migrations());
    }

    public function testAMissingMigrationsDirectoryRefusesNamingIt(): void
    {
        $this->writeDeclaredConfiguration();

        $this->expectExceptionObject(new MigrationException(
            "The migrations directory '/nowhere/migrations' does not exist or is not a directory.",
        ));

        MigrationsBoot::booted($this->configs(), '/nowhere/migrations');
    }

    public function testABadVersionStampRefusesAtTheRegisterVerbsOwnCorridor(): void
    {
        $this->writeDeclaredConfiguration();
        $this->writeMigrationFile('short', 'create users', ['SELECT 1'], ['SELECT 1']);

        $this->expectExceptionObject(new MigrationException(
            "Migration version 'short' must be a 14-digit timestamp (YYYYmmddHHMMSS).",
        ));

        MigrationsBoot::booted($this->configs(), $this->migrations());
    }

    public function testTheStatusTableSpellsTheCountsAndBothSections(): void
    {
        self::assertSame(
            'Migrations status -- 1 applied, 1 pending, 2 declared' . "\n"
            . "applied:\n"
            . '  20261102120000  create users' . "\n"
            . "pending:\n"
            . '  20261102130000  create sessions' . "\n",
            MigrationsStatusText::table(
                $this->declaredNames(),
                [self::FIRST_VERSION],
                [self::SECOND_VERSION],
            ),
        );
    }

    public function testTheStatusTableIsHonestAboutAnUndeclaredAppliedVersion(): void
    {
        self::assertSame(
            'Migrations status -- 1 applied, 0 pending, 0 declared' . "\n"
            . "applied:\n"
            . '  20261102120000  (undeclared)' . "\n"
            . "pending:\n",
            MigrationsStatusText::table([], [self::FIRST_VERSION], []),
        );
    }

    public function testTheStatusJsonDocumentCarriesTheRecordsAndCounts(): void
    {
        $document = json_decode(
            MigrationsStatusJson::rendered(
                $this->declaredNames(),
                [self::FIRST_VERSION],
                [self::SECOND_VERSION],
            ),
            true,
        );

        self::assertIsArray($document);

        $counts = $document['counts'] ?? null;
        self::assertIsArray($counts);
        self::assertSame(1, $counts['applied']);
        self::assertSame(1, $counts['pending']);
        self::assertSame(2, $counts['declared']);

        $applied = $document['applied'] ?? null;
        self::assertIsArray($applied);
        self::assertSame(
            ['version' => self::FIRST_VERSION, 'name' => 'create users'],
            $applied[0] ?? null,
        );

        $pending = $document['pending'] ?? null;
        self::assertIsArray($pending);
        self::assertSame(
            ['version' => self::SECOND_VERSION, 'name' => 'create sessions'],
            $pending[0] ?? null,
        );
    }

    public function testTheStatusJsonNamesAnUndeclaredAppliedVersionNull(): void
    {
        $document = json_decode(MigrationsStatusJson::rendered([], [self::FIRST_VERSION], []), true);

        self::assertIsArray($document);

        $applied = $document['applied'] ?? null;
        self::assertIsArray($applied);
        self::assertSame(
            ['version' => self::FIRST_VERSION, 'name' => null],
            $applied[0] ?? null,
            'the honest JSON name for an applied version whose declaration left: null, never a guess',
        );
    }

    public function testTheRunTextSpellsTheVersionsApplied(): void
    {
        self::assertSame(
            "applied 2 migrations:\n"
            . '  20261102120000  create users' . "\n"
            . '  20261102130000  create sessions' . "\n",
            MigrationsRunText::applied(
                $this->declaredNames(),
                [self::FIRST_VERSION, self::SECOND_VERSION],
            ),
        );
    }

    public function testTheRollbackTextSpellsTheSingularMigration(): void
    {
        self::assertSame(
            "rolled back 1 migration:\n"
            . '  20261102130000  create sessions' . "\n",
            MigrationsRunText::rolledBack(
                $this->declaredNames([self::SECOND_VERSION], ['create sessions']),
                [self::SECOND_VERSION],
            ),
        );
    }

    public function testTheRunTextOverAnEmptyAnswerSpellsTheHonestEmpty(): void
    {
        self::assertSame(
            "applied 0 migrations:\n",
            MigrationsRunText::applied([], []),
            'a second run touching nothing says so, never an empty document',
        );
    }

    /**
     * Writes the declared database profile: the default connection over
     * the temporary SQLite file.
     */
    private function writeDeclaredConfiguration(): void
    {
        file_put_contents($this->configs() . '/database.php', '<?php return [' . \PHP_EOL
            . "    'default' => 'app'," . \PHP_EOL
            . "    'connections' => ['app' => ['driver' => 'sqlite', 'dbname' => " . var_export(
                $this->databaseFile(),
                true,
            ) . ']],' . \PHP_EOL
            . '];');
    }

    private function writeCreateUsersMigration(): void
    {
        $this->writeMigrationFile(
            self::FIRST_VERSION,
            'create users',
            ['CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)'],
            ['DROP TABLE users'],
        );
    }

    private function writeCreateSessionsMigration(): void
    {
        $this->writeMigrationFile(
            self::SECOND_VERSION,
            'create sessions',
            ['CREATE TABLE sessions (id TEXT PRIMARY KEY)'],
            ['DROP TABLE sessions'],
        );
    }

    /**
     * @param list<string> $upStatements   raw SQL the migration applies
     * @param list<string> $downStatements raw SQL the migration reverts
     */
    private function writeMigrationFile(
        string $version,
        string $name,
        array $upStatements,
        array $downStatements,
    ): void {
        file_put_contents(
            $this->migrations() . '/' . $version . '-' . str_replace(' ', '-', $name) . '.php',
            '<?php return new Zef\Test\Support\Database\ScriptedMigration('
            . var_export($version, true) . ', ' . var_export($name, true) . ', '
            . var_export($upStatements, true) . ', ' . var_export($downStatements, true) . ');',
        );
    }

    /**
     * The host configuration directory under test.
     *
     * @return non-empty-string
     */
    private function configs(): string
    {
        return $this->root . '/configs';
    }

    /**
     * The operator migrations directory under test.
     *
     * @return non-empty-string
     */
    private function migrations(): string
    {
        return $this->root . '/migrations';
    }

    /**
     * The SQLite database file the declared profile rides.
     *
     * @return non-empty-string
     */
    private function databaseFile(): string
    {
        return $this->root . '/migrations.sqlite';
    }

    /**
     * A declared names map over the two standard versions, or the subset
     * the caller spells: the versions key the names the way the boot's
     * own discovered map does.
     *
     * @param list<string> $versions
     * @param list<string> $names
     *
     * @return array<string, string>
     */
    private function declaredNames(
        array $versions = [self::FIRST_VERSION, self::SECOND_VERSION],
        array $names = ['create users', 'create sessions'],
    ): array {
        return array_combine($versions, $names);
    }
}
