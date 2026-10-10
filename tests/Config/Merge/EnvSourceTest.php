<?php

declare(strict_types=1);

namespace Zef\Test\Config\Merge;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Merge\EnvSource;

/**
 * @internal
 */
#[CoversClass(EnvSource::class)]
final class EnvSourceTest extends TestCase
{
    private const array FROM_ENVIRONMENT_VARIABLES = [
        'ZEF_TEST_DATABASE__HOST',
        'ZEF_TEST_DATABASE__PORT',
        'ZEF_TEST_UNRELATED',
        'ZEF_TESTOTHER',
    ];

    #[Override]
    protected function tearDown(): void
    {
        foreach (self::FROM_ENVIRONMENT_VARIABLES as $name) {
            putenv($name);
        }
    }

    public function testFoldsAMatchingVariableIntoANestedArray(): void
    {
        $envSource = new EnvSource('APP_', ['APP_DATABASE__HOST' => 'db.example.com']);

        self::assertSame(['database' => ['host' => 'db.example.com']], $envSource->toArray());
    }

    public function testFoldsAMatchingVariableThreeLevelsDeep(): void
    {
        $envSource = new EnvSource('APP_', ['APP_DATABASE__MYSQL__HOST' => 'db.example.com']);

        self::assertSame(
            ['database' => ['mysql' => ['host' => 'db.example.com']]],
            $envSource->toArray(),
        );
    }

    public function testIgnoresVariablesOutsideThePrefix(): void
    {
        $envSource = new EnvSource('APP_', [
            'OTHER_DATABASE__HOST' => 'ignored',
            'APPX_DATABASE__HOST' => 'also-ignored',
            'APP' => 'still-ignored',
        ]);

        self::assertSame([], $envSource->toArray());
    }

    public function testReturnsAnEmptyArrayWithoutAnyVariables(): void
    {
        self::assertSame([], new EnvSource('APP_', [])->toArray());
    }

    public function testMatchesThePrefixCaseSensitively(): void
    {
        self::assertSame([], new EnvSource('APP_', ['app_database__host' => 'ignored'])->toArray());
    }

    public function testLowercasesTheAddressAfterThePrefix(): void
    {
        $envSource = new EnvSource('APP_', [
            'APP_DATABASE__HOST' => 'first',
            'APP_database__PORT' => '3306',
        ]);

        self::assertSame(['database' => ['host' => 'first', 'port' => '3306']], $envSource->toArray());
    }

    public function testKeepsSingleUnderscoresInsideASegment(): void
    {
        $envSource = new EnvSource('APP_', ['APP_SESSION_DRIVER' => 'redis']);

        self::assertSame(['session_driver' => 'redis'], $envSource->toArray());
    }

    public function testFoldsMultiByteSegmentsToTheirLowerCaseForm(): void
    {
        $envSource = new EnvSource('APP_', ['APP_CAFÉ__LIMIT' => '3']);

        self::assertSame(['café' => ['limit' => '3']], $envSource->toArray());
    }

    public function testStripsAMultiBytePrefixByCharacters(): void
    {
        $envSource = new EnvSource('CAFÉ_', ['CAFÉ_DATABASE__HOST' => 'db.example.com']);

        self::assertSame(['database' => ['host' => 'db.example.com']], $envSource->toArray());
    }

    public function testCarriesValuesThroughAsRawStrings(): void
    {
        $envSource = new EnvSource('APP_', ['APP_DATABASE__PORT' => '3306']);

        self::assertSame(['database' => ['port' => '3306']], $envSource->toArray());
    }

    public function testResolvesDuplicateAddressesWithTheLaterVariableWinning(): void
    {
        $envSource = new EnvSource('APP_', [
            'APP_DB__HOST' => 'first',
            'APP_db__host' => 'second',
        ]);

        self::assertSame(['db' => ['host' => 'second']], $envSource->toArray());
    }

    public function testResolvesDuplicateAddressesInFoldOrder(): void
    {
        $envSource = new EnvSource('APP_', [
            'APP_db__host' => 'second',
            'APP_DB__HOST' => 'first',
        ]);

        self::assertSame(['db' => ['host' => 'first']], $envSource->toArray());
    }

    public function testRejectsAVariableThatNamesExactlyThePrefix(): void
    {
        $envSource = new EnvSource('APP_', ['APP_' => 'orphan']);

        try {
            $envSource->toArray();
            self::fail('A variable that names exactly the prefix must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnEmptySegmentAfterASeparator(): void
    {
        $envSource = new EnvSource('APP_', ['APP_DB__' => 'orphan']);

        try {
            $envSource->toArray();
            self::fail('A separator that leaves an empty segment must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "db." is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAConsecutiveSeparatorPair(): void
    {
        $envSource = new EnvSource('APP_', ['APP_A____B' => 'orphan']);

        try {
            $envSource->toArray();
            self::fail('A consecutive separator pair must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "a..b" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testFromEnvironmentCapturesTheProcessEnvironment(): void
    {
        putenv('ZEF_TEST_DATABASE__HOST=db.example.com');
        putenv('ZEF_TEST_DATABASE__PORT=3306');
        putenv('ZEF_TEST_UNRELATED=ignored');
        putenv('ZEF_TESTOTHER=skipped');

        $envSource = EnvSource::fromEnvironment('ZEF_TEST_');

        self::assertSame(
            ['database' => ['host' => 'db.example.com', 'port' => '3306'], 'unrelated' => 'ignored'],
            $envSource->toArray(),
        );
    }
}
