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
        // zeta.php is created *before* alpha.php on purpose: the unsorted
        // directory iteration order must differ from the sorted expectation
        // on every supported filesystem, otherwise killing the ksort()-removal
        // mutant depends on readdir luck rather than on the test itself.
        file_put_contents($this->directory . '/zeta.php', '<?php return ["key" => "zeta"];');
        file_put_contents($this->directory . '/alpha.php', '<?php return ["key" => "alpha"];');
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->directory . '/zeta.php');
        unlink($this->directory . '/alpha.php');

        if (is_dir($this->directory . '/module.php')) {
            rmdir($this->directory . '/module.php');
        }

        rmdir($this->directory);
    }

    public function testLoadsFilesSortedByNameRegardlessOfCreationOrder(): void
    {
        $data = new DirectoryLoader(new PhpFileLoader(['zeta.php', 'alpha.php']))->load($this->directory);

        self::assertSame(
            ['alpha' => ['key' => 'alpha'], 'zeta' => ['key' => 'zeta']],
            $data,
        );
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
            new DirectoryLoader(new PhpFileLoader(['alpha.php']))->load($this->directory);
            self::fail('A directory child outside the allowlist must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf(
                    'The configuration file "%s" is not allowed.',
                    $this->directory . '/zeta.php',
                ),
                $configException->getMessage(),
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
            new DirectoryLoader(new PhpFileLoader(['zeta.php', 'alpha.php', 'module.php']))
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
