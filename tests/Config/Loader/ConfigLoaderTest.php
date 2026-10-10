<?php

declare(strict_types=1);

namespace Zef\Test\Config\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Loader\ConfigLoader;

/**
 * @internal
 */
#[CoversClass(ConfigLoader::class)]
final class ConfigLoaderTest extends TestCase
{
    public function testFileLoadsAConfigurationFile(): void
    {
        $data = ConfigLoader::file(__DIR__ . '/fixtures/app.php');

        self::assertSame(['name' => 'zef', 'debug' => true], $data);
    }

    public function testFileReturnsTheDataUnchangedWithoutAPrefix(): void
    {
        $data = ConfigLoader::file(__DIR__ . '/fixtures/app.php', '');

        self::assertSame(['name' => 'zef', 'debug' => true], $data);
    }

    public function testFileNestsTheDataUnderASingleSegmentPrefix(): void
    {
        $data = ConfigLoader::file(__DIR__ . '/fixtures/app.php', 'custom');

        self::assertSame(
            ['custom' => ['name' => 'zef', 'debug' => true]],
            $data,
        );
    }

    public function testFileNestsTheDataUnderAMultiSegmentPrefix(): void
    {
        $data = ConfigLoader::file(__DIR__ . '/fixtures/app.php', 'runtime.sources');

        self::assertSame(
            ['runtime' => ['sources' => ['name' => 'zef', 'debug' => true]]],
            $data,
        );
    }

    public function testFileRejectsAMalformedPrefix(): void
    {
        try {
            ConfigLoader::file(__DIR__ . '/fixtures/app.php', 'a..b');
            self::fail('A malformed prefix must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "a..b" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testFileRejectsAMissingFile(): void
    {
        $path = __DIR__ . '/fixtures/missing.php';

        try {
            ConfigLoader::file($path);
            self::fail('A missing configuration file must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration file "%s" is not a regular file.', $path),
                $configException->getMessage(),
            );
        }
    }

    public function testDirectoryLoadsEveryPhpFileUnderItsBasename(): void
    {
        $data = ConfigLoader::directory(__DIR__ . '/fixtures/project');

        self::assertSame(
            [
                'app' => ['name' => 'project'],
                'database' => ['mysql' => ['host' => 'localhost', 'port' => 3306]],
            ],
            $data,
        );
    }

    public function testDirectoryReturnsTheDataUnchangedWithoutAPrefix(): void
    {
        $data = ConfigLoader::directory(__DIR__ . '/fixtures/project', '');

        self::assertSame(
            [
                'app' => ['name' => 'project'],
                'database' => ['mysql' => ['host' => 'localhost', 'port' => 3306]],
            ],
            $data,
        );
    }

    public function testDirectoryNestsTheDataUnderAPrefix(): void
    {
        $data = ConfigLoader::directory(__DIR__ . '/fixtures/project', 'sources');

        self::assertSame(
            [
                'sources' => [
                    'app' => ['name' => 'project'],
                    'database' => ['mysql' => ['host' => 'localhost', 'port' => 3306]],
                ],
            ],
            $data,
        );
    }

    public function testDirectoryRejectsAMissingDirectory(): void
    {
        $path = __DIR__ . '/fixtures/missing-directory';

        try {
            ConfigLoader::directory($path);
            self::fail('A missing configuration directory must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('The configuration source "%s" is not a directory.', $path),
                $configException->getMessage(),
            );
        }
    }
}
