<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\RouteObservabilityKnobs;
use Zef\Framework\Http\RouteObservabilityPolicy;

/**
 * The router observability family's knob reading, on its own terms:
 * the absent family's double fail-closed defaults (the telemetry
 * arm ships OFF, the debug disclosure ships OFF), the strict
 * readings (both toggles' closed vocabulary), the census halts with
 * the known lists spelled out (the family's own knob census and the
 * observability map's family census, the router family now among
 * the known), and the profile mirror every family's test pins the
 * same way.
 *
 * @internal
 */
#[CoversClass(RouteObservabilityPolicy::class)]
#[CoversClass(RouteObservabilityKnobs::class)]
#[CoversClass(ObservabilityFamilies::class)]
#[CoversClass(FrameworkDefaults::class)]
final class RouteObservabilityPolicyTest extends TestCase
{
    public function testTheAbsentFamilyReadsTheDoubleFailClosedDefaults(): void
    {
        $policy = RouteObservabilityPolicy::fromMap(null);

        self::assertFalse($policy->enabled, 'the telemetry arm ships OFF');
        self::assertFalse($policy->debugMatch, 'the debug disclosure ships OFF');
    }

    public function testTheFrameworkProfileMirrorsTheDoubleFailClosedDefaults(): void
    {
        $fromEmptyMap = RouteObservabilityPolicy::fromMap([]);

        $observability = FrameworkDefaults::profile()['observability'] ?? null;
        self::assertIsArray($observability);
        $fromDefaults = $observability['router'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'debug_match' => $fromEmptyMap->debugMatch,
            ],
            $fromDefaults,
            'the profile declaration and the constructor defaults are one posture'
            . ' -- the boot cannot drift between them',
        );
    }

    public function testTheEmptyMapReadsTheSameDefaults(): void
    {
        $policy = RouteObservabilityPolicy::fromMap([]);

        self::assertFalse($policy->enabled);
        self::assertFalse($policy->debugMatch);
    }

    public function testAMapReadsItsEveryKnob(): void
    {
        $policy = RouteObservabilityPolicy::fromMap([
            'enabled' => true,
            'debug_match' => true,
        ]);

        self::assertTrue($policy->enabled);
        self::assertTrue($policy->debugMatch);
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysTruthCases')]
    public function testTheArmReadsTheToggleVocabularysTruth(mixed $spelling): void
    {
        $policy = RouteObservabilityPolicy::fromMap(['enabled' => $spelling]);

        self::assertTrue($policy->enabled);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function provideTheArmReadsTheToggleVocabularysTruthCases(): iterable
    {
        yield 'native true' => [true];

        yield 'digit one' => ['1'];

        yield 'lowercase true' => ['true'];

        yield 'uppercase TRUE' => ['TRUE'];

        yield 'on' => ['on'];

        yield 'yes' => ['yes'];
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysFalsehoodCases')]
    public function testTheArmReadsTheToggleVocabularysFalsehood(mixed $spelling): void
    {
        $policy = RouteObservabilityPolicy::fromMap(['enabled' => $spelling]);

        self::assertFalse($policy->enabled);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function provideTheArmReadsTheToggleVocabularysFalsehoodCases(): iterable
    {
        yield 'native false' => [false];

        yield 'digit zero' => ['0'];

        yield 'lowercase false' => ['false'];

        yield 'uppercase FALSE' => ['FALSE'];

        yield 'off' => ['off'];

        yield 'no' => ['no'];

        yield 'explicit empty' => [''];
    }

    #[DataProvider('provideTheDebugDisclosureReadsTheToggleVocabularyCases')]
    public function testTheDebugDisclosureReadsTheToggleVocabulary(mixed $spelling, bool $expected): void
    {
        $policy = RouteObservabilityPolicy::fromMap(['debug_match' => $spelling]);

        self::assertSame($expected, $policy->debugMatch);
    }

    /**
     * @return iterable<array{mixed,bool}>
     */
    public static function provideTheDebugDisclosureReadsTheToggleVocabularyCases(): iterable
    {
        yield 'native true' => [true, true];

        yield 'on' => ['on', true];

        yield 'native false' => [false, false];

        yield 'off' => ['off', false];

        yield 'explicit empty' => ['', false];
    }

    #[DataProvider('provideAMistypedKnobIsRefusedCases')]
    public function testAMistypedKnobIsRefused(string $knob, mixed $value, string $message): void
    {
        try {
            RouteObservabilityPolicy::fromMap([$knob => $value]);
            self::fail('A mistyped knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<array{string, mixed, string}>
     */
    public static function provideAMistypedKnobIsRefusedCases(): iterable
    {
        yield 'the arm as a number' => [
            'enabled',
            1,
            'The observability.router enabled knob must be a boolean or one of the boolean string'
            . ' spellings (true/false, on/off, yes/no, 1/0), int given.',
        ];

        yield 'the debug disclosure as a list' => [
            'debug_match',
            ['on'],
            'The observability.router debug_match knob must be a boolean or one of the boolean string'
            . ' spellings (true/false, on/off, yes/no, 1/0), array given.',
        ];
    }

    public function testANonMapSubtreeIsRefused(): void
    {
        try {
            RouteObservabilityPolicy::fromMap('on');
            self::fail('A subtree that is not a map must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.router configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobIsRefusedWithTheKnownList(): void
    {
        try {
            RouteObservabilityPolicy::fromMap(['debug_matsh' => true]);
            self::fail('An unknown knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.router configuration carries the unknown knob 'debug_matsh'"
                . ' (known knobs: enabled, debug_match).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheUnknownFamilyIsRefusedWithTheRouterAmongTheKnown(): void
    {
        try {
            ObservabilityFamilies::subtree(['routre' => []], 'router');
            self::fail('An unknown family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'routre'"
                . ' (known families: timing, tracing, correlation, export, metrics, router).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFamilySubtreeReadsTheRouterFamily(): void
    {
        $map = [
            'timing' => ['enabled' => false],
            'metrics' => ['enabled' => true],
            'router' => ['enabled' => true, 'debug_match' => true],
        ];

        self::assertSame(
            ['enabled' => true, 'debug_match' => true],
            ObservabilityFamilies::subtree($map, 'router'),
        );
        self::assertNull(ObservabilityFamilies::subtree(null, 'router'));
    }
}
