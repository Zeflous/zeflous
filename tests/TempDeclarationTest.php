<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\TempDeclaration;
use Zef\Framework\TempKnobs;
use Zef\Framework\TempRetention;

/**
 * Pins the temp family's declaration fold: the strict census of
 * the one `temp` knob block (the silent posture an absent block
 * or a null retention answers, the named halts a typo'd or
 * wrong-typed knob earns, the retention's integer seam with its
 * environment-overlay fold), and the drift pin that holds the
 * framework profile's own declaration and the shipped
 * configuration file to the same knobs -- the two cannot disagree
 * without a test naming it. The covers list names the whole
 * reading family this class exercises -- the declaration and the
 * two folds its grammar reads through (`TempKnobs`,
 * `TempRetention`) plus the Default layer the drift pin reads:
 * the suite runs with requireCoverageMetadata, so coverage of a
 * fold whose home does not name it is filtered out of the clover
 * report ({@see UploadsDeclarationTest} the covers
 * attribution doctrine the uploads slice spelled).
 *
 * @internal
 */
#[CoversClass(TempDeclaration::class)]
#[CoversClass(TempKnobs::class)]
#[CoversClass(TempRetention::class)]
#[CoversClass(FrameworkDefaults::class)]
final class TempDeclarationTest extends TestCase
{
    public function testAnAbsentTempBlockAnswersTheSilentPosture(): void
    {
        $declaration = TempDeclaration::fromConfig(new Config([]));

        self::assertSame('', $declaration->path);
        self::assertNull($declaration->ttlSeconds);
        self::assertFalse($declaration->wantsWriter(), 'an absent family declares no armed writer');
    }

    public function testAnEmptyBlockAnswersTheSilentPosture(): void
    {
        $declaration = TempDeclaration::fromConfig(new Config([
            'temp' => [],
        ]));

        self::assertSame('', $declaration->path);
        self::assertNull($declaration->ttlSeconds);
        self::assertFalse($declaration->wantsWriter());
    }

    public function testTheShippedNullRetentionAnswersTheSilentPosture(): void
    {
        $declaration = TempDeclaration::fromConfig(new Config([
            'temp' => ['ttl' => null, 'path' => ''],
        ]));

        self::assertSame('', $declaration->path);
        self::assertNull($declaration->ttlSeconds);
        self::assertFalse(
            $declaration->wantsWriter(),
            'the retention the host never spelled keeps the surface shut',
        );
    }

    public function testAnArmedDeclarationIsReadWithItsOwnKnobsVerbatim(): void
    {
        $declaration = TempDeclaration::fromConfig(new Config([
            'temp' => [
                'path' => '/srv/app/elsewhere',
                'ttl' => 3600,
            ],
        ]));

        self::assertSame('/srv/app/elsewhere', $declaration->path);
        self::assertSame(3600, $declaration->ttlSeconds);
        self::assertTrue($declaration->wantsWriter(), 'a declared retention arms the writer');
    }

    public function testAnEnvironmentSpelledRetentionFoldsToTheIntegerItSpells(): void
    {
        $declaration = TempDeclaration::fromConfig(new Config([
            'temp' => ['ttl' => '3600'],
        ]));

        self::assertSame(3600, $declaration->ttlSeconds);
    }

    public function testAOneSecondRetentionIsItsOwnAcceptableBound(): void
    {
        $declaration = TempDeclaration::fromConfig(new Config([
            'temp' => ['ttl' => 1],
        ]));

        self::assertSame(1, $declaration->ttlSeconds);
        self::assertTrue($declaration->wantsWriter());
    }

    public function testTheShippedProfileAndTheFrameworkDefaultsDeclareTheSameKnobs(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/temp.php';

        self::assertSame(
            FrameworkDefaults::profile()['temp'],
            $profile,
            'the Default layer and the shipped file cannot drift apart',
        );
    }

    public function testANonMapTempBlockRefuses(): void
    {
        try {
            TempDeclaration::fromConfig(new Config(['temp' => 'scratch']));
            self::fail('A non-map temp block must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The temp configuration must be a map, string given.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobRefusesWithTheKnownListSpelled(): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['ttl' => 3600, 'moon' => 1],
            ]));
            self::fail('An unknown knob must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The temp configuration carries the unknown knob 'moon'"
                . ' (known knobs: path, ttl).',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobBehindAKnownOneStillRefuses(): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['path' => '', 'ttl' => 3600, 'zzz' => 1],
            ]));
            self::fail('An unknown knob behind a known one must still refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The temp configuration carries the unknown knob 'zzz'"
                . ' (known knobs: path, ttl).',
                $configException->getMessage(),
            );
        }
    }

    public function testANonStringPathRefuses(): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['path' => 42, 'ttl' => 3600],
            ]));
            self::fail('A non-string path must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame('temp.path must be a string.', $configException->getMessage());
        }
    }

    public function testANonIntegerRetentionRefuses(): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['ttl' => 'about an hour'],
            ]));
            self::fail('A non-integer retention must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'temp.ttl must be an integer of at least one second, string given.',
                $configException->getMessage(),
            );
        }
    }

    #[DataProvider('provideAPartiallyDigitSpellingRefusesBothAnchorsCases')]
    public function testAPartiallyDigitSpellingRefusesBothAnchors(string $spelling): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['ttl' => $spelling],
            ]));
            self::fail('A partially-digit spelling must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'temp.ttl must be an integer of at least one second, string given.',
                $configException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideAPartiallyDigitSpellingRefusesBothAnchorsCases(): iterable
    {
        yield 'a trailing unit' => ['3600s'];

        yield 'a leading space' => [' 3600'];

        yield 'a trailing space' => ['3600 '];

        yield 'a signed spelling' => ['+3600'];
    }

    public function testABelowOneSecondRetentionRefuses(): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['ttl' => 0],
            ]));
            self::fail('A below-one-second retention must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'temp.ttl must be an integer of at least one second.',
                $configException->getMessage(),
            );
        }
    }

    public function testADigitStringBelowOneSecondRefusesTheSameBound(): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['ttl' => '0'],
            ]));
            self::fail('A digit-string below one second must refuse the same bound.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'temp.ttl must be an integer of at least one second.',
                $configException->getMessage(),
            );
        }
    }

    public function testABooleanRetentionRefusesWithItsTypeNamed(): void
    {
        try {
            TempDeclaration::fromConfig(new Config([
                'temp' => ['ttl' => true],
            ]));
            self::fail('A boolean retention must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'temp.ttl must be an integer of at least one second, bool given.',
                $configException->getMessage(),
            );
        }
    }
}
