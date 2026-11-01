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
        $this->expectExceptionMessageIsOrContains($message);

        new ModuleName($name);
    }

    /**
     * @return iterable<non-empty-string, array{string, non-empty-string}>
     */
    public static function provideRefusedNamesNameTheRuleCases(): iterable
    {
        yield 'one segment' => ['payment', "must be exactly two '/'-joined segments"];

        yield 'three segments' => ['zef/payment/extra', "must be exactly two '/'-joined segments"];

        yield 'empty spelling' => ['', "must be exactly two '/'-joined segments"];

        yield 'uppercase is refused, not normalised' => ['Zef/Payment', 'breaks the grammar'];

        yield 'dot inside a segment' => ['zef/pay.ment', 'breaks the grammar'];

        yield 'segment starting with a dash' => ['zef/-payment', 'breaks the grammar'];

        yield 'segment ending with a dash' => ['zef/payment-', 'breaks the grammar'];

        yield 'thirty-three byte segment' => [str_repeat('a', 33) . '/b', 'spans 1-32 bytes'];

        yield 'empty segment' => ['zef/', 'spans 1-32 bytes'];
    }
}
