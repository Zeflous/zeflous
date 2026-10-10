<?php

declare(strict_types=1);

namespace Zef\Test\Config\Loader;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Loader\PhpFileLoader;

/**
 * @internal
 */
#[CoversClass(PhpFileLoader::class)]
final class PhpFileLoaderTest extends TestCase
{
    private string $temporaryPath;

    #[Override]
    protected function setUp(): void
    {
        $this->temporaryPath = (string) tempnam(sys_get_temp_dir(), 'zef-phpfile-');
        unlink($this->temporaryPath);
        mkdir($this->temporaryPath);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->temporaryPath . '/module.php')) {
            rmdir($this->temporaryPath . '/module.php');
        }

        rmdir($this->temporaryPath);
    }

    public function testLoadsAFileOfScalars(): void
    {
        $data = new PhpFileLoader(['app.php'])->load(__DIR__ . '/fixtures/app.php');

        self::assertSame(['name' => 'zef', 'debug' => true], $data);
    }

    public function testLoadsAFileReturningAnEmptyArray(): void
    {
        $data = new PhpFileLoader(['empty-array.php'])->load(__DIR__ . '/fixtures/empty-array.php');

        self::assertSame([], $data);
    }

    public function testLoadsTheSameFileTwice(): void
    {
        $phpFileLoader = new PhpFileLoader(['app.php']);
        $path = __DIR__ . '/fixtures/app.php';

        self::assertSame(['name' => 'zef', 'debug' => true], $phpFileLoader->load($path));
        self::assertSame(['name' => 'zef', 'debug' => true], $phpFileLoader->load($path));
    }

    public function testRejectsAFileMissingFromTheAllowlist(): void
    {
        $path = __DIR__ . '/fixtures/app.php';

        try {
            new PhpFileLoader(['database.php'])->load($path);
            self::fail('A configuration file outside the allowlist must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" is not allowed.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAPathThatCannotBeResolved(): void
    {
        $path = 'file://' . __DIR__ . '/fixtures/app.php';

        try {
            new PhpFileLoader(['app.php'])->load($path);
            self::fail('A stream-wrapper path must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" could not be resolved.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testExecutesTheCanonicalPathResolvedBeforeLoading(): void
    {
        $path = __DIR__ . '/./fixtures/returns-int.php';

        try {
            new PhpFileLoader(['returns-int.php'])->load($path);
            self::fail('A scalar return value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" must return an array.', (string) realpath($path)),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAMissingFile(): void
    {
        $path = __DIR__ . '/fixtures/missing.php';

        try {
            new PhpFileLoader(['missing.php'])->load($path);
            self::fail('A missing configuration file must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" could not be resolved.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnUnsupportedExtension(): void
    {
        $path = __DIR__ . '/fixtures/notes.txt';

        try {
            new PhpFileLoader(['notes.txt'])->load($path);
            self::fail('A non-php configuration source must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf(
                    'The configuration file "%s" does not use the supported .php extension.',
                    (string) realpath($path),
                ),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsADirectoryNamedLikeAPhpFile(): void
    {
        mkdir($this->temporaryPath . '/module.php');

        $path = $this->temporaryPath . '/module.php';

        try {
            new PhpFileLoader(['module.php'])->load($path);
            self::fail('A directory named like a php file must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" is not a regular file.', (string) realpath($path)),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAScalarReturnValue(): void
    {
        $path = __DIR__ . '/fixtures/returns-int.php';

        try {
            new PhpFileLoader(['returns-int.php'])->load($path);
            self::fail('A scalar return value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" must return an array.', (string) realpath($path)),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsANullReturnValue(): void
    {
        $path = __DIR__ . '/fixtures/returns-null.php';

        try {
            new PhpFileLoader(['returns-null.php'])->load($path);
            self::fail('A null return value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" must return an array.', (string) realpath($path)),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAFileWithoutAReturnStatement(): void
    {
        $path = __DIR__ . '/fixtures/no-return.php';

        try {
            new PhpFileLoader(['no-return.php'])->load($path);
            self::fail('A configuration file without a return statement must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" must return an array.', (string) realpath($path)),
                $configException->getMessage(),
            );
        }
    }
}
