<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Modules\ModuleEntries;
use Zef\Framework\Modules\ModuleException;

/**
 * The `modules` knob's own grammar: the whitelist is a list of non-empty
 * strings, one entry's spelling classifies by shape (the namespace
 * separator marks the class spelling, a rooted `.php` path the manifest
 * spelling), and everything else -- an associative list, a number, an
 * empty entry, a spelling that is neither shape -- is refused with the
 * entry named.
 *
 * @internal
 */
#[CoversClass(ModuleEntries::class)]
#[CoversClass(ModuleException::class)]
final class ModuleEntriesTest extends TestCase
{
    public function testTheEmptyKnobParsesToAnEmptyList(): void
    {
        self::assertSame([], ModuleEntries::parse([]));
    }

    public function testTheEntryListParsesVerbatim(): void
    {
        $entries = ['Zef\Modules\Payment\PaymentModule', '/modules/payment/module.php'];

        self::assertSame($entries, ModuleEntries::parse($entries));
    }

    public function testAnAssociativeKnobIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'modules' configuration must be a list of module entries; array arrived.",
        );

        ModuleEntries::parse(['payment' => 'Zef\Modules\Payment\PaymentModule']);
    }

    public function testANonListKnobIsRefusedWithItsType(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('must be a list of module entries; string arrived.');

        ModuleEntries::parse('payment');
    }

    public function testANonStringEntryIsRefusedWithItsIndex(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'modules' entry at index 1 is not a module spelling: int arrived.",
        );

        ModuleEntries::parse(['Zef\Modules\Payment\PaymentModule', 7]);
    }

    public function testAnEmptyEntryIsRefusedWithItsIndex(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'modules' entry at index 0 is not a module spelling: '' arrived.",
        );

        ModuleEntries::parse(['']);
    }

    #[DataProvider('provideAnEntryClassifiesByItsShapeCases')]
    public function testAnEntryClassifiesByItsShape(string $entry, string $kind): void
    {
        self::assertSame($kind, ModuleEntries::classify($entry));
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, string}>
     */
    public static function provideAnEntryClassifiesByItsShapeCases(): iterable
    {
        yield 'qualified class name' => ['Zef\Modules\Payment\PaymentModule', ModuleEntries::KIND_CLASS];

        yield 'rooted manifest path' => ['/app/modules/payment/module.php', ModuleEntries::KIND_MANIFEST];

        yield 'class spelling wins over the php suffix' => [
            'Zef\Modules\Payment\module.php',
            ModuleEntries::KIND_CLASS,
        ];
    }

    public function testANeitherShapeEntryIsRefusedWithBothSpellingsNamed(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module entry 'payment/module.php' is neither a qualified class name nor "
            . 'an absolute module.php path (a class spelling carries the '
            . "namespace separator; a manifest spelling is a rooted '.php' path).",
        );

        ModuleEntries::classify('payment/module.php');
    }

    public function testARelativePhpPathIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module entry 'module.php' is neither a qualified class name nor "
            . 'an absolute module.php path (a class spelling carries the '
            . "namespace separator; a manifest spelling is a rooted '.php' path).",
        );

        ModuleEntries::classify('module.php');
    }
}
