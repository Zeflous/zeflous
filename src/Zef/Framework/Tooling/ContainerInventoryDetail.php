<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Zef\Framework\Container\ContainerInventory;

/**
 * The container inventory's detail view: the `--id=` form the
 * analysis CLI answers -- one identifier's full entry, every field
 * the inventory carries, the value shape's held type named.
 *
 * Kept its own archeology-sized unit beside the table form
 * ({@see ContainerInventoryText}): the two views read the same
 * entry, each owning its own words.
 */
final readonly class ContainerInventoryDetail
{
    /**
     * One identifier's full entry. A queried identifier the boot
     * declared no service for refuses ({@see ToolingException}) --
     * the analysis was asked a question the configuration does not
     * answer, and the caller's exit code must say so.
     */
    public static function rendered(ContainerInventory $inventory, string $identifier): string
    {
        $inventoryEntry = $inventory->entry($identifier);

        if ($inventoryEntry === null) {
            throw new ToolingException("The boot declared no service '{$identifier}'.");
        }

        $lines = [
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
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $values
     */
    private static function listed(array $values): string
    {
        return $values === [] ? '(none)' : implode(', ', $values);
    }
}
