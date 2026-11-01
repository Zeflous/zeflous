<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleOrder;
use Zef\Framework\Modules\ModuleOrderBook;
use Zef\Framework\Modules\ModuleOrderRefusals;

/**
 * The boot-order fold: requirements before requirers, the declaration
 * order as the explicit tiebreak the host reads as, and every refusal the
 * ordering can meet -- a duplicate name, an unknown requirement, a closed
 * cycle spelled as the chain the walk found -- fires before a single
 * module registers.
 *
 * @internal
 */
#[CoversClass(ModuleOrder::class)]
#[CoversClass(ModuleOrderBook::class)]
#[CoversClass(ModuleOrderRefusals::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleException::class)]
final class ModuleOrderTest extends TestCase
{
    public function testRequirementsOrderBeforeTheirRequirers(): void
    {
        $ordered = ModuleOrder::ordered([
            $this->declaration('test/gamma', ['test/beta']),
            $this->declaration('test/beta', ['test/alpha']),
            $this->declaration('test/alpha'),
        ]);

        self::assertSame(
            ['test/alpha', 'test/beta', 'test/gamma'],
            $this->names($ordered),
        );
    }

    public function testIndependentModulesRideTheDeclarationOrder(): void
    {
        $ordered = ModuleOrder::ordered([
            $this->declaration('test/second'),
            $this->declaration('test/first'),
        ]);

        self::assertSame(
            ['test/second', 'test/first'],
            $this->names($ordered),
        );
    }

    public function testTheDiamondSortsWithTheDeclarationTiebreak(): void
    {
        $ordered = ModuleOrder::ordered([
            $this->declaration('test/top', ['test/left', 'test/right']),
            $this->declaration('test/left', ['test/base']),
            $this->declaration('test/right', ['test/base']),
            $this->declaration('test/base'),
        ]);

        self::assertSame(
            ['test/base', 'test/left', 'test/right', 'test/top'],
            $this->names($ordered),
        );
    }

    public function testADeclaredTwiceNameIsRefusedWithBothEntries(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module name 'test/alpha' is declared twice (entries 0 and 1); one name is one module.",
        );

        ModuleOrder::ordered([
            $this->declaration('test/alpha'),
            $this->declaration('test/alpha'),
        ]);
    }

    public function testAnUnknownRequirementIsRefusedWithTheDeclaredSet(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module 'test/beta' requires 'test/ghost', which no declared module answers (declared: test/beta).",
        );

        ModuleOrder::ordered([
            $this->declaration('test/beta', ['test/ghost']),
        ]);
    }

    public function testAnUnknownRequirementBehindAKnownOneIsStillRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module 'test/beta' requires 'test/ghost', which no declared module answers "
            . '(declared: test/beta, test/alpha).',
        );

        ModuleOrder::ordered([
            $this->declaration('test/beta', ['test/alpha', 'test/ghost']),
            $this->declaration('test/alpha'),
        ]);
    }

    public function testATwoModuleCycleIsSpelledAsTheChain(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            'The module declarations close a dependency cycle: test/a -> test/b -> test/a.',
        );

        ModuleOrder::ordered([
            $this->declaration('test/a', ['test/b']),
            $this->declaration('test/b', ['test/a']),
        ]);
    }

    public function testAThreeModuleCycleIsSpelledAsTheChain(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            'The module declarations close a dependency cycle: test/a -> test/b -> test/c -> test/a.',
        );

        ModuleOrder::ordered([
            $this->declaration('test/a', ['test/b']),
            $this->declaration('test/b', ['test/c']),
            $this->declaration('test/c', ['test/a']),
        ]);
    }

    public function testACycleSpellsTheLoopWithoutItsTail(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            'The module declarations close a dependency cycle: test/a -> test/b -> test/a.',
        );

        ModuleOrder::ordered([
            $this->declaration('test/tail', ['test/a']),
            $this->declaration('test/a', ['test/b']),
            $this->declaration('test/b', ['test/a']),
        ]);
    }

    public function testACycleWalksTheFirstOutstandingRequirementOfARequirer(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            'The module declarations close a dependency cycle: test/a -> test/b -> test/a.',
        );

        ModuleOrder::ordered([
            $this->declaration('test/a', ['test/b', 'test/c']),
            $this->declaration('test/b', ['test/a']),
            $this->declaration('test/c', ['test/a']),
        ]);
    }

    /**
     * @param list<string> $requires
     */
    private function declaration(string $name, array $requires = []): ModuleDeclaration
    {
        $class = 'Zef\Test\Stubs\\' . str_replace('/', '_', $name);

        return new ModuleDeclaration($class, new ModuleMetadata($name, '1.0.0', $requires));
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     *
     * @return list<string>
     */
    private function names(array $declarations): array
    {
        $names = [];

        foreach ($declarations as $declaration) {
            $names[] = $declaration->metadata->name;
        }

        return $names;
    }
}
