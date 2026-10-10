<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\DotKey;

/**
 * @internal
 */
#[CoversClass(DotKey::class)]
final class DotKeyTest extends TestCase
{
    public function testParsesAFlatKey(): void
    {
        self::assertSame(['name'], DotKey::parse('name'));
    }

    public function testParsesANestedKey(): void
    {
        self::assertSame(['database', 'mysql', 'host'], DotKey::parse('database.mysql.host'));
    }

    public function testParsesNumericSegments(): void
    {
        self::assertSame(['users', '0', 'name'], DotKey::parse('users.0.name'));
    }

    public function testRejectsAnEmptyKey(): void
    {
        try {
            DotKey::parse('');
            self::fail('An empty key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsALoneDot(): void
    {
        $this->assertMalformed('.');
    }

    public function testRejectsALeadingDot(): void
    {
        $this->assertMalformed('.app');
    }

    public function testRejectsATrailingDot(): void
    {
        $this->assertMalformed('app.');
    }

    public function testRejectsADoubleDot(): void
    {
        $this->assertMalformed('app..name');
    }

    public function testLookupFindsAFlatValue(): void
    {
        self::assertSame([true, 'zef'], DotKey::lookup(['app' => 'zef'], ['app']));
    }

    public function testLookupFindsANestedValue(): void
    {
        $data = ['database' => ['mysql' => ['host' => 'localhost']]];

        self::assertSame([true, 'localhost'], DotKey::lookup($data, ['database', 'mysql', 'host']));
    }

    public function testLookupReportsAMissingTopLevelKey(): void
    {
        self::assertSame([false, null], DotKey::lookup(['app' => 'zef'], ['cache']));
    }

    public function testLookupReportsAMissingNestedKey(): void
    {
        self::assertSame([false, null], DotKey::lookup(['database' => []], ['database', 'mysql']));
    }

    public function testLookupStopsAtScalarIntermediates(): void
    {
        self::assertSame([false, null], DotKey::lookup(['database' => 'mysql'], ['database', 'host']));
    }

    public function testLookupIndexesListsByNumericSegment(): void
    {
        $data = ['users' => [['name' => 'ada'], ['name' => 'grace']]];

        self::assertSame([true, 'grace'], DotKey::lookup($data, ['users', '1', 'name']));
    }

    public function testLookupReportsAnOutOfBoundsListIndex(): void
    {
        $data = ['users' => [['name' => 'ada']]];

        self::assertSame([false, null], DotKey::lookup($data, ['users', '5', 'name']));
    }

    public function testLookupDistinguishesAnExplicitNullFromAnAbsentKey(): void
    {
        self::assertSame([true, null], DotKey::lookup(['debug' => null], ['debug']));
    }

    public function testLookupOfEmptySegmentsReturnsTheWholeData(): void
    {
        $data = ['app' => 'zef'];

        self::assertSame([true, $data], DotKey::lookup($data, []));
    }

    private function assertMalformed(string $key): void
    {
        try {
            DotKey::parse($key);
            self::fail(\sprintf('The key "%s" must raise a ConfigException.', $key));
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf('Configuration key "%s" is malformed: empty segment.', $key),
                $configException->getMessage(),
            );
        }
    }
}
