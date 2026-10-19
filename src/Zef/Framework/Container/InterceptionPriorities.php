<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interceptor entry's priority grammar (the onion's order, its own
 * vocabulary unit): absent is 0 -- the declaration-order band -- and
 * present must be an integer. The strict integer gate is deliberate: a
 * boolean is not a priority, and a numeric string is a spelling the
 * configuration layer already owns; this knob's grammar names the one
 * type it accepts (a deliberately stricter spelling than the HTTP
 * middleware stack's own `is_numeric` gate -- the container knobs'
 * discipline for a newer knob).
 */
final readonly class InterceptionPriorities
{
    /**
     * The entry's priority, validated.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function validated(string $label, array $entry): int
    {
        $priority = $entry['priority'] ?? 0;

        if (!\is_int($priority)) {
            throw InterceptionRefusals::priorityNotAnInteger($label, $priority);
        }

        return $priority;
    }
}
