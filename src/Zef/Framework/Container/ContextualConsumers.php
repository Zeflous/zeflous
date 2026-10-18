<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding knob's consumer entries ({@see ContextualMap}
 * for the knob itself): the map of consumers to their dependency
 * overrides, validated entry by entry, fail-closed.
 *
 * A consumer key must be a non-empty string; its overrides are
 * validated by {@see ContextualOverrides}. PHP's own unique-key
 * contract makes a doubled pair impossible from the grammar -- the
 * apply-side guards remain the documented defense against hand-built
 * misuse.
 */
final readonly class ContextualConsumers
{
    /**
     * Validates the knob's consumer entries into their binding maps.
     *
     * @param array<array-key, mixed> $source
     *
     * @return array<string, array<string, string>>
     */
    public static function bindings(string $key, array $source): array
    {
        $bindings = [];
        foreach (array_keys($source) as $consumer) {
            if (!\is_string($consumer) || $consumer === '') {
                throw ContextualRefusals::consumerKey($key, $consumer);
            }

            $bindings[$consumer] = ContextualOverrides::validated($key, $consumer, $source[$consumer]);
        }

        return $bindings;
    }
}
