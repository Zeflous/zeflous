<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\TracingKnobs;
use Zef\Framework\Http\TracingPolicy;

/**
 * The tracing family's knob reading, on its own terms: the absent
 * family's fail-closed defaults (the collection ships OFF, the two
 * bounds at the processor's own constructor contract), the strict
 * readings (the toggle's closed vocabulary, the integers' all-digit
 * fold), and the two census halts -- the unknown family and the
 * unknown knob -- with the known lists spelled out, plus the
 * profile mirror the timing family pinned the same way.
 *
 * @internal
 */
#[CoversClass(TracingPolicy::class)]
#[CoversClass(TracingKnobs::class)]
#[CoversClass(ObservabilityFamilies::class)]
#[CoversClass(FrameworkDefaults::class)]
final class TracingPolicyTest extends TestCase
{
    public function testTheAbsentFamilyReadsTheFailClosedDefaults(): void
    {
        $policy = TracingPolicy::fromMap(null);

        self::assertFalse($policy->enabled, 'the collection ships OFF');
        self::assertSame(2048, $policy->maxQueue);
        self::assertSame(256, $policy->batchSize);
    }

    public function testTheFrameworkProfileMirrorsTheFailClosedDefaults(): void
    {
        $fromEmptyMap = TracingPolicy::fromMap([]);

        $observability = FrameworkDefaults::profile()['observability'] ?? null;
        self::assertIsArray($observability);
        $fromDefaults = $observability['tracing'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'max_queue' => $fromEmptyMap->maxQueue,
                'batch_size' => $fromEmptyMap->batchSize,
            ],
            $fromDefaults,
            'the profile declaration and the constructor defaults are one posture'
            . ' -- the boot cannot drift between them',
        );
    }

    public function testTheEmptyMapReadsTheSameDefaults(): void
    {
        $policy = TracingPolicy::fromMap([]);

        self::assertFalse($policy->enabled);
        self::assertSame(2048, $policy->maxQueue);
        self::assertSame(256, $policy->batchSize);
    }

    public function testAMapReadsItsEveryKnob(): void
    {
        $policy = TracingPolicy::fromMap([
            'enabled' => true,
            'max_queue' => 512,
            'batch_size' => 64,
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame(512, $policy->maxQueue);
        self::assertSame(64, $policy->batchSize);
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysTruthCases')]
    public function testTheArmReadsTheToggleVocabularysTruth(mixed $spelling): void
    {
        $policy = TracingPolicy::fromMap(['enabled' => $spelling]);

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
        $policy = TracingPolicy::fromMap(['enabled' => $spelling]);

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

    #[DataProvider('provideTheArmRefusesEverythingElseCases')]
    public function testTheArmRefusesEverythingElse(mixed $value, string $type): void
    {
        try {
            TracingPolicy::fromMap(['enabled' => $value]);
            self::fail('A mistyped arm must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.tracing enabled knob must be a boolean or one of the boolean string '
                . 'spellings (true/false, on/off, yes/no, 1/0), ' . $type . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{mixed,string}>
     */
    public static function provideTheArmRefusesEverythingElseCases(): iterable
    {
        yield 'an integer' => [1, 'int'];

        yield 'a float' => [1.5, 'float'];

        yield 'a mistyped word' => ['ture', 'string'];
    }

    public function testTheQueueBoundReadsNativeAndDigitStringSpellings(): void
    {
        self::assertSame(512, TracingPolicy::fromMap(['max_queue' => 512])->maxQueue);
        self::assertSame(512, TracingPolicy::fromMap(['max_queue' => '512'])->maxQueue);
    }

    public function testTheBatchSizeReadsNativeAndDigitStringSpellings(): void
    {
        self::assertSame(64, TracingPolicy::fromMap(['batch_size' => 64])->batchSize);
        self::assertSame(64, TracingPolicy::fromMap(['batch_size' => '64'])->batchSize);
    }

    #[DataProvider('provideTheBoundsRefuseEverythingElseCases')]
    public function testTheBoundsRefuseEverythingElse(string $knob, mixed $value, string $type): void
    {
        try {
            TracingPolicy::fromMap([$knob => $value]);
            self::fail('A mistyped bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.tracing ' . $knob
                . ' knob must be an integer or an all-digit string, ' . $type . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{string,mixed,string}>
     */
    public static function provideTheBoundsRefuseEverythingElseCases(): iterable
    {
        yield 'queue float' => ['max_queue', 5.12, 'float'];

        yield 'queue bool' => ['max_queue', true, 'bool'];

        yield 'queue negative string' => ['max_queue', '-512', 'string'];

        yield 'queue spaced string' => ['max_queue', ' 512', 'string'];

        yield 'batch float' => ['batch_size', 0.64, 'float'];

        yield 'batch mistyped word' => ['batch_size', 'large', 'string'];
    }

    public function testANonMapFamilyIsRefused(): void
    {
        try {
            TracingPolicy::fromMap('off');
            self::fail('A non-map family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.tracing configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobIsRefusedWithTheKnownList(): void
    {
        try {
            TracingPolicy::fromMap(['max_queu' => 512]);
            self::fail('An unknown knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.tracing configuration carries the unknown knob 'max_queu'"
                . ' (known knobs: enabled, max_queue, batch_size).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheUnknownFamilyIsRefusedWithTheKnownFamilies(): void
    {
        try {
            ObservabilityFamilies::subtree(['trcing' => []], 'tracing');
            self::fail('An unknown family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'trcing'"
                . ' (known families: timing, tracing, correlation, export, metrics, router, slow_request).',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonMapObservabilityRootIsRefused(): void
    {
        try {
            ObservabilityFamilies::subtree('on', 'tracing');
            self::fail('A non-map observability root must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFamilySubtreeReadsOneFamily(): void
    {
        $map = [
            'timing' => ['enabled' => false],
            'tracing' => ['enabled' => true],
        ];

        self::assertSame(['enabled' => true], ObservabilityFamilies::subtree($map, 'tracing'));
        self::assertNull(ObservabilityFamilies::subtree(null, 'tracing'));
    }
}
