<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CorrelationAttributes;

/**
 * The correlation attribute table, on its own terms: the bounds
 * census's every refusal, the key grammar, the scalar-only values
 * and the aggregate byte budget.
 *
 * @internal
 */
#[CoversClass(CorrelationAttributes::class)]
final class CorrelationAttributesTest extends TestCase
{
    public function testValidatesTheTableAsGiven(): void
    {
        $attributes = CorrelationAttributes::validated([
            'operation.class' => 'order',
            'status' => 200,
            'ratio' => 0.5,
            'flag' => true,
            'absent' => null,
        ]);

        self::assertSame([
            'operation.class' => 'order',
            'status' => 200,
            'ratio' => 0.5,
            'flag' => true,
            'absent' => null,
        ], $attributes);
    }

    public function testRejectsTheTableThatExceedsTheEntryCeiling(): void
    {
        $attributes = [];

        for ($i = 1; $i <= CorrelationAttributes::MAX_ATTRIBUTES + 1; ++$i) {
            $attributes['key' . $i] = 'v';
        }

        $this->assertRejected($attributes, 'the entry ceiling');
    }

    #[DataProvider('provideRejectsTheKeyThatFailsTheGrammarCases')]
    public function testRejectsTheKeyThatFailsTheGrammar(string $key): void
    {
        $this->assertRejected([$key => 'v'], 'the key grammar');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectsTheKeyThatFailsTheGrammarCases(): iterable
    {
        yield 'empty key' => [''];

        yield 'key with a space' => ['has space'];

        yield 'key with a slash' => ['has/slash'];

        yield 'over-long key' => [str_repeat('k', CorrelationAttributes::MAX_ATTRIBUTE_KEY_BYTES + 1)];
    }

    public function testRejectsTheNonScalarValue(): void
    {
        $this->assertRejected(['k' => ['an array']], 'the scalar-only values');
        $this->assertRejected(['k' => (object) ['an object']], 'the scalar-only values');
    }

    public function testRejectsTheOverLongStringValue(): void
    {
        $this->assertRejected(
            ['k' => str_repeat('v', CorrelationAttributes::MAX_ATTRIBUTE_VALUE_BYTES + 1)],
            'the value ceiling',
        );
    }

    public function testRejectsTheTableThatExceedsTheAggregateBudget(): void
    {
        $attributes = [];

        for ($i = 1; $i <= CorrelationAttributes::MAX_ATTRIBUTES; ++$i) {
            $attributes['key' . $i] = str_repeat('v', CorrelationAttributes::MAX_ATTRIBUTE_VALUE_BYTES);
        }

        $this->assertRejected($attributes, 'the aggregate budget');
    }

    public function testTheTableAtItsEntryCeilingShips(): void
    {
        $attributes = [];

        for ($i = 1; $i <= CorrelationAttributes::MAX_ATTRIBUTES; ++$i) {
            $attributes['key' . $i] = 'v';
        }

        self::assertSame($attributes, CorrelationAttributes::validated($attributes));
    }

    public function testTheKeyAtItsOwnCeilingShips(): void
    {
        $key = str_repeat('k', CorrelationAttributes::MAX_ATTRIBUTE_KEY_BYTES);

        self::assertSame(
            [$key => 'v'],
            CorrelationAttributes::validated([$key => 'v']),
        );
    }

    public function testTheStringValueAtItsOwnCeilingShips(): void
    {
        $value = str_repeat('v', CorrelationAttributes::MAX_ATTRIBUTE_VALUE_BYTES);

        self::assertSame(
            ['k' => $value],
            CorrelationAttributes::validated(['k' => $value]),
        );
    }

    /**
     * @param array<mixed> $attributes
     */
    private function assertRejected(array $attributes, string $because): void
    {
        try {
            CorrelationAttributes::validated($attributes);
            self::fail("A table that fails {$because} must be rejected.");
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
