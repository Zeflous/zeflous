<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception lane's materialisation (ROADMAP, "CONTAINER" area 9):
 * the boot-time construction of the interceptors and listeners their
 * knob entries declare -- each entry built once through {@see
 * InterceptionBuilder} (the definition grammar's own construction
 * idioms, narrowed to what an entry is) and proven to implement the
 * contract its knob demands, the middleware stack's own discipline: a
 * non-conforming entry fails the boot closed, the entry and the
 * contract named.
 *
 * The entries are machinery, never services: the sets this unit answers
 * are the {@see InterceptionSet}'s own state, built before the onion
 * exists, so a resolution never constructs an interceptor through the
 * seam it arms. The labels the refusals quote are the knob paths the
 * entries arrived by.
 */
final readonly class InterceptionMaterialisations
{
    /**
     * Materialises the interceptor entries into the pipeline's input:
     * each built object proven a {@see ServiceInterceptor}, each pair
     * carrying its entry's priority.
     *
     * @param list<InterceptionEntry> $entries
     *
     * @return list<array{interceptor: ServiceInterceptor, priority: int}>
     */
    public static function interceptors(array $entries, Container $container): array
    {
        $materialised = [];

        foreach ($entries as $position => $entry) {
            $built = InterceptionBuilder::built($entry, 'interceptors.' . $position, $container);
            if (!$built instanceof ServiceInterceptor) {
                throw InterceptionBuiltRefusals::notImplementingContract(
                    'interceptors.' . $position,
                    ServiceInterceptor::class,
                    $built,
                );
            }

            $materialised[] = ['interceptor' => $built, 'priority' => $entry->priority];
        }

        return $materialised;
    }

    /**
     * Materialises the resolving entries: each built object proven a
     * {@see ResolvingListener}.
     *
     * @param list<InterceptionEntry> $entries
     *
     * @return list<ResolvingListener>
     */
    public static function resolving(array $entries, Container $container): array
    {
        return self::proven('listeners.resolving', $entries, ResolvingListener::class, $container);
    }

    /**
     * Materialises the resolved entries: each built object proven a
     * {@see ResolvedListener}.
     *
     * @param list<InterceptionEntry> $entries
     *
     * @return list<ResolvedListener>
     */
    public static function resolved(array $entries, Container $container): array
    {
        return self::proven('listeners.resolved', $entries, ResolvedListener::class, $container);
    }

    /**
     * The two listener kinds' shared walk: every entry built through
     * the builder, every built object proven against its kind's
     * contract, in declaration order.
     *
     * @template T of object
     *
     * @param list<InterceptionEntry> $entries
     * @param class-string<T>         $contract
     *
     * @return list<T>
     */
    private static function proven(string $label, array $entries, string $contract, Container $container): array
    {
        $materialised = [];

        foreach ($entries as $position => $entry) {
            $built = InterceptionBuilder::built($entry, $label . '.' . $position, $container);
            if (!$built instanceof $contract) {
                throw InterceptionBuiltRefusals::notImplementingContract(
                    $label . '.' . $position,
                    $contract,
                    $built,
                );
            }

            $materialised[] = $built;
        }

        return $materialised;
    }
}
