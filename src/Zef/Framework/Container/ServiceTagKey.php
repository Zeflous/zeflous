<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The grammar's `tags` key parser: one definition-map entry's tag
 * list into its {@see ServiceTags} value (the seam the class and
 * factory shapes consult, kept its own unit the way the arguments
 * grammar's parsers are).
 *
 * An absent key answers null -- the caller's default (no tags)
 * applies -- while a present key must be a list of tag strings: a
 * non-array or a map-keyed array is the declaration defect it is,
 * refused with the service's identifier. The entries themselves are
 * validated by the value object's own constructor (the grammar every
 * tag must match, no tag twice).
 */
final readonly class ServiceTagKey
{
    /**
     * Parses the grammar's `tags` key for one service.
     *
     * @param mixed $key the raw `tags` value from the definition map
     */
    public static function fromKey(string $id, mixed $key): ?ServiceTags
    {
        if ($key === null) {
            return null;
        }

        if (!\is_array($key) || !array_is_list($key)) {
            throw new DefinitionException(
                "Service '{$id}' tags must be a list of tag strings, " . get_debug_type($key) . ' given.',
            );
        }

        return new ServiceTags($key);
    }
}
