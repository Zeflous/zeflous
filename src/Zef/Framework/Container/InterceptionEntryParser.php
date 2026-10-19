<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception entry grammar's parse pipeline: the map shape and
 * the known keys -- the {@see DecoratorEntryParser} posture, split
 * from the value object it answers so the grammar's shape parsers
 * never reference the entry type back (a vocabulary that closes on
 * itself is a dependency cycle the boot's own gate refuses).
 *
 * The two entry points are the two grammars: the interceptor entry
 * carries `priority` ({@see InterceptionPriorities} -- the onion's
 * order), the listener entries refuse it (their firing order is the
 * declaration order). The proven entry's own composition is
 * {@see InterceptionEntryComposer}'s.
 */
final readonly class InterceptionEntryParser
{
    /**
     * Parses one `interceptors` entry, fail-closed.
     *
     * @param mixed $entry the raw interceptor map
     */
    public static function interceptor(string $label, mixed $entry): InterceptionEntry
    {
        if (\is_array($entry)) {
            InterceptionEntryKeys::unknownInterceptorChecked($label, $entry);

            return InterceptionEntryComposer::composed(
                $label,
                $entry,
                InterceptionPriorities::validated($label, $entry),
            );
        }

        throw InterceptionRefusals::entryNotAMap($label, $entry);
    }

    /**
     * Parses one listener entry (either kind), fail-closed.
     *
     * @param mixed $entry the raw listener map
     */
    public static function listener(string $label, mixed $entry): InterceptionEntry
    {
        if (\is_array($entry)) {
            InterceptionEntryKeys::unknownListenerChecked($label, $entry);

            return InterceptionEntryComposer::composed($label, $entry, 0);
        }

        throw InterceptionRefusals::entryNotAMap($label, $entry);
    }
}
