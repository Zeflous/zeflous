<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\ContainerMode;
use Zef\Framework\FrameworkDefaults;

/**
 * @internal
 */
#[CoversClass(ContainerMode::class)]
#[CoversClass(FrameworkDefaults::class)]
final class ContainerModeTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        putenv('APP_ENV');
    }

    public function testTheAbsentKnobIsTheEnvironmentDerivedVerdict(): void
    {
        $this->withoutEnvironment();

        $mode = ContainerMode::parse('container', null);

        self::assertTrue($mode->isProduction());
        self::assertFalse($mode->isDevelopment());
    }

    public function testTheEmptyMapIsTheEnvironmentDerivedVerdict(): void
    {
        $this->withoutEnvironment();

        $mode = ContainerMode::parse('container', []);

        self::assertTrue($mode->isProduction());
    }

    /**
     * The default layer's own container knob, pinned: the framework
     * ships environment-bound (`auto`), never mode-bound -- the same
     * everything-off posture every sibling knob carries.
     */
    public function testTheDefaultProfileDeclaresTheAutoMode(): void
    {
        self::assertSame(
            ['mode' => 'auto'],
            FrameworkDefaults::profile()['container'],
        );
    }

    public function testTheExplicitNullModeIsTheEnvironmentDerivedVerdict(): void
    {
        $this->withoutEnvironment();

        $mode = ContainerMode::parse('container', ['mode' => null]);

        self::assertTrue($mode->isProduction());
    }

    public function testDevelopmentAnswersItsOwnPosture(): void
    {
        $mode = ContainerMode::parse('container', ['mode' => 'development']);

        self::assertTrue($mode->isDevelopment());
        self::assertFalse($mode->isProduction());
    }

    public function testProductionAnswersItsOwnPosture(): void
    {
        $mode = ContainerMode::parse('container', ['mode' => 'production']);

        self::assertTrue($mode->isProduction());
        self::assertFalse($mode->isDevelopment());
    }

    #[DataProvider('provideTheModeSpellingsFoldCases')]
    public function testTheModeSpellingsFold(string $spelling, bool $expectedDevelopment): void
    {
        $mode = ContainerMode::parse('container', ['mode' => $spelling]);

        self::assertSame($expectedDevelopment, $mode->isDevelopment());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function provideTheModeSpellingsFoldCases(): iterable
    {
        yield 'uppercase development' => ['DEVELOPMENT', true];

        yield 'mixed-case development' => ['Development', true];

        yield 'uppercase production' => ['PRODUCTION', false];

        yield 'mixed-case production' => ['ProDucTion', false];
    }

    #[DataProvider('provideTheAutoEnvironmentVocabularyAnswersDevelopmentCases')]
    public function testTheAutoEnvironmentVocabularyAnswersDevelopment(string $environment): void
    {
        putenv('APP_ENV=' . $environment);

        $mode = ContainerMode::parse('container', ['mode' => 'auto']);

        self::assertTrue($mode->isDevelopment());
    }

    /** @return iterable<string, array{string}> */
    public static function provideTheAutoEnvironmentVocabularyAnswersDevelopmentCases(): iterable
    {
        yield 'local' => ['local'];

        yield 'development' => ['development'];

        yield 'dev' => ['dev'];

        yield 'test' => ['test'];

        yield 'testing' => ['testing'];

        yield 'uppercase LOCAL' => ['LOCAL'];

        yield 'mixed-case Dev' => ['Dev'];

        yield 'mixed-case TeStInG' => ['TeStInG'];
    }

    #[DataProvider('provideTheAutoEnvironmentFoldIsFailClosedCases')]
    public function testTheAutoEnvironmentFoldIsFailClosed(string $environment): void
    {
        putenv('APP_ENV=' . $environment);

        $mode = ContainerMode::parse('container', ['mode' => 'auto']);

        self::assertTrue($mode->isProduction());
    }

    /** @return iterable<string, array{string}> */
    public static function provideTheAutoEnvironmentFoldIsFailClosedCases(): iterable
    {
        yield 'production' => ['production'];

        yield 'staging' => ['staging'];

        yield 'prod' => ['prod'];

        yield 'an empty value' => [''];

        yield 'a misspelled dev' => ['devv'];
    }

    public function testTheAutoSpellingWithoutAnyEnvironmentAnswersProduction(): void
    {
        $this->withoutEnvironment();

        $mode = ContainerMode::parse('container', ['mode' => 'auto']);

        self::assertTrue($mode->isProduction());
    }

    public function testAnExplicitModeBeatsTheEnvironment(): void
    {
        putenv('APP_ENV=production');

        $mode = ContainerMode::parse('container', ['mode' => 'development']);

        self::assertTrue($mode->isDevelopment());
    }

    public function testTheExplicitProductionModeBeatsTheDevelopmentEnvironment(): void
    {
        putenv('APP_ENV=local');

        $mode = ContainerMode::parse('container', ['mode' => 'production']);

        self::assertTrue($mode->isProduction());
    }

    public function testANonMapSourceRefuses(): void
    {
        $this->withoutEnvironment();

        try {
            ContainerMode::parse('container', 'development');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'container' configuration must be a map of container keys, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownKeyRefusesWithTheKnownList(): void
    {
        $this->withoutEnvironment();

        try {
            ContainerMode::parse('container', ['debug' => true]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'container' configuration carries the unknown key 'debug' (known keys: mode).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListSourceRefusesAsAnUnknownKey(): void
    {
        $this->withoutEnvironment();

        try {
            ContainerMode::parse('container', ['development']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'container' configuration carries the unknown key '0' (known keys: mode).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringModeRefuses(): void
    {
        $this->withoutEnvironment();

        try {
            ContainerMode::parse('container', ['mode' => true]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'container.mode' configuration must be a string, bool given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownSpellingRefusesWithTheVocabulary(): void
    {
        $this->withoutEnvironment();

        try {
            ContainerMode::parse('container', ['mode' => 'staging']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'container.mode' configuration must be one of 'development', 'production', 'auto' "
                . "(case-insensitive); 'staging' given.",
                $definitionException->getMessage(),
            );
        }
    }

    private function withoutEnvironment(): void
    {
        putenv('APP_ENV');
    }
}
