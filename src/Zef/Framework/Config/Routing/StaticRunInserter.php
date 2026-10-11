<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Inserts one static run into a node's children.
 *
 * A run descends the static child whose prefix shares the run's first
 * character; that child is split when the shared text runs out mid-way,
 * so the shared prefix is stored exactly once (the radix compression).
 * All string arithmetic is character-based (`mb_*`), so multi-byte
 * static text splits exactly like ASCII text.
 *
 * @internal
 */
final class StaticRunInserter
{
    /**
     * The single character a static child is looked up by: the first
     * character of the run being inserted.
     */
    private const int ONE_CHARACTER = 1;

    /**
     * Inserts one static run below a node, then consumes the remaining
     * tokens through {@see RouteNodeInserter::insert()}.
     *
     * @param list<array{bool, string}> $restTokens
     */
    public function insertRun(
        RadixNode $radixNode,
        string $run,
        array $restTokens,
        CompiledRoute $compiledRoute,
        RouteNodeInserter $routeNodeInserter,
    ): void {
        $child = $radixNode->staticChild(mb_substr($run, 0, self::ONE_CHARACTER));

        if (!$child instanceof RadixNode) {
            $routeNodeInserter->insert($radixNode->appendStaticRun($run), $restTokens, $compiledRoute);

            return;
        }

        $shared = SharedPrefixLength::of($child->prefix(), $run);

        if ($shared !== mb_strlen($child->prefix())) {
            $child->splitAt($shared);
        }

        $remaining = mb_substr($run, $shared);

        if ($remaining === '') {
            $routeNodeInserter->insert($child, $restTokens, $compiledRoute);
        } else {
            $this->insertRun($child, $remaining, $restTokens, $compiledRoute, $routeNodeInserter);
        }
    }
}
