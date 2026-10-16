<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The runtime cycle guard behind {@see ServiceStore::resolve}.
 *
 * The ids whose factories are currently running, in resolution order:
 * a factory that re-enters its own id -- or any id still beneath it on
 * the stack -- is a cycle the guard answers with the catchable
 * exception (PSR-11: a resolution failure is an exception the caller
 * can catch, never a fatal the process cannot), never the unbounded
 * recursion a memo-only algorithm would recurse into.
 *
 * The frame's lifetime is the factory call's lifetime: the id is
 * marked as the factory starts and unmarked as it returns -- the
 * finally keeps the unmark honest through a throwing factory, so a
 * refused cycle leaves no residue a later honest resolution of the
 * same ids could trip over. A diamond is not a cycle: both branches
 * of a shared dependency answer, the leaf's frame closed before
 * either sibling started.
 *
 * The refusal message carries the whole chain that closed the cycle,
 * in the walk order the stack recorded it, in the same shape the
 * definitions graph's own compile-time detector reports its chains
 * ({@see ServiceDefinitionGraph}) -- the two guards are the same
 * discipline at the two times a cycle can surface: declared edges
 * before the boot resolves them, factory calls while it does.
 */
final class CycleGuard
{
    /**
     * The frames currently open, in resolution order: the key is the
     * membership the guard reads, the value the frame's own name -- the
     * id marking its own frame, the names the refusal's walked chain
     * is rendered from, never a bare marker a reader would have to
     * trust.
     *
     * @var array<string, string>
     */
    private array $resolving = [];

    /**
     * Runs the factory inside its own guarded frame: the id marked as
     * the call starts, unmarked as it returns, a re-entry while the
     * mark is live refused with the walked chain that closed it.
     *
     * @param Closure(Container): mixed $factory
     */
    public function resolve(string $id, Closure $factory, Container $container): mixed
    {
        if (isset($this->resolving[$id])) {
            throw new CircularDependencyException(
                "Service '" . $id . "' is part of a circular reference chain: "
                . implode(' -> ', [...$this->resolving, $id]) . '.',
            );
        }

        $this->resolving[$id] = $id;

        try {
            return $factory($container);
        } finally {
            unset($this->resolving[$id]);
        }
    }
}
