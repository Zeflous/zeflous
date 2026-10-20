<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The inventory entry builder: one declared definition walked into
 * its {@see InventoryEntry} -- the shape's dispatch ({@see
 * InventoryShapes}), the shape's own members ({@see
 * InventoryMembers}), and the shared members (lifetime, tags, calls,
 * declared dependencies) answered once for every shape.
 *
 * The declared dependencies ride the graph's own walk
 * ({@see DefinitionReferences::of()}): the same `['service' => id]`
 * references the boot's acyclicity proof walked, single-sourced -- the
 * inventory never re-derives what the graph already answers.
 */
final readonly class InventoryEntries
{
    /**
     * Answers the definition's inventory entry, the lazy mark read by
     * the caller ({@see ContainerInventory} holds the live registry).
     *
     * @param null|class-string $lazyClass the runtime's mark for the identifier
     */
    public static function of(ServiceDefinition $serviceDefinition, ?string $lazyClass): InventoryEntry
    {
        $members = InventoryMembers::of($serviceDefinition);

        return new InventoryEntry(
            $serviceDefinition->id,
            InventoryShapes::of($serviceDefinition),
            $members[0],
            $members[1],
            $members[2],
            $members[3],
            $serviceDefinition->lifetime->value,
            $serviceDefinition->tags->all(),
            self::calls($serviceDefinition),
            $lazyClass,
            DefinitionReferences::of($serviceDefinition),
        );
    }

    /**
     * The method-call injections' method names, in declaration order
     * -- the two constructing shapes' own list, empty for the shapes
     * that build nothing.
     *
     * @return list<string>
     */
    private static function calls(ServiceDefinition $serviceDefinition): array
    {
        if (!$serviceDefinition instanceof InstanceDefinition && !$serviceDefinition instanceof FactoryDefinition) {
            return [];
        }

        $methods = [];

        foreach ($serviceDefinition->calls as $call) {
            $methods[] = $call->method;
        }

        return $methods;
    }
}
