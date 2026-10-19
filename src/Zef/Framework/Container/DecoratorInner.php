<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's reserved inner reference -- the `@inner`
 * spelling's own vocabulary: the recognition contract the entry
 * grammar's presence proof resolves by, and the substitution that
 * rewrites every recognised entry to the identifier of the layer being
 * wrapped (the {@see ContextualReference} posture: one unit owns the
 * reference form's whole life).
 *
 * The reference never survives the apply: the substitution runs inside
 * the wrapper construction, so the ordinary service-reference grammar,
 * the boot graph and the AOT dump all stay unchanged -- a decorator's
 * wrapper is an ordinary definition by the time anything else reads it.
 */
final readonly class DecoratorInner
{
    /**
     * Does the argument entry spell the reserved inner reference?
     */
    public static function names(mixed $argument): bool
    {
        return \is_array($argument)
            && \array_key_exists('service', $argument)
            && $argument['service'] === DecoratorLayers::INNER;
    }

    /**
     * Does the argument list spell the inner reference at least once?
     *
     * @param list<mixed> $arguments
     */
    public static function carried(array $arguments): bool
    {
        return array_any($arguments, static fn ($argument): bool => self::names($argument));
    }

    /**
     * The argument list with every inner reference rewritten to the
     * identifier of the layer being wrapped; every other entry passes
     * through verbatim.
     *
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    public static function rewritten(array $arguments, string $innerId): array
    {
        return array_map(
            static fn (mixed $argument): mixed => self::names($argument)
                ? ['service' => $innerId]
                : $argument,
            $arguments,
        );
    }
}
