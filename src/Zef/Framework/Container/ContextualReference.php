<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's entry-level reference form (the shared
 * vocabulary of the declaration check and the rewrite).
 *
 * An argument entry is the service-reference form naming the dependency
 * when it is an array carrying a `service` key that spells the
 * identifier -- the recognition contract {@see ArgumentResolution}
 * itself resolves by. The substituted entry is the same reference
 * flowing through the binding's synthetic identifier instead; the
 * substituted list walks a whole argument list of them.
 */
final readonly class ContextualReference
{
    /**
     * Is the entry the service-reference form naming the dependency?
     */
    public static function names(mixed $argument, string $dep): bool
    {
        return \is_array($argument)
            && \array_key_exists('service', $argument)
            && $argument['service'] === $dep;
    }

    /**
     * The argument list with every naming reference rewritten.
     *
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    public static function substitutedList(array $arguments, string $dep, string $via): array
    {
        return array_map(
            static fn (mixed $argument): mixed => self::substituted($argument, $dep, $via),
            $arguments,
        );
    }

    /**
     * The entry with a naming reference rewritten to the synthetic
     * identifier; every other entry passes through verbatim.
     */
    private static function substituted(mixed $argument, string $dep, string $via): mixed
    {
        return self::names($argument, $dep)
            ? ['service' => $via]
            : $argument;
    }
}
