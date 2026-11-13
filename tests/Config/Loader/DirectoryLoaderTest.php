<?php

declare(strict_types=1);

namespace Zef\Test\Config\Loader;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Loader\DirectoryLoader;
use Zef\Framework\Config\Loader\PhpFileLoader;

/**
 * @internal
 */
#[CoversClass(DirectoryLoader::class)]
final class DirectoryLoaderTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = (string) tempnam(sys_get_temp_dir(), 'zef-dir-');
        unlink($this->directory);
        mkdir($this->directory);
        // x-y.php is created *before* x.php on purpose, and the pair is
        // chosen so the two orders genuinely differ: by NAME the hyphen
        // sorts before the dot ('x-y.php' < 'x.php'), while by KEY the
        // shorter basename sorts first ('x' < 'x-y'). The sorted walk
        // delegates in name order, so the emission-order ksort() is the
        // only thing standing between the two -- its removal changes the
        // answer, regardless of how any filesystem orders the entries.
        file_put_contents($this->directory . '/x-y.php', '<?php return ["key" => "hyphen"];');
        file_put_contents($this->directory . '/x.php', '<?php return ["key" => "plain"];');
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->directory . '/x-y.php');
        unlink($this->directory . '/x.php');

        if (is_dir($this->directory . '/module.php')) {
            rmdir($this->directory . '/module.php');
        }

        rmdir($this->directory);
    }

    public function testLoadsFilesSortedByKeyRegardlessOfWalkOrder(): void
    {
        $data = new DirectoryLoader(new PhpFileLoader(['x-y.php', 'x.php']))->load($this->directory);

        self::assertSame(
            ['x' => ['key' => 'plain'], 'x-y' => ['key' => 'hyphen']],
            $data,
        );
    }

    public function testSkipsNonPhpAndHiddenEntriesBeforeALoadableFile(): void
    {
        $directory = (string) tempnam(sys_get_temp_dir(), 'zef-skip-');
        unlink($directory);
        mkdir($directory);

        // The skippable entries sort *before* the loadable one on purpose:
        // skipping is a per-entry decision, so a mutant that aborts the
        // whole iteration (continue -> break) stops before last.php is
        // ever reached and the load comes back empty. The walk's own
        // sorted-name order (not the filesystem's readdir order, and not
        // the creation order) guarantees that placement on every host --
        // the dot sorts below every letter, and 'a-notes.txt' sorts below
        // 'last.php'.
        file_put_contents($directory . '/.hidden.php', '<?php return [];');
        file_put_contents($directory . '/a-notes.txt', 'not php');
        file_put_contents($directory . '/last.php', '<?php return ["key" => "last"];');

        try {
            self::assertSame(
                ['last' => ['key' => 'last']],
                new DirectoryLoader(new PhpFileLoader(['last.php']))->load($directory),
            );
        } finally {
            unlink($directory . '/.hidden.php');
            unlink($directory . '/a-notes.txt');
            unlink($directory . '/last.php');
            rmdir($directory);
        }
    }

    public function testAnEmptyDirectoryYieldsAnEmptyArray(): void
    {
        $emptyDirectory = (string) tempnam(sys_get_temp_dir(), 'zef-empty-');
        unlink($emptyDirectory);
        mkdir($emptyDirectory);

        try {
            self::assertSame(
                [],
                new DirectoryLoader(new PhpFileLoader(['zeta.php', 'alpha.php']))->load($emptyDirectory),
            );
        } finally {
            rmdir($emptyDirectory);
        }
    }

    public function testLoadsEveryPhpFileUnderItsBasename(): void
    {
        $data = new DirectoryLoader(new PhpFileLoader(['app.php', 'database.php']))
            ->load(__DIR__ . '/fixtures/project')
        ;

        self::assertSame(
            [
                'app' => ['name' => 'project'],
                'database' => ['mysql' => ['host' => 'localhost', 'port' => 3306]],
            ],
            $data,
        );
    }

    public function testRejectsAChildMissingFromTheAllowlist(): void
    {
        try {
            new DirectoryLoader(new PhpFileLoader(['x.php']))->load($this->directory);
            self::fail('A directory child outside the allowlist must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf(
                    'The configuration file "%s" is not allowed.',
                    $this->directory . '/x-y.php',
                ),
                $configException->getMessage(),
                'the sorted walk delegates x-y.php first, so it is the refusal the allowlist answers',
            );
        }
    }

    public function testSkipsEntriesWithAnUnsupportedExtension(): void
    {
        $data = new DirectoryLoader(new PhpFileLoader(['app.php', 'database.php']))
            ->load(__DIR__ . '/fixtures/project')
        ;

        self::assertArrayNotHasKey('notes', $data);
    }

    public function testSkipsHiddenEntries(): void
    {
        $data = new DirectoryLoader(new PhpFileLoader(['app.php', 'database.php']))
            ->load(__DIR__ . '/fixtures/project')
        ;

        self::assertArrayNotHasKey('.hidden', $data);
        self::assertArrayNotHasKey('hidden', $data);
    }

    public function testRejectsAMissingDirectory(): void
    {
        $path = __DIR__ . '/fixtures/missing-directory';

        try {
            new DirectoryLoader(new PhpFileLoader(['app.php']))->load($path);
            self::fail('A missing configuration directory must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration source "%s" is not a directory.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAFileInsteadOfADirectory(): void
    {
        $path = __DIR__ . '/fixtures/app.php';

        try {
            new DirectoryLoader(new PhpFileLoader(['app.php']))->load($path);
            self::fail('A file addressed as a directory must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration source "%s" is not a directory.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsADirectoryEntryNamedLikeAPhpFile(): void
    {
        mkdir($this->directory . '/module.php');

        try {
            new DirectoryLoader(new PhpFileLoader(['x-y.php', 'x.php', 'module.php']))
                ->load($this->directory)
            ;
            self::fail('A nested directory named like a php file must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf(
                    'The configuration file "%s" is not a regular file.',
                    (string) realpath($this->directory . '/module.php'),
                ),
                $configException->getMessage(),
            );
        }
    }
}
