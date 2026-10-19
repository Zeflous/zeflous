<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\NamespaceRadixTree;
use Zef\Framework\Container\NamespaceScope;
use Zef\Framework\Container\NamespaceSpellings;
use Zef\Framework\Container\NamespaceTreeBuilder;
use Zef\Framework\Container\NamespaceTreeCollect;
use Zef\Framework\Container\NamespaceTreeCompression;
use Zef\Framework\Container\NamespaceTreeCounters;
use Zef\Framework\Container\NamespaceTreeIndexing;
use Zef\Framework\Container\NamespaceTreeScopes;
use Zef\Framework\Container\NamespaceTreeSealing;
use Zef\Framework\Container\NamespaceTreeStats;
use Zef\Framework\Container\RadixEdgeLabels;
use Zef\Framework\Container\RadixTreeHop;
use Zef\Framework\Container\RadixTreeNavigator;

/**
 * The sealed namespace radix tree's query behaviour: the exact-position
 * lookup, the prefix enumeration through the compressed mid-edge, the
 * nearest-ancestor scope answer, and the measurements -- the read-only
 * product the builder seals (ROADMAP area 9's namespace half).
 *
 * @internal
 */
#[CoversClass(NamespaceRadixTree::class)]
#[CoversClass(RadixTreeNavigator::class)]
#[CoversClass(RadixTreeHop::class)]
#[CoversClass(RadixEdgeLabels::class)]
#[CoversClass(NamespaceScope::class)]
#[CoversClass(NamespaceTreeIndexing::class)]
#[CoversClass(NamespaceTreeBuilder::class)]
#[CoversClass(NamespaceTreeSealing::class)]
#[CoversClass(NamespaceTreeCollect::class)]
#[CoversClass(NamespaceTreeCompression::class)]
#[CoversClass(NamespaceTreeCounters::class)]
#[CoversClass(NamespaceTreeStats::class)]
#[CoversClass(NamespaceTreeScopes::class)]
#[CoversClass(NamespaceSpellings::class)]
final class NamespaceRadixTreeTest extends TestCase
{
    public function testContainsExactAnswersOverTheIndexedPositions(): void
    {
        $tree = $this->built('app.window', 'app.house.door', 'flat', 'App\Widget\Controller');

        self::assertTrue($tree->containsExact('app.window'));
        self::assertTrue($tree->containsExact('app.house.door'));
        self::assertTrue($tree->containsExact('flat'));
        self::assertTrue($tree->containsExact('App\Widget\Controller'));
        self::assertFalse($tree->containsExact('app.house'));
        self::assertFalse($tree->containsExact('app'));
        self::assertFalse($tree->containsExact(''));
        self::assertFalse($tree->containsExact('missing.entirely'));
    }

    public function testTheTwoSeparatorsSpellOnePosition(): void
    {
        $tree = $this->built('App\Widget\Controller');

        self::assertTrue($tree->containsExact('App.Widget.Controller'));
        self::assertSame(['App\Widget\Controller'], $tree->idsUnderPrefix('App.Widget'));
        self::assertSame(['App\Widget\Controller'], $tree->idsUnderPrefix('App\Widget\\'));
    }

    public function testIdsUnderPrefixAnswersTheSubtreeWithBoundarySemantics(): void
    {
        $tree = $this->built(
            'framework.config',
            'framework.tags',
            'frameworkX.other',
            'security.headers',
            'flat',
        );

        self::assertSame(['framework.config', 'framework.tags'], $tree->idsUnderPrefix('framework'));
        self::assertSame(['framework.config', 'framework.tags'], $tree->idsUnderPrefix('framework.'));
        self::assertSame(['flat'], $tree->idsUnderPrefix('flat'));
        self::assertSame(['frameworkX.other'], $tree->idsUnderPrefix('frameworkX'));
        self::assertSame([], $tree->idsUnderPrefix('missing'));
        self::assertSame([], $tree->idsUnderPrefix('\\'));
        self::assertSame([], $tree->idsUnderPrefix(''));
    }

    public function testIdsUnderPrefixAnswersThroughTheCompressedMidEdge(): void
    {
        $tree = $this->built('a.b.c.d.one', 'a.b.c.d.two', 'a.b.other');

        // The 'a.b.c.d' chain compresses into one compound edge; the
        // prefix 'a.b.c' ends MID-EDGE over it.
        self::assertSame(['a.b.c.d.one', 'a.b.c.d.two'], $tree->idsUnderPrefix('a.b.c'));
        self::assertSame(['a.b.c.d.one', 'a.b.c.d.two'], $tree->idsUnderPrefix('a.b.c.d'));
        self::assertSame(['a.b.c.d.one', 'a.b.c.d.two', 'a.b.other'], $tree->idsUnderPrefix('a.b'));
        self::assertSame([], $tree->idsUnderPrefix('a.b.c.x'));
        self::assertSame([], $tree->idsUnderPrefix('a.b.c.d.one.deeper'));
    }

    public function testAMidEdgePrefixThatDisagreesWithTheLabelOwnsNothing(): void
    {
        // The prefix 'a.b.x' ends MID-EDGE over the compound 'a.b.c.d'
        // label and disagrees with it at the third segment: nothing
        // lies under a prefix the tree's own edges refuse -- the same
        // empty answer a missing edge gives, not the subtree the
        // half-matched edge carries.
        $tree = $this->built('a.b.c.d.one', 'a.b.c.d.two');

        self::assertSame([], $tree->idsUnderPrefix('a.b.x'));
        self::assertSame([], $tree->idsUnderPrefix('a.b.c.d.o'));
    }

    public function testTheEnumerationAnswersSortedWhateverTheInsertionOrder(): void
    {
        // The identifiers arrive unsorted: the enumeration's answer is
        // the sorted one, the determinism the locator's own callers
        // ride.
        $tree = $this->built('app.zz', 'app.mm', 'app.aa');

        self::assertSame(['app.aa', 'app.mm', 'app.zz'], $tree->idsUnderPrefix('app'));
    }

    public function testAnIdentifierThatIsAPrefixOfAnotherKeepsItsOwnPosition(): void
    {
        // 'a.b' is both an identifier and a namespace position on the
        // path to 'a.b.c': the tree indexes both -- the parent position
        // carries its own identifier instead of being absorbed into
        // the child chain.
        $tree = $this->built('a.b', 'a.b.c');

        self::assertTrue($tree->containsExact('a.b'));
        self::assertTrue($tree->containsExact('a.b.c'));
        self::assertSame(['a.b', 'a.b.c'], $tree->idsUnderPrefix('a.b'));
    }

    public function testTheLookupDescentAnswersTheFullNodeItStopsAt(): void
    {
        // The lookup's descent contract is the NODE -- its identifiers
        // AND its edges, the shape the exact-position check and any
        // deeper walk both read.
        $root = ['ids' => [], 'children' => []];
        $root = new NamespaceTreeIndexing()->insertInto($root, ['a', 'b'], 'a.b', 'a.b');

        $node = RadixTreeNavigator::descendForLookup($root, ['a', 'b']);

        self::assertSame(['a.b' => 'a.b'], $node['ids']);
        self::assertSame([], $node['children']);

        $partial = RadixTreeNavigator::descendForLookup($root, ['a', 'b', 'c']);

        self::assertSame(['a.b' => 'a.b'], $partial['ids']);
        self::assertSame([], $partial['children']);
    }

    public function testCanonicalNumericSegmentsWalkTheirOwnEdges(): void
    {
        // The tagged lane's own shape: 'app.carrier.0'...'app.carrier.9'
        // -- PHP stores each canonical-numeric segment as an int array
        // key, so the seal's compression walk reads it back in its own
        // spelling at the key-to-label boundary (the bench's discovery,
        // pinned here as the tree's own grammar).
        $ids = [];
        foreach (range(0, 9) as $index) {
            $ids[] = "app.carrier.{$index}";
        }

        $tree = $this->built(...$ids);

        foreach (range(0, 9) as $index) {
            self::assertTrue($tree->containsExact("app.carrier.{$index}"));
        }

        self::assertFalse($tree->containsExact('app.carrier'));
        self::assertSame($ids, $tree->idsUnderPrefix('app.carrier'));
        self::assertSame($ids, $tree->idsUnderPrefix('app.carrier.'));
    }

    public function testNumericSegmentsKeepTheirSegmentBoundaries(): void
    {
        // '10' and '1' are different segments even though PHP coerces
        // both spellings to int keys: the boundary semantics answer the
        // subtree under '1' without the '10'/'11' siblings.
        $tree = $this->built('app.carrier.1', 'app.carrier.10', 'app.carrier.11');

        self::assertTrue($tree->containsExact('app.carrier.1'));
        self::assertTrue($tree->containsExact('app.carrier.10'));
        self::assertSame(['app.carrier.1'], $tree->idsUnderPrefix('app.carrier.1'));
        self::assertSame(
            ['app.carrier.1', 'app.carrier.10', 'app.carrier.11'],
            $tree->idsUnderPrefix('app.carrier'),
        );
        self::assertSame([], $tree->idsUnderPrefix('app.carrier.0'));
    }

    public function testScopeOfAnswersTheNearestAncestor(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('App\Internal\Thing');
        $namespaceTreeBuilder->insert('App\Internal\Deep\Deeper');
        $namespaceTreeBuilder->insert('App\Public\Face');
        $namespaceTreeBuilder->insert('outside');
        $namespaceTreeBuilder->annotate('App\Internal', 'internal');
        $namespaceTreeBuilder->annotate('App\Internal\Deep', 'module');
        $namespaceTreeBuilder->annotate('App\Public', 'public');

        $tree = $namespaceTreeBuilder->seal();

        $thingScope = $tree->scopeOf('App\Internal\Thing');
        self::assertInstanceOf(NamespaceScope::class, $thingScope);
        self::assertSame('internal', $thingScope->kind);
        self::assertSame('App.Internal.', $thingScope->prefix);
        self::assertSame('module', $tree->scopeOf('App\Internal\Deep\Deeper')?->kind);
        self::assertSame('public', $tree->scopeOf('App\Public\Face')?->kind);
        self::assertNull($tree->scopeOf('outside'));
        self::assertNull($tree->scopeOf('App\Other\Thing'));
    }

    public function testScopeOfAnswersThroughBothSpellings(): void
    {
        $tree = $this->annotated('App\Internal', 'internal', 'App\Internal\Thing');

        self::assertSame('internal', $tree->scopeOf('App.Internal.Thing')?->kind);
    }

    public function testScopeOfGovernsTheSubtreeRootItself(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('App\Internal\Face');
        $namespaceTreeBuilder->annotate('App\Internal\Face\\', 'public');

        $tree = $namespaceTreeBuilder->seal();

        // The subtree root is governed by its own annotation -- the
        // reopen the root-inclusive semantics owes.
        self::assertSame('public', $tree->scopeOf('App\Internal\Face')?->kind);
    }

    public function testAnnotationsAreKeySorted(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->annotate('zeta', 'internal');
        $namespaceTreeBuilder->annotate('alpha', 'module');
        $namespaceTreeBuilder->annotate('middle', 'public');

        $tree = $namespaceTreeBuilder->seal();

        self::assertSame(
            ['alpha.' => 'module', 'middle.' => 'public', 'zeta.' => 'internal'],
            $tree->annotations(),
        );
    }

    public function testStatsMeasureTheCompressedShape(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('a.b.c.d.one');
        $namespaceTreeBuilder->insert('a.b.c.d.two');
        $namespaceTreeBuilder->insert('a.b.c.d.three');
        $namespaceTreeBuilder->annotate('a.b', 'internal');

        $tree = $namespaceTreeBuilder->seal();

        $stats = $tree->stats();

        self::assertSame(3, $stats['serviceIds']);
        self::assertSame(1, $stats['annotations']);
        self::assertTrue($stats['sealed']);
        self::assertSame(5, $stats['maxDepth']);
        self::assertSame(15, $stats['rawSegments']);
        // The five-segment chains compress: far fewer edges than the
        // fifteen raw segments they were built from.
        self::assertLessThan(15, $stats['edges']);
        self::assertGreaterThan(1.0, $stats['compressionRatio']);
    }

    public function testStatsMeasureTheCompressedShapeExactly(): void
    {
        // 'a.b.c.d.e' and 'a.b.f': eight raw segments over three
        // compressed edges (root -> 'a.b' -> {'c.d.e', 'f'}) -- the
        // ratio 8/3 carries a repeating decimal, so the rounding and
        // the arithmetic are both pinned by the answer.
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('a.b.c.d.e');
        $namespaceTreeBuilder->insert('a.b.f');

        $tree = $namespaceTreeBuilder->seal();

        $stats = $tree->stats();

        self::assertSame(2, $stats['serviceIds']);
        self::assertSame(4, $stats['nodes']);
        self::assertSame(3, $stats['edges']);
        self::assertSame(5, $stats['maxDepth']);
        self::assertSame(8, $stats['rawSegments']);
        self::assertSame(2.6667, $stats['compressionRatio']);
        self::assertSame(0, $stats['annotations']);
        self::assertTrue($stats['sealed']);
    }

    public function testStatsOnTheEmptyTreeAnswerTheDegenerateRatio(): void
    {
        $tree = new NamespaceTreeBuilder()->seal();

        $stats = $tree->stats();

        self::assertSame(0, $stats['serviceIds']);
        self::assertSame(0, $stats['maxDepth']);
        self::assertSame(0, $stats['rawSegments']);
        self::assertSame(1, $stats['nodes']);
        self::assertSame(0, $stats['edges']);
        self::assertSame(1.0, $stats['compressionRatio']);
        self::assertSame(0, $stats['annotations']);
    }

    /**
     * Builds a tree over the identifiers, no annotations.
     */
    private function built(string ...$ids): NamespaceRadixTree
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();

        foreach ($ids as $id) {
            $namespaceTreeBuilder->insert($id);
        }

        return $namespaceTreeBuilder->seal();
    }

    /**
     * Builds a tree over one identifier under one annotation.
     */
    private function annotated(string $prefix, string $kind, string $id): NamespaceRadixTree
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert($id);
        $namespaceTreeBuilder->annotate($prefix, $kind);

        return $namespaceTreeBuilder->seal();
    }
}
