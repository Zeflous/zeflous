<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The alias ride registry behind {@see ServiceStore::set()}'s
 * invalidation: which identifiers are ordinary aliases riding which
 * target, the reverse index a re-registration walks to forget every
 * memo slot the rebind makes stale (#770).
 *
 * The bug this unit closes: the store's `set()` forgot only the
 * re-registered id's own memo, while an aligned alias's singleton
 * answer lives in the *alias's* slot ({@see AliasLifetimes} kept the
 * own-slot memo and only aligned the policy), so a re-registered
 * target left every alias in the chain serving the instance the memo
 * contract says "must never answer again" -- half the container
 * serving a stale object while the target's own lane answered the new
 * one.
 *
 * The registry records what the *installed plan* says ({@see
 * ContainerInstalls} marks every {@see AliasDefinition} it installs,
 * whatever identifier the pipeline's surgery left on it -- a re-homed
 * `@decor:` alias rides its target the same way, and a contextual
 * lane's `@contextual:` synthetic the implementation it points at):
 * the graph the invalidation walks is the graph the boot installed,
 * not the declarations the host wrote. A re-registered alias id drops
 * its own record -- the id answers its own factory now, and a later
 * rebind of the old target must not reach through it -- while the
 * aliases *riding* the re-registered id keep theirs: their delegated
 * answer changed, transitively, and the walk follows.
 *
 * The walk is a reverse breadth-first reach over the recorded edges
 * with a visited set -- defensive against a cycle the boot's own graph
 * validator already refuses, the same cycle arm {@see AliasChains}
 * carries -- continuing *through* reached aliases, because a rider's
 * rider is exactly as stale as the rider: rebinding `c` must forget
 * `b -> c` and `a -> b` both.
 */
final class AliasRides
{
    /** @var array<string, string> alias identifier => target identifier, the recorded rides */
    private array $rides = [];

    /**
     * Records (or re-records) one alias's ride. The install lanes'
     * own bookkeeping mark ({@see Container::markAliasRide()} -- the
     * facade the compile lane and the artifact lane both reach
     * through, the same posture {@see Container::markLazy()} takes).
     */
    public function record(string $aliasId, string $targetId): void
    {
        $this->rides[$aliasId] = $targetId;
    }

    /**
     * Drops a re-registered identifier's own ride: the id answers its
     * own factory now, so later rebinds of the old target must not
     * reach through it.
     */
    public function drop(string $aliasId): void
    {
        unset($this->rides[$aliasId]);
    }

    /**
     * The memo slots a re-registration must forget beyond the
     * re-registered id itself: every alias still riding it,
     * transitively -- the closure the store's `set()` forgets before
     * the id's own slot. The order is the walk's own breadth-first
     * one -- the direct riders in declaration order, then the riders
     * they reach -- which no caller ever observes as an order (the
     * forget is per-slot), pinned only so the answer stays
     * deterministic for the registry's own battery.
     *
     * @return list<string> the riding alias identifiers, breadth-first
     */
    public function forgottenFor(string $reboundId): array
    {
        $byTarget = [];

        foreach ($this->rides as $aliasId => $targetId) {
            $byTarget[$targetId][] = $aliasId;
        }

        $riders = [];
        $seen = [$reboundId => true];
        $queue = [$reboundId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($byTarget[$current] ?? [] as $aliasId) {
                if (isset($seen[$aliasId])) {
                    continue;
                }

                $seen[$aliasId] = true;
                $riders[] = $aliasId;
                $queue[] = $aliasId;
            }
        }

        return $riders;
    }

    /**
     * The recorded rides, keyed by alias identifier in declaration
     * order -- the read side the registry's own battery pins its
     * recording and drop answers through; the src call graph never
     * consults it (the store walks {@see forgottenFor()}, the lanes
     * mark through {@see record()}), so the psalm baseline carries
     * the boundary honestly rather than dead code silently.
     *
     * @return array<string, string>
     */
    public function rides(): array
    {
        return $this->rides;
    }
}
