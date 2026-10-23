<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteTableCompiler;
use Zef\Framework\Config\Routing\RouteTreeCache;
use Zef\Framework\Config\Routing\RouteTreeCacheHydration;
use Zef\Framework\Config\Routing\RouteTreeStructure;

/**
 * The tree's inspection seams: {@see RadixTree::routes()} -- the
 * compiled entries in the table's own declaration order -- and
 * {@see RadixTree::structure()} -- the node graph as plain
 * depth-first rows. The CLI analysis commands render both; these
 * tests pin the order contract (declaration, never tree insertion),
 * the zero-routing table's honest empty answer, the cache
 * round-trip's identical order, and the structure's description of
 * the compiled graph.
 *
 * @internal
 */
#[CoversClass(RadixTree::class)]
#[CoversClass(RouteTreeStructure::class)]
#[CoversClass(RouteTableCompiler::class)]
#[CoversClass(RouteTreeCache::class)]
#[CoversClass(RouteTreeCacheHydration::class)]
final class RadixTreeInspectionTest extends TestCase
{
    private string $directory;

    /** @var non-empty-string */
    private string $file;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-route-inspection-' . uniqid('', false);
        mkdir($this->directory);
        $this->file = $this->directory . '/route-tree.cache.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        rmdir($this->directory);
    }

    /**
     * The declaration order is the contract: a table whose declaration
     * order differs from the tree's insertion order (zebra declared
     * before alpha, the pattern route before its static sibling) still
     * answers its entries in the order the host spelled them.
     */
    public function testRoutesAnswersTheTableDeclarationOrder(): void
    {
        $radixTree = RadixTree::compile($this->table());

        self::assertSame(
            ['/zebra', '/alpha', '/users/{id}', '/users'],
            array_map(
                static fn (CompiledRoute $compiledRoute): string => $compiledRoute->path()->pattern(),
                $radixTree->routes(),
            ),
        );
    }

    /**
     * Named and unnamed entries ride the same list: the reverse-routing
     * index is a different seam, and the inspection list never drops
     * the unnamed half of the table.
     */
    public function testRoutesCarriesNamedAndUnnamedEntriesAlike(): void
    {
        $radixTree = RadixTree::compile($this->table());

        self::assertSame(
            ['zebra', null, 'users.show', 'users.index'],
            array_map(
                static fn (CompiledRoute $compiledRoute): ?string => $compiledRoute->name(),
                $radixTree->routes(),
            ),
        );
    }

    /**
     * The compiled fields ride unchanged: the methods the entry
     * declared in a lower-case spelling answer as the canonical sorted
     * set the compile time owns.
     */
    public function testRoutesCarriesTheCanonicalCompiledFields(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}',
                'method' => ['head', 'get'],
                'handler' => 'UserController::show',
                'name' => 'users.show',
            ],
        ]);

        self::assertSame(['GET', 'HEAD'], $radixTree->routes()[0]->methods());
        self::assertSame('UserController::show', $radixTree->routes()[0]->handler());
    }

    /**
     * The zero-routing table is valid, and its inspection answer is
     * the honest empty list -- never null, never an exception.
     */
    public function testRoutesAnswersAnEmptyListForTheZeroRoutingTable(): void
    {
        self::assertSame([], RadixTree::compile([])->routes());
    }

    /**
     * The cache round-trip answers the identical list in the identical
     * order: the artifact carries the entries in the table's own order
     * ({@see RouteTreeCache::dump()}'s table-side indexing), so a boot
     * from the cache renders exactly what a boot from the sources
     * would.
     */
    public function testTheCachedTreeAnswersTheSameRoutesInTheSameOrder(): void
    {
        $routeTreeCache = new RouteTreeCache($this->file);
        $routeTreeCache->dump($this->table());

        $rehydrated = $routeTreeCache->load();

        self::assertSame(
            ['/zebra', '/alpha', '/users/{id}', '/users'],
            array_map(
                static fn (CompiledRoute $compiledRoute): string => $compiledRoute->path()->pattern(),
                $rehydrated->routes(),
            ),
        );
        self::assertSame(
            ['zebra', null, 'users.show', 'users.index'],
            array_map(
                static fn (CompiledRoute $compiledRoute): ?string => $compiledRoute->name(),
                $rehydrated->routes(),
            ),
        );
    }

    /**
     * The structure describes the compiled graph as the engine built
     * it: the root's shared slash run, the static children in the
     * builder's insertion order (zebra declared first), and the
     * parameter edge under the pattern route's trailing run -- flat
     * depth-first rows, one per node, the parameter edge last.
     */
    public function testStructureDescribesTheCompiledGraph(): void
    {
        $radixTree = RadixTree::compile($this->table());

        self::assertSame(
            [
                ['depth' => 0, 'edge' => 'root', 'prefix' => '', 'routes' => []],
                ['depth' => 1, 'edge' => 'static', 'prefix' => '/', 'routes' => []],
                ['depth' => 2, 'edge' => 'static', 'prefix' => 'zebra', 'routes' => ['/zebra']],
                ['depth' => 2, 'edge' => 'static', 'prefix' => 'alpha', 'routes' => ['/alpha']],
                ['depth' => 2, 'edge' => 'static', 'prefix' => 'users', 'routes' => ['/users']],
                ['depth' => 3, 'edge' => 'static', 'prefix' => '/', 'routes' => []],
                ['depth' => 4, 'edge' => 'parameter', 'prefix' => '', 'routes' => ['/users/{id}']],
            ],
            $radixTree->structure(),
        );
    }

    /**
     * A table whose patterns share no leading static run still answers
     * a structure -- every pattern contributes the leading slash, so
     * the root always carries the one shared run.
     */
    public function testStructureAnswersThePatternsSettledOnEachNode(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/', 'method' => ['GET'], 'handler' => 'RootController::index'],
        ]);

        self::assertSame(
            [
                ['depth' => 0, 'edge' => 'root', 'prefix' => '', 'routes' => []],
                ['depth' => 1, 'edge' => 'static', 'prefix' => '/', 'routes' => ['/']],
            ],
            $radixTree->structure(),
        );
    }

    /**
     * @return array<mixed> the inspection table: declaration order
     *                      deliberately unlike the tree's insertion order
     */
    private function table(): array
    {
        return [
            [
                'path' => '/zebra',
                'method' => ['GET'],
                'handler' => 'ZebraController::show',
                'name' => 'zebra',
            ],
            [
                'path' => '/alpha',
                'method' => ['GET'],
                'handler' => 'AlphaController::show',
            ],
            [
                'path' => '/users/{id}',
                'method' => ['GET', 'HEAD'],
                'handler' => 'UserController::show',
                'name' => 'users.show',
            ],
            [
                'path' => '/users',
                'method' => ['GET'],
                'handler' => 'UserController::index',
                'name' => 'users.index',
            ],
        ];
    }
}
