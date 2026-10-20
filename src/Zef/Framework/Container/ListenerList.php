<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One listener kind's list parse (the {@see DecoratorEntries} posture,
 * under the kind's own label and budget): a positional list of entries
 * parsed by {@see InterceptionEntryParser::listener()}, bounded at the
 * reference container's own 32-listener budget -- a configuration
 * generation mistake fails the boot with a named overflow instead of
 * an unbounded firing loop.
 */
final readonly class ListenerList
{
    /** The hard bound on declared listeners of the kind. */
    public const int BUDGET = 32;

    /**
     * The kind's validated entries, in declaration order.
     *
     * @return list<InterceptionEntry>
     */
    public static function parsed(string $key, string $kind, mixed $list): array
    {
        if ($list === null) {
            return [];
        }

        if (!\is_array($list) || !array_is_list($list)) {
            throw InterceptionRefusals::listenerListNotAList($key, $kind, $list);
        }

        if (\count($list) > self::BUDGET) {
            throw InterceptionRefusals::listenerBudgetExceeded($kind, self::BUDGET);
        }

        return array_map(
            static fn (int $position, mixed $entry): InterceptionEntry => InterceptionEntryParser::listener(
                $key . '.' . $kind . '.' . $position,
                $entry,
            ),
            array_keys($list),
            $list,
        );
    }
}
