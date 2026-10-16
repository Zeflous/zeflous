<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\StrictKnobs;
use Zef\Framework\Http\StrictToggles;
use Zef\Framework\Http\TimingKnobs;
use Zef\Framework\Http\TimingPolicy;

/**
 * The timing family's knob reading, on its own terms: the default
 * posture (armed, header off -- the ZEF-DX-08 production default),
 * the full knob map, the environment's string spellings, the
 * unknown-key census, the observability map's own family census --
 * plus the profile mirror the auth family pinned the same way.
 *
 * @internal
 */
#[CoversClass(TimingPolicy::class)]
#[CoversClass(TimingKnobs::class)]
#[CoversClass(ObservabilityFamilies::class)]
#[CoversClass(StrictKnobs::class)]
#[CoversClass(StrictToggles::class)]
#[CoversClass(FrameworkDefaults::class)]
final class TimingPolicyTest extends TestCase
{
    public function testAnAbsentFamilyReadsTheDefaultPosture(): void
    {
        $timingPolicy = TimingPolicy::fromMap(null);

        self::assertTrue($timingPolicy->enabled, 'the family is armed by default');
        self::assertFalse($timingPolicy->emitHeader, 'the header ships off -- the production posture');
    }

    public function testAnEmptyMapReadsTheDefaultPosture(): void
    {
        $timingPolicy = TimingPolicy::fromMap([]);

        self::assertTrue($timingPolicy->enabled);
        self::assertFalse($timingPolicy->emitHeader);
    }

    public function testTheFullKnobMapReadsBothToggles(): void
    {
        $timingPolicy = TimingPolicy::fromMap([
            'enabled' => false,
            'emit_header' => true,
        ]);

        self::assertFalse($timingPolicy->enabled);
        self::assertTrue($timingPolicy->emitHeader);
    }

    /**
     * @param string $spelling the environment overlay's spelling
     */
    #[DataProvider('provideTheEnvironmentSpellingsFoldCases')]
    public function testTheEnvironmentSpellingsFold(string $spelling, bool $enabled, bool $emitHeader): void
    {
        $timingPolicy = TimingPolicy::fromMap([
            'enabled' => $spelling,
            'emit_header' => $spelling,
        ]);

        self::assertSame($enabled, $timingPolicy->enabled);
        self::assertSame($emitHeader, $timingPolicy->emitHeader);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function provideTheEnvironmentSpellingsFoldCases(): iterable
    {
        yield 'native truth words' => ['on', true, true];

        yield 'case-mixed truth words' => ['TRUE', true, true];

        yield 'native falsehood words' => ['off', false, false];

        yield 'the digit spellings' => ['0', false, false];

        yield 'the explicitly empty value reads as off' => ['', false, false];
    }

    /**
     * @param mixed $value a value the toggle vocabulary refuses
     */
    #[DataProvider('provideAWrongTypedKnobHaltsTheBootCases')]
    public function testAWrongTypedKnobHaltsTheBoot(mixed $value): void
    {
        try {
            TimingPolicy::fromMap(['emit_header' => $value]);
            self::fail('A wrong-typed knob must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.timing emit_header knob must be a boolean or one of the'
                . ' boolean string spellings (true/false, on/off, yes/no, 1/0), '
                . get_debug_type($value) . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideAWrongTypedKnobHaltsTheBootCases(): iterable
    {
        yield 'an integer is not a boolean' => [1];

        yield 'a mistyped word never reads as false' => ['ture'];

        yield 'a maybe is not a posture' => ['maybe'];

        yield 'a float is not a boolean' => [0.5];
    }

    public function testTheSubtreeMustBeAMap(): void
    {
        try {
            TimingPolicy::fromMap('timing');
            self::fail('A non-map subtree must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.timing configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobIsRejectedWithTheKnownListSpelledOut(): void
    {
        try {
            TimingPolicy::fromMap(['emmit_header' => true]);
            self::fail('An unknown knob must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.timing configuration carries the unknown knob 'emmit_header'"
                . ' (known knobs: enabled, emit_header).',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonStringKnobIsRejectedByItsType(): void
    {
        try {
            TimingPolicy::fromMap([0 => true]);
            self::fail('A non-string knob key must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.timing configuration carries the unknown knob 'int'"
                . ' (known knobs: enabled, emit_header).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheObservabilityMapCarriesItsOwnFamilyCensus(): void
    {
        try {
            ObservabilityFamilies::subtree(['timming' => []], 'timing');
            self::fail('An unknown observability family must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'timming'"
                . ' (known families: timing, tracing, correlation).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheObservabilityMapItselfMustBeAMap(): void
    {
        try {
            ObservabilityFamilies::subtree('observability', 'timing');
            self::fail('A non-map observability root must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheAbsentObservabilityMapReadsAsTheAbsentFamily(): void
    {
        self::assertNull(ObservabilityFamilies::subtree(null, 'timing'));
    }

    public function testThePresentFamilySubtreeTravelsThrough(): void
    {
        self::assertSame(
            ['emit_header' => true],
            ObservabilityFamilies::subtree(['timing' => ['emit_header' => true]], 'timing'),
        );
    }

    public function testTheAbsentFamilySubtreeReadsAsNull(): void
    {
        self::assertNull(ObservabilityFamilies::subtree([], 'timing'));
    }

    public function testTheFrameworkProfileMirrorsTheDefaultPosture(): void
    {
        $fromEmptyMap = TimingPolicy::fromMap([]);

        $observability = FrameworkDefaults::profile()['observability'] ?? null;
        self::assertIsArray($observability);
        $fromDefaults = $observability['timing'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'emit_header' => $fromEmptyMap->emitHeader,
            ],
            $fromDefaults,
        );
    }
}
