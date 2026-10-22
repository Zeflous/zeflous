<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The replay side of the compiled route tree cache
 * ({@see RouteTreeCache}): one question -- what tree does the exported
 * graph rehydrate into?
 *
 * `tree()` re-runs the `RouteTableRegistry` over the re-validated entries
 * (method claims, host claims and name uniqueness -- a hand-edited
 * artifact that introduces an ambiguous table is refused with the
 * engine's own messages, KDD-RC2), then rebuilds the node graph through
 * `RadixNode`'s own builder mutators without paying the tree build
 * again: the radix compression (the node splits and merges that dominate
 * compile time) is never re-run, only replayed from plain data. Static
 * children are re-keyed under the first character of their prefixes --
 * by character, the engine's own vocabulary, not by byte -- and a route
 * reference that names no carried entry fails closed.
 *
 * @internal
 */
final readonly class RouteTreeCacheHydration
{
    private RouteTreeCacheNodeFields $routeTreeCacheNodeFields;

    /**
     * @param non-empty-string $file path of the cache artifact
     */
    public function __construct(
        private string $file,
    ) {
        $this->routeTreeCacheNodeFields = new RouteTreeCacheNodeFields($file);
    }

    /**
     * Rehydrates the compiled radix tree from the exported root node and
     * the re-validated entries: the registry re-runs, then the node graph
     * replays through the node's own builder mutators.
     *
     * @param mixed               $root           the exported root node
     * @param list<CompiledRoute> $compiledRoutes the re-validated entries, indexed as the artifact references them
     *
     * @throws ConfigException when the exported graph is corrupted, or
     *                         the re-validated table is ambiguous
     */
    public function tree(mixed $root, array $compiledRoutes): RadixTree
    {
        $routeTableRegistry = new RouteTableRegistry();

        foreach ($compiledRoutes as $compiledRoute) {
            $routeTableRegistry->register($compiledRoute);
        }

        $rootFields = $this->routeTreeCacheNodeFields->narrow($root);
        $radixNode = new RadixNode($rootFields['prefix']);
        $this->hydrateNode($radixNode, $rootFields, $compiledRoutes);

        return new RadixTree($radixNode, $routeTableRegistry->namedRoutes());
    }

    /**
     * Rebuilds one node of the graph from its exported fields: static
     * children are re-keyed under the first character of their prefixes
     * (an empty static prefix, or two children sharing a first character,
     * is corruption the compiled format never contains), the parameter
     * edge is reattached, and the route references are resolved against
     * the re-validated entries, which the artifact indexes exactly as its
     * carried table orders them.
     *
     * @param array{
     *     prefix: string,
     *     static: list<mixed>,
     *     parameter: null|array<mixed>,
     *     routes: list<mixed>,
     * } $nodeFields
     * @param list<CompiledRoute> $compiledRoutes
     */
    private function hydrateNode(RadixNode $radixNode, array $nodeFields, array $compiledRoutes): void
    {
        $claimedFirstCharacters = [];

        foreach (array_keys($nodeFields['static']) as $index) {
            $childFields = $this->routeTreeCacheNodeFields->narrow($nodeFields['static'][$index]);

            if ($childFields['prefix'] === '') {
                throw new ConfigException(
                    \sprintf(
                        'The route tree cache "%s" is corrupted: a compiled node static child carries an empty prefix.',
                        $this->file,
                    ),
                );
            }

            $firstCharacter = mb_substr($childFields['prefix'], 0, 1);

            if (isset($claimedFirstCharacters[$firstCharacter])) {
                throw new ConfigException(
                    \sprintf(
                        'The route tree cache "%s" is corrupted: a compiled node carries two static children '
                        . 'under the first character "%s".',
                        $this->file,
                        $firstCharacter,
                    ),
                );
            }

            $claimedFirstCharacters[$firstCharacter] = $childFields['prefix'];
            $this->hydrateNode($radixNode->appendStaticRun($childFields['prefix']), $childFields, $compiledRoutes);
        }

        if ($nodeFields['parameter'] !== null) {
            $parameterFields = $this->routeTreeCacheNodeFields->narrow($nodeFields['parameter']);
            $parameterRadixNode = new RadixNode($parameterFields['prefix']);
            $radixNode->setParameterChild($parameterRadixNode);
            $this->hydrateNode($parameterRadixNode, $parameterFields, $compiledRoutes);
        }

        foreach ($nodeFields['routes'] as $routeId) {
            if (!\is_int($routeId)) {
                throw new ConfigException(
                    \sprintf(
                        'The route tree cache "%s" is corrupted: a compiled node references a route '
                        . 'by the non-integer "%s".',
                        $this->file,
                        get_debug_type($routeId),
                    ),
                );
            }

            if (!isset($compiledRoutes[$routeId])) {
                throw new ConfigException(
                    \sprintf(
                        'The route tree cache "%s" is corrupted: a compiled node references the route '
                        . 'entry %d the cache does not carry.',
                        $this->file,
                        $routeId,
                    ),
                );
            }

            $radixNode->addRoute($compiledRoutes[$routeId]);
        }
    }
}
