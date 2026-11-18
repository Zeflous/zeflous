<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

/**
 * The `middleware.stack` entry's key census: the map may carry only the
 * vocabulary `MiddlewareDefinition` reads, and an unknown key halts the
 * parse with the known list spelled out (issue #785's finding 3).
 *
 * The doctrine is {@see \Zef\Framework\Http\StrictKnobs}'s own -- a
 * typo'd key must never fall through to the default posture unnoticed,
 * the operator believing their knob is live while the default's is --
 * closed here at the entry level: a `prioriti` typo is refused with
 * the known keys spelled out instead of silently defaulting the entry
 * to priority 0. The census stands as its own unit the way the
 * strict-knob census does (and the way the boot-graph's shadow
 * judgment does, {@see
 * \Zef\Framework\Container\FactoryShadows::target()}): the entry
 * grammar's one question, kept out of the definition's own metrics.
 */
final class StrictEntryKeys
{
    /** The entry map's whole vocabulary, as the rejection spells it. */
    private const array KNOWN_KEYS = ['service', 'id', 'priority'];

    /**
     * Rejects the entry's unknown keys with the known list spelled out.
     *
     * @param array<array-key, mixed> $entry   the stack entry's map
     * @param string                  $service the entry's parsed service
     *                                         identifier, as the rejection
     *                                         names it
     *
     * @throws MiddlewareException when the entry carries an unknown key
     */
    public static function rejectUnknown(array $entry, string $service): void
    {
        foreach (array_keys($entry) as $key) {
            if (\in_array($key, self::KNOWN_KEYS, true)) {
                continue;
            }

            $spelled = \is_string($key) ? $key : get_debug_type($key);

            throw new MiddlewareException(
                "Middleware '{$service}' carries the unknown key '{$spelled}' (known keys: service, id, priority).",
            );
        }
    }
}
