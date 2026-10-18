<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One consumer's dependency overrides, as the contextual-binding knob
 * declares them: a map of dependency identifiers to the target each
 * binding gives instead, validated entry by entry, fail-closed.
 *
 * The dependency keys must each be non-empty strings -- a malformed key
 * halts the boot with the knob's own name, the consumer, and the
 * offending dependency in the message. PHP's own unique-key contract
 * makes a doubled pair impossible from the grammar -- the apply-side
 * guards remain the documented defense against hand-built misuse.
 */
final readonly class ContextualOverrides
{
    /**
     * Validates one consumer's override map into its target map.
     *
     * @return array<string, string>
     */
    public static function validated(string $key, string $consumer, mixed $deps): array
    {
        if (!\is_array($deps)) {
            throw ContextualRefusals::depsNotAMap($key, $consumer, $deps);
        }

        $targets = [];
        foreach (array_keys($deps) as $dep) {
            if (!\is_string($dep) || $dep === '') {
                throw ContextualRefusals::depKey($key, $consumer, $dep);
            }

            $targets[$dep] = ContextualOverride::target($key, $consumer, $dep, $deps[$dep]);
        }

        return $targets;
    }
}
