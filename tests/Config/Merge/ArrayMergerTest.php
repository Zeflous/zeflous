<?php

declare(strict_types=1);

namespace Zef\Test\Config\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Merge\ArrayMerger;

/**
 * @internal
 */
#[CoversClass(ArrayMerger::class)]
final class ArrayMergerTest extends TestCase
{
    public function testMergesNothingWithoutLayers(): void
    {
        self::assertSame([], ArrayMerger::merge());
    }

    public function testReturnsASingleLayerUnchanged(): void
    {
        self::assertSame(
            ['name' => 'zef', 'database' => ['host' => 'localhost']],
            ArrayMerger::merge(['name' => 'zef', 'database' => ['host' => 'localhost']]),
        );
    }

    public function testMergesThreeLayersWithTheLastLayerWinning(): void
    {
        self::assertSame(
            [
                'db' => ['host' => 'env', 'port' => 3306],
                'debug' => false,
                'only_default' => 'd',
                'only_app' => 'a',
                'only_env' => 'e',
            ],
            ArrayMerger::merge(
                ['db' => ['host' => 'default', 'port' => 3306], 'debug' => true, 'only_default' => 'd'],
                ['db' => ['host' => 'app'], 'debug' => false, 'only_app' => 'a'],
                ['db' => ['host' => 'env'], 'only_env' => 'e'],
            ),
        );
    }

    public function testOverridesScalarsWithTheLaterLayer(): void
    {
        self::assertSame(['retries' => 2], ArrayMerger::merge(['retries' => 1], ['retries' => 2]));
    }

    public function testALaterNullOverridesTheEarlierValue(): void
    {
        self::assertSame(['feature' => null], ArrayMerger::merge(['feature' => true], ['feature' => null]));
    }

    public function testMergesAnEmptyLayerAsANoop(): void
    {
        self::assertSame(
            ['first' => 1, 'second' => 2],
            ArrayMerger::merge(['first' => 1], [], ['second' => 2]),
        );
    }

    public function testRecursesIntoDeeplyNestedMaps(): void
    {
        self::assertSame(
            ['a' => ['b' => ['c' => 10, 'd' => 2]]],
            ArrayMerger::merge(
                ['a' => ['b' => ['c' => 1, 'd' => 2]]],
                ['a' => ['b' => ['c' => 10]]],
            ),
        );
    }

    public function testKeepsAMapTheOverlayDoesNotTouch(): void
    {
        self::assertSame(
            ['kept' => ['nested' => true], 'replaced' => 'scalar'],
            ArrayMerger::merge(
                ['kept' => ['nested' => true], 'replaced' => 'old'],
                ['replaced' => 'scalar'],
            ),
        );
    }

    public function testMergesLaterMapKeysAfterAScalarReplacement(): void
    {
        self::assertSame(
            ['a' => 'scalar', 'b' => ['x' => 1, 'y' => 2]],
            ArrayMerger::merge(
                ['a' => ['old' => true], 'b' => ['x' => 1]],
                ['a' => 'scalar', 'b' => ['y' => 2]],
            ),
        );
    }

    public function testReplacesListsInsteadOfConcatenating(): void
    {
        self::assertSame(
            ['items' => ['x']],
            ArrayMerger::merge(['items' => ['a', 'b']], ['items' => ['x']]),
        );
    }

    public function testTheLastListDeclarationWinsAcrossThreeLayers(): void
    {
        self::assertSame(
            ['items' => ['z']],
            ArrayMerger::merge(
                ['items' => ['a']],
                ['items' => ['b', 'c']],
                ['items' => ['z']],
            ),
        );
    }

    public function testReplacesAMapWhenTheLaterLayerCarriesAList(): void
    {
        self::assertSame(
            ['db' => ['primary']],
            ArrayMerger::merge(['db' => ['host' => 'h', 'port' => 1]], ['db' => ['primary']]),
        );
    }

    public function testReplacesAListWhenTheLaterLayerCarriesAMap(): void
    {
        self::assertSame(
            ['db' => ['host' => 'h']],
            ArrayMerger::merge(['db' => ['primary']], ['db' => ['host' => 'h']]),
        );
    }

    public function testClearsAMapWhenTheLaterLayerDeclaresAnEmptyArray(): void
    {
        self::assertSame(
            ['db' => []],
            ArrayMerger::merge(['db' => ['host' => 'h', 'port' => 1]], ['db' => []]),
        );
    }

    public function testReplacesIntegerKeyedEntriesInsideAMap(): void
    {
        self::assertSame(
            ['mixed' => ['label' => 'layer', 0 => ['height' => 2]]],
            ArrayMerger::merge(
                ['mixed' => ['label' => 'base', 0 => ['depth' => 1]]],
                ['mixed' => ['label' => 'layer', 0 => ['height' => 2]]],
            ),
        );
    }

    public function testKeepsTheBaseKeyOrderAndAppendsNewKeys(): void
    {
        self::assertSame(
            ['b' => 1, 'a' => 4, 'c' => 3],
            ArrayMerger::merge(['b' => 1, 'a' => 2], ['c' => 3, 'a' => 4]),
        );
    }

    public function testDoesNotModifyTheInputLayers(): void
    {
        $first = $this->inputLayer('y', 1);
        $second = $this->inputLayer('z', 2);

        self::assertSame(['x' => ['y' => 1, 'z' => 2]], ArrayMerger::merge($first, $second));
        self::assertSame(['x' => ['y' => 1]], $first);
        self::assertSame(['x' => ['z' => 2]], $second);
    }

    /**
     * Builds an input layer through a declared general array type, so the
     * immutability assertions above stay meaningful to both PHPUnit and the
     * static analysers (a literal array would narrow them to always-true).
     *
     * @return array<array-key, mixed>
     */
    private function inputLayer(string $child, int $value): array
    {
        return ['x' => [$child => $value]];
    }
}
