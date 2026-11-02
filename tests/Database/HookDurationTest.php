<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\HookDuration;

/**
 * The slow-hook guard's timing folds at their exact boundaries: the
 * nanosecond-to-millisecond fold (ceil, never floor) and the strict
 * threshold comparison -- the boundary no sleep-based test can spell
 * deterministically.
 *
 * @internal
 */
#[CoversClass(HookDuration::class)]
final class HookDurationTest extends TestCase
{
    #[DataProvider('provideTheNanosecondsFoldAnswersCeiledMillisecondsCases')]
    public function testTheNanosecondsFoldAnswersCeiledMilliseconds(int $elapsedNs, int $expectedMs): void
    {
        self::assertSame($expectedMs, HookDuration::milliseconds($elapsedNs));
    }

    /**
     * @return iterable<non-empty-string, array{int, int}>
     */
    public static function provideTheNanosecondsFoldAnswersCeiledMillisecondsCases(): iterable
    {
        yield 'zero nanoseconds answers zero' => [0, 0];

        yield 'one nanosecond already answers one millisecond' => [1, 1];

        yield 'half a millisecond ceils up' => [500_000, 1];

        yield 'exactly one millisecond answers one' => [1_000_000, 1];

        yield 'one nanosecond past a millisecond answers two' => [1_000_001, 2];

        yield 'a hundred microseconds ceils to one' => [100_100, 1];

        yield 'fifty milliseconds answers fifty' => [50_000_000, 50];
    }

    #[DataProvider('provideExceededIsStrictAtTheBoundaryCases')]
    public function testExceededIsStrictAtTheBoundary(int $elapsedMs, int $thresholdMs, bool $expected): void
    {
        self::assertSame($expected, HookDuration::exceeded($elapsedMs, $thresholdMs));
    }

    /**
     * @return iterable<non-empty-string, array{int, int, bool}>
     */
    public static function provideExceededIsStrictAtTheBoundaryCases(): iterable
    {
        yield 'zero against zero does not exceed' => [0, 0, false];

        yield 'one against zero exceeds' => [1, 0, true];

        yield 'exactly on the threshold does not exceed' => [1, 1, false];

        yield 'one past the threshold exceeds' => [2, 1, true];

        yield 'far under the threshold does not exceed' => [50, 10_000, false];

        yield 'far past the threshold exceeds' => [50_001, 50_000, true];
    }
}
