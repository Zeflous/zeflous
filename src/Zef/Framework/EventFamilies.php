<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The `events` map's family tree: the census of legitimate
 * families, shared by every family's reader so an unknown sibling
 * halts the ask.
 *
 * The seam mirrors the HTTP lane's own ({@see Http\HttpFamilies})
 * because the maps are the same shape -- a family block whose
 * children are families, each family's own knobs read strictly by
 * its own fold -- and it lands for the same reason the session
 * family's arrival forced the `http` census out of the first
 * factory that needed it: the events map's first family
 * (`deferred`, the durable delivery store's wired default) carries
 * enough knobs of its own that the declaration that reads them
 * answers the architecture ceiling unless the family census is a
 * fold of its own. The tree is family-additive: the module
 * system's `events` reader (the named future three decision
 * records carry) joins its family here, and the known list it
 * finds already guards the map for every boot that resolves a
 * family through it.
 */
final class EventFamilies
{
    /**
     * The legitimate children of the `events` map, in landing
     * order.
     */
    private const array FAMILIES = ['deferred'];

    /**
     * Reads one family's subtree out of the merged `events` map.
     *
     * @param mixed  $events the `events` map as the frozen repository
     *                       carries it: a family map, or null when the whole
     *                       key is absent
     * @param string $family the family whose subtree is read
     *
     * @return mixed the family's own map, or null when the family is
     *               absent -- the silent posture the declaration reads
     *
     * @throws ConfigException when the map carries an unknown family,
     *                         or is neither a map nor null
     */
    public static function subtree(mixed $events, string $family): mixed
    {
        if ($events === null) {
            return null;
        }

        if (!\is_array($events)) {
            throw new ConfigException(
                'The events configuration must be a map, ' . get_debug_type($events) . ' given.',
            );
        }

        foreach (array_keys($events) as $child) {
            if (!\is_string($child) || !\in_array($child, self::FAMILIES, true)) {
                throw new ConfigException(
                    "The events configuration carries the unknown knob '"
                    . (\is_string($child) ? $child : get_debug_type($child))
                    . "' (known knobs: " . implode(', ', self::FAMILIES) . ').',
                );
            }
        }

        return $events[$family] ?? null;
    }
}
