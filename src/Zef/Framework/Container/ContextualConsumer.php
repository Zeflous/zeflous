<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's consumer location: the effective
 * declaration of a consumer in a definition set -- its last occurrence,
 * the one the rebind discipline installs.
 */
final readonly class ContextualConsumer
{
    /**
     * Locates the consumer's effective declaration as its position and
     * definition. The walk runs reversed, so the first match is that
     * last occurrence.
     *
     * @param list<ServiceDefinition> $definitions
     *
     * @return array{int, ServiceDefinition}
     */
    public static function effective(array $definitions, string $consumer): array
    {
        foreach (array_reverse($definitions, true) as $position => $serviceDefinition) {
            if ($serviceDefinition->id !== $consumer) {
                continue;
            }

            return [$position, $serviceDefinition];
        }

        throw ContextualRefusals::consumerUnknown($consumer);
    }
}
