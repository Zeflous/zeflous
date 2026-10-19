<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The armed interception itself (ROADMAP, "CONTAINER" area 9): the
 * immutable state one boot's arming produced -- the sealed onion
 * ({@see InterceptionPipeline}) and the construction events
 * ({@see InterceptionFires}) -- and the run every construction rides
 * while it is the registry's current set.
 *
 * A value object by construction: readonly, its two halves built once
 * by the registry's arming through the plain lane (the entries'
 * arguments resolving before the onion exists, the materialised
 * objects proven against the contracts their knobs demand), no public
 * mutation path at all.
 *
 * The run's order keeps the reference's own: the resolving listeners
 * fire when a construction starts (before the onion), the onion wraps
 * the terminal step (the guarded factory call the resolution lane
 * hands over -- an interceptor's frame sits outside the cycle-guard
 * frame, so re-entry detection is untouched), and the resolved
 * listeners fire over the answer (a non-null answer replacing the
 * instance before the per-lifetime memo records it). The container's
 * own machinery identifiers (`@decor:`, `@contextual:`, `@value:`)
 * bypass the onion alone -- an interceptor wraps the host's services,
 * not the container's plumbing -- while the listeners observe every
 * construction.
 */
final readonly class InterceptionSet
{
    /**
     * @param ?InterceptionPipeline $interceptionPipeline the sealed onion (null when no interceptor rides the chain)
     */
    public function __construct(
        private ?InterceptionPipeline $interceptionPipeline,
        private InterceptionFires $interceptionFires,
    ) {
    }

    /**
     * Whether the set carries any interception at all -- the registry's
     * fast-path question over the armed state.
     */
    public function carries(): bool
    {
        return $this->interceptionPipeline instanceof InterceptionPipeline || $this->interceptionFires->carries();
    }

    /**
     * Runs one construction under the interception.
     *
     * @param Closure(): mixed $terminal the guarded factory call
     */
    public function run(string $id, Closure $terminal): mixed
    {
        $this->interceptionFires->resolving($id);

        return $this->interceptionFires->replaced($id, $this->onion($id, $terminal));
    }

    /**
     * The onion around the terminal step -- the terminal itself when
     * no interceptor rides the chain or the identifier is the
     * container's own machinery.
     *
     * @param Closure(): mixed $terminal
     */
    private function onion(string $id, Closure $terminal): mixed
    {
        $pipeline = $this->interceptionPipeline;
        if ($pipeline instanceof InterceptionPipeline && !str_starts_with($id, '@')) {
            return $pipeline->run($id, $terminal);
        }

        return $terminal();
    }
}
