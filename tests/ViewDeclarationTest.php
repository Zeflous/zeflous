<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\ViewDeclaration;
use Zef\Framework\ViewEngines;
use Zef\Framework\ViewKnobs;

/**
 * Pins the view family's declaration fold: the strict census of the
 * one `views` knob block (the silent posture an absent block answers,
 * the named halts a typo'd or wrong-typed knob earns, the engine
 * word's closed vocabulary with its environment-overlay fold), and
 * the drift pin that holds the framework profile's own declaration
 * and the shipped configuration file to the same knobs -- the two
 * cannot disagree without a test naming it. The covers list names
 * the whole reading family this class exercises -- the declaration
 * and the two folds its grammar reads through (`ViewKnobs`,
 * `ViewEngines`) plus the Default layer the drift pin reads: the
 * suite runs with requireCoverageMetadata, so coverage of a fold
 * whose home does not name it is filtered out of the clover report
 * -- invisible to the mutation ledger AND to SonarCloud's new-code
 * gate alike, the covers-attribution doctrine KDD-DU13 spelled
 * first.
 *
 * @internal
 */
#[CoversClass(ViewDeclaration::class)]
#[CoversClass(ViewKnobs::class)]
#[CoversClass(ViewEngines::class)]
#[CoversClass(FrameworkDefaults::class)]
final class ViewDeclarationTest extends TestCase
{
    public function testAnAbsentViewsBlockAnswersTheSilentPosture(): void
    {
        $declaration = ViewDeclaration::fromConfig(new Config([]));

        self::assertSame('', $declaration->engine);
        self::assertSame('', $declaration->path);
        self::assertFalse($declaration->wantsWiredEngine(), 'an absent family declares no wired engine');
    }

    public function testTheSilentFamilyAnswersTheEmptyWord(): void
    {
        $declaration = ViewDeclaration::fromConfig(new Config([
            'views' => [],
        ]));

        self::assertSame('', $declaration->engine);
        self::assertSame('', $declaration->path);
        self::assertFalse($declaration->wantsWiredEngine(), 'the empty engine word declares no wired engine');
    }

    public function testAnArmedDeclarationIsReadWithItsOwnKnobsVerbatim(): void
    {
        $declaration = ViewDeclaration::fromConfig(new Config([
            'views' => [
                'engine' => 'php',
                'path' => '/srv/app/templates',
            ],
        ]));

        self::assertSame('php', $declaration->engine);
        self::assertSame('/srv/app/templates', $declaration->path);
        self::assertTrue($declaration->wantsWiredEngine(), 'the php word asks for the wired engine');
    }

    public function testAnEnvironmentSpelledEngineWordFoldsToTheWordItSpells(): void
    {
        $declaration = ViewDeclaration::fromConfig(new Config([
            'views' => ['engine' => 'PHP'],
        ]));

        self::assertSame('php', $declaration->engine, 'an APP_VIEWS__ENGINE=PHP overlay reads as the php word');
        self::assertTrue($declaration->wantsWiredEngine());
    }

    public function testAPathKnobAloneAnswersTheAddresslessWord(): void
    {
        $declaration = ViewDeclaration::fromConfig(new Config([
            'views' => ['path' => '/srv/app/templates'],
        ]));

        self::assertSame('', $declaration->engine);
        self::assertSame('/srv/app/templates', $declaration->path);
        self::assertFalse($declaration->wantsWiredEngine(), 'a path alone declares no wired engine');
    }

    public function testTheShippedProfileAndTheFrameworkDefaultsDeclareTheSameKnobs(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/views.php';

        self::assertSame(
            FrameworkDefaults::profile()['views'],
            $profile,
            'the Default layer and the shipped file cannot drift apart',
        );
    }

    public function testANonMapViewsConfigurationRefuses(): void
    {
        try {
            ViewDeclaration::fromConfig(new Config([
                'views' => 'the-presentation-lane',
            ]));
            self::fail('A non-map views configuration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The views configuration must be a map, string given.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownViewsKnobRefusesWithTheKnownListSpelled(): void
    {
        try {
            ViewDeclaration::fromConfig(new Config([
                'views' => ['engien' => 'php'],
            ]));
            self::fail('An unknown views knob must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The views configuration carries the unknown knob 'engien'"
                . ' (known knobs: engine, path).',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobBehindAKnownOneStillRefuses(): void
    {
        try {
            ViewDeclaration::fromConfig(new Config([
                'views' => ['engine' => 'php', 'paths' => '/srv/app/templates'],
            ]));
            self::fail('An unknown knob behind a known one must still refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The views configuration carries the unknown knob 'paths'"
                . ' (known knobs: engine, path).',
                $configException->getMessage(),
            );
        }
    }

    public function testANonStringKnobKeyRefusesWithItsTypeSpelled(): void
    {
        try {
            ViewDeclaration::fromConfig(new Config([
                'views' => [42 => 'php'],
            ]));
            self::fail('A non-string knob key must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The views configuration carries the unknown knob 'int'"
                . ' (known knobs: engine, path).',
                $configException->getMessage(),
            );
        }
    }

    public function testANonStringEngineWordRefuses(): void
    {
        try {
            ViewDeclaration::fromConfig(new Config([
                'views' => ['engine' => 42],
            ]));
            self::fail('A non-string engine word must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame('views.engine must be a string.', $configException->getMessage());
        }
    }

    public function testANonStringPathRefuses(): void
    {
        try {
            ViewDeclaration::fromConfig(new Config([
                'views' => ['engine' => 'php', 'path' => 42],
            ]));
            self::fail('A non-string path must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame('views.path must be a string.', $configException->getMessage());
        }
    }

    public function testTheTwigWordIsReadVerbatim(): void
    {
        $declaration = ViewDeclaration::fromConfig(new Config([
            'views' => ['engine' => 'twig', 'path' => '/srv/app/templates'],
        ]));

        self::assertSame('twig', $declaration->engine);
        self::assertTrue(
            $declaration->wantsWiredEngine(),
            'the twig word asks for the wired engine -- the optional answer over the'
                . " host tree's own Twig, the adoption the owner's recorded direction names",
        );
    }

    public function testAnEnvironmentSpelledTwigWordFoldsToTheWordItSpells(): void
    {
        $declaration = ViewDeclaration::fromConfig(new Config([
            'views' => ['engine' => 'TWIG'],
        ]));

        self::assertSame('twig', $declaration->engine, 'an APP_VIEWS__ENGINE=TWIG overlay reads as the twig word');
        self::assertTrue($declaration->wantsWiredEngine());
    }

    public function testAnEngineWordTheWiringDoesNotKnowRefusesWithTheWordSpelled(): void
    {
        try {
            ViewDeclaration::fromConfig(new Config([
                // The vocabulary's own docblock names the shape: a `pfp`
                // is a typo of `php`, not an engine.
                'views' => ['engine' => 'pfp'],
            ]));
            self::fail('An engine word the wiring does not know must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The views.engine word 'pfp' is not one the wiring knows"
                . ' (known words: php, twig).',
                $configException->getMessage(),
            );
        }
    }
}
