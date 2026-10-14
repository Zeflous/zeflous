<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * Parses one definition map -- the framework's reserved `framework.services`
 * map or the host's `services` map -- into its definition objects, the
 * fail-closed parsing half the container bootstrapper composes over.
 *
 * The map's own key travels into every failure message, so the framework's
 * machinery and the host's declaration surface report under their own
 * names: a non-map value, a non-empty-identifier key violation or a
 * malformed entry each halt the boot with the source named. A `null`
 * source is the valid empty set (a layer that declares no definitions).
 */
final readonly class DefinitionMap
{
    /**
     * Parses the map under the given configuration key into its
     * definition objects, in declaration order.
     *
     * @return list<ServiceDefinition>
     */
    public static function parse(string $key, mixed $source): array
    {
        if ($source === null) {
            return [];
        }

        if (!\is_array($source)) {
            throw new DefinitionException(
                "The '{$key}' configuration must be a map of definitions, " . get_debug_type($source) . ' given.',
            );
        }

        $definitions = [];
        foreach (array_keys($source) as $id) {
            if (!\is_string($id) || $id === '') {
                throw new DefinitionException(
                    "The '{$key}' configuration keys must be non-empty service identifiers, "
                    . get_debug_type($id) . ' given.',
                );
            }

            $definitions[] = ServiceDefinition::fromArray($id, $source[$id]);
        }

        return $definitions;
    }
}
