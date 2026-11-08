<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Database\ScriptedMigration;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\Migrations;

/**
 * The migrations verb as a pure outcome: the joined grammar's own
 * refusals (the missing or flag-shaped positional, the unresolved
 * directory, the unknown or mis-scoped flag, the invalid step count,
 * the generate spelling's positional-count discipline, the template
 * grammar refusing a name), the four commands' answers (the
 * scaffolded file, the status table, the run and rollback rows, the
 * JSON forms), and the delegation to the family's own units -- a
 * refused boot surfacing with the command's own refusal word,
 * exactly the words the joined branch spelled before the lift.
 *
 * @internal
 */
#[CoversClass(Migrations::class)]
#[CoversClass(CommandOutcome::class)]
final class MigrationsTest extends TestCase
{
    private const string FIRST_VERSION = '20261106120000';

    private const string SECOND_VERSION = '20261106130000';

    private const string USAGE_FIRST = 'Usage: bin/zef migrations:status <config-directory>'
        . " [<migrations-directory>] [--json]\n"
        . '       bin/zef migrations:run <config-directory>'
        . " [<migrations-directory>] [--json]\n"
        . '       bin/zef migrations:rollback <config-directory>'
        . " [<migrations-directory>] [--steps=<n>] [--json]\n"
        . '       bin/zef migrations:generate <config-directory>'
        . " [<migrations-directory>] <name> [--json]\n"
        . '       (the migrations directory defaults to the configuration'
        . " directory's sibling 'migrations')\n";

    public function testNoArgumentsAnswerTheUsageForEveryCommand(): void
    {
        foreach (['migrations:status', 'migrations:run', 'migrations:rollback', 'migrations:generate'] as $command) {
            $outcome = Migrations::run($command, []);

            self::assertSame(2, $outcome->exitCode, $command);
            self::assertSame('', $outcome->stdout, $command);
            self::assertSame(self::USAGE_FIRST, $outcome->stderr, $command);
        }
    }

    public function testAFlagShapedPositionalAnswersTheUsage(): void
    {
        $outcome = Migrations::run('migrations:status', ['--json']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE_FIRST, $outcome->stderr);
    }

    public function testAMissingDirectoryIsAPathDefectNamingIt(): void
    {
        $outcome = Migrations::run('migrations:status', ['/does/not/exist']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "Configuration directory does not exist: /does/not/exist\n",
            $outcome->stderr,
        );
    }

    public function testAnUnknownFlagAnswersTheUsage(): void
    {
        $outcome = Migrations::run('migrations:status', ['/does/not/matter', '--bogus']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE_FIRST, $outcome->stderr);
    }

    /**
     * The steps flag belongs to the rollback spelling alone: a
     * mis-scoped spelling answers the usage, not a step count.
     */
    public function testAMisScopedStepsFlagAnswersTheUsage(): void
    {
        $outcome = Migrations::run('migrations:run', ['/does/not/matter', '--steps=2']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE_FIRST, $outcome->stderr);
    }

    /**
     * The step grammar is one-to-nine digits: zero, ten digits and
     * non-numeric spellings are all usage defects, while the
     * nine-digit edge passes the judgement.
     */
    public function testAnInvalidStepCountIsAUsageDefect(): void
    {
        foreach (['0', '01', '1234567890', 'two'] as $steps) {
            $outcome = Migrations::run(
                'migrations:rollback',
                ['/does/not/matter', '--steps=' . $steps],
            );

            self::assertSame(2, $outcome->exitCode, $steps);
            self::assertSame(self::USAGE_FIRST, $outcome->stderr, $steps);
        }
    }

    /**
     * The generate spelling carries its own positional-count
     * discipline: no positional and three positionals are both usage
     * defects, and the non-generate spellings refuse a second
     * positional the same way.
     */
    public function testThePositionalCountsAreJudged(): void
    {
        $configs = '/does/not/matter';

        $generateWithoutName = Migrations::run('migrations:generate', [$configs]);
        self::assertSame(2, $generateWithoutName->exitCode);
        self::assertSame(self::USAGE_FIRST, $generateWithoutName->stderr);

        $generateWithThree = Migrations::run(
            'migrations:generate',
            [$configs, 'add', 'column', 'and more'],
        );
        self::assertSame(2, $generateWithThree->exitCode);
        self::assertSame(self::USAGE_FIRST, $generateWithThree->stderr);

        $statusWithTwo = Migrations::run('migrations:status', [$configs, 'first', 'second']);
        self::assertSame(2, $statusWithTwo->exitCode);
        self::assertSame(self::USAGE_FIRST, $statusWithTwo->stderr);
    }

    /**
     * A name the template grammar refuses answers the usage with
     * exit 2, the construction defect answered at the boundary.
     */
    public function testANameTheTemplateRefusesIsAUsageDefect(): void
    {
        $host = $this->scaffoldHost();

        try {
            $outcome = Migrations::run('migrations:generate', [$host . '/configs', 'Create/Bad']);

            self::assertSame(2, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame(self::USAGE_FIRST, $outcome->stderr);
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The scaffold answers the created file in the sibling
     * migrations directory the default answers.
     */
    public function testTheDefaultSiblingDirectoryAnswersTheCreatedFile(): void
    {
        $host = $this->scaffoldHost();

        try {
            $outcome = Migrations::run('migrations:generate', [$host . '/configs', 'create users']);

            $found = glob($host . '/migrations/*');
            $files = \is_array($found) ? $found : [];

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertStringContainsString('Migrations generate -- 1 file created:', $outcome->stdout);
            self::assertMatchesRegularExpression(
                '~/migrations/\d{14}-create-users\.php$~',
                $files[0] ?? '',
            );
            self::assertStringContainsString($files[0] ?? '', $outcome->stdout);
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The JSON flag answers the machine form over the created-file
     * text, and the explicit migrations-directory positional carries
     * the same composition.
     */
    public function testTheJsonFlagAnswersTheMachineForm(): void
    {
        $host = $this->scaffoldHost();

        try {
            $outcome = Migrations::run(
                'migrations:generate',
                [$host . '/configs', $host . '/migrations', 'enable 2fa login', '--json'],
            );

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertStringEndsWith("\n", $outcome->stdout);

            $document = $this->mapAt(json_decode(mb_rtrim($outcome->stdout, "\n"), true));
            self::assertSame('enable 2fa login', $document['name']);
            $created = $document['created'] ?? '';
            self::assertIsString($created);
            self::assertStringEndsWith('-enable-2fa-login.php', $created);
            $version = $document['version'] ?? '';
            self::assertIsString($version);
            self::assertMatchesRegularExpression('/^\d{14}$/', $version);
        } finally {
            $this->clean($host);
        }
    }

    public function testTheStatusAnswersTheDeclaredAndPendingTable(): void
    {
        $host = $this->mutationHost();

        try {
            $outcome = Migrations::run('migrations:status', [$host . '/configs']);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertSame(
                'Migrations status -- 0 applied, 2 pending, 2 declared' . "\n"
                . 'applied:' . "\n"
                . 'pending:' . "\n"
                . '  ' . self::FIRST_VERSION . '  create users' . "\n"
                . '  ' . self::SECOND_VERSION . '  create sessions' . "\n",
                $outcome->stdout,
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The optional migrations-directory positional is honoured: an
     * explicit directory answers the set IT declares, not the
     * configuration directory's default sibling -- and exactly one
     * positional is the accepted shape, never a second-positional
     * usage defect.
     */
    public function testAnExplicitMigrationsDirectoryAnswersItsOwnDeclaredSet(): void
    {
        $host = $this->mutationHost();
        mkdir($host . '/alt-migrations', 0o700);
        file_put_contents(
            $host . '/alt-migrations/20261106140000-create-audit.php',
            '<?php return new ' . ScriptedMigration::class . '('
            . var_export('20261106140000', true) . ', ' . var_export('create audit', true) . ', '
            . var_export(['CREATE TABLE audit (id INTEGER PRIMARY KEY)'], true) . ', '
            . var_export(['DROP TABLE audit'], true) . ');',
        );

        try {
            $outcome = Migrations::run('migrations:status', [$host . '/configs', $host . '/alt-migrations']);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertSame(
                'Migrations status -- 0 applied, 1 pending, 1 declared' . "\n"
                . 'applied:' . "\n"
                . 'pending:' . "\n"
                . '  20261106140000  create audit' . "\n",
                $outcome->stdout,
            );
        } finally {
            $altFiles = glob($host . '/alt-migrations/*');
            array_map(unlink(...), \is_array($altFiles) ? $altFiles : []);
            rmdir($host . '/alt-migrations');
            $this->clean($host);
        }
    }

    public function testTheRunAnswersTheAppliedVersions(): void
    {
        $host = $this->mutationHost();

        try {
            $outcome = Migrations::run('migrations:run', [$host . '/configs']);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertSame(
                'applied 2 migrations:' . "\n"
                . '  ' . self::FIRST_VERSION . '  create users' . "\n"
                . '  ' . self::SECOND_VERSION . '  create sessions' . "\n",
                $outcome->stdout,
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The steps flag reaches the rollback: one step answers the
     * newest migration alone, the last spelling winning when two
     * steps flags are spelled.
     */
    public function testTheStepsFlagReachesTheRollback(): void
    {
        $host = $this->mutationHost();

        try {
            Migrations::run('migrations:run', [$host . '/configs']);

            $outcome = Migrations::run(
                'migrations:rollback',
                [$host . '/configs', '--steps=2', '--steps=1'],
            );

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertSame(
                'rolled back 1 migration:' . "\n"
                . '  ' . self::SECOND_VERSION . '  create sessions' . "\n",
                $outcome->stdout,
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The rollback's un-flagged spelling answers the default step
     * count of one: the newest applied migration alone.
     */
    public function testTheUnflaggedRollbackAnswersTheDefaultSingleStep(): void
    {
        $host = $this->mutationHost();

        try {
            Migrations::run('migrations:run', [$host . '/configs']);

            $outcome = Migrations::run('migrations:rollback', [$host . '/configs']);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertSame(
                'rolled back 1 migration:' . "\n"
                . '  ' . self::SECOND_VERSION . '  create sessions' . "\n",
                $outcome->stdout,
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The JSON flag answers the run's machine form over the rows.
     */
    public function testTheRunJsonAnswersTheMachineForm(): void
    {
        $host = $this->mutationHost();

        try {
            $outcome = Migrations::run('migrations:run', [$host . '/configs', '--json']);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringEndsWith("\n", $outcome->stdout);

            $document = $this->mapAt(json_decode(mb_rtrim($outcome->stdout, "\n"), true));
            $applied = $this->mapAt($document['applied']);
            self::assertCount(2, $applied);
            $first = $this->mapAt($applied[0]);
            $second = $this->mapAt($applied[1]);
            self::assertSame(self::FIRST_VERSION, $first['version']);
            self::assertSame('create users', $first['name']);
            self::assertSame(self::SECOND_VERSION, $second['version']);
            self::assertSame('create sessions', $second['name']);
        } finally {
            $this->clean($host);
        }
    }

    /**
     * A store the boot refuses surfaces the full descriptive refusal
     * with exit 1 -- the operator's window carries the chain the
     * boot walked.
     */
    public function testARefusedBootSurfacesTheDescriptiveRefusal(): void
    {
        $host = $this->mutationHost();
        // A migration whose declaration the boot cannot load: a
        // broken file in the declared directory.
        file_put_contents($host . '/migrations/broken-file.php', '<?php return null;');

        try {
            $outcome = Migrations::run('migrations:status', [$host . '/configs']);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertStringStartsWith('The migrations command refused: ', $outcome->stderr);
            self::assertStringContainsString('broken-file.php', $outcome->stderr);
            self::assertStringContainsString('must return a MigrationInterface instance', $outcome->stderr);
            self::assertStringEndsWith("\n", $outcome->stderr);
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The scaffold's operational refusals answer the command's own
     * refusal word with exit 1: a migrations directory that cannot
     * hold the file.
     */
    public function testAnUnwritableDirectorySurfacesTheRefusal(): void
    {
        $host = $this->scaffoldHost();
        chmod($host . '/migrations', 0o500);

        try {
            $outcome = Migrations::run('migrations:generate', [$host . '/configs', 'create users']);

            self::assertSame(1, $outcome->exitCode);
            self::assertStringStartsWith('The migrations command refused: ', $outcome->stderr);
        } finally {
            chmod($host . '/migrations', 0o700);
            $this->clean($host);
        }
    }

    /**
     * A generate failure over a host the boot itself can answer: the
     * operational refusal must not be able to hide behind the
     * fall-through -- a removed catch-return would boot the runner and
     * answer the rollback's empty rows instead of the refusal, so the
     * host here spells a valid database profile the fall-through would
     * happily boot, and the refusal's own middle is pinned by name.
     */
    public function testAGenerateFailureOverABootableHostAnswersTheRefusal(): void
    {
        $host = $this->mutationHost();
        chmod($host . '/migrations', 0o500);

        try {
            $outcome = Migrations::run('migrations:generate', [$host . '/configs', 'create triggers']);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertStringStartsWith('The migrations command refused: ', $outcome->stderr);
            self::assertStringContainsString('is not writable', $outcome->stderr);
            self::assertStringEndsWith("\n", $outcome->stderr);
        } finally {
            chmod($host . '/migrations', 0o700);
            $this->clean($host);
        }
    }

    /**
     * The scaffold host: a configuration directory that spells no
     * database profile at all -- the generate verb never boots the
     * application and never touches a database.
     *
     * @return non-empty-string the host directory
     */
    private function scaffoldHost(): string
    {
        $host = sys_get_temp_dir() . '/zef-migrations-verb-' . uniqid('', false);
        mkdir($host . '/configs', 0o700, true);
        mkdir($host . '/migrations', 0o700, true);

        return $host;
    }

    /**
     * The mutation host: a sqlite-backed configuration directory
     * with two declared migrations, the run/rollback discipline's
     * own fixture.
     *
     * @return non-empty-string the host directory
     */
    private function mutationHost(): string
    {
        $host = sys_get_temp_dir() . '/zef-migrations-verb-store-' . uniqid('', false);
        mkdir($host . '/configs', 0o700, true);
        mkdir($host . '/migrations', 0o700, true);

        file_put_contents(
            $host . '/configs/database.php',
            '<?php return [' . \PHP_EOL
            . "    'default' => 'app'," . \PHP_EOL
            . "    'connections' => ['app' => ['driver' => 'sqlite', 'dbname' => " . var_export(
                $host . '/migrations.sqlite',
                true,
            ) . ']],' . \PHP_EOL
            . '];',
        );

        $declaredMigrations = [
            [self::FIRST_VERSION, 'create users', 'users', 'id INTEGER PRIMARY KEY'],
            [self::SECOND_VERSION, 'create sessions', 'sessions', 'id TEXT PRIMARY KEY'],
        ];

        foreach ($declaredMigrations as [$version, $name, $table, $column]) {
            file_put_contents(
                $host . '/migrations/' . $version . '-' . str_replace(' ', '-', $name) . '.php',
                '<?php return new ' . ScriptedMigration::class . '('
                . var_export($version, true) . ', ' . var_export($name, true) . ', '
                . var_export(["CREATE TABLE {$table} ({$column})"], true) . ', '
                . var_export(["DROP TABLE {$table}"], true) . ');',
            );
        }

        return $host;
    }

    /**
     * The decoded document node an assertion reads, narrowed to the
     * map shape the JSON form answers.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node)) {
            self::fail('The document node the assertion read is not an object.');
        }

        return $node;
    }

    private function clean(string $host): void
    {
        $migrationFiles = glob($host . '/migrations/*');
        array_map(unlink(...), \is_array($migrationFiles) ? $migrationFiles : []);
        $configFiles = glob($host . '/configs/*');
        array_map(unlink(...), \is_array($configFiles) ? $configFiles : []);
        @unlink($host . '/migrations.sqlite');
        rmdir($host . '/migrations');
        rmdir($host . '/configs');
        rmdir($host);
    }
}
