<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The shared planning loop: a constructor's whole spec list planned
 * into the positional argument list, one stretch per parameter (the one
 * loop both halves of the lane run -- the pass over a declaration, the
 * references over a birth).
 *
 * Kept its own unit so the loop's vocabulary lives once: the planner
 * answers each parameter's stretch ({@see AutowireParameterPlanner}),
 * this class only sequences them.
 */
final readonly class AutowirePlanning
{
    /**
     * @param list<AutowireParameterSpec> $specs the constructor's own
     *                                           plan, in declaration order
     * @param null|array<string, mixed>   $named the declaration's
     *                                           name-keyed arguments, null for
     *                                           the whole-constructor `autowire` form
     *
     * @return list<mixed> the positional argument list, in the definition grammar's own form
     */
    public static function arguments(
        string $ownerId,
        array $specs,
        ?array $named,
        AutowireReferences $autowireReferences,
    ): array {
        $autowireParameterPlanner = new AutowireParameterPlanner();

        $arguments = [];
        foreach ($specs as $spec) {
            $planned = $autowireParameterPlanner->plan($ownerId, $spec, $named, $autowireReferences);
            $arguments = [...$arguments, ...$planned];
        }

        return $arguments;
    }
}
