<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The `console` map's family tree: the census of legitimate families,
 * shared by every family's reader so an unknown sibling halts the ask.
 *
 * The seam mirrors the events lane's own ({@see EventFamilies}) because
 * the maps are the same shape -- a family block whose children are
 * families, each family's own knobs read strictly by its own fold. The
 * tree is family-additive: the console lane's first family (`commands`,
 * the command registry's declaration table, the module system's CLI
 * extension point's joined key) joins here, and the known list it finds
 * already guards the map for every boot that resolves a family through
 * it.
 */
final class ConsoleFamilies
{
    /**
     * The legitimate children of the `console` map, in landing order.
     */
    private const array FAMILIES = ['commands'];

    /**
     * Reads one family's subtree out of the merged `console` map.
     *
     * @param mixed  $console the `console` map as the frozen repository
     *                        carries it: a family map, or null when the whole
     *                        key is absent
     * @param string $family  the family whose subtree is read
     *
     * @return mixed the family's own value, or null when the family is
     *               absent -- the silent posture the declaration reads
     *
     * @throws ConfigException when the map carries an unknown family,
     *                         or is neither a map nor null
     */
    public static function subtree(mixed $console, string $family): mixed
    {
        if ($console === null) {
            return null;
        }

        if (!\is_array($console)) {
            throw new ConfigException(
                'The console configuration must be a map, ' . get_debug_type($console) . ' given.',
            );
        }

        foreach (array_keys($console) as $child) {
            if (!\is_string($child) || !\in_array($child, self::FAMILIES, true)) {
                throw new ConfigException(
                    "The console configuration carries the unknown knob '"
                    . (\is_string($child) ? $child : get_debug_type($child))
                    . "' (known knobs: " . implode(', ', self::FAMILIES) . ').',
                );
            }
        }

        return $console[$family] ?? null;
    }
}
