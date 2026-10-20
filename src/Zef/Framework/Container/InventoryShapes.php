<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The inventory's shape name: which of the grammar's four shapes a
 * declared definition spelled -- 'class', 'factory', 'alias' or
 * 'value' -- answered as the entry's own one-word vocabulary.
 */
final readonly class InventoryShapes
{
    /**
     * Answers the declaration's shape name.
     */
    public static function of(ServiceDefinition $serviceDefinition): string
    {
        return match (true) {
            $serviceDefinition instanceof InstanceDefinition => 'class',
            $serviceDefinition instanceof FactoryDefinition => 'factory',
            $serviceDefinition instanceof AliasDefinition => 'alias',
            default => 'value',
        };
    }
}
