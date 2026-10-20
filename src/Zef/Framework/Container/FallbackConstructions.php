<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The fallback lane's guarded construction seam: the cycle guard's
 * frame and the interception set's wrap, the pipeline order the
 * registered lanes' own guarded seam uses -- split from
 * {@see NamespaceFallbacks} as its own unit so the registry keeps the
 * registration and coverage vocabulary and the construction keeps the
 * machinery it rides.
 *
 * Unarmed, the plain guarded call; armed, the onion wraps it -- a
 * fallback construction is construction, exactly the posture the
 * reference's own audit finding gave it. A throwing fallback factory
 * propagates raw through both arms, the same discipline the registered
 * factories keep.
 */
final readonly class FallbackConstructions
{
    public function __construct(
        private CycleGuard $cycleGuard,
        private Interceptions $interceptions,
    ) {
    }

    /**
     * Runs one fallback factory inside its guarded frame.
     *
     * @param string                            $id      the identifier being resolved
     * @param Closure(Container, string): mixed $factory the covering fallback's factory
     */
    public function run(string $id, Closure $factory, Container $container): mixed
    {
        $terminal = fn (): mixed => $this->cycleGuard->resolve(
            $id,
            static fn (Container $container): mixed => $factory($container, $id),
            $container,
        );

        if (!$this->interceptions->armed()) {
            return $terminal();
        }

        return $this->interceptions->run($id, $terminal);
    }
}
