<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespaces knob's source validation: the map shape and the
 * two-key vocabulary -- the checks the knob's own parse owes before
 * its members' parses run -- split from {@see NamespacesMap} as its
 * own unit (the source-versus-members boundary the scopes member's
 * own source validation draws).
 */
final readonly class NamespacesSource
{
    /**
     * The knob's own map shape, refusing the values the members cannot
     * be read from.
     *
     * @param string $key    the knob's own key, for the refusals
     * @param mixed  $source the raw `namespaces` value from the configuration
     *
     * @return array<array-key, mixed> the knob's map, shape-validated
     */
    public function members(string $key, mixed $source): array
    {
        if (!\is_array($source)) {
            throw NamespaceRefusals::notAMap($key, $source);
        }

        if ($source !== [] && array_is_list($source)) {
            throw NamespaceRefusals::notAMap($key, $source);
        }

        return $source;
    }
}
