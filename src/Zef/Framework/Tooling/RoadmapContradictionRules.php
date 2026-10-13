<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The cross-section contradiction rule of the roadmap document gate.
 *
 * It lives in its own class for the same reason {@see RoadmapTableRules},
 * {@see RoadmapCommitRules}, {@see RoadmapCountRules} and
 * {@see RoadmapPhraseRules} do: the repository's architecture gate
 * (`php-codearch-config.yaml`, `tooComplex.difficulty: 20`) scores a class that
 * carries several operator varieties above the ceiling, so each file owns one
 * focused review surface.
 *
 * The rule closes the class of defect that shipped a `## Not yet done` bullet
 * declaring an area "not started" while the very same file's numbered section
 * already carried merged rows for it (the HTTP Layer bullet said "not started
 * beyond the PSR container shim" while three HTTP pull requests had merged):
 * the structural rules never compare one section against another, so the
 * contradiction passed CI.
 *
 * The rule is deliberately narrow and high-precision. It fires only when a
 * bullet's claim *begins* with the "not started" marker -- i.e. the bullet
 * declares the whole area unstarted -- and the area already has a numbered
 * section with at least one merged row. A bullet that merely lists unstarted
 * sub-features ("handler chain, debug/production modes, observability not
 * started") is not a contradiction and is left alone.
 *
 * The rule is scoped to `ROADMAP-HISTORY.md`, the only document that carries
 * both the `## Not yet done` section and the numbered sections it can
 * contradict.
 */
final class RoadmapContradictionRules
{
    /**
     * The heading that introduces the "not yet done" list.
     */
    private const string NOT_YET_DONE_HEADING = '## Not yet done';

    /**
     * The marker that declares an area unstarted when it opens the claim.
     */
    private const string UNSTARTED_MARKER = 'not started';

    /**
     * Invariant H: a `## Not yet done` bullet that declares an area "not
     * started" must not name an area that already has a numbered section with
     * merged rows.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function unstartedAreasWithMergedWork(string $name, array $lines): array
    {
        $sectionRows = RoadmapSectionRows::counts($lines);
        $failures = [];
        $inSection = false;

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '## ')) {
                $inSection = mb_trim($line) === self::NOT_YET_DONE_HEADING;

                continue;
            }

            if (!$inSection || !str_starts_with($line, '- **')) {
                continue;
            }

            $bullet = self::bullet($line);

            if ($bullet === null) {
                continue;
            }

            [$area, $claim] = $bullet;

            if (!self::declaresUnstarted($claim)) {
                continue;
            }

            $section = self::matchingSection($area, $sectionRows);

            if ($section === null || $sectionRows[$section] === 0) {
                continue;
            }

            $failures[] = \sprintf(
                '%s:%d declares "%s" not started, but its section has %d merged row(s).',
                $name,
                $index + 1,
                $area,
                $sectionRows[$section],
            );
        }

        return $failures;
    }

    /**
     * The area and claim of a `- **Area** — claim` bullet, or null when the
     * line is not such a bullet.
     *
     * @return null|array{0: string, 1: string}
     */
    private static function bullet(string $line): ?array
    {
        if (preg_match('/^- \*\*(.+?)\*\*\s*[—–-]\s*(.+)$/u', $line, $matches) !== 1) {
            return null;
        }

        return [self::trim($matches[1]), self::trim($matches[2])];
    }

    /**
     * Whether the claim opens with the "not started" marker, tolerating the
     * non-breaking space the documents use after the em-dash.
     */
    private static function declaresUnstarted(string $claim): bool
    {
        $pattern = '/^[\s\x{00A0}]*' . preg_quote(self::UNSTARTED_MARKER, '/') . '/u';

        return preg_match($pattern, mb_strtolower($claim)) === 1;
    }

    /**
     * Trims a captured fragment, including the non-breaking space the documents
     * use after the em-dash (which `mb_trim`'s default charlist does not cover).
     */
    private static function trim(string $value): string
    {
        return (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $value);
    }

    /**
     * The normalised title of the numbered section the area names, or null when
     * no section matches. A section matches when its normalised title is a
     * prefix of the normalised area name, so "HTTP Layer" matches the bullet's
     * "HTTP Layer (Zero-Library Shim)".
     *
     * @param array<string, int> $sectionRows
     */
    private static function matchingSection(string $area, array $sectionRows): ?string
    {
        $normalised = RoadmapTableCells::normalise($area);

        foreach (array_keys($sectionRows) as $section) {
            if ($section !== '' && str_starts_with($normalised, $section)) {
                return $section;
            }
        }

        return null;
    }
}
