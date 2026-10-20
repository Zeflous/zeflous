<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The inventory entry's shape-specific members: the quartet only one
 * shape of the four carries -- the class shape's own class, the
 * factory shape's `'Target::method'` spelling, the alias shape's
 * target, the value shape's held value -- answered as one tuple so
 * {@see InventoryEntries} keeps the composition it composes.
 *
 * The members the shapes do not carry answer null: the entry's
 * surface is uniform (one shape's recipe reads the same as another's)
 * even though the grammar's four shapes spell different keys.
 *
 * @phpstan-type ShapeMembers array{null|class-string, null|string, null|string, mixed}
 *
 * @psalm-type ShapeMembers array{null|class-string, null|string, null|string, mixed}
 */
final readonly class InventoryMembers
{
    /**
     * Answers the declaration's shape-specific member quartet.
     *
     * @return array{null|class-string, null|string, null|string, mixed} [class, factory, alias, value]
     */
    public static function of(ServiceDefinition $serviceDefinition): array
    {
        return [
            $serviceDefinition instanceof InstanceDefinition ? $serviceDefinition->class : null,
            $serviceDefinition instanceof FactoryDefinition ? $serviceDefinition->factory : null,
            $serviceDefinition instanceof AliasDefinition ? $serviceDefinition->target : null,
            $serviceDefinition instanceof ValueDefinition ? $serviceDefinition->value : null,
        ];
    }
}
