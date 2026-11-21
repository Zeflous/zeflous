<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\UploadsBudget;
use Zef\Framework\UploadsDeclaration;
use Zef\Framework\UploadsKnobs;
use Zef\Framework\UploadsTypes;

/**
 * Pins the uploads family's declaration fold: the strict census of
 * the one `uploads` knob block (the silent posture an absent block
 * answers, the named halts a typo'd or wrong-typed knob earns, the
 * ceiling's integer seam with its environment-overlay fold, the
 * allowlist's grammar with its lowercase canonical spelling), and
 * the drift pin that holds the framework profile's own declaration
 * and the shipped configuration file to the same knobs -- the two
 * cannot disagree without a test naming it. The covers list names
 * the whole reading family this class exercises -- the declaration
 * and the three folds its grammar reads through (`UploadsKnobs`,
 * `UploadsBudget`, `UploadsTypes`) plus the Default layer the drift
 * pin reads: the suite runs with requireCoverageMetadata, so
 * coverage of a fold whose home does not name it is filtered out
 * of the clover report -- invisible to the mutation ledger AND to
 * SonarCloud's new-code gate alike, the covers-attribution
 * doctrine KDD-DU13 spelled first.
 *
 * @internal
 */
#[CoversClass(UploadsDeclaration::class)]
#[CoversClass(UploadsKnobs::class)]
#[CoversClass(UploadsBudget::class)]
#[CoversClass(UploadsTypes::class)]
#[CoversClass(FrameworkDefaults::class)]
final class UploadsDeclarationTest extends TestCase
{
    public function testAnAbsentUploadsBlockAnswersTheSilentPosture(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([]));

        self::assertSame('', $declaration->path);
        self::assertSame(2_097_152, $declaration->maxBytes);
        self::assertSame([], $declaration->types);
        self::assertFalse($declaration->wantsWriter(), 'an absent family declares no armed writer');
    }

    public function testTheSilentFamilyAnswersTheDefaultCeiling(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([
            'uploads' => [],
        ]));

        self::assertSame('', $declaration->path);
        self::assertSame(2_097_152, $declaration->maxBytes);
        self::assertSame([], $declaration->types);
    }

    public function testAnArmedDeclarationIsReadWithItsOwnKnobsVerbatim(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([
            'uploads' => [
                'path' => '/srv/app/elsewhere',
                'max_bytes' => 5,
                'types' => ['image/png', 'application/pdf'],
            ],
        ]));

        self::assertSame('/srv/app/elsewhere', $declaration->path);
        self::assertSame(5, $declaration->maxBytes);
        self::assertSame(['image/png', 'application/pdf'], $declaration->types);
        self::assertTrue($declaration->wantsWriter(), 'a declared allowlist arms the writer');
    }

    public function testAnAbsentCeilingAnswersTheFamilyDefault(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([
            'uploads' => ['types' => ['image/png']],
        ]));

        self::assertSame(2_097_152, $declaration->maxBytes);
        self::assertTrue($declaration->wantsWriter());
    }

    public function testAnEnvironmentSpelledCeilingFoldsToTheIntegerItSpells(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([
            'uploads' => ['max_bytes' => '5242880', 'types' => ['image/png']],
        ]));

        self::assertSame(5242880, $declaration->maxBytes);
    }

    public function testAOneByteCeilingIsItsOwnAcceptableBound(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([
            'uploads' => ['max_bytes' => 1, 'types' => ['image/png']],
        ]));

        self::assertSame(1, $declaration->maxBytes);
        self::assertTrue($declaration->wantsWriter());
    }

    public function testTheShippedProfileAndTheFrameworkDefaultsDeclareTheSameKnobs(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/uploads.php';

        self::assertSame(
            FrameworkDefaults::profile()['uploads'],
            $profile,
            'the Default layer and the shipped file cannot drift apart',
        );
    }

    public function testANonMapUploadsConfigurationRefuses(): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => 'the-inbound-lane',
            ]));
            self::fail('A non-map uploads configuration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The uploads configuration must be a map, string given.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownUploadsKnobRefusesWithTheKnownListSpelled(): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['max_bytse' => 5],
            ]));
            self::fail('An unknown uploads knob must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The uploads configuration carries the unknown knob 'max_bytse'"
                . ' (known knobs: path, max_bytes, types).',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobBehindAKnownOneStillRefuses(): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['path' => '', 'types' => ['image/png'], 'max_bytse' => 5],
            ]));
            self::fail('An unknown knob behind a known one must still refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The uploads configuration carries the unknown knob 'max_bytse'"
                . ' (known knobs: path, max_bytes, types).',
                $configException->getMessage(),
            );
        }
    }

    public function testANonStringPathRefuses(): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['path' => 42],
            ]));
            self::fail('A non-string path must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame('uploads.path must be a string.', $configException->getMessage());
        }
    }

    public function testANonIntegerCeilingRefuses(): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['max_bytes' => 'five'],
            ]));
            self::fail('A non-integer ceiling must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'uploads.max_bytes must be an integer of at least one byte, string given.',
                $configException->getMessage(),
            );
        }
    }

    #[DataProvider('provideAPartiallyDigitSpellingRefusesBothAnchorsCases')]
    public function testAPartiallyDigitSpellingRefusesBothAnchors(string $spelling): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['max_bytes' => $spelling],
            ]));
            self::fail('A partially-digit spelling must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'uploads.max_bytes must be an integer of at least one byte, string given.',
                $configException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideAPartiallyDigitSpellingRefusesBothAnchorsCases(): iterable
    {
        yield 'a non-digit prefix' => ['abc123'];

        yield 'a non-digit suffix' => ['123abc'];

        yield 'a trailing newline' => ["123\n"];
    }

    public function testABelowOneCeilingRefuses(): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['max_bytes' => 0],
            ]));
            self::fail('A below-one ceiling must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'uploads.max_bytes must be an integer of at least one byte.',
                $configException->getMessage(),
            );
        }
    }

    public function testANonListAllowlistRefuses(): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['types' => 'image/png'],
            ]));
            self::fail('A non-list allowlist must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'uploads.types must be a list of media types, string given.',
                $configException->getMessage(),
            );
        }
    }

    #[DataProvider('provideAGrammarRefusingEntryNamesTheEntryCases')]
    public function testAGrammarRefusingEntryNamesTheEntry(mixed $entry, string $described): void
    {
        try {
            UploadsDeclaration::fromConfig(new Config([
                'uploads' => ['types' => [$entry]],
            ]));
            self::fail('A grammar-refusing entry must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'uploads.types must carry lowercase media types (type/subtype), '
                . $described . ' given.',
                $configException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<non-empty-string, array{mixed, string}>
     */
    public static function provideAGrammarRefusingEntryNamesTheEntryCases(): iterable
    {
        yield 'a non-string entry' => [42, 'int'];

        yield 'an uppercase entry' => ['IMAGE/PNG', "'IMAGE/PNG'"];

        yield 'a parameter-carrying entry' => ['text/plain; charset=utf-8', "'text/plain; charset=utf-8'"];

        yield 'an underscored entry' => ['image_png', "'image_png'"];

        yield 'a typeless entry' => ['/png', "'/png'"];

        yield 'a subtype-less entry' => ['image/', "'image/'"];

        yield 'a double-slashed entry' => ['image/png/extra', "'image/png/extra'"];
    }

    public function testTheRegisteredSubtypePunctuationRides(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([
            'uploads' => ['types' => ['image/svg+xml', 'application/vnd.ms-excel']],
        ]));

        self::assertSame(
            ['image/svg+xml', 'application/vnd.ms-excel'],
            $declaration->types,
            'the grammar carries the registered subtype punctuation',
        );
    }

    public function testARepeatedEntryRidesVerbatim(): void
    {
        $declaration = UploadsDeclaration::fromConfig(new Config([
            'uploads' => ['types' => ['image/png', 'image/png']],
        ]));

        self::assertSame(
            ['image/png', 'image/png'],
            $declaration->types,
            'the exact-match comparison is repeat-invariant, so the fold never de-duplicates',
        );
    }
}
