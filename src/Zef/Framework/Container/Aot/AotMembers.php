<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact entry members' readers: the two member shapes the
 * build-kind entries carry -- a non-empty string and a list -- read
 * under the one guard each spelling shares, so every entry unit reads
 * its members through the same narrow door instead of re-spelling the
 * guards (the refusals staying {@see AotRefusals'} alone, the entry's
 * identifier in every one).
 */
final readonly class AotMembers
{
    /**
     * Reads one member that must be a non-empty string.
     *
     * @param array<array-key, mixed> $entry  the entry carrying the member
     * @param string                  $member the member's own name
     */
    public static function string(int|string $id, array $entry, string $member): string
    {
        $spelling = $entry[$member];

        if (!\is_string($spelling) || $spelling === '') {
            throw AotRefusals::entryMemberString($id, $member, $spelling);
        }

        return $spelling;
    }

    /**
     * Reads one member that must be a list.
     *
     * @param array<array-key, mixed> $entry  the entry carrying the member
     * @param string                  $member the member's own name
     *
     * @return list<mixed>
     */
    public static function list(int|string $id, array $entry, string $member): array
    {
        $list = $entry[$member];

        if (!\is_array($list) || !array_is_list($list)) {
            throw AotRefusals::entryMemberList($id, $member, $list);
        }

        return $list;
    }
}
