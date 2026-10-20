<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's target location: the effective declaration of a
 * decorated target in a definition set -- its last occurrence, the one
 * the rebind discipline installs (the {@see ContextualConsumer} posture,
 * kept for the same reason: a same-identifier rebind wins, so the
 * decoration applies to the declaration the container would actually
 * run).
 */
final readonly class DecoratorTarget
{
    /**
     * Locates the target's effective declaration as its position and
     * definition. The walk runs reversed, so the first match is that
     * last occurrence.
     *
     * @param list<ServiceDefinition> $definitions
     *
     * @return array{int, ServiceDefinition}
     */
    public static function effective(array $definitions, string $target): array
    {
        foreach (array_reverse($definitions, true) as $position => $serviceDefinition) {
            if ($serviceDefinition->id !== $target) {
                continue;
            }

            return [$position, $serviceDefinition];
        }

        throw DecoratorRefusals::unknownTarget($target);
    }
}
