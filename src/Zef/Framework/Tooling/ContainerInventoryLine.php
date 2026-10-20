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
    public static function rendered(InventoryEntry $entry): string
    {
        $parts = [$entry->id, $entry->shape, $entry->lifetime];

        if ($entry->class !== null) {
            $parts[] = $entry->class;
        } elseif ($entry->alias !== null) {
            $parts[] = '-> ' . $entry->alias;
        } elseif ($entry->factory !== null) {
            $parts[] = $entry->factory . '()';
        } else {
            $parts[] = get_debug_type($entry->value);
        }

        if ($entry->tags !== []) {
            $parts[] = 'tags: ' . implode(',', $entry->tags);
        }

        if ($entry->dependencies !== []) {
            $parts[] = 'deps: ' . implode(',', $entry->dependencies);
        }

        if ($entry->lazy !== null) {
            $parts[] = 'lazy';
        }

        return implode('  ', $parts);
    }
}
