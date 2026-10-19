<?php

declare(strict_types=1);

namespace Zef\Test\Container\Aot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Aot\AotMap;
use Zef\Framework\Container\Aot\AotRefusals;
use Zef\Framework\Container\Aot\AotToggles;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\FrameworkDefaults;

/**
 * @internal
 */
#[CoversClass(AotMap::class)]
#[CoversClass(AotToggles::class)]
#[CoversClass(AotRefusals::class)]
final class AotMapTest extends TestCase
{
    public function testTheAbsentKnobIsTheEverythingOffBoot(): void
    {
        $map = AotMap::parse('aot', null);

        self::assertFalse($map->enabled());
        self::assertFalse($map->warm());
    }

    /**
     * The default layer's own aot knob, pinned: the framework ships the
     * lane closed, both toggles spelled, so the profile's shape is part
     * of the public contract the boot reads.
     */
    public function testTheDefaultProfileDeclaresBothTogglesOff(): void
    {
        self::assertSame(
            ['enabled' => false, 'warm' => false],
            FrameworkDefaults::profile()['aot'],
        );
    }

    public function testTheEmptyMapIsTheEverythingOffBoot(): void
    {
        $map = AotMap::parse('aot', []);

        self::assertFalse($map->enabled());
        self::assertFalse($map->warm());
    }

    public function testEachToggleDefaultsOffIndependently(): void
    {
        $enabledOnly = AotMap::parse('aot', ['enabled' => true]);
        self::assertTrue($enabledOnly->enabled());
        self::assertFalse($enabledOnly->warm());

        $warmOnly = AotMap::parse('aot', ['warm' => true]);
        self::assertFalse($warmOnly->enabled());
        self::assertTrue($warmOnly->warm());
    }

    public function testNativeBooleansPassThroughUntouched(): void
    {
        $map = AotMap::parse('aot', ['enabled' => false, 'warm' => true]);

        self::assertFalse($map->enabled());
        self::assertTrue($map->warm());
    }

    #[DataProvider('provideTheTruthVocabularyFoldsCases')]
    public function testTheTruthVocabularyFolds(string $spelling): void
    {
        $map = AotMap::parse('aot', ['enabled' => $spelling]);

        self::assertTrue($map->enabled());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheTruthVocabularyFoldsCases(): iterable
    {
        yield 'the 1 spelling' => ['1'];

        yield 'the true spelling' => ['true'];

        yield 'the upper TRUE spelling' => ['TRUE'];

        yield 'the on spelling' => ['on'];

        yield 'the mixed On spelling' => ['On'];

        yield 'the yes spelling' => ['yes'];

        yield 'the upper YES spelling' => ['YES'];
    }

    #[DataProvider('provideTheFalsehoodVocabularyFoldsCases')]
    public function testTheFalsehoodVocabularyFolds(string $spelling): void
    {
        $map = AotMap::parse('aot', ['warm' => $spelling]);

        self::assertFalse($map->warm());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheFalsehoodVocabularyFoldsCases(): iterable
    {
        yield 'the 0 spelling' => ['0'];

        yield 'the false spelling' => ['false'];

        yield 'the upper FALSE spelling' => ['FALSE'];

        yield 'the off spelling' => ['off'];

        yield 'the mixed Off spelling' => ['Off'];

        yield 'the no spelling' => ['no'];

        yield 'the upper NO spelling' => ['NO'];

        yield 'the empty spelling' => [''];
    }

    public function testANonMapKnobIsRefusedWithItsType(): void
    {
        try {
            AotMap::parse('aot', 'on');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'aot' configuration must be a map of AOT keys, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListKnobIsRefusedAsAList(): void
    {
        try {
            AotMap::parse('aot', ['on']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'aot' configuration must be a map of AOT keys, list given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownKeyIsRefusedWithTheKnownList(): void
    {
        try {
            AotMap::parse('aot', ['prune' => true]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'aot' configuration carries the unknown key 'prune' (known keys: enabled, warm).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAToggleOfTheWrongTypeIsRefusedWithTheKnobNamed(): void
    {
        try {
            AotMap::parse('aot', ['enabled' => 2]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'The aot.enabled knob must be a boolean or one of the boolean string '
                . 'spellings (true/false, on/off, yes/no, 1/0), int given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMistypedFoldWordIsRefusedRatherThanReadAsOff(): void
    {
        try {
            AotMap::parse('aot', ['warm' => 'maybe']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'The aot.warm knob must be a boolean or one of the boolean string '
                . 'spellings (true/false, on/off, yes/no, 1/0), string given.',
                $definitionException->getMessage(),
            );
        }
    }
}
