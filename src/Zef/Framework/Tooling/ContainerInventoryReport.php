<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\ContainerInventory;
use Zef\Framework\Container\InventoryEntry;
use Zef\Framework\FrameworkDefaults;

/**
 * The container's analysis report (ROADMAP, "CONTAINER" area 12's CLI
 * item -- "Perintah CLI analisis: melihat daftar service, dependensi,
 * dan status tag"): the answer `bin/zef container:inventory` renders,
 * booted from the host's own configuration directory through the same
 * application orchestrator the runtime rides.
 *
 * The boot is the analysis's honesty: the report answers the
 * declarations the configuration actually spells, not a parallel
 * parse -- and because the boot carries the environment mode doctrine
 * ({@see \Zef\Framework\ContainerMode}), the analysis inherits it for
 * free: a development environment compiles the live source afresh,
 * a production one keeps the artifact lane's own posture. The one
 * seam the analysis owns is the artifact cache: the host's real boot
 * wires their own `ContainerAotCache` location, which the CLI cannot
 * know -- so the analysis boot wires a throwaway cache file (the
 * seam's `aot.enabled` guard stays satisfied, the full lane compiles
 * the same declarations whichever artifact the host's own boot would
 * have answered from, and the temporary file never outlives the
 * report).
 *
 * The rendering itself is plain text over the inventory's own data:
 * one line per declared identifier (the shape, the lifetime, the
 * tags, the declared dependencies), or the one identifier's full
 * entry when the caller asks for it -- the same data either way,
 * never a re-derivation.
 */
final readonly class ContainerInventoryReport
{
    /**
     * Boots the configuration directory's container and renders its
     * inventory: the whole table, or one identifier's full entry.
     *
     * @param non-empty-string $configDirectory the host's configuration
     *                                          directory (the `app/configs` layout)
     * @param null|string      $identifier      one declared identifier's own
     *                                          entry, when the caller asks for it
     */
    public static function rendering(string $configDirectory, ?string $identifier = null): string
    {
        $artifactFile = sys_get_temp_dir() . '/zef-container-inventory-' . uniqid('', false) . '.php';

        try {
            $kernel = new ApplicationBootstrapper(
                containerAotCache: new ContainerAotCache($artifactFile),
            )->boot($configDirectory);

            $inventory = $kernel->container()->get(FrameworkDefaults::INVENTORY_SERVICE);
            $outcome = $kernel->container()->get(FrameworkDefaults::AOT_SERVICE);

            \assert($inventory instanceof ContainerInventory);
            \assert($outcome instanceof AotOutcome);

            return $identifier === null
                ? self::table($inventory, $outcome)
                : self::entry($inventory, $identifier);
        } finally {
            if (is_file($artifactFile)) {
                unlink($artifactFile);
            }
        }
    }

    /**
     * The whole declared set, one line per identifier, headed by the
     * boot's own answer (which lane answered it, and why) and closed
     * by the runtime's live lazy marks -- which identifiers defer
     * behind which proxy class, the boot's own `@`-machinery
     * excluded the way the inventory's own view excludes it.
     */
    private static function table(ContainerInventory $inventory, AotOutcome $outcome): string
    {
        $ids = $inventory->ids();

        $lines = [
            \sprintf('Container inventory -- %d services', \count($ids)),
            'boot: ' . $outcome->note,
        ];

        if ($outcome->unreferenced !== []) {
            $lines[] = 'unreferenced: ' . implode(', ', $outcome->unreferenced);
        }

        foreach ($ids as $id) {
            $entry = $inventory->entry($id);
            $lines[] = $entry === null
                ? '  ' . $id
                : '  ' . self::line($entry);
        }

        $lazy = $inventory->lazy();

        if ($lazy !== []) {
            $lines[] = \sprintf('lazy marks -- %d', \count($lazy));

            foreach ($lazy as $id => $class) {
                $lines[] = '  ' . $id . ' -> ' . $class;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * One identifier's full entry: every field the inventory answers,
     * the value shape's held type named. A queried identifier the boot
     * declared no service for refuses ({@see ToolingException}) -- the
     * analysis was asked a question the configuration does not answer,
     * and the caller's exit code must say so.
     */
    private static function entry(ContainerInventory $inventory, string $identifier): string
    {
        $inventoryEntry = $inventory->entry($identifier);

        if ($inventoryEntry === null) {
            throw new ToolingException("The boot declared no service '{$identifier}'.");
        }

        return implode("\n", [
            $inventoryEntry->id,
            '  shape:        ' . $inventoryEntry->shape,
            '  class:        ' . ($inventoryEntry->class ?? '(none)'),
            '  factory:      ' . ($inventoryEntry->factory ?? '(none)'),
            '  alias:        ' . ($inventoryEntry->alias ?? '(none)'),
            '  value:        ' . get_debug_type($inventoryEntry->value),
            '  lifetime:     ' . $inventoryEntry->lifetime,
            '  tags:         ' . self::listed($inventoryEntry->tags),
            '  calls:        ' . self::listed($inventoryEntry->calls),
            '  lazy:         ' . ($inventoryEntry->lazy ?? '(eager)'),
            '  dependencies: ' . self::listed($inventoryEntry->dependencies),
        ]) . "\n";
    }

    /**
     * One identifier's table line: the shape and lifetime always, the
     * class or alias or value type the shape names, the tags and the
     * declared dependencies when any.
     */
    private static function line(InventoryEntry $entry): string
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

    /**
     * @param list<string> $values
     */
    private static function listed(array $values): string
    {
        return $values === [] ? '(none)' : implode(', ', $values);
    }
}
