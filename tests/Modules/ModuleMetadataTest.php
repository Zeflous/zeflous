<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleRequires;
use Zef\Framework\Modules\ModuleVersion;

/**
 * The module identity's construction-time honesty: a valid metadata answers
 * its normalized fields, an empty requires list is the default, and every
 * malformed spelling -- the name's, the version's, a duplicated
 * requirement, the self-requirement, a non-list -- is refused before any
 * malformed identity can ride into the ordering fold.
 *
 * @internal
 */
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleRequires::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleVersion::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleException::class)]
final class ModuleMetadataTest extends TestCase
{
    public function testTheIdentityAnswersItsFields(): void
    {
        $moduleMetadata = new ModuleMetadata('zef/payment', '^1.0', ['zef/events']);

        self::assertSame('zef/payment', $moduleMetadata->name);
        self::assertSame('^1.0', $moduleMetadata->version);
        self::assertSame(['zef/events'], $moduleMetadata->requires);
    }

    public function testTheRequiresListDefaultsToEmpty(): void
    {
        $moduleMetadata = new ModuleMetadata('zef/payment', '^1.0');

        self::assertSame([], $moduleMetadata->requires);
    }

    public function testAVersionlessNameIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module name 'Payment' must be");

        new ModuleMetadata('Payment', '^1.0');
    }

    public function testAMalformedVersionIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module version 'one' breaks the grammar");

        new ModuleMetadata('zef/payment', 'one');
    }

    public function testARequirementThatIsNotANameIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module name 'alpha' must be exactly two");

        new ModuleMetadata('zef/payment', '^1.0', ['alpha']);
    }

    public function testANonStringRequirementIsRefusedWithItsType(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('is not a module name spelling: int arrived');

        new ModuleMetadata('zef/payment', '^1.0', [42]);
    }

    public function testAnAssociativeRequiresListIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('must be a list of module names; array arrived');

        new ModuleMetadata('zef/payment', '^1.0', ['zef/events' => 'zef/events']);
    }

    public function testADuplicatedRequirementIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module requires list names 'zef/events' twice; a requirement is declared once.",
        );

        new ModuleMetadata('zef/payment', '^1.0', ['zef/events', 'zef/events']);
    }

    public function testASelfRequirementIsRefusedAsTheCycleOfOne(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module 'zef/payment' cannot require itself; a self-requirement is a cycle of one.",
        );

        new ModuleMetadata('zef/payment', '^1.0', ['zef/payment']);
    }
}
