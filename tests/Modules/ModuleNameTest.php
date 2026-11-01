<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleName;

/**
 * The module name grammar's own matrix: every accepted `vendor/name`
 * spelling answers, the two-segment shape is the only shape, the 32-byte
 * segment ceiling bites on both sides of 32, and the canonical lowercase
 * spelling is enforced rather than normalised -- the name appears in
 * refusals, in configuration keys and in requires lists, and one spelling
 * is the only way all three agree.
 *
 * @internal
 */
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleException::class)]
final class ModuleNameTest extends TestCase
{
    #[DataProvider('provideAcceptedNamesAnswerCases')]
    public function testAcceptedNamesAnswer(string $name, string $configKey): void
    {
        $moduleName = new ModuleName($name);

        self::assertSame($name, $moduleName->name);
        self::assertSame($configKey, ModuleName::configKey($name));
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    public static function provideAcceptedNamesAnswerCases(): iterable
    {
        yield "the roadmap's own spelling" => ['zef/payment', 'zef.payment'];

        yield 'single letters' => ['a/b', 'a.b'];

        yield 'dashes inside segments' => ['my-app/core-svc', 'my-app.core-svc'];

        yield 'underscores inside segments' => ['my_app/core_svc', 'my_app.core_svc'];

        yield 'digits at the edges' => ['v2/api2', 'v2.api2'];

        $head = str_repeat('a', 32);

        yield 'thirty-two byte segments' => ["{$head}/{$head}", "{$head}.{$head}"];
    }

    #[DataProvider('provideRefusedNamesNameTheRuleCases')]
    public function testRefusedNamesNameTheRule(string $name, string $message): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs($message);

        new ModuleName($name);
    }

    /**
     * Every refusal is pinned to its whole sentence -- the grammar's
     * contract with its reader -- including the two multibyte arms where
     * the character-wise ceiling and the byte-wise ceiling disagree: a
     * segment that passes the ceiling as characters but fails it as bytes
     * must still be refused by the GRAMMAR arm (not swapped onto the
     * ceiling arm), and a segment beyond the ceiling in characters
     * spells its own character count.
     *
     * @return iterable<non-empty-string, array{string, non-empty-string}>
     */
    public static function provideRefusedNamesNameTheRuleCases(): iterable
    {
        $twoSegments = "The module name '%s' must be exactly two '/'-joined segments "
            . "(the 'vendor/name' spelling, e.g. 'zef/payment').";
        $ceiling = "The module name segment '%s' is %d bytes; a segment "
            . "spans 1-32 bytes (name: '%s').";
        $grammar = "The module name segment '%s' breaks the grammar: 1-32 bytes, "
            . "starting and ending with [a-z0-9], with '-' and '_' allowed inside "
            . "(name: '%s').";

        $tooLong = str_repeat('a', 33);
        $byteBoundary = str_repeat('a', 30) . 'éé';
        $wideOverflow = str_repeat('é', 33);

        yield 'one segment' => ['payment', \sprintf($twoSegments, 'payment')];

        yield 'three segments' => ['zef/payment/extra', \sprintf($twoSegments, 'zef/payment/extra')];

        yield 'empty spelling' => ['', \sprintf($twoSegments, '')];

        yield 'uppercase is refused, not normalised' => [
            'Zef/Payment',
            \sprintf($grammar, 'Zef', 'Zef/Payment'),
        ];

        yield 'dot inside a segment' => [
            'zef/pay.ment',
            \sprintf($grammar, 'pay.ment', 'zef/pay.ment'),
        ];

        yield 'segment starting with a dash' => [
            'zef/-payment',
            \sprintf($grammar, '-payment', 'zef/-payment'),
        ];

        yield 'segment ending with a dash' => [
            'zef/payment-',
            \sprintf($grammar, 'payment-', 'zef/payment-'),
        ];

        yield 'thirty-three byte segment' => [
            "{$tooLong}/b",
            \sprintf($ceiling, $tooLong, 33, "{$tooLong}/b"),
        ];

        yield 'empty segment' => ['zef/', \sprintf($ceiling, '', 0, 'zef/')];

        yield 'thirty-two characters but thirty-four bytes is refused by the grammar' => [
            "zef/{$byteBoundary}",
            \sprintf($grammar, $byteBoundary, "zef/{$byteBoundary}"),
        ];

        yield 'a wide segment beyond the ceiling spells its character count' => [
            "x/{$wideOverflow}",
            \sprintf($ceiling, $wideOverflow, 33, "x/{$wideOverflow}"),
        ];
    }
}
