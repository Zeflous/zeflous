<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The pointer-freshness rule of the roadmap document gate.
 *
 * It lives in its own class for the same reason {@see RoadmapTableRules},
 * {@see RoadmapCommitRules}, {@see RoadmapCountRules} and
 * {@see RoadmapPhraseRules} do: the repository's architecture gate
 * (`php-codearch-config.yaml`, `tooComplex.difficulty: 20`) scores a class that
 * carries several operator varieties above the ceiling, so each file owns one
 * focused review surface.
 *
 * The rule closes the class of defect that let the `Last updated` pointer of
 * `ROADMAP-HISTORY.md` name a commit older than the newest row the file already
 * recorded (the pointer named the #298 row while the #303 row was already
 * present): the structural rules never look at the pointer, so a stale pointer
 * passed CI.
 *
 * The rule is a pure function over the document text -- it needs no git
 * checkout, so it runs identically in a shallow CI clone and in a unit test. It
 * enforces the convention the document has always followed: the pointer names
 * the commit of the newest recorded row, where "newest" is the greatest merge
 * date and, among rows sharing that date, the greatest pull-request number.
 *
 * A document without a pointer line, or without a history table, is not judged
 * (the collector also reads `CONTRIBUTING.md`, and `ROADMAP-STATUS.md` carries
 * no history table).
 */
final class RoadmapPointerRules
{
    /**
     * The pointer line, e.g. `**Last updated:** 2026-10-13 · `main` @ `8da8e96``.
     */
    private const string POINTER_PATTERN = '/\*\*Last updated:\*\*\s+(\d{4}-\d{2}-\d{2})'
        . '\s+·\s+`main`\s+@\s+`([0-9a-f]{7,40})`/';

    /**
     * The pointer a document declares, as `[date, commit]`, or null when it has
     * none.
     *
     * @param list<string> $lines
     *
     * @return null|array{0: string, 1: string}
     */
    public static function pointer(array $lines): ?array
    {
        if (preg_match(self::POINTER_PATTERN, implode("\n", $lines), $matches) !== 1) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    /**
     * Invariant G: the `Last updated` pointer must name the commit of the newest
     * recorded row.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function stalePointers(string $name, array $lines): array
    {
        $pointer = self::pointer($lines);

        if ($pointer === null) {
            return [];
        }

        $newest = RoadmapNewestRow::of(RoadmapHistoryRows::rows($lines));

        if ($newest === null || str_starts_with($newest[2], $pointer[1])) {
            return [];
        }

        return [\sprintf(
            '%s "Last updated" points at `%s`, but the newest recorded row is `%s` (%s).',
            $name,
            $pointer[1],
            $newest[2],
            $newest[0],
        )];
    }
}
