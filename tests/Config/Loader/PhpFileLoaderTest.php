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
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_dir($this->temporaryPath)) {
            return;
        }

        rmdir($this->temporaryPath);
    }

    public function testLoadsAFileOfScalars(): void
    {
        $data = new PhpFileLoader()->load(__DIR__ . '/fixtures/app.php');

        self::assertSame(['name' => 'zef', 'debug' => true], $data);
    }

    public function testLoadsAFileReturningAnEmptyArray(): void
    {
        $data = new PhpFileLoader()->load(__DIR__ . '/fixtures/empty-array.php');

        self::assertSame([], $data);
    }

    public function testRejectsAMissingFile(): void
    {
        $path = __DIR__ . '/fixtures/missing.php';

        try {
            new PhpFileLoader()->load($path);
            self::fail('A missing configuration file must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" is not a regular file.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnUnsupportedExtension(): void
    {
        $path = __DIR__ . '/fixtures/notes.txt';

        try {
            new PhpFileLoader()->load($path);
            self::fail('A non-php configuration source must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" does not use the supported .php extension.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsADirectoryNamedLikeAPhpFile(): void
    {
        mkdir($this->temporaryPath . '.php');

        $path = $this->temporaryPath . '.php';

        try {
            new PhpFileLoader()->load($path);
            self::fail('A directory named like a php file must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" is not a regular file.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAScalarReturnValue(): void
    {
        $path = __DIR__ . '/fixtures/returns-int.php';

        try {
            new PhpFileLoader()->load($path);
            self::fail('A scalar return value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" must return an array.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsANullReturnValue(): void
    {
        $path = __DIR__ . '/fixtures/returns-null.php';

        try {
            new PhpFileLoader()->load($path);
            self::fail('A null return value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" must return an array.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAFileWithoutAReturnStatement(): void
    {
        $path = __DIR__ . '/fixtures/no-return.php';

        try {
            new PhpFileLoader()->load($path);
            self::fail('A configuration file without a return statement must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" must return an array.', $path),
                $configException->getMessage(),
            );
        }
    }
}
