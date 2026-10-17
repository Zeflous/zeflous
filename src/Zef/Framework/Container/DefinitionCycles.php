<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The cycle walk of a definition set's reference graph (the second
 * discipline {@see ServiceDefinitionGraph} runs over the edges
 * {@see DefinitionEdges} collects).
 *
 * Depth-first walk of every definition's edges, carrying the walked
 * chain so a cycle is reported with the path that closed it --
 * directly or through intermediaries.
 */
final readonly class DefinitionCycles
{
    /**
     * @param array<string, list<string>> $edges
     */
    public static function assertAcyclic(array $edges): void
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
