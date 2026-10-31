<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleVersion;

/**
 * The module version grammar's own matrix: every constraint spelling the
 * manifests and metadata answers carry (the roadmap's own `^1.0` among
 * them) is accepted, and everything around the shape -- a bare number, a
 * `v` prefix, a fourth component, an empty suffix, the 32-byte ceiling --
 * is refused with the whole grammar spelled.
 *
 * @internal
 */
#[CoversClass(ModuleVersion::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleException::class)]
final class ModuleVersionTest extends TestCase
{
    #[DataProvider('provideAcceptedVersionsAnswerCases')]
    public function testAcceptedVersionsAnswer(string $version): void
    {
        self::assertSame($version, new ModuleVersion($version)->version);
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function provideAcceptedVersionsAnswerCases(): iterable
    {
        yield 'bare three-part' => ['1.0.0'];

        yield "the roadmap's own caret" => ['^1.0'];

        yield 'tilde' => ['~2.1'];

        yield 'greater or equal' => ['>=1.0.0'];

        yield 'exact with the equals sign' => ['=1.2'];

        yield 'two parts' => ['1.0'];

        yield 'multi-digit components' => ['10.20.30'];

        yield 'pre-release suffix' => ['1.0.0-beta.1'];

        yield 'build suffix' => ['1.0.0+build.7'];
    }

    #[DataProvider('provideRefusedVersionsSpellTheGrammarCases')]
    public function testRefusedVersionsSpellTheGrammar(string $version): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module version '{$version}' breaks the grammar");

        new ModuleVersion($version);
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideRefusedVersionsSpellTheGrammarCases(): iterable
    {
        yield 'empty spelling' => [''];

        yield 'bare number' => ['1'];

        yield 'the v prefix is not a constraint operator' => ['v1.0.0'];

        yield 'four components' => ['1.0.0.0'];

        yield 'space after the operator' => ['^ 1.0'];

        yield 'empty pre-release suffix' => ['1.0-'];

        yield 'letters in the numbers' => ['one.point'];

        yield 'thirty-three bytes' => ['^' . str_repeat('1.2', 11)];
    }
}
