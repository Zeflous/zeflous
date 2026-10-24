<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\BodyParserKnobs;
use Zef\Framework\Http\BodyParserPolicy;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpFamilies;
use Zef\Framework\Http\StrictIntegers;
use Zef\Framework\Http\StrictKnobs;
use Zef\Framework\Http\StrictToggles;

/**
 * The body-parser family's knob reading, on its own terms: the default
 * posture (armed, the error system's own 32-level depth ceiling), the
 * full knob map, the environment's string spellings, the unknown-key
 * census, the `http` map's own family census -- plus the profile
 * mirror the compression family pinned the same way.
 *
 * @internal
 */
#[CoversClass(BodyParserPolicy::class)]
#[CoversClass(BodyParserKnobs::class)]
#[CoversClass(HttpFamilies::class)]
#[CoversClass(StrictKnobs::class)]
#[CoversClass(StrictToggles::class)]
#[CoversClass(StrictIntegers::class)]
final class BodyParserPolicyTest extends TestCase
{
    public function testAnAbsentFamilyReadsTheDefaultPosture(): void
    {
        $bodyParserPolicy = BodyParserPolicy::fromMap(null);

        self::assertTrue($bodyParserPolicy->enabled, 'the family is armed by default');
        self::assertSame(32, $bodyParserPolicy->jsonDepth, "the error system's own documented ceiling");
    }

    public function testTheFullKnobMapReads(): void
    {
        $bodyParserPolicy = BodyParserPolicy::fromMap([
            'enabled' => false,
            'json_depth' => 128,
        ]);

        self::assertFalse($bodyParserPolicy->enabled);
        self::assertSame(128, $bodyParserPolicy->jsonDepth);
    }

    public function testTheEnvironmentStringSpellingsFold(): void
    {
        $bodyParserPolicy = BodyParserPolicy::fromMap([
            'json_depth' => '64',
        ]);

        self::assertSame(64, $bodyParserPolicy->jsonDepth);
    }

    public function testTheMapMustBeAMap(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('The http.body_parser configuration must be a map, string given.');

        BodyParserPolicy::fromMap('json');
    }

    public function testAnEmptyMapReadsTheDefaultPosture(): void
    {
        $bodyParserPolicy = BodyParserPolicy::fromMap([]);

        self::assertTrue($bodyParserPolicy->enabled, "the absent knobs read the defaults, not the constructor's");
        self::assertSame(32, $bodyParserPolicy->jsonDepth);
    }

    public function testAnUnknownKnobHaltsTheBoot(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The http.body_parser configuration carries the unknown knob 'json_deph'"
            . ' (known knobs: enabled, json_depth).',
        );

        BodyParserPolicy::fromMap(['json_deph' => 64]);
    }

    public function testAWrongTypedKnobHaltsTheBoot(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.body_parser json_depth knob must be an integer or an all-digit string, float given.',
        );

        BodyParserPolicy::fromMap(['json_depth' => 10.5]);
    }

    #[DataProvider('provideAnOutOfBoundKnobHaltsTheBootCases')]
    public function testAnOutOfBoundKnobHaltsTheBoot(int $value, string $spelled): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains($spelled);

        BodyParserPolicy::fromMap(['json_depth' => $value]);
    }

    /** @return iterable<string, array{int, string}> */
    public static function provideAnOutOfBoundKnobHaltsTheBootCases(): iterable
    {
        yield 'depth below one' => [
            0,
            'The http.body_parser json_depth knob must be between 1 and 512, 0 given.',
        ];

        yield 'depth above the engine maximum' => [
            513,
            'The http.body_parser json_depth knob must be between 1 and 512, 513 given.',
        ];
    }

    public function testTheBoundCorridorEdgesRead(): void
    {
        $floor = BodyParserPolicy::fromMap(['json_depth' => 1]);
        self::assertSame(1, $floor->jsonDepth);

        $ceiling = BodyParserPolicy::fromMap(['json_depth' => 512]);
        self::assertSame(512, $ceiling->jsonDepth);
    }

    public function testTheHttpMapCensusRejectsAnUnknownFamily(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The http configuration carries the unknown family 'body_parse'"
            . ' (known families: error_handling, compression, body_parser, session).',
        );

        HttpFamilies::subtree(['error_handling' => [], 'body_parse' => []], 'body_parser');
    }

    public function testTheHttpMapMustBeAMap(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('The http configuration must be a map, int given.');

        HttpFamilies::subtree(7, 'body_parser');
    }

    public function testAnAbsentHttpMapReadsTheAbsentFamily(): void
    {
        self::assertNull(HttpFamilies::subtree(null, 'body_parser'));
    }

    public function testAnAbsentFamilyInsideTheMapReadsTheDefaultPosture(): void
    {
        $bodyParserPolicy = BodyParserPolicy::fromMap(
            HttpFamilies::subtree(['error_handling' => ['debug' => false]], 'body_parser'),
        );

        self::assertTrue($bodyParserPolicy->enabled);
        self::assertSame(32, $bodyParserPolicy->jsonDepth);
    }

    public function testTheProfileDeclarationMirrorsThePolicyDefaults(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/http.php';
        self::assertIsArray($profile, 'the shipped profile is a plain PHP map');
        self::assertArrayHasKey('body_parser', $profile);

        self::assertSame(
            [
                'enabled' => true,
                'json_depth' => 32,
            ],
            $profile['body_parser'],
            "the shipped profile spells the policy's own default posture, so the two cannot drift",
        );
    }
}
