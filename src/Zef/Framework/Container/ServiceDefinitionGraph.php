<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The boot-time validation of a definition set's reference graph.
 *
 * Two failures are surfaced before a single service is resolved -- the
 * roadmap's "detected at boot, not at runtime" discipline:
 *
 * - an *unknown target*: a declared reference names neither another
 *   definition nor a service the container already carries (collected by
 *   {@see DefinitionEdges});
 * - a *circular chain*: definitions referencing each other in a loop
 *   (directly or through intermediaries), reported with the walked chain.
 *
 * Only definition-to-definition edges can cycle: an edge into a
 * closure-registered service is a leaf whose own behaviour is the host
 * application's responsibility, exactly as it is without the declarative
 * format.
 */
final readonly class ServiceDefinitionGraph
{
    /**
     * @param list<ServiceDefinition> $definitions
     */
    public static function validate(array $definitions, Container $container): void
    {
        self::assertAcyclic(DefinitionEdges::collect($definitions, $container));
    }

    /**
     * @param array<string, list<string>> $edges
     */
    private static function assertAcyclic(array $edges): void
    {
        $settled = [];

        foreach (array_keys($edges) as $id) {
            self::visit($id, [], $edges, $settled);
        }
    }

    /**
     * Depth-first walk of one definition's edges, carrying the walked chain
     * so a cycle can be reported with the path that closed it.
     *
     * The early return is the walk's settled memo: a performance guard, not
     * a correctness branch -- re-visiting a settled node recomputes the
     * identical acyclic verdict, so the mutation of removing it is
     * value-equivalent and answered with a documented, method-scoped
     * ReturnRemoval ignore in `infection.json5` (the cycle detector's chain
     * test is independent of the memo).
     *
     * @param list<string>                $chain
     * @param array<string, list<string>> $edges
     * @param array<string, string>       $settled
     */
    private static function visit(string $id, array $chain, array $edges, array &$settled): void
    {
        if (isset($settled[$id])) {
            return;
        }

        $chain[] = $id;
        foreach ($edges[$id] ?? [] as $target) {
            if (\in_array($target, $chain, true)) {
                throw new DefinitionException(
                    "Service '" . $target . "' is part of a circular reference chain: "
                    . implode(' -> ', [...$chain, $target]) . '.',
                );
            }

            self::visit($target, $chain, $edges, $settled);
        }

        $settled[$id] = $id;
    }
}
