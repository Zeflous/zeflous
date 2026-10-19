<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\CrossScopeBudget;
use Zef\Framework\Container\CrossScopeEdgeLedger;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\NamespaceBuiltRefusals;
use Zef\Framework\Container\NamespaceGuardedScopes;
use Zef\Framework\Container\NamespaceScopeEdges;
use Zef\Framework\Container\NamespaceScopes;
use Zef\Framework\Container\NamespacesMap;
use Zef\Framework\Container\NamespaceTree;
use Zef\Test\Support\Container\Greeter;

/**
 * The namespace scope enforcement walk's behaviour: the internal hard
 * deny, the module budget's distinct-target accounting, and the
 * exemptions the walk owes the machinery (ROADMAP area 9's namespace
 * half -- the boot-time policy gate over the dependency graph).
 *
 * @internal
 */
#[CoversClass(NamespaceScopes::class)]
#[CoversClass(NamespaceScopeEdges::class)]
#[CoversClass(CrossScopeBudget::class)]
#[CoversClass(NamespaceGuardedScopes::class)]
#[CoversClass(NamespaceBuiltRefusals::class)]
#[CoversClass(CrossScopeEdgeLedger::class)]
#[CoversClass(NamespaceTree::class)]
final class NamespaceScopesTest extends TestCase
{
    public function testTheUngovernedGraphPasses(): void
    {
        $this->enforce([
            new InstanceDefinition('app.outside', Greeter::class, [['service' => 'app.target']]),
            new InstanceDefinition('app.target', Greeter::class),
        ], ['scopes' => []]);

        $this->expectNotToPerformAssertions(); // the ungoverned graph is the pass the empty policy owes
    }

    public function testAnInternalTargetFromOutsideTheSubtreeIsRefused(): void
    {
        try {
            $this->enforce([
                new InstanceDefinition('app.outside', Greeter::class, [['service' => 'App\Internal\Thing']]),
                new InstanceDefinition('App\Internal\Thing', Greeter::class),
            ], ['scopes' => ['App\Internal\\' => 'internal']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Namespace scope violation: service 'app.outside' references internal service "
                . "'App\\Internal\\Thing' from outside the guarded namespace 'App.Internal.'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInternalTargetFromInsideTheSubtreePasses(): void
    {
        $this->enforce([
            new InstanceDefinition('App\Internal\Friend', Greeter::class, [['service' => 'App\Internal\Thing']]),
            new InstanceDefinition('App\Internal\Thing', Greeter::class),
        ], ['scopes' => ['App\Internal\\' => 'internal']]);

        $this->expectNotToPerformAssertions(); // the subtree's own members may always reference each other
    }

    public function testAnInnerPublicAnnotationReopensAnOuterInternalSubtree(): void
    {
        $this->enforce([
            new InstanceDefinition('app.outside', Greeter::class, [['service' => 'App\Internal\Face']]),
            new InstanceDefinition('App\Internal\Face', Greeter::class),
        ], [
            'scopes' => [
                'App\Internal\\' => 'internal',
                'App\Internal\Face\\' => 'public',
            ],
        ]);

        $this->expectNotToPerformAssertions(); // the nearest annotation wins: the inner face is open
    }

    public function testAPubliclyAnnotatedTargetIsOpenToEveryone(): void
    {
        $this->enforce([
            new InstanceDefinition('app.outside', Greeter::class, [['service' => 'store.open']]),
            new InstanceDefinition('store.open', Greeter::class),
        ], ['scopes' => ['store' => 'public']]);

        $this->expectNotToPerformAssertions(); // the public kind is the open default, spelled
    }

    public function testAModuleTargetWithAZeroBudgetIsRefused(): void
    {
        try {
            $this->enforce([
                new InstanceDefinition('app.outside', Greeter::class, [['service' => 'mod.thing']]),
                new InstanceDefinition('mod.thing', Greeter::class),
            ], ['scopes' => ['mod' => 'module']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Namespace scope violation: service 'app.outside' references module-scoped service "
                . "'mod.thing' while max_cross_scope_refs is 0.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheModuleBudgetCountsDistinctTargetsPerPair(): void
    {
        $planned = [
            new InstanceDefinition('app.outside', Greeter::class, [
                ['service' => 'mod.one'],
                ['service' => 'mod.two'],
            ]),
            new InstanceDefinition('mod.one', Greeter::class),
            new InstanceDefinition('mod.two', Greeter::class),
            new InstanceDefinition('mod.three', Greeter::class),
        ];

        // Budget two: the two distinct targets spend it exactly.
        $this->enforce($planned, ['scopes' => ['mod' => 'module'], 'max_cross_scope_refs' => 2]);

        try {
            // The third distinct target exceeds it.
            $this->enforce([
                ...$planned,
                new InstanceDefinition('app.watcher', Greeter::class, [['service' => 'mod.three']]),
            ], ['scopes' => ['mod' => 'module'], 'max_cross_scope_refs' => 2]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Namespace scope violation: cross-scope references into '=>mod' exceed "
                . 'the limit (2).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheSameTargetReferencedTwiceSpendsOneSlot(): void
    {
        $this->enforce([
            new InstanceDefinition('app.first', Greeter::class, [['service' => 'mod.thing']]),
            new InstanceDefinition('app.second', Greeter::class, [['service' => 'mod.thing']]),
            new InstanceDefinition('mod.thing', Greeter::class),
        ], ['scopes' => ['mod' => 'module'], 'max_cross_scope_refs' => 1]);

        $this->expectNotToPerformAssertions(); // the distinct-target accounting dedups the repeat
    }

    public function testThePairIsGroupedByTheConsumersOwnGuardedPrefix(): void
    {
        // Two guarded consumers reach into the same module subtree: each
        // pair ('A=>mod', 'B=>mod') spends its own budget.
        $this->enforce([
            new InstanceDefinition('aaa.first', Greeter::class, [['service' => 'mod.one']]),
            new InstanceDefinition('bbb.second', Greeter::class, [['service' => 'mod.two']]),
            new InstanceDefinition('mod.one', Greeter::class),
            new InstanceDefinition('mod.two', Greeter::class),
        ], [
            'scopes' => ['aaa' => 'module', 'bbb' => 'module', 'mod' => 'module'],
            'max_cross_scope_refs' => 1,
        ]);

        $this->expectNotToPerformAssertions(); // each guarded prefix carries its own budget
    }

    public function testTheMachineryConsumersAreExempt(): void
    {
        // The decoration/contextual machinery's edges are the boot's own
        // surgery: a '@'-prefixed consumer never reaches the policy walk.
        $this->enforce([
            new InstanceDefinition('@decor:app.outside:base', Greeter::class, [['service' => 'App\Internal\Thing']]),
            new InstanceDefinition('App\Internal\Thing', Greeter::class),
        ], ['scopes' => ['App\Internal\\' => 'internal']]);

        $this->expectNotToPerformAssertions(); // the machinery consumer is exempt by construction
    }

    public function testTheMachineryExemptionSkipsOnlyTheMachinery(): void
    {
        // A machinery consumer FIRST, a violating consumer after it: the
        // exemption is the skip of one entry, never the end of the walk.
        try {
            $this->enforce([
                new InstanceDefinition('@decor:app.outside:base', Greeter::class, [['service' => 'mod.thing']]),
                new InstanceDefinition('app.bad', Greeter::class, [['service' => 'mod.thing']]),
                new InstanceDefinition('mod.thing', Greeter::class),
            ], ['scopes' => ['mod' => 'module']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Namespace scope violation: service 'app.bad' references module-scoped service "
                . "'mod.thing' while max_cross_scope_refs is 0.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testEachGuardedPrefixPairCarriesItsOwnBudget(): void
    {
        // The pair is spelled consumer-prefix => target-prefix, and the
        // two pairs here ('a=>bc' and 'ab=>c') are distinct budgets of
        // one target each: the nesting that shares no budget with its
        // parent, whatever the pair spellings' own segments share.
        $this->enforce([
            new InstanceDefinition('a.x', Greeter::class, [['service' => 'bc.one']]),
            new InstanceDefinition('ab.y', Greeter::class, [['service' => 'c.two']]),
            new InstanceDefinition('bc.one', Greeter::class),
            new InstanceDefinition('c.two', Greeter::class),
        ], [
            'scopes' => ['a' => 'module', 'ab' => 'module', 'bc' => 'module', 'c' => 'module'],
            'max_cross_scope_refs' => 1,
        ]);

        $this->expectNotToPerformAssertions(); // each prefix pair spends its own single slot
    }

    public function testTheMachineryTargetsAreUngoverned(): void
    {
        // A declared consumer referencing '@value:' machinery: the
        // machinery never enters the public tree, so no annotation
        // governs it.
        $this->enforce([
            new InstanceDefinition('App\Internal\Thing', Greeter::class, [['service' => '@value:app.key']]),
            new InstanceDefinition('@value:app.key', Greeter::class),
        ], ['scopes' => ['App\Internal\\' => 'internal']]);

        $this->expectNotToPerformAssertions(); // the machinery target is ungoverned by construction
    }

    /**
     * Runs the enforcement walk over a planned set under a raw knob.
     *
     * @param list<InstanceDefinition> $planned
     * @param array<string, mixed>     $knob
     */
    private function enforce(array $planned, array $knob): void
    {
        $container = new Container();
        $namespacesMap = NamespacesMap::parse('namespaces', $knob);
        $ids = [];

        foreach ($planned as $definition) {
            $ids[] = $definition->id;
        }

        $tree = NamespaceTree::sealed($ids, $namespacesMap);

        NamespaceScopes::enforce($planned, $container, $tree, $namespacesMap);
    }
}
