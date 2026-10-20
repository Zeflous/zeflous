<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Zef\Framework\Container\InventoryEntry;

/**
 * The container inventory's line form: the compact per-entry view the
 * table listing renders -- the shape and lifetime always, the class,
 * alias, factory or value type the shape names, the tags and the
 * declared dependencies when any, the lazy flag when the runtime's
 * mark defers the identifier.
 *
 * Kept its own archeology-sized unit beside the table and detail
 * forms ({@see ContainerInventoryText}, {@see
 * ContainerInventoryDetail}): the three views read the same entry,
 * each owning its own words.
 */
final readonly class ContainerInventoryLine
{
    /**
     * One identifier's table line.
     */
    public static function rendered(InventoryEntry $inventoryEntry): string
    {
        $parts = [$inventoryEntry->id, $inventoryEntry->shape, $inventoryEntry->lifetime];

        if ($inventoryEntry->class !== null) {
            $parts[] = $inventoryEntry->class;
        } elseif ($inventoryEntry->alias !== null) {
            $parts[] = '-> ' . $inventoryEntry->alias;
        } elseif ($inventoryEntry->factory !== null) {
            $parts[] = $inventoryEntry->factory . '()';
        } else {
            $parts[] = get_debug_type($inventoryEntry->value);
        }

        if ($inventoryEntry->tags !== []) {
            $parts[] = 'tags: ' . implode(',', $inventoryEntry->tags);
        }

        if ($inventoryEntry->dependencies !== []) {
            $parts[] = 'deps: ' . implode(',', $inventoryEntry->dependencies);
        }

        if ($inventoryEntry->lazy !== null) {
            $parts[] = 'lazy';
        }

        return implode('  ', $parts);
    }
}
