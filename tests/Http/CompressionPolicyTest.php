<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CompressionKnobs;
use Zef\Framework\Http\CompressionPolicy;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpFamilies;
use Zef\Framework\Http\StrictIntegers;
use Zef\Framework\Http\StrictKnobs;
use Zef\Framework\Http\StrictToggles;

/**
 * The compression family's knob reading, on its own terms: the default
 * posture (armed, the roadmap's own 1 KB threshold, zlib's own middle
 * level), the full knob map, the environment's string spellings, the
 * unknown-key census, the `http` map's own family census -- plus the
 * profile mirror the timing family pinned the same way.
 *
 * @internal
 */
#[CoversClass(CompressionPolicy::class)]
#[CoversClass(CompressionKnobs::class)]
#[CoversClass(HttpFamilies::class)]
#[CoversClass(StrictKnobs::class)]
#[CoversClass(StrictToggles::class)]
#[CoversClass(StrictIntegers::class)]
final class CompressionPolicyTest extends TestCase
{
    public function testAnAbsentFamilyReadsTheDefaultPosture(): void
    {
        $compressionPolicy = CompressionPolicy::fromMap(null);

        self::assertTrue($compressionPolicy->enabled, 'the family is armed by default');
        self::assertSame(1024, $compressionPolicy->minimumBytes, "the roadmap's own example threshold");
        self::assertSame(6, $compressionPolicy->level, "zlib's own middle");
    }

    public function testTheFullKnobMapReads(): void
    {
        $compressionPolicy = CompressionPolicy::fromMap([
            'enabled' => false,
            'minimum_bytes' => 4096,
            'level' => 9,
        ]);

        self::assertFalse($compressionPolicy->enabled);
        self::assertSame(4096, $compressionPolicy->minimumBytes);
        self::assertSame(9, $compressionPolicy->level);
    }

    public function testTheEnvironmentStringSpellingsFold(): void
    {
        $compressionPolicy = CompressionPolicy::fromMap([
            'minimum_bytes' => '2048',
            'level' => '1',
        ]);

        self::assertSame(2048, $compressionPolicy->minimumBytes);
        self::assertSame(1, $compressionPolicy->level);
    }

    public function testTheMapMustBeAMap(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('The http.compression configuration must be a map, string given.');

        CompressionPolicy::fromMap('gzip');
    }

    public function testAnEmptyMapReadsTheDefaultPosture(): void
    {
        $compressionPolicy = CompressionPolicy::fromMap([]);

        self::assertTrue($compressionPolicy->enabled, "the absent knobs read the defaults, not the constructor's");
        self::assertSame(1024, $compressionPolicy->minimumBytes);
        self::assertSame(6, $compressionPolicy->level);
    }

    public function testAnUnknownKnobHaltsTheBoot(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The http.compression configuration carries the unknown knob 'minumum_bytes'"
            . ' (known knobs: enabled, minimum_bytes, level).',
        );

        CompressionPolicy::fromMap(['minumum_bytes' => 4096]);
    }

    public function testAWrongTypedKnobHaltsTheBoot(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.compression minimum_bytes knob must be an integer or an all-digit string, float given.',
        );

        CompressionPolicy::fromMap(['minimum_bytes' => 10.5]);
    }

    #[DataProvider('provideAnOutOfBoundKnobHaltsTheBootCases')]
    public function testAnOutOfBoundKnobHaltsTheBoot(string $knob, int $value, string $spelled): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains($spelled);

        CompressionPolicy::fromMap([$knob => $value]);
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function provideAnOutOfBoundKnobHaltsTheBootCases(): iterable
    {
        yield 'minimum below one' => [
            'minimum_bytes',
            0,
            'The http.compression minimum_bytes knob must be between 1 and 1048576, 0 given.',
        ];

        yield 'minimum above the mebibyte' => [
            'minimum_bytes',
            1048577,
            'The http.compression minimum_bytes knob must be between 1 and 1048576, 1048577 given.',
        ];

        yield 'level below one' => [
            'level',
            0,
            'The http.compression level knob must be between 1 and 9, 0 given.',
        ];

        yield 'level above nine' => [
            'level',
            10,
            'The http.compression level knob must be between 1 and 9, 10 given.',
        ];
    }

    public function testTheBoundCorridorEdgesRead(): void
    {
        $floor = CompressionPolicy::fromMap(['minimum_bytes' => 1, 'level' => 1]);
        self::assertSame(1, $floor->minimumBytes);
        self::assertSame(1, $floor->level);

        $ceiling = CompressionPolicy::fromMap(['minimum_bytes' => 1048576, 'level' => 9]);
        self::assertSame(1048576, $ceiling->minimumBytes);
        self::assertSame(9, $ceiling->level);
    }

    public function testTheHttpMapCensusRejectsAnUnknownFamily(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The http configuration carries the unknown family 'compresssion'"
            . ' (known families: error_handling, compression, body_parser, session).',
        );

        HttpFamilies::subtree(['error_handling' => [], 'compresssion' => []], 'compression');
    }

    public function testTheHttpMapMustBeAMap(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('The http configuration must be a map, int given.');

        HttpFamilies::subtree(7, 'compression');
    }

    public function testAnAbsentHttpMapReadsTheAbsentFamily(): void
    {
        self::assertNull(HttpFamilies::subtree(null, 'compression'));
    }

    public function testAnAbsentFamilyInsideTheMapReadsTheDefaultPosture(): void
    {
        $compressionPolicy = CompressionPolicy::fromMap(
            HttpFamilies::subtree(['error_handling' => ['debug' => false]], 'compression'),
        );

        self::assertTrue($compressionPolicy->enabled);
        self::assertSame(1024, $compressionPolicy->minimumBytes);
        self::assertSame(6, $compressionPolicy->level);
    }

    public function testTheProfileDeclarationMirrorsThePolicyDefaults(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/http.php';
        self::assertIsArray($profile, 'the shipped profile is a plain PHP map');
        self::assertArrayHasKey('compression', $profile);

        self::assertSame(
            [
                'enabled' => true,
                'minimum_bytes' => 1024,
                'level' => 6,
            ],
            $profile['compression'],
            "the shipped profile spells the policy's own default posture, so the two cannot drift",
        );
    }
}
