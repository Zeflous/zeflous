<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ResponseTime;

/**
 * The response-time header's value shape, on its own terms: the
 * two-decimal millisecond rendering across the span matrix -- zero,
 * sub-milli, exact, rounding, second-scale and the engine's own
 * ceiling -- plus the negative clamp a broken clock seam's delta
 * answers.
 *
 * @internal
 */
#[CoversClass(ResponseTime::class)]
final class ResponseTimeTest extends TestCase
{
    public function testAZeroSpanRendersWithBothDecimals(): void
    {
        self::assertSame('0.00ms', ResponseTime::fromElapsedNs(0)->value);
    }

    /**
     * @param non-negative-int $elapsedNs the span the middleware measured
     * @param non-empty-string $expected  the header value the span renders as
     */
    #[DataProvider('provideASpanRendersAsTheTwoDecimalMillisecondShapeCases')]
    public function testASpanRendersAsTheTwoDecimalMillisecondShape(int $elapsedNs, string $expected): void
    {
        self::assertSame($expected, ResponseTime::fromElapsedNs($elapsedNs)->value);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideASpanRendersAsTheTwoDecimalMillisecondShapeCases(): iterable
    {
        yield 'a zero span keeps the two-decimal shape' => [0, '0.00ms'];

        yield 'one nanosecond rounds away' => [1, '0.00ms'];

        yield 'a quarter millisecond' => [250_000, '0.25ms'];

        yield 'three quarters of a millisecond' => [750_000, '0.75ms'];

        yield 'exactly one millisecond' => [1_000_000, '1.00ms'];

        yield 'one nanosecond short of a millisecond rounds up' => [999_999, '1.00ms'];

        yield 'a fractional millisecond truncates at the second decimal' => [1_234_567, '1.23ms'];

        yield 'past the half boundary rounds up' => [1_236_000, '1.24ms'];

        yield 'an exact half stays exact' => [2_500_000, '2.50ms'];

        yield 'a full second spells without a separator' => [1_000_000_000, '1000.00ms'];

        yield 'the engine ceiling renders without overflow' => [\PHP_INT_MAX, '9223372036854.78ms'];
    }

    /**
     * @param negative-int $elapsedNs the broken clock seam's delta
     */
    #[DataProvider('provideAClockArtifactDeltaClampsToTheZeroSpanCases')]
    public function testAClockArtifactDeltaClampsToTheZeroSpan(int $elapsedNs): void
    {
        self::assertSame('0.00ms', ResponseTime::fromElapsedNs($elapsedNs)->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAClockArtifactDeltaClampsToTheZeroSpanCases(): iterable
    {
        yield 'one nanosecond backwards' => [-1];

        yield 'a quarter millisecond backwards' => [-250_000];

        yield 'the engine floor' => [\PHP_INT_MIN];
    }
}
