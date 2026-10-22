<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The compiled route tree cache: the engine's fast-boot artifact (see
 * `ROADMAP.md`, Router -- "Performance & Production" -- "Compiled Cache
 * File: Ekspor struktur pohon sebagai file PHP statis untuk booting instan
 * di produksi").
 *
 * `dump()` compiles a route table through the engine's own front doors --
 * one `CompiledRoute::fromArray()` per entry, then `RouteTreeBuilder` and
 * the `RadixTree` constructor, exactly the orchestration of
 * `RadixTree::compile()` -- because the export needs the entry-to-route
 * identity map alongside the built tree; the round-trip tests pin the
 * dumped-and-loaded tree against `RadixTree::compile()` so the two
 * orchestrations cannot drift apart. `load()` then rehydrates the tree
 * WITHOUT paying the build again: the radix compression (the node splits
 * and merges that dominate compile time) is never re-run, only replayed
 * from plain data.
 *
 * The artifact is a plain PHP file -- `var_export()` output behind a
 * `return`, the cheapest deserialisation PHP itself can do, with zero
 * dependencies and no format to version (the `ConfigCache` design). It
 * carries three fields:
 *
 * - `signature` -- the sha256 of the route table it was dumped from, the
 *   revision identity below;
 * - `routes` -- the raw Standard Route Schema entries in declaration
 *   order, the data `CompiledRoute::fromArray()` re-validates on load;
 * - `root` -- the node graph: every node is
 *   `['prefix' => string, 'static' => list<node>, 'parameter' => node|null,
 *   'routes' => list<int>]`, where the integers index into `routes` and the
 *   static children are stored as a LIST because their first-character
 *   keys are derived on rehydration, never stored. The root node and every
 *   parameter node carry an empty prefix; a static child never does (its
 *   first character keys it under its parent).
 *
 * The trust boundary follows the configuration cache's KDD-6 decision:
 * mtime-free, and a rebuild is an explicit tooling act (`dump()` again),
 * never a heuristic the runtime guesses at. `load()` therefore replays
 * what is cheap and judges what could be corrupted -- every entry goes
 * through `CompiledRoute::fromArray()` (the same fail-closed net), and the
 * `RouteTableRegistry` re-runs (method claims, host claims and name
 * uniqueness), so a hand-edited artifact that introduces an ambiguous
 * table is refused with the engine's own messages -- while the node graph
 * itself is trusted exactly as far as its shape narrowing sees: a torn
 * write (a `ParseError`), a truncated or non-array artifact, a node that
 * is not the cache's format, a route reference that names no carried
 * entry, or two static children under one first character all fail closed
 * with the artifact path in the message.
 *
 * The signature is the roadmap's "Revision-Aware Compile" as a tooling
 * concern: the runtime NEVER compares it (that would read the sources the
 * cache exists to skip), the rebuild tooling does -- compute
 * `signatureOf()` over the freshly loaded route table, compare it with
 * `signature()`, and re-dump on a mismatch. The recorded signature is not
 * a checksum of the artifact's own content: editing it does not corrupt
 * the artifact, it only changes what the rebuild tooling sees.
 *
 * The lane's four single-question folds carry the mechanics: the export
 * ({@see RouteTreeCacheExport}, the dump side), the artifact read
 * ({@see RouteTreeCacheArtifact}, the file-level narrowing), the node
 * format ({@see RouteTreeCacheNodeFields}) and the graph replay
 * ({@see RouteTreeCacheHydration}). This facade is the boot pipeline's
 * one public surface over them.
 *
 * @api
 */
final readonly class RouteTreeCache
{
    /**
     * @param non-empty-string $file path of the cache artifact (created by dump, read by load)
     */
    public function __construct(
        private string $file,
    ) {
    }

    /**
     * Compiles the route table and writes the cache artifact, replacing
     * whatever the file carried before, and returns the compiled tree so
     * the build tooling keeps answering from the compilation it just paid
     * for.
     *
     * @param array<mixed> $routes a list of Standard Route Schema entries
     *
     * @throws ConfigException when the table is not a list, an entry is
     *                         invalid, the artifact directory does not
     *                         exist, or the artifact cannot be written
     */
    public function dump(array $routes): RadixTree
    {
        if (!array_is_list($routes)) {
            throw new ConfigException('The route table must be a list of route entries.');
        }

        $compiledRoutes = [];

        foreach (array_keys($routes) as $index) {
            $compiledRoutes[] = CompiledRoute::fromArray($routes[$index]);
        }

        $routeTreeBuilder = new RouteTreeBuilder();
        $radixNode = $routeTreeBuilder->build($compiledRoutes);
        $radixTree = new RadixTree($radixNode, $routeTreeBuilder->registry()->namedRoutes());

        $directory = \dirname($this->file);

        if (!is_dir($directory)) {
            throw new ConfigException(
                \sprintf('The route tree cache directory "%s" does not exist.', $directory),
            );
        }

        $bytes = file_put_contents(
            $this->file,
            new RouteTreeCacheExport()->content($routes, $compiledRoutes, $radixNode),
        );

        if (!\is_int($bytes)) {
            throw new ConfigException(
                \sprintf('The route tree cache "%s" could not be written.', $this->file),
            );
        }

        return $radixTree;
    }

    /**
     * Whether a cache artifact is present for this path -- the boot
     * pipeline's fast-path probe: an existing cache is loaded (and never
     * rebuilt), a missing one is compiled and dumped by the build tooling.
     */
    public function exists(): bool
    {
        return is_file($this->file);
    }

    /**
     * Rehydrates the compiled radix tree from the cache artifact: the
     * entries are re-validated, the registry re-runs, and the node graph
     * is replayed -- the tree build itself is never paid again.
     *
     * @throws ConfigException when the artifact is missing, is not valid
     *                         PHP, does not return the cache format, or
     *                         carries a corrupted table or node graph
     */
    public function load(): RadixTree
    {
        $artifact = new RouteTreeCacheArtifact($this->file)->read();

        $compiledRoutes = [];

        foreach (array_keys($artifact['routes']) as $index) {
            $compiledRoutes[] = CompiledRoute::fromArray($artifact['routes'][$index]);
        }

        return new RouteTreeCacheHydration($this->file)->tree($artifact['root'], $compiledRoutes);
    }

    /**
     * The route table signature recorded in the artifact -- the value the
     * rebuild tooling compares `signatureOf()` against to decide whether
     * the routes changed since the last dump. Reading it never touches the
     * sources: it is the artifact's own record.
     *
     * @return non-empty-string
     *
     * @throws ConfigException when the artifact is missing or corrupted
     */
    public function signature(): string
    {
        return new RouteTreeCacheArtifact($this->file)->read()['signature'];
    }

    /**
     * The signature of a route table -- the revision identity `dump()`
     * records: a deterministic sha256 over the table's plain export, so
     * the same declaration list always carries the same signature and any
     * change to it does not.
     *
     * @param array<mixed> $routes a list of Standard Route Schema entries
     *
     * @return non-empty-string
     */
    public static function signatureOf(array $routes): string
    {
        return RouteTreeCacheExport::signatureOf($routes);
    }
}
