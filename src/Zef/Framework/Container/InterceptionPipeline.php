<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The interception lane's onion (ROADMAP, "CONTAINER" area 9 -- the
 * reference container's `ServiceMiddlewarePipeline`, adapted): the
 * materialised {@see ServiceInterceptor} chain, ordered HIGHEST
 * priority first (the outermost layer), ties keeping declaration
 * order -- the engine's own stable `usort`, a documented guarantee
 * since PHP 8.0, so the order is deterministic by construction rather
 * than by a hand-kept sequence number.
 *
 * The chain is built once, at the arming ({@see self::over()}), from
 * the complete entry set -- so the sort runs in the constructor and
 * the pipeline is immutable afterwards, sealed by construction: there
 * is no public add path at all, the declarative knob being the only
 * spelling an interceptor arrives by. The reference seals the same
 * pipeline at its freeze; the boot's own arming is this container's
 * freeze.
 *
 * The run nests innermost-first (a `for` loop over the sorted chain,
 * never `range()` -- `range(1, 0)` DESCENDS in PHP, the lesson the
 * decoration lane's mutant hunt paid for): the terminal closure the
 * resolution lane hands over becomes the innermost link, each
 * interceptor outward wraps it, and the outermost link is invoked
 * once.
 */
final readonly class InterceptionPipeline
{
    /**
     * The sorted chain: priority DESC (outermost first), declaration
     * order on ties (the engine's stable sort).
     *
     * @var list<array{interceptor: ServiceInterceptor, priority: int}>
     */
    private array $sorted;

    /**
     * The materialised interceptors, in declaration order.
     *
     * @param list<array{interceptor: ServiceInterceptor, priority: int}> $entries
     */
    public function __construct(array $entries)
    {
        usort($entries, static fn (array $left, array $right): int => $right['priority'] <=> $left['priority']);

        $this->sorted = $entries;
    }

    /**
     * The interceptors' materialised onion: the knob's entries built
     * and proven ({@see InterceptionMaterialisations}), sealed over
     * their priorities -- or null when the knob carries no entry at
     * all (no interceptor rides the chain).
     */
    public static function over(InterceptorsMap $interceptorsMap, Container $container): ?self
    {
        $materialised = InterceptionMaterialisations::interceptors($interceptorsMap->interceptors(), $container);

        return $materialised === [] ? null : new self($materialised);
    }

    /**
     * Runs the onion: the outermost interceptor invoked first, `$terminal`
     * the innermost step (the resolution lane's guarded factory call).
     *
     * @param Closure(): mixed $terminal the construction's terminal step
     */
    public function run(string $id, Closure $terminal): mixed
    {
        $next = $terminal;

        for ($i = \count($this->sorted) - 1; $i >= 0; --$i) {
            $interceptor = $this->sorted[$i]['interceptor'];
            $next = static fn (): mixed => $interceptor->intercept($id, $next);
        }

        return $next();
    }
}
