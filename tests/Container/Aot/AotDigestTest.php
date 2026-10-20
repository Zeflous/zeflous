<?php

declare(strict_types=1);

namespace Zef\Test\Container\Aot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\Aot\AotDigest;
use Zef\Framework\Container\Aot\AotException;
use Zef\Framework\Container\Aot\AotRefusals;

/**
 * @internal
 */
#[CoversClass(AotDigest::class)]
#[CoversClass(AotRefusals::class)]
final class AotDigestTest extends TestCase
{
    private const array SOURCES = [
        'framework.services' => ['security.headers' => ['factory' => 'A::fromConfig']],
        'services' => ['app.house' => ['class' => 'House', 'arguments' => [['service' => 'app.window'], 4]]],
        'parameters' => ['db.host' => 'localhost'],
        'tags' => ['interfaces' => []],
        'contextual' => [],
    ];

    public function testTheSameSourcesAndVersionFoldToTheSameDigest(): void
    {
        self::assertSame(
            AotDigest::over(self::SOURCES, '1.2.3'),
            AotDigest::over(self::SOURCES, '1.2.3'),
        );
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $changed
     */
    #[DataProvider('provideEverySourceMapCarriesItsWeightCases')]
    public function testEverySourceMapCarriesItsWeight(string $map, array $original, array $changed): void
    {
        self::assertNotSame(
            AotDigest::over($original, '1.2.3'),
            AotDigest::over($changed, '1.2.3'),
            "a changed '{$map}' map must change the digest",
        );
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, array<string, mixed>}>
     */
    public static function provideEverySourceMapCarriesItsWeightCases(): iterable
    {
        yield 'the framework.services map' => [
            'framework.services',
            self::SOURCES,
            [
                'framework.services' => [],
                'services' => self::SOURCES['services'],
                'parameters' => self::SOURCES['parameters'],
                'tags' => self::SOURCES['tags'],
                'contextual' => self::SOURCES['contextual'],
            ],
        ];

        yield 'the services map' => [
            'services',
            self::SOURCES,
            [
                'framework.services' => self::SOURCES['framework.services'],
                'services' => [],
                'parameters' => self::SOURCES['parameters'],
                'tags' => self::SOURCES['tags'],
                'contextual' => self::SOURCES['contextual'],
            ],
        ];

        yield 'the parameters map' => [
            'parameters',
            self::SOURCES,
            [
                'framework.services' => self::SOURCES['framework.services'],
                'services' => self::SOURCES['services'],
                'parameters' => ['db.host' => 'elsewhere'],
                'tags' => self::SOURCES['tags'],
                'contextual' => self::SOURCES['contextual'],
            ],
        ];

        yield 'the tags map' => [
            'tags',
            self::SOURCES,
            [
                'framework.services' => self::SOURCES['framework.services'],
                'services' => self::SOURCES['services'],
                'parameters' => self::SOURCES['parameters'],
                'tags' => null,
                'contextual' => self::SOURCES['contextual'],
            ],
        ];

        yield 'the contextual map' => [
            'contextual',
            self::SOURCES,
            [
                'framework.services' => self::SOURCES['framework.services'],
                'services' => self::SOURCES['services'],
                'parameters' => self::SOURCES['parameters'],
                'tags' => self::SOURCES['tags'],
                'contextual' => ['billing' => []],
            ],
        ];
    }

    public function testTheFrameworkVersionCarriesItsWeight(): void
    {
        self::assertNotSame(
            AotDigest::over(self::SOURCES, '0.1.0'),
            AotDigest::over(self::SOURCES, '0.2.0'),
        );
    }

    /**
     * The artifact generation carries its weight the same way: a bumped
     * generation is a different fingerprint, so every artifact of the
     * older generation is proven stale the next boot.
     */
    public function testTheArtifactGenerationCarriesItsWeight(): void
    {
        self::assertNotSame(
            AotDigest::over([], '0.1.0'),
            $this->foldedWithoutGeneration('0.1.0'),
        );
    }

    public function testScalarTypesAreNamedInTheFold(): void
    {
        $digests = [
            AotDigest::over(['v' => 1], '1'),
            AotDigest::over(['v' => '1'], '1'),
            AotDigest::over(['v' => 1.0], '1'),
            AotDigest::over(['v' => true], '1'),
            AotDigest::over(['v' => false], '1'),
            AotDigest::over(['v' => null], '1'),
        ];

        self::assertCount(6, array_unique($digests));
    }

    public function testKeyOrderIsPartOfTheFingerprint(): void
    {
        self::assertNotSame(
            AotDigest::over(['a' => 1, 'b' => 2], '1'),
            AotDigest::over(['b' => 2, 'a' => 1], '1'),
        );
    }

    public function testNestedArraysFoldToTheSameAnswer(): void
    {
        self::assertSame(
            AotDigest::over(['a' => ['b' => ['c' => 1]]], '1'),
            AotDigest::over(['a' => ['b' => ['c' => 1]]], '1'),
        );
    }

    public function testTheEmptySourcesFoldStably(): void
    {
        self::assertSame(AotDigest::over([], '1'), AotDigest::over([], '1'));
    }

    /**
     * The fold's exact spelling, pinned: the digest's design contract is
     * stability across processes and releases -- every type-tagged
     * prefix, map name, the version and the artifact generation fold
     * into one SHA-256 whose literal value the lane promises not to
     * change silently.
     */
    public function testTheFoldSpellsItsCanonicalFormStably(): void
    {
        self::assertSame(
            '522b16cf8d0237b464c78b738b701cbcd116db6351de9c83a57cb35b3b2ec654',
            AotDigest::over([
                'framework.services' => ['x' => ['factory' => 'A::b']],
                'services' => ['a' => ['class' => 'C', 'arguments' => [1, '1', 1.5, true, false, null,
                    ['nested' => 'v']]]],
                'parameters' => ['db.host' => 'localhost'],
                'tags' => ['interfaces' => []],
                'contextual' => [],
            ], '2.9.0-zeflous'),
        );

        self::assertSame(
            '224db7de00e29302a8352bbab0b33e186061311797d096a9b765f324c70ba7db',
            AotDigest::over([], '1'),
        );
    }

    public function testAnObjectRefusesTheFoldWithItsPath(): void
    {
        try {
            AotDigest::over(['services' => new stdClass()], '1');
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertSame(
                "The AOT digest cannot fold the value at 'services' (stdClass) -- "
                . 'the AOT lane requires data-only configuration (arrays and scalars).',
                $aotException->getMessage(),
            );
        }
    }

    public function testANestedObjectRefusesWithItsDottedPath(): void
    {
        try {
            AotDigest::over(['services' => ['house' => ['arguments' => [new stdClass()]]]], '1');
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertSame(
                "The AOT digest cannot fold the value at 'services.house.arguments.0' (stdClass) -- "
                . 'the AOT lane requires data-only configuration (arrays and scalars).',
                $aotException->getMessage(),
            );
        }
    }

    public function testAClosureRefusesTheFold(): void
    {
        try {
            AotDigest::over(['parameters' => ['answer' => static fn (): int => 1]], '1');
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertSame(
                "The AOT digest cannot fold the value at 'parameters.answer' (Closure) -- "
                . 'the AOT lane requires data-only configuration (arrays and scalars).',
                $aotException->getMessage(),
            );
        }
    }

    /**
     * The same fold without the generation suffix -- the older
     * generation's spelling, mirrored here so the weight the generation
     * carries stays pinned.
     */
    private function foldedWithoutGeneration(string $version): string
    {
        return hash('sha256', 'version:' . $version);
    }
}
