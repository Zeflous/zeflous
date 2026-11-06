<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Zef\Framework\Database\MigrationInterface;

/**
 * Exercises the migrations scaffolding command end to end as a
 * real subprocess, the same way a host's shell invokes it -- the
 * errors lane's own CLI family discipline (`bin/zef errors:*`)
 * joined by the route inspection commands: guarded arguments,
 * honest exit codes (0 the created answer, 1 the operational
 * refusal -- a directory that cannot hold the file, a version the
 * directory already holds, 2 the usage defect -- the operator's own
 * arguments wrong), the answer on the standard output and the
 * refusals on the standard error.
 *
 * The verb never boots the application and never touches a
 * database: a configuration directory that spells NO database
 * profile at all still answers the scaffold, the design record's
 * own template-verb honesty, so the fixture is an empty directory
 * -- the honest posture, not a database-less coincidence.
 *
 * @internal
 */
#[CoversNothing]
final class MigrationsGenerateCommandTest extends TestCase
{
    private const string COMMAND = 'bin/zef';

    public function testTheDefaultSiblingDirectoryAnswersTheCreatedFile(): void
    {
        $host = $this->host();

        try {
            [$exitCode, $output] = $this->runCommand(
                ['migrations:generate', $host . '/configs', 'create users'],
                $host,
            );

            $files = $this->generatedFiles($host . '/migrations');

            self::assertSame(0, $exitCode);
            self::assertCount(1, $files);
            self::assertStringContainsString(
                'Migrations generate -- 1 file created:',
                $output,
            );
            self::assertStringContainsString($files[0], $output);
            self::assertMatchesRegularExpression(
                '~/migrations/\d{14}-create-users\.php$~',
                $files[0],
            );
        } finally {
            $this->clean($host);
        }
    }

    public function testTheCreatedFileAnswersTheInterfaceContract(): void
    {
        $host = $this->host();

        try {
            [$exitCode] = $this->runCommand(
                ['migrations:generate', $host . '/configs', 'enable 2fa login'],
                $host,
            );

            $files = $this->generatedFiles($host . '/migrations');

            self::assertSame(0, $exitCode);
            self::assertCount(1, $files);

            $migration = include $files[0];

            self::assertInstanceOf(MigrationInterface::class, $migration);
            self::assertSame(basename($files[0], '.php'), $migration->version()
                . '-enable-2fa-login');
            self::assertSame('enable 2fa login', $migration->name());
            self::assertNull($migration->getLockTtl());
        } finally {
            $this->clean($host);
        }
    }

    public function testTheExplicitMigrationsDirectoryAnswersTheCreatedFile(): void
    {
        $host = $this->host();
        mkdir($host . '/declared-migrations', 0o700, true);

        try {
            [$exitCode, $output] = $this->runCommand(
                ['migrations:generate', $host . '/configs', $host . '/declared-migrations', 'audit table'],
                $host,
            );

            $files = $this->generatedFiles($host . '/declared-migrations');

            self::assertSame(0, $exitCode);
            self::assertCount(1, $files);
            self::assertStringContainsString($files[0], $output);
            self::assertMatchesRegularExpression(
                '~/declared-migrations/\d{14}-audit-table\.php$~',
                $files[0],
            );
        } finally {
            $this->clean($host);
        }
    }

    public function testTheJsonAnswerPinsTheCreatedIdentity(): void
    {
        $host = $this->host();

        try {
            [$exitCode, $output] = $this->runCommand(
                ['migrations:generate', $host . '/configs', 'create users', '--json'],
                $host,
            );

            $files = $this->generatedFiles($host . '/migrations');
            $document = $this->decoded($output);

            self::assertSame(0, $exitCode);
            self::assertCount(1, $files);
            self::assertSame($files[0], $document['created']);

            $version = $document['version'] ?? null;

            self::assertIsString($version);
            self::assertMatchesRegularExpression('/^\d{14}$/', $version);
            self::assertSame('create users', $document['name']);
        } finally {
            $this->clean($host);
        }
    }

    public function testAMissingMigrationsDirectoryRefusesWithExitOne(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(
                ['migrations:generate', $host . '/configs', $host . '/no-such-directory', 'create users'],
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

    public function testAnUnwritableMigrationsDirectoryRefusesWithExitOne(): void
    {
        $host = $this->host();
        mkdir($host . '/locked-migrations', 0o500, true);

        try {
            $process = $this->command(
                ['migrations:generate', $host . '/configs', $host . '/locked-migrations', 'create users'],
                $host,
            );
            $process->run();

            self::assertSame(1, $process->getExitCode());
            self::assertStringContainsString(
                "The migrations directory '{$host}/locked-migrations' is not writable.",
                $process->getErrorOutput(),
            );
        } finally {
            chmod($host . '/locked-migrations', 0o700);
            $this->clean($host);
        }
    }

    public function testANameOutsideTheGrammarAnswersTheUsageOnTheStandardError(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(
                ['migrations:generate', $host . '/configs', 'Create Users'],
                $host,
            );
            $process->run();

            self::assertSame(2, $process->getExitCode());
            self::assertSame('', $process->getOutput());
            self::assertStringContainsString(
                'bin/zef migrations:generate <config-directory> [<migrations-directory>] <name> [--json]',
                $process->getErrorOutput(),
            );
            self::assertSame([], $this->generatedFiles($host . '/migrations'));
        } finally {
            $this->clean($host);
        }
    }

    public function testAMissingNameAnswersTheUsageOnTheStandardError(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(['migrations:generate', $host . '/configs'], $host);
            $process->run();

            self::assertSame(2, $process->getExitCode());
            self::assertStringContainsString(
                'bin/zef migrations:generate <config-directory> [<migrations-directory>] <name> [--json]',
                $process->getErrorOutput(),
            );
        } finally {
            $this->clean($host);
        }
    }

    public function testAnUnknownFlagAnswersTheUsageOnTheStandardError(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(
                ['migrations:generate', $host . '/configs', 'create users', '--steps=1'],
                $host,
            );
            $process->run();

            self::assertSame(2, $process->getExitCode());
            self::assertStringContainsString(
                'bin/zef migrations:generate <config-directory> [<migrations-directory>] <name> [--json]',
                $process->getErrorOutput(),
            );
            self::assertSame([], $this->generatedFiles($host . '/migrations'));
        } finally {
            $this->clean($host);
        }
    }

    public function testAMissingConfigurationDirectoryAnswersExitTwo(): void
    {
        $host = $this->host();

        try {
            $process = $this->command(
                ['migrations:generate', $host . '/no-configs', 'create users'],
                $host,
            );
            $process->run();

            self::assertSame(2, $process->getExitCode());
            self::assertStringContainsString(
                'Configuration directory does not exist',
                $process->getErrorOutput(),
            );
        } finally {
            $this->clean($host);
        }
    }

    /**
     * The usage line the verbs share, spelled with the generate
     * row -- the directory surface a host reads before the first
     * invocation.
     */
    public function testTheUsageSpellsTheGenerateRow(): void
    {
        $process = $this->command(['migrations:generate'], \dirname(__DIR__, 2));
        $process->run();

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString(
            'bin/zef migrations:generate <config-directory> [<migrations-directory>] <name> [--json]',
            $process->getErrorOutput(),
        );
    }

    /**
     * The directory listing answers the new verb's own line: the
     * vocabulary and the CLI's dispatch spell one surface.
     */
    public function testTheDirectoryAnswersTheGenerateLine(): void
    {
        [$exitCode, $output] = $this->runCommand(['list', 'app/configs'], \dirname(__DIR__, 2));

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Zef commands -- 16 framework', $output);
        self::assertStringContainsString(
            'migrations:generate  Scaffold a new migration file from the house template,'
            . ' no database touched.',
            $output,
        );
    }

    /**
     * One host fixture: an empty configuration directory (the verb
     * never boots it -- the empty spelling is the honest posture)
     * and the sibling migrations directory the default resolves to.
     *
     * @return non-empty-string the host root's path
     */
    private function host(): string
    {
        $host = sys_get_temp_dir() . '/zef-migrations-generate-cli-' . uniqid('', false);
        mkdir($host . '/configs', 0o700, true);
        mkdir($host . '/migrations', 0o700, true);

        return $host;
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runCommand(array $arguments, string $workingDirectory): array
    {
        $process = $this->command($arguments, $workingDirectory);
        $process->run();

        return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
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
     * The migration files a generate left in one directory.
     *
     * @return list<string>
     */
    private function generatedFiles(string $directory): array
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return [];
        }

        return array_values(array_filter($files, is_file(...)));
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
     * Removes the host fixture, its locked directory's own
     * permission change undone by the caller that made it.
     */
    private function clean(string $host): void
    {
        foreach ((array) glob($host . '/*') as $child) {
            if (!\is_string($child)) {
                continue;
            }

            if (is_dir($child)) {
                foreach ((array) glob($child . '/*') as $grandChild) {
                    if (!\is_string($grandChild) || !is_file($grandChild)) {
                        continue;
                    }

                    unlink($grandChild);
                }

                if (is_dir($child)) {
                    rmdir($child);
                }
            } elseif (is_file($child)) {
                unlink($child);
            }
        }

        if (!is_dir($host)) {
            return;
        }

        rmdir($host);
    }
}
