<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The compile pipeline's product: the planned definition set with
 * everything the boot does with it after the passes agree -- the
 * multi-pass compilation's single hand-off point (ROADMAP area 8).
 *
 * The plan is what the install loop consumes, what the AOT dump renders
 * ({@see Aot\AotEntries}) and what the tags locator's read side answers
 * from -- and, since the namespace lane landed, what the sealed radix
 * tree rides ({@see NamespaceRadixTree}, built over the planned set's
 * public identifiers under the parsed scope policy) -- one product,
 * four consumers, the boundary that keeps the passes' output honest:
 * nothing reaches the container without passing through the pipeline
 * that validated it, and nothing gets dumped that did not pass the
 * same validation.
 */
final readonly class ContainerCompilePlan
{
    /**
     * @param list<ServiceDefinition> $definitions   the planned set, in declaration order
     * @param ServiceTagIndex         $tagIndex      the tags the planned set carries
     * @param list<string>            $unreferenced  the identifiers no definition
     *                                               edge names, in declaration order
     * @param NamespaceRadixTree      $namespaceTree the sealed radix tree over the
     *                                               planned set's public identifiers
     */
    public function __construct(
        public array $definitions,
        public ServiceTagIndex $tagIndex,
        public array $unreferenced,
        public NamespaceRadixTree $namespaceTree,
    ) {
    }
}
