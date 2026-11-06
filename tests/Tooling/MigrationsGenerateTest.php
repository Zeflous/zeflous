<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\MigrationException;
use Zef\Framework\Database\MigrationTemplate;
use Zef\Test\Support\Tooling\MigrationsGenerate;
use Zef\Test\Support\Tooling\MigrationsGenerateJson;
use Zef\Test\Support\Tooling\MigrationsGenerateText;

/**
 * The migrations scaffolding verb's own fold ({@see MigrationsGenerate})
 * and its renderer pair ({@see MigrationsGenerateText}, {@see
 * MigrationsGenerateJson}): the refusal table the design record names as
 * the contract -- each defect answered with its own verdict, never a
 * traceback an operator has to parse -- and the answer's two spellings,
 * pinned the way the family's renderers are pinned.
 *
 * @internal
 */
#[CoversClass(MigrationsGenerate::class)]
#[CoversClass(MigrationsGenerateText::class)]
#[CoversClass(MigrationsGenerateJson::class)]
final class MigrationsGenerateTest extends TestCase
{
    private string $migrationsDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->migrationsDirectory = sys_get_temp_dir()
            . '/zef-migrations-generate-' . uniqid('', false);

        mkdir($this->migrationsDirectory, 0o700, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ((array) glob($this->migrationsDirectory . '/*') as $file) {
            if (!\is_string($file) || !is_file($file)) {
                continue;
            }

            unlink($file);
        }

        if (!is_dir($this->migrationsDirectory)) {
            return;
        }

        rmdir($this->migrationsDirectory);
    }

    /**
     * The write itself: the file lands in the directory the caller
     * spelled, carrying the template's own bytes, and the answer is
     * the created migration's own identity -- the path, the version
     * stamp, the canonical name.
     */
    public function testTheWriteLandsTheTemplatesOwnBytes(): void
    {
        $answer = MigrationsGenerate::written(
            $this->migrationsDirectory,
            'create users',
            '20261106143000',
        );

        $file = $this->migrationsDirectory . '/20261106143000-create-users.php';

        self::assertSame($file, $answer['file']);
        self::assertSame('20261106143000', $answer['version']);
        self::assertSame('create users', $answer['name']);
        self::assertFileExists($file);
        self::assertSame(
            MigrationTemplate::content('create users', '20261106143000'),
            file_get_contents($file),
        );
    }

    /**
     * The hyphen-spelled name lands the same file the space-spelled
     * name lands: one migration, one canonical name, whichever
     * separator the argument carried.
     */
    public function testTheHyphenSpelledNameLandsTheSameFile(): void
    {
        $answer = MigrationsGenerate::written(
            $this->migrationsDirectory,
            'create-users',
            '20261106143000',
        );

        self::assertSame('create users', $answer['name']);
        self::assertSame(
            '20261106143000-create-users.php',
            basename($answer['file']),
        );
    }

    /**
     * The trailing-slash spelling of the directory lands the same
     * file: the boundary trims the separator, never inventing a
     * doubled path.
     */
    public function testTheTrailingSlashDirectoryLandsTheSameFile(): void
    {
        $answer = MigrationsGenerate::written(
            $this->migrationsDirectory . '/',
            'create users',
            '20261106143000',
        );

        self::assertSame(
            $this->migrationsDirectory . '/20261106143000-create-users.php',
            $answer['file'],
        );
    }

    /**
     * The refusal table's missing-directory row: the same verdict
     * the discovery fold words for the same directory, the read
     * side and the write side answering one grammar.
     */
    public function testAMissingDirectoryRefusesAsTheDiscoveryFoldWordsIt(): void
    {
        $missing = $this->migrationsDirectory . '/no-such-directory';

        $this->assertRefused(
            "The migrations directory '{$missing}' does not exist or is not a directory.",
            static fn (): array => MigrationsGenerate::written($missing, 'create users', '20261106143000'),
        );
    }

    /**
     * The refusal table's plain-file row: a directory path worn by
     * a regular file is not a directory, the same verdict.
     */
    public function testAPlainFileWearingThePathRefuses(): void
    {
        $file = $this->migrationsDirectory . '/occupied';
        file_put_contents($file, 'not a directory');

        $this->assertRefused(
            "The migrations directory '{$file}' does not exist or is not a directory.",
            static fn (): array => MigrationsGenerate::written($file, 'create users', '20261106143000'),
        );
    }

    /**
     * The refusal table's unwritable row: a directory that cannot
     * hold the file refuses naming it -- the write side's own
     * verdict, the storage lane's own grammar.
     */
    public function testAnUnwritableDirectoryRefusesNamingIt(): void
    {
        chmod($this->migrationsDirectory, 0o500);

        try {
            $this->assertRefused(
                "The migrations directory '{$this->migrationsDirectory}' is not writable.",
                fn (): array => MigrationsGenerate::written(
                    $this->migrationsDirectory,
                    'create users',
                    '20261106143000',
                ),
            );
        } finally {
            chmod($this->migrationsDirectory, 0o700);
        }
    }

    /**
     * The refusal table's collision row: a name colliding with an
     * existing file refuses, never a silent clobber -- the existing
     * file's own bytes survive the refusal untouched.
     */
    public function testANameCollidingWithAnExistingFileRefuses(): void
    {
        $file = $this->migrationsDirectory . '/20261106143000-create-users.php';
        file_put_contents($file, "the operator's own hand-written migration");

        $this->assertRefused(
            "The migration file '{$file}' already exists.",
            fn (): array => MigrationsGenerate::written($this->migrationsDirectory, 'create users', '20261106143000'),
        );
        self::assertSame(
            "the operator's own hand-written migration",
            file_get_contents($file),
        );
    }

    /**
     * The refusal table's held-version row: a version stamp already
     * held by an existing file refuses naming the holder -- the
     * write verb never prints a file the discovery walk would
     * refuse for a duplicate version.
     */
    public function testAVersionAlreadyHeldByAnExistingFileRefusesNamingTheHolder(): void
    {
        $holder = $this->migrationsDirectory . '/20261106143000-create-accounts.php';
        file_put_contents($holder, "the operator's own hand-written migration");

        $this->assertRefused(
            "The migration version '20261106143000' is already held by the existing"
            . " file '{$holder}'.",
            fn (): array => MigrationsGenerate::written($this->migrationsDirectory, 'create users', '20261106143000'),
        );
    }

    /**
     * A hand-written file whose name carries no stamp does not trip
     * the held-version guard: its duplicate version, if any, stays
     * the discovery walk's own refusal, where loading host code
     * belongs -- the guard reads the file grammar the template
     * itself prints, nothing broader.
     */
    public function testAnUnstampedHandWrittenFileStaysOutsideTheVersionGuard(): void
    {
        file_put_contents(
            $this->migrationsDirectory . '/legacy-operators-own-file.php',
            "a hand-written migration wearing the house's oldest grammar",
        );

        $answer = MigrationsGenerate::written(
            $this->migrationsDirectory,
            'create users',
            '20261106143000',
        );

        self::assertSame(
            $this->migrationsDirectory . '/20261106143000-create-users.php',
            $answer['file'],
        );
    }

    /**
     * The construction-value refusals pass through unchanged: the
     * name and version grammars are the template's own corridor,
     * answered before the world is consulted.
     */
    public function testTheTemplateGrammarRefusalsPassThrough(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'Migration name must be lowercase words of letters and digits',
        );

        MigrationsGenerate::written($this->migrationsDirectory, 'Create Users', '20261106143000');
    }

    /**
     * The text answer: the family's own header and the created
     * file's path on its own indented row, byte for byte.
     */
    public function testTheTextAnswerPinsThePath(): void
    {
        self::assertSame(
            "Migrations generate -- 1 file created:\n"
            . "  /host/app/migrations/20261106143000-create-users.php\n",
            MigrationsGenerateText::created('/host/app/migrations/20261106143000-create-users.php'),
        );
    }

    /**
     * The JSON answer: the created file's path, its version stamp
     * and its canonical name, over the inspection family's one
     * encoding spelling -- decoded here, pinned by value.
     */
    public function testTheJsonAnswerPinsTheIdentity(): void
    {
        $document = json_decode(
            MigrationsGenerateJson::rendered(
                '20261106143000',
                'create users',
                '/host/app/migrations/20261106143000-create-users.php',
            ),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            [
                'created' => '/host/app/migrations/20261106143000-create-users.php',
                'version' => '20261106143000',
                'name' => 'create users',
            ],
            $document,
        );
    }

    /**
     * One refusal, one typed verdict, one message.
     *
     * @param callable(): array{file: string, version: string, name: string} $write
     */
    private function assertRefused(string $message, callable $write): void
    {
        try {
            $write();
            self::fail('The write was not refused.');
        } catch (MigrationException $migrationException) {
            self::assertSame($message, $migrationException->getMessage());
        }
    }
}
