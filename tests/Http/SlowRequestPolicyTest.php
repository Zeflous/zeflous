<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\SlowRequestBounds;
use Zef\Framework\Http\SlowRequestKnobs;
use Zef\Framework\Http\SlowRequestPolicy;
use Zef\Framework\Http\StrictIntegers;
use Zef\Framework\Http\StrictKnobs;
use Zef\Framework\Http\StrictToggles;

/**
 * The slow-request family's knob reading, on its own terms: the
 * default posture (armed, the one-second bar), the full knob map,
 * the environment's string spellings, the unknown-key census, the
 * `observability` map's own family census (the family the
 * request-lifecycle tracing's closing item answers), the
 * threshold's corridor held at BOTH construction paths -- plus the
 * profile mirror the timing family pinned the same way.
 *
 * @internal
 */
#[CoversClass(SlowRequestPolicy::class)]
#[CoversClass(SlowRequestKnobs::class)]
#[CoversClass(SlowRequestBounds::class)]
#[CoversClass(ObservabilityFamilies::class)]
#[CoversClass(StrictKnobs::class)]
#[CoversClass(StrictToggles::class)]
#[CoversClass(StrictIntegers::class)]
#[CoversClass(FrameworkDefaults::class)]
final class SlowRequestPolicyTest extends TestCase
{
    public function testAnAbsentFamilyReadsTheDefaultPosture(): void
    {
        $slowRequestPolicy = SlowRequestPolicy::fromMap(null);

        self::assertTrue($slowRequestPolicy->enabled, 'the family is armed by default');
        self::assertSame(1000, $slowRequestPolicy->thresholdMs, 'the default bar is one full second');
    }

    public function testAnEmptyMapReadsTheDefaultPosture(): void
    {
        $slowRequestPolicy = SlowRequestPolicy::fromMap([]);

        self::assertTrue($slowRequestPolicy->enabled);
        self::assertSame(1000, $slowRequestPolicy->thresholdMs);
    }

    public function testTheFullKnobMapReadsBothKnobs(): void
    {
        $slowRequestPolicy = SlowRequestPolicy::fromMap([
            'enabled' => false,
            'threshold_ms' => 2500,
        ]);

        self::assertFalse($slowRequestPolicy->enabled);
        self::assertSame(2500, $slowRequestPolicy->thresholdMs);
    }

    /**
     * @param string $spelling the environment overlay's spelling
     */
    #[DataProvider('provideTheEnvironmentSpellingsFoldCases')]
    public function testTheEnvironmentSpellingsFold(string $spelling, bool $enabled): void
    {
        $slowRequestPolicy = SlowRequestPolicy::fromMap([
            'enabled' => $spelling,
        ]);

        self::assertSame($enabled, $slowRequestPolicy->enabled);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideTheEnvironmentSpellingsFoldCases(): iterable
    {
        yield 'native truth words' => ['on', true];

        yield 'case-mixed truth words' => ['TRUE', true];

        yield 'native falsehood words' => ['off', false];

        yield 'the digit spellings' => ['0', false];

        yield 'the explicitly empty value reads as off' => ['', false];
    }

    public function testTheEnvironmentIntegerSpellingFolds(): void
    {
        $slowRequestPolicy = SlowRequestPolicy::fromMap([
            'threshold_ms' => '2048',
        ]);

        self::assertSame(2048, $slowRequestPolicy->thresholdMs, 'an all-digit string folds to the integer');
    }

    /**
     * @param mixed $value a value the toggle vocabulary refuses
     */
    #[DataProvider('provideAWrongTypedArmHaltsTheBootCases')]
    public function testAWrongTypedArmHaltsTheBoot(mixed $value): void
    {
        try {
            SlowRequestPolicy::fromMap(['enabled' => $value]);
            self::fail('A wrong-typed arm must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.slow_request enabled knob must be a boolean or one of the'
                . ' boolean string spellings (true/false, on/off, yes/no, 1/0), '
                . get_debug_type($value) . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideAWrongTypedArmHaltsTheBootCases(): iterable
    {
        yield 'an integer is not a boolean' => [1];

        yield 'a mistyped word never reads as false' => ['ture'];

        yield 'a maybe is not a posture' => ['maybe'];

        yield 'a float is not a boolean' => [0.5];
    }

    /**
     * @param mixed $value a value the integer vocabulary refuses
     */
    #[DataProvider('provideAWrongTypedThresholdHaltsTheBootCases')]
    public function testAWrongTypedThresholdHaltsTheBoot(mixed $value): void
    {
        try {
            SlowRequestPolicy::fromMap(['threshold_ms' => $value]);
            self::fail('A wrong-typed threshold must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.slow_request threshold_ms knob must be an integer or an'
                . ' all-digit string, ' . get_debug_type($value) . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideAWrongTypedThresholdHaltsTheBootCases(): iterable
    {
        yield 'a boolean is not a threshold' => [true];

        yield 'a float is not a threshold' => [1.5];

        yield 'a unit-suffixed string is not a fold' => ['1000ms'];

        yield 'an array is not a threshold' => [[]];
    }

    public function testTheSubtreeMustBeAMap(): void
    {
        try {
            SlowRequestPolicy::fromMap('slow_request');
            self::fail('A non-map subtree must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.slow_request configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobIsRejectedWithTheKnownListSpelledOut(): void
    {
        try {
            SlowRequestPolicy::fromMap(['threshold' => 2500]);
            self::fail('An unknown knob must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.slow_request configuration carries the unknown knob 'threshold'"
                . ' (known knobs: enabled, threshold_ms).',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonStringKnobIsRejectedByItsType(): void
    {
        try {
            SlowRequestPolicy::fromMap([0 => true]);
            self::fail('A non-string knob key must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.slow_request configuration carries the unknown knob 'int'"
                . ' (known knobs: enabled, threshold_ms).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @param int $thresholdMs the out-of-corridor spelling
     */
    #[DataProvider('provideTheThresholdCorridorHoldsAtBothConstructionPathsCases')]
    public function testTheThresholdCorridorHoldsAtTheMapReading(int $thresholdMs): void
    {
        try {
            SlowRequestPolicy::fromMap(['threshold_ms' => $thresholdMs]);
            self::fail('An out-of-corridor threshold must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.slow_request threshold_ms knob must be between 1 and 3600000, '
                . $thresholdMs . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @param int $thresholdMs the out-of-corridor spelling
     */
    #[DataProvider('provideTheThresholdCorridorHoldsAtBothConstructionPathsCases')]
    public function testTheThresholdCorridorHoldsAtTheProgrammaticSpelling(int $thresholdMs): void
    {
        try {
            new SlowRequestPolicy(thresholdMs: $thresholdMs);
            self::fail('An out-of-corridor threshold must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.slow_request threshold_ms knob must be between 1 and 3600000, '
                . $thresholdMs . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideTheThresholdCorridorHoldsAtBothConstructionPathsCases(): iterable
    {
        yield 'the zero-millisecond bar records every request' => [0];

        yield 'a negative bar records nothing' => [-1];

        yield 'the corridor ceiling exceeded' => [3600001];
    }

    public function testTheCorridorEdgesAreLegalSpellings(): void
    {
        self::assertSame(1, new SlowRequestPolicy(thresholdMs: 1)->thresholdMs, 'the floor is legal');
        self::assertSame(
            3600000,
            new SlowRequestPolicy(thresholdMs: 3600000)->thresholdMs,
            'the ceiling is legal',
        );
    }

    public function testTheSlowRequestFamilySubtreeTravelsThrough(): void
    {
        self::assertSame(
            ['threshold_ms' => 2500],
            ObservabilityFamilies::subtree(['slow_request' => ['threshold_ms' => 2500]], 'slow_request'),
        );
    }

    public function testTheAbsentSlowRequestFamilySubtreeReadsAsNull(): void
    {
        self::assertNull(ObservabilityFamilies::subtree([], 'slow_request'));
    }

    public function testTheFrameworkProfileMirrorsTheDefaultPosture(): void
    {
        $fromEmptyMap = SlowRequestPolicy::fromMap([]);

        $observability = FrameworkDefaults::profile()['observability'] ?? null;
        self::assertIsArray($observability);
        $fromDefaults = $observability['slow_request'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'threshold_ms' => $fromEmptyMap->thresholdMs,
            ],
            $fromDefaults,
            "the shipped defaults spell the policy's own default posture, so the two cannot drift",
        );
    }
}
