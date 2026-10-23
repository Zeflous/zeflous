<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\CorrelationKnobs;
use Zef\Framework\Http\CorrelationPolicy;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ObservabilityFamilies;

/**
 * The correlation family's knob reading, on its own terms: the
 * absent family's double fail-closed defaults (the boundary ships
 * OFF, the echo's disclosure ships OFF), the strict readings (both
 * toggles' closed vocabulary), the two census halts with the known
 * lists spelled out, and the profile mirror every family's test
 * pins the same way.
 *
 * @internal
 */
#[CoversClass(CorrelationPolicy::class)]
#[CoversClass(CorrelationKnobs::class)]
#[CoversClass(ObservabilityFamilies::class)]
#[CoversClass(FrameworkDefaults::class)]
final class CorrelationPolicyTest extends TestCase
{
    public function testTheAbsentFamilyReadsTheDoubleFailClosedDefaults(): void
    {
        $policy = CorrelationPolicy::fromMap(null);

        self::assertFalse($policy->enabled, 'the boundary ships OFF');
        self::assertFalse($policy->echoTraceParent, 'the disclosure ships OFF');
    }

    public function testTheFrameworkProfileMirrorsTheDoubleFailClosedDefaults(): void
    {
        $fromEmptyMap = CorrelationPolicy::fromMap([]);

        $observability = FrameworkDefaults::profile()['observability'] ?? null;
        self::assertIsArray($observability);
        $fromDefaults = $observability['correlation'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'echo_traceparent' => $fromEmptyMap->echoTraceParent,
            ],
            $fromDefaults,
            'the profile declaration and the constructor defaults are one posture'
            . ' -- the boot cannot drift between them',
        );
    }

    public function testTheEmptyMapReadsTheSameDefaults(): void
    {
        $policy = CorrelationPolicy::fromMap([]);

        self::assertFalse($policy->enabled);
        self::assertFalse($policy->echoTraceParent);
    }

    public function testAMapReadsItsEveryKnob(): void
    {
        $policy = CorrelationPolicy::fromMap([
            'enabled' => true,
            'echo_traceparent' => true,
        ]);

        self::assertTrue($policy->enabled);
        self::assertTrue($policy->echoTraceParent);
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysTruthCases')]
    public function testTheArmReadsTheToggleVocabularysTruth(mixed $spelling): void
    {
        $policy = CorrelationPolicy::fromMap(['enabled' => $spelling]);

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

        yield 'mixed-case True' => ['True'];

        yield 'on' => ['on'];

        yield 'yes' => ['yes'];
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysFalsehoodCases')]
    public function testTheArmReadsTheToggleVocabularysFalsehood(mixed $spelling): void
    {
        $policy = CorrelationPolicy::fromMap(['enabled' => $spelling]);

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

    #[DataProvider('provideTheEchoReadsTheToggleVocabularyCases')]
    public function testTheEchoReadsTheToggleVocabulary(mixed $spelling, bool $expected): void
    {
        $policy = CorrelationPolicy::fromMap(['echo_traceparent' => $spelling]);

        self::assertSame($expected, $policy->echoTraceParent);
    }

    /**
     * @return iterable<array{mixed,bool}>
     */
    public static function provideTheEchoReadsTheToggleVocabularyCases(): iterable
    {
        yield 'native true' => [true, true];

        yield 'digit one' => ['1', true];

        yield 'on' => ['on', true];

        yield 'native false' => [false, false];

        yield 'off' => ['off', false];

        yield 'digit zero' => ['0', false];
    }

    #[DataProvider('provideTheTogglesRefuseEverythingElseCases')]
    public function testTheTogglesRefuseEverythingElse(string $knob, mixed $value, string $type): void
    {
        try {
            CorrelationPolicy::fromMap([$knob => $value]);
            self::fail('A mistyped knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.correlation ' . $knob
                . ' knob must be a boolean or one of the boolean string spellings'
                . ' (true/false, on/off, yes/no, 1/0), ' . $type . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{string,mixed,string}>
     */
    public static function provideTheTogglesRefuseEverythingElseCases(): iterable
    {
        yield 'arm integer' => ['enabled', 1, 'int'];

        yield 'arm float' => ['enabled', 1.5, 'float'];

        yield 'arm mistyped word' => ['enabled', 'ture', 'string'];

        yield 'echo integer' => ['echo_traceparent', 1, 'int'];

        yield 'echo mistyped word' => ['echo_traceparent', 'flase', 'string'];
    }

    public function testANonMapFamilyIsRefused(): void
    {
        try {
            CorrelationPolicy::fromMap('off');
            self::fail('A non-map family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.correlation configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobIsRefusedWithTheKnownList(): void
    {
        try {
            CorrelationPolicy::fromMap(['echo_traceparnt' => true]);
            self::fail('An unknown knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.correlation configuration carries the unknown knob 'echo_traceparnt'"
                . ' (known knobs: enabled, echo_traceparent).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheUnknownFamilyIsRefusedWithTheKnownFamilies(): void
    {
        try {
            ObservabilityFamilies::subtree(['correlaton' => []], 'correlation');
            self::fail('An unknown family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'correlaton'"
                . ' (known families: timing, tracing, correlation, export, metrics, router).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFamilySubtreeReadsOneFamily(): void
    {
        $map = [
            'timing' => ['enabled' => false],
            'tracing' => ['enabled' => true],
            'correlation' => ['enabled' => true],
        ];

        self::assertSame(['enabled' => true], ObservabilityFamilies::subtree($map, 'correlation'));
        self::assertNull(ObservabilityFamilies::subtree(null, 'correlation'));
    }
}
