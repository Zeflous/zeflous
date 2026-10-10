<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * @internal
 */
#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testGetReturnsAFlatValue(): void
    {
        $config = new Config(['app' => 'zef']);

        self::assertSame('zef', $config->get('app'));
    }

    public function testGetReturnsANestedValue(): void
    {
        $config = new Config(['database' => ['mysql' => ['host' => 'localhost', 'port' => 3306]]]);

        self::assertSame('localhost', $config->get('database.mysql.host'));
        self::assertSame(3306, $config->get('database.mysql.port'));
    }

    public function testGetReturnsNullByDefaultForAMissingKey(): void
    {
        self::assertNull(new Config(['app' => 'zef'])->get('missing'));
    }

    public function testGetReturnsTheProvidedDefaultForAMissingKey(): void
    {
        $config = new Config(['app' => 'zef']);

        self::assertSame('fallback', $config->get('missing', 'fallback'));
    }

    public function testGetReturnsTheDefaultWhenAnIntermediateSegmentIsAScalar(): void
    {
        $config = new Config(['database' => 'mysql']);

        self::assertSame('fallback', $config->get('database.host', 'fallback'));
    }

    public function testGetReadsListEntriesByNumericSegment(): void
    {
        $config = new Config(['users' => [['name' => 'ada'], ['name' => 'grace']]]);

        self::assertSame('ada', $config->get('users.0.name'));
        self::assertSame('grace', $config->get('users.1.name'));
    }

    public function testGetReturnsTheDefaultForAnOutOfBoundsListIndex(): void
    {
        $config = new Config(['users' => [['name' => 'ada']]]);

        self::assertSame('fallback', $config->get('users.5.name', 'fallback'));
    }

    public function testGetReturnsAnExplicitNullInsteadOfTheDefault(): void
    {
        // "Explicitly null" is a found value: the default is only for paths
        // that do not resolve. This is what keeps has() meaningful.
        $config = new Config(['debug' => null]);

        self::assertNull($config->get('debug', 'fallback'));
    }

    public function testHasReturnsTrueForSetKeys(): void
    {
        $config = new Config(['database' => ['mysql' => ['host' => 'localhost']]]);

        self::assertTrue($config->has('database.mysql.host'));
    }

    public function testHasReturnsFalseForAMissingKey(): void
    {
        self::assertFalse(new Config(['app' => 'zef'])->has('missing'));
    }

    public function testHasReturnsFalseWhenAnIntermediateSegmentIsAScalar(): void
    {
        self::assertFalse(new Config(['database' => 'mysql'])->has('database.host'));
    }

    public function testHasReturnsTrueForAListIndex(): void
    {
        $config = new Config(['users' => [['name' => 'ada']]]);

        self::assertTrue($config->has('users.0.name'));
        self::assertFalse($config->has('users.5.name'));
    }

    public function testHasDetectsAnExplicitNullValue(): void
    {
        self::assertTrue(new Config(['debug' => null])->has('debug'));
    }

    public function testGetRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->get('app..name');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "app..name" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testHasRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->has('app.');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "app." is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testSplitReturnsAScopedRepository(): void
    {
        $config = new Config(['database' => ['mysql' => ['host' => 'localhost'], 'driver' => 'mysql']]);

        $database = $config->split('database');

        self::assertSame('localhost', $database->get('mysql.host'));
        self::assertSame('mysql', $database->get('driver'));
        self::assertTrue($database->has('mysql.host'));
        self::assertFalse($database->has('redis.host'));
    }

    public function testSplitKeepsTheSubArrayIntact(): void
    {
        $mysql = ['host' => 'localhost', 'port' => 3306];
        $config = new Config(['database' => ['mysql' => $mysql]]);

        self::assertSame($mysql, $config->split('database.mysql')->toArray());
    }

    public function testSplitOfAnEmptyArrayYieldsAnEmptyRepository(): void
    {
        $split = new Config(['tags' => []])->split('tags');

        self::assertSame([], $split->toArray());
        self::assertFalse($split->has('anything'));
    }

    public function testSplitRejectsAMissingKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->split('missing');
            self::fail('Splitting a missing key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot split configuration key "missing": it is not set.',
                $configException->getMessage(),
            );
        }
    }

    public function testSplitRejectsAScalarValue(): void
    {
        $config = new Config(['database' => 'mysql']);

        try {
            $config->split('database');
            self::fail('Splitting a scalar must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot split configuration key "database": it is not an array.',
                $configException->getMessage(),
            );
        }
    }

    public function testToArrayReturnsTheDataIdentically(): void
    {
        $data = ['app' => ['name' => 'zef'], 'users' => [['name' => 'ada']]];

        self::assertSame($data, new Config($data)->toArray());
    }

    public function testAnEmptyConfigAnswersEveryLookupWithTheDefault(): void
    {
        $config = new Config();

        self::assertNull($config->get('anything'));
        self::assertSame('fallback', $config->get('anything.nested', 'fallback'));
        self::assertFalse($config->has('anything'));
        self::assertSame([], $config->toArray());
    }

    public function testReadingDoesNotMutateTheRepositoryData(): void
    {
        $data = ['app' => ['name' => 'zef']];
        $config = new Config($data);

        $config->get('app.name');
        $config->has('app.name');
        $config->split('app');

        self::assertSame($data, $config->toArray());
    }
}
