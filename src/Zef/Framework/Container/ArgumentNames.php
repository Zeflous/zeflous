<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The identifier an argument entry names when it is the
 * service-reference form -- the recognition the boot graph's edge
 * collection walks argument lists by ({@see DefinitionReferences}).
 *
 * The form is the resolution half's own contract
 * ({@see ArgumentResolution}): an array carrying a string `service`
 * key names that identifier; every other entry names nothing.
 */
final readonly class ArgumentNames
{
    /**
     * The identifier a well-formed reference entry names, or null for
     * every other entry.
     */
    public static function identifier(mixed $entry): ?string
    {
        if (\is_array($entry) && \array_key_exists('service', $entry) && \is_string($entry['service'])) {
            return $entry['service'];
        }

        return null;
    }
}
