<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleMetadata;

/**
 * The declaration's own spell-check: the class must be a qualified class
 * name (the namespace separator is part of the spelling), the metadata
 * rides verbatim, and the schema path is a wither's refinement -- the
 * declaration is immutable, a rewrite answers a new one.
 *
 * @internal
 */
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleException::class)]
final class ModuleDeclarationTest extends TestCase
{
    public function testTheDeclarationAnswersItsFields(): void
    {
        $moduleMetadata = new ModuleMetadata('zef/payment', '^1.0');
        $moduleDeclaration = new ModuleDeclaration('Zef\Modules\Payment\PaymentModule', $moduleMetadata);

        self::assertSame('Zef\Modules\Payment\PaymentModule', $moduleDeclaration->class);
        self::assertSame($moduleMetadata, $moduleDeclaration->metadata);
        self::assertNull($moduleDeclaration->configSchema);
    }

    public function testAnUnqualifiedClassSpellingIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module class spelling 'PaymentModule' must be a qualified");

        new ModuleDeclaration('PaymentModule', new ModuleMetadata('zef/payment', '^1.0'));
    }

    public function testAnEmptyClassSpellingIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module class spelling '' must be a qualified");

        new ModuleDeclaration('', new ModuleMetadata('zef/payment', '^1.0'));
    }

    public function testTheSchemaPathRidesTheWither(): void
    {
        $moduleDeclaration = new ModuleDeclaration(
            'Zef\Modules\Payment\PaymentModule',
            new ModuleMetadata('zef/payment', '^1.0'),
        );

        $resolved = $moduleDeclaration->withConfigSchema('/modules/payment/config/schema.php');

        self::assertNull($moduleDeclaration->configSchema);
        self::assertSame('/modules/payment/config/schema.php', $resolved->configSchema);
        self::assertSame($moduleDeclaration->class, $resolved->class);
        self::assertSame($moduleDeclaration->metadata, $resolved->metadata);
    }
}
