<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\ContainerInventory;
use Zef\Framework\Container\InventoryEntry;

/**
 * The container inventory's table form: the plain listing the
 * analysis CLI answers -- one line per declared identifier (the
 * shape, the lifetime, the class, alias, factory or value type, the
 * tags, the declared dependencies, the lazy flag), headed by the
 * boot answer's own note and the unreferenced report, and closed by
 * the runtime's live lazy marks.
 *
 * Pure presentation over the inventory's own data, kept its own
 * archeology-sized unit beside the detail form
 * ({@see ContainerInventoryDetail}) so each view owns its own words
 * -- never a re-derivation, every field read straight off the entry.
 */
final readonly class ContainerInventoryText
{
    /**
     * The whole declared set, one line per identifier.
     */
    public static function table(ContainerInventory $containerInventory, AotOutcome $aotOutcome): string
    {
        $ids = $containerInventory->ids();

        $lines = [
            \sprintf('Container inventory -- %d services', \count($ids)),
            'boot: ' . $aotOutcome->note,
        ];

        if ($aotOutcome->unreferenced !== []) {
            $lines[] = 'unreferenced: ' . implode(', ', $aotOutcome->unreferenced);
        }

        foreach ($ids as $id) {
            $entry = $containerInventory->entry($id);
            $lines[] = $entry instanceof InventoryEntry
                ? '  ' . ContainerInventoryLine::rendered($entry)
                : '  ' . $id;
        }

        $lazy = $containerInventory->lazy();

        if ($lazy !== []) {
            $lines[] = \sprintf('lazy marks -- %d', \count($lazy));

            foreach ($lazy as $id => $class) {
                $lines[] = '  ' . $id . ' -> ' . $class;
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
