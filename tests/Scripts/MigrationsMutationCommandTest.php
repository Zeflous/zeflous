<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Exercises the migrations MUTATION verbs' JSON answers end to end as
 * real subprocesses, the same way a deployment pipeline invokes them
 * -- `bin/zef migrations:run --json` and `migrations:rollback --json`
 * over a real declared host (the SQLite profile the wiring composes,
 * three declared migrations), pinned against the text spellings the
 * operator reads: the same facts on both sides, the same exit codes
 * (the shape is a flag, the verdict is not -- the `storage:map`
 * discipline), the answer on the standard output and the refusals on
 * the standard error.
 *
 * The payload is the text renderer's own facts: the applied and
 * rolled-back versions in the runner's own order, each carrying its
 * declared name -- the design record's own pins (three declared, the
 * answer three in order; steps=2, the answer exactly two).
 *
 * @internal
 */
#[CoversNothing]
final class MigrationsMutationCommandTest extends TestCase
{
    private const string COMMAND = 'bin/zef';

    private const string FIRST_VERSION = '20261106120000';

    private const string SECOND_VERSION = '20261106130000';

    private const string THIRD_VERSION = '20261106140000';

    /**
     * The text baseline the JSON answers are pinned against: three
     * declared migrations, the run's headline and one row per version,
     * the answer alone on the standard output.
     */
    public function testTheRunAnswersTheAppliedVersionsInText(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(['migrations:run', $host . '/configs'], $host);
            $process->run();

            self::assertSame(0, $process->getExitCode());
            self::assertSame('', $process->getErrorOutput());
            self::assertSame(
                'applied 3 migrations:' . "\n"
                . '  ' . self::FIRST_VERSION . '  create users' . "\n"
                . '  ' . self::SECOND_VERSION . '  create sessions' . "\n"
                . '  ' . self::THIRD_VERSION . '  create tokens' . "\n",
                $process->getOutput(),
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The design record's own payload pin: a run over three declared
     * migrations answers the three applied versions in order, each
     * carrying its declared name -- over the explicit migrations
     * directory too, the flag composition unchanged.
     */
    public function testTheRunJsonAnswersTheThreeAppliedVersionsInOrder(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(
                ['migrations:run', $host . '/configs', $host . '/migrations', '--json'],
                $host,
            );
            $process->run();

            $document = $this->decoded($process->getOutput());

            self::assertSame(0, $process->getExitCode());
            self::assertSame('', $process->getErrorOutput());
            self::assertSame(
                [
                    ['version' => self::FIRST_VERSION, 'name' => 'create users'],
                    ['version' => self::SECOND_VERSION, 'name' => 'create sessions'],
                    ['version' => self::THIRD_VERSION, 'name' => 'create tokens'],
                ],
                $document['applied'] ?? null,
                'the JSON answer carries the three applied versions in order with their declared names',
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The honest empty in both spellings, the SAME verdict: a second
     * run touching nothing answers exit 0 with the empty applied list
     * -- the exit-code table is the contract both spellings share, the
     * shape never the verdict.
     */
    public function testTheSecondRunJsonAnswersTheHonestEmptyWithTheSameExitCode(): void
    {
        $host = $this->host();

        try {
            $this->command(['migrations:run', $host . '/configs'], $host)->run();

            $process = $this->command(['migrations:run', $host . '/configs', '--json'], $host);
            $process->run();

            $document = $this->decoded($process->getOutput());

            self::assertSame(0, $process->getExitCode());
            self::assertSame([], $document['applied'] ?? null);
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The design record's own steps pin: three applied, `--steps=2`
     * answers EXACTLY two -- the two newest, the runner's own
     * newest-first order, the declared names travelling with the
     * versions.
     */
    public function testTheRollbackJsonWithStepsTwoAnswersExactlyTwo(): void
    {
        $host = $this->host();

        try {
            $this->command(['migrations:run', $host . '/configs'], $host)->run();

            $process = $this->command(
                ['migrations:rollback', $host . '/configs', '--steps=2', '--json'],
                $host,
            );
            $process->run();

            $document = $this->decoded($process->getOutput());

            self::assertSame(0, $process->getExitCode());
            self::assertSame('', $process->getErrorOutput());
            self::assertSame(
                [
                    ['version' => self::THIRD_VERSION, 'name' => 'create tokens'],
                    ['version' => self::SECOND_VERSION, 'name' => 'create sessions'],
                ],
                $document['rolledBack'] ?? null,
                'steps=2 answers exactly the two newest applied versions, newest first, with their declared names',
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The twins answer ONE truth: the text rollback and the JSON
     * rollback over the same operation spell the same versions and
     * the same names -- an operator and a pipeline reading one answer,
     * the drift question the design record exists to close.
     */
    public function testTheRollbackTextAndJsonAnswerTheSameFacts(): void
    {
        $host = $this->host();

        try {
            $this->command(['migrations:run', $host . '/configs'], $host)->run();

            $textProcess = $this->command(
                ['migrations:rollback', $host . '/configs', '--steps=2'],
                $host,
            );
            $textProcess->run();

            $this->command(['migrations:run', $host . '/configs'], $host)->run();

            $jsonProcess = $this->command(
                ['migrations:rollback', $host . '/configs', '--steps=2', '--json'],
                $host,
            );
            $jsonProcess->run();

            $document = $this->decoded($jsonProcess->getOutput());

            self::assertSame(0, $textProcess->getExitCode());
            self::assertSame(0, $jsonProcess->getExitCode());
            self::assertStringContainsString(
                '  ' . self::THIRD_VERSION . '  create tokens',
                $textProcess->getOutput(),
            );
            self::assertStringContainsString(
                '  ' . self::SECOND_VERSION . '  create sessions',
                $textProcess->getOutput(),
            );
            self::assertStringContainsString('rolled back 2 migrations:', $textProcess->getOutput());
            self::assertSame(
                [
                    ['version' => self::THIRD_VERSION, 'name' => 'create tokens'],
                    ['version' => self::SECOND_VERSION, 'name' => 'create sessions'],
                ],
                $document['rolledBack'] ?? null,
                'the JSON twin carries the same facts the text twin spelled over the same operation',
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * A flag the verb does not own refuses with the usage, exit 2 --
     * the status precedent's own grammar: `--steps` is the rollback
     * verb's, never the run's.
     */
    public function testAFlagTheRunVerbDoesNotOwnRefusesWithTheUsage(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(
                ['migrations:run', $host . '/configs', '--steps=1'],
                $host,
            );
            $process->run();

            self::assertSame(2, $process->getExitCode());
            self::assertSame('', $process->getOutput());
            self::assertStringContainsString(
                'bin/zef migrations:run <config-directory> [<migrations-directory>] [--json]',
                $process->getErrorOutput(),
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The usage line the verbs share spells both mutation rows with
     * their own flags -- the directory surface a host reads before the
     * first invocation.
     */
    public function testTheUsageSpellsTheMutationRowsWithTheJsonFlag(): void
    {
        $process = $this->command(['migrations:run'], \dirname(__DIR__, 2));
        $process->run();

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString(
            'bin/zef migrations:run <config-directory> [<migrations-directory>] [--json]',
            $process->getErrorOutput(),
        );
        self::assertStringContainsString(
            'bin/zef migrations:rollback <config-directory>'
            . ' [<migrations-directory>] [--steps=<n>] [--json]',
            $process->getErrorOutput(),
        );
    }

    /**
     * The operational refusal stays the operational refusal in both
     * spellings: a missing migrations directory answers exit 1 naming
     * the directory, the answer never reaching the standard output.
     */
    public function testAMissingMigrationsDirectoryRefusesWithExitOne(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(
                ['migrations:run', $host . '/configs', $host . '/no-such-directory', '--json'],
                $host,
            );
            $process->run();

            self::assertSame(1, $process->getExitCode());
            self::assertSame('', $process->getOutput());
            self::assertStringContainsString(
                "The migrations directory '{$host}/no-such-directory' does not exist"
                . ' or is not a directory.',
                $process->getErrorOutput(),
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * One host fixture: the declared database profile over the
     * temporary SQLite file and three declared migrations -- the same
     * shape the boot-level pins ride, one run away from applied.
     *
     * @return non-empty-string the host root's path
     */
    private function host(): string
    {
        $host = sys_get_temp_dir() . '/zef-migrations-mutation-cli-' . uniqid('', false);
        mkdir($host . '/configs', 0o700, true);
        mkdir($host . '/migrations', 0o700, true);

        file_put_contents($host . '/configs/database.php', '<?php return [' . \PHP_EOL
            . "    'default' => 'app'," . \PHP_EOL
            . "    'connections' => ['app' => ['driver' => 'sqlite', 'dbname' => " . var_export(
                $host . '/migrations.sqlite',
                true,
            ) . ']],' . \PHP_EOL
            . '];');

        $declaredMigrations = [
            [self::FIRST_VERSION, 'create users', 'users', 'id INTEGER PRIMARY KEY'],
            [self::SECOND_VERSION, 'create sessions', 'sessions', 'id TEXT PRIMARY KEY'],
            [self::THIRD_VERSION, 'create tokens', 'tokens', 'id TEXT PRIMARY KEY'],
        ];

        foreach ($declaredMigrations as [$version, $name, $table, $column]) {
            file_put_contents(
                $host . '/migrations/' . $version . '-' . str_replace(' ', '-', $name) . '.php',
                '<?php return new Zef\Test\Support\Database\ScriptedMigration('
                . var_export($version, true) . ', ' . var_export($name, true) . ', '
                . var_export(["CREATE TABLE {$table} ({$column})"], true) . ', '
                . var_export(["DROP TABLE {$table}"], true) . ');',
            );
        }

        return $host;
    }

    /**
     * @param list<string> $arguments
     */
    private function command(array $arguments, string $workingDirectory): Process
    {
        return new Process(
            [\PHP_BINARY, \dirname(__DIR__, 2) . '/' . self::COMMAND, ...$arguments],
            $workingDirectory,
        );
    }

    /**
     * The command's JSON answer, decoded and shaped to the map the
     * assertions read through.
     *
     * @return array<array-key, mixed>
     */
    private function decoded(string $output): array
    {
        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The command output did not decode to an object.');
        }

        return $decoded;
    }

    /**
     * Removes the host fixture: the declared profile, the three
     * migrations, the SQLite file the runs materialised, and the
     * directories that held them.
     */
    private function clean(string $host): void
    {
        $patterns = [$host . '/configs/*.php', $host . '/migrations/*.php', $host . '/*.sqlite'];

        foreach ($patterns as $pattern) {
            foreach ((array) glob($pattern) as $entry) {
                if (!\is_string($entry) || !is_file($entry)) {
                    continue;
                }

                unlink($entry);
            }
        }

        if (!is_dir($host)) {
            return;
        }

        rmdir($host . '/configs');
        rmdir($host . '/migrations');
        rmdir($host);
    }
}
