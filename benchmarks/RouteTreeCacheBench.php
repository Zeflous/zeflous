<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteTreeCache;
use Zef\Framework\Exception\InvariantViolationException;

/**
 * The compiled route tree cache's economics (see `RouteTreeCache`): the
 * artifact is dumped once at build time -- the compile price, paid by the
 * rebuild tooling -- and loaded at boot without paying the tree build
 * again (the roadmap's "Compiled Cache File ... untuk booting instan di
 * produksi").
 *
 * The compile subjects are the honest baseline, not competitors: the
 * cache exists to move that cost off the boot path. Matching through a
 * loaded tree sits at the walk's flat cost -- the same claim
 * `RoutingRuntimeBench` pins for the memoised runtime, observed here from
 * the cache's own boot path. Nothing races and nothing is stat-ed: the
 * load subjects read the artifact the constructor dumped.
 */
final readonly class RouteTreeCacheBench
{
    private string $smallFile;

    private string $largeFile;

    /** @var list<array<string, mixed>> */
    private array $smallTable;

    /** @var list<array<string, mixed>> */
    private array $largeTable;

    private RadixTree $radixTree;

    public function __construct()
    {
        $directory = __DIR__ . '/../build';

        if (!is_dir($directory)) {
            mkdir($directory, 0755);
        }

        $this->smallFile = $directory . '/route-tree-cache-small.bench.php';
        $this->largeFile = $directory . '/route-tree-cache-large.bench.php';

        $this->smallTable = $this->table(15);
        $this->largeTable = $this->table(300);

        $smallCache = new RouteTreeCache($this->smallFile);
        $smallCache->dump($this->smallTable);

        $largeCache = new RouteTreeCache($this->largeFile);
        $largeCache->dump($this->largeTable);

        $this->radixTree = $largeCache->load();
    }

    #[Revs(1000)]
    public function benchCompileTheSmallTable(): void
    {
        RadixTree::compile($this->smallTable);
    }

    #[Revs(1000)]
    public function benchLoadTheSmallTable(): void
    {
        new RouteTreeCache($this->smallFile)->load();
    }

    #[Revs(100)]
    public function benchCompileTheLargeTable(): void
    {
        RadixTree::compile($this->largeTable);
    }

    #[Revs(100)]
    public function benchLoadTheLargeTable(): void
    {
        new RouteTreeCache($this->largeFile)->load();
    }

    #[Revs(1000)]
    public function benchMatchThroughTheLoadedLargeTree(): void
    {
        $this->assertResolved($this->radixTree->match('GET', '/section/299'));
    }

    /**
     * @return list<array<string, mixed>> a table of numbered section routes
     */
    private function table(int $count): array
    {
        $routes = [];

        for ($section = 0; $section < $count; ++$section) {
            $routes[] = [
                'path' => '/section/' . $section,
                'method' => ['GET'],
                'handler' => 'Section@show',
            ];
        }

        return $routes;
    }

    private function assertResolved(MatchResult $matchResult): void
    {
        if (!$matchResult->isFound()) {
            throw new InvariantViolationException('The sought route did not resolve.');
        }
    }
}
