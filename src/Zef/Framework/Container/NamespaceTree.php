<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace tree's build pass (the reference container's
 * `RadixTreeCompilerPass`, adapted to the pipeline stage the compile
 * lane already runs): the one spelling of the step that turns a
 * planned definition set and the parsed scope policy into the sealed
 * radix tree the boot's read side answers from.
 *
 * Every planned identifier is indexed except the container's own
 * machinery -- the `@`-prefixed ids (`@decor:`, `@contextual:`,
 * `@value:`) are internal rewiring, never part of the public namespace
 * -- and the policy's scope annotations ride along before the seal.
 * Both callers share the one spelling: the compile lane passing the
 * pipeline's planned set, and the artifact lane passing the baked
 * entries' identifiers ({@see AotBootLane} -- the digest guarantees the
 * knob the artifact was dumped under is the knob the boot parsed, so
 * the two trees are the same tree by construction).
 */
final readonly class NamespaceTree
{
    /**
     * Builds the sealed radix tree over a planned identifier set under
     * the parsed scope policy.
     *
     * @param list<string>  $ids           the planned identifiers, machinery included
     *                                     (the `@`-prefixed spellings are skipped
     *                                     here, the one spelling of the exemption)
     * @param NamespacesMap $namespacesMap the parsed scope policy
     */
    public static function sealed(array $ids, NamespacesMap $namespacesMap): NamespaceRadixTree
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();

        foreach ($ids as $id) {
            if (str_starts_with($id, '@')) {
                continue;
            }

            $namespaceTreeBuilder->insert($id);
        }

        foreach ($namespacesMap->scopes() as $prefix => $kind) {
            $namespaceTreeBuilder->annotate($prefix, $kind);
        }

        return $namespaceTreeBuilder->seal();
    }
}
