<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One override of the contextual-binding knob ({@see ContextualOverrides}
 * for the map a consumer declares): the target a single dependency
 * binding gives instead, validated fail-closed.
 */
final readonly class ContextualOverride
{
    /**
     * Validates one override's target into its non-empty identifier.
     */
    public static function target(string $key, string $consumer, string $dep, mixed $target): string
    {
        if (!\is_string($target) || $target === '') {
            throw ContextualRefusals::target($key, $consumer, $dep, $target);
        }

        return $target;
    }
}
