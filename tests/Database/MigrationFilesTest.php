<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Database\MigrationException;
use Zef\Framework\Database\MigrationFile;
use Zef\Framework\Database\MigrationFiles;

/**
 * Pins the migration files' discovery fold: the operator's migrations
 * directory as a source of declared migrations -- the happy walk over
 * one-file-one-migration (dot-files invisible, no ordering contract),
 * and every refusal naming exactly what an operator needs to find the
 * defect: the missing directory, the file whose return is not a
 * migration, the glob match that is not a regular file, and the
 * version two files both declare (BOTH files named, the reason the
 * duplicate guard lives here and not only at the register verb) --
 * the one-file declaration fold ({@see MigrationFile}) walked through
 * the discovery's own every branch.
 *
 * @internal
 */
#[CoversClass(MigrationFile::class)]
#[CoversClass(MigrationFiles::class)]
final class MigrationFilesTest extends TestCase
{
    private ?string $directory = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->directory !== null && is_dir($this->directory)) {
            $entries = glob($this->directory . '/*');
            $hidden = glob($this->directory . '/.*');

            foreach (array_merge(\is_array($entries) ? $entries : [], \is_array($hidden) ? $hidden : []) as $entry) {
                if (is_file($entry)) {
                    unlink($entry);
                } elseif (is_dir($entry) && !str_ends_with($entry, '/.') && !str_ends_with($entry, '/..')) {
                    rmdir($entry);
                }
            }

            rmdir($this->directory);
        }

        $this->directory = null;
    }

    public function testDiscoversOneMigrationPerFileKeyedByVersion(): void
    {
        $this->writeMigration('20261102120000', 'create users');
        $this->writeMigration('20261102130000', 'create sessions');

        $rows = [];
        foreach (MigrationFiles::discover($this->directory()) as $version => $migration) {
            $rows[] = $version . ' ' . $migration->name();
        }

        self::assertSame(
            ['20261102120000 create users', '20261102130000 create sessions'],
            $rows,
            'the discovered map is keyed by version, one migration per file,'
                . " ascending through the walk's own deterministic order",
        );
    }

    public function testAnEmptyDirectoryDiscoversNothing(): void
    {
        self::assertSame([], MigrationFiles::discover($this->directory()));
    }

    public function testDotFilesStayInvisibleToTheScan(): void
    {
        $this->writeMigration('20261102120000', 'create users');
        file_put_contents($this->directory() . '/.staged.php', '<?php return 1;');

        $migrations = MigrationFiles::discover($this->directory());

        self::assertSame(['20261102120000'], array_map(strval(...), array_keys($migrations)));
    }

    public function testNonPhpFilesAreNeverIncluded(): void
    {
        $this->writeMigration('20261102120000', 'create users');
        file_put_contents($this->directory() . '/NOTES.md', 'operator notes');

        $migrations = MigrationFiles::discover($this->directory());

        self::assertSame(['20261102120000'], array_map(strval(...), array_keys($migrations)));
    }

    public function testAMissingDirectoryRefusesNamingIt(): void
    {
        $this->expectExceptionObject(new MigrationException(
            "The migrations directory '/nowhere/migrations' does not exist or is not a directory.",
        ));

        MigrationFiles::discover('/nowhere/migrations');
    }

    public function testAFileReturningAMigrationInstanceRefusesNamingTheFile(): void
    {
        file_put_contents($this->directory() . '/stray.php', '<?php return 1;');

        $this->expectExceptionObject(new MigrationException(
            "Migration file '" . $this->directory() . "/stray.php' must return a MigrationInterface instance.",
        ));

        MigrationFiles::discover($this->directory());
    }

    public function testAGlobMatchThatIsNotARegularFileRefusesNamingIt(): void
    {
        mkdir($this->directory() . '/stray.php');

        $this->expectExceptionObject(new MigrationException(
            "Migration file '" . $this->directory() . "/stray.php' is not a regular file.",
        ));

        MigrationFiles::discover($this->directory());
    }

    public function testAThrowingFileSurfacesItsOwnFailure(): void
    {
        file_put_contents(
            $this->directory() . '/broken.php',
            '<?php throw new RuntimeException("host migration defect");',
        );

        try {
            MigrationFiles::discover($this->directory());
            self::fail('The host migration defect must surface unchanged.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('host migration defect', $runtimeException->getMessage());
        }
    }

    public function testTwoFilesDeclaringTheSameVersionRefuseNamingBoth(): void
    {
        $this->writeMigration('20261102120000', 'create users');
        $this->writeMigration('20261102120000', 'create sessions');

        // The glob walks alphabetically, so the sessions file --
        // 'create-s' sorts before 'create-u' -- is the FIRST declarer.
        $this->expectExceptionObject(new MigrationException(
            "Migration version '20261102120000' is declared by both"
            . " '" . $this->directory() . "/20261102120000-create-sessions.php'"
            . " and '" . $this->directory() . "/20261102120000-create-users.php'.",
        ));

        MigrationFiles::discover($this->directory());
    }

    private function directory(): string
    {
        if ($this->directory === null) {
            $this->directory = sys_get_temp_dir() . '/zef-migration-files-' . uniqid();
            mkdir($this->directory, 0o777, true);
        }

        return $this->directory;
    }

    private function writeMigration(string $version, string $name): void
    {
        $file = $this->directory() . '/' . $version . '-' . str_replace(' ', '-', $name) . '.php';

        file_put_contents($file, '<?php return new Zef\Test\Support\Database\ScriptedMigration('
            . var_export($version, true) . ', '
            . var_export($name, true) . ');');
    }
}
