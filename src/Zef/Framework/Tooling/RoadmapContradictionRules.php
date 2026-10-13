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
            } elseif ($inSection) {
                $failure = self::bulletFailure($name, $index, $line, $sectionRows);

                if ($failure !== null) {
                    $failures[] = $failure;
                }
            }
        }

        return $failures;
    }

    /**
     * The failure for one bullet line, or null when the line is not a bullet
     * that declares an area "not started" while its section already has merged
     * rows.
     *
     * @param array<string, int> $sectionRows
     */
    private static function bulletFailure(string $name, int $index, string $line, array $sectionRows): ?string
    {
        $bullet = self::bullet($line);

        if ($bullet === null) {
            return null;
        }

        [$area, $claim] = $bullet;

        if (!self::declaresUnstarted($claim)) {
            return null;
        }

        $section = self::matchingSection($area, $sectionRows);

        if ($section === null || $sectionRows[$section] === 0) {
            return null;
        }

        return \sprintf(
            '%s:%d declares "%s" not started, but its section has %d merged row(s).',
            $name,
            $index + 1,
            $area,
            $sectionRows[$section],
        );
    }

    /**
     * The area and claim of a `- **Area** — claim` bullet, or null when the
     * line is not such a bullet. The opening marker is checked as a string
     * before the body is parsed, so the pattern needs no leading context.
     *
     * @return null|array{0: string, 1: string}
     */
    private static function bullet(string $line): ?array
    {
        if (!str_starts_with($line, '- **')) {
            return null;
        }

        if (preg_match('/\*\*(.+?)\*\*\s*[—–-]\s*(.+)/u', $line, $matches) !== 1) {
            return null;
        }

        return [self::trim($matches[1]), self::trim($matches[2])];
    }

    /**
     * Whether the claim opens with the "not started" marker. {@see bullet()}
     * has already trimmed the claim's edges, so a case-insensitive prefix test
     * is enough; the fold is ASCII because the marker is ASCII and no non-ASCII
     * character lower-cases into it.
     */
    private static function declaresUnstarted(string $claim): bool
    {
        return mb_stripos($claim, self::UNSTARTED_MARKER) === 0;
    }

    /**
     * Trims a captured fragment, including the non-breaking space the documents
     * use after the em-dash (which `mb_trim`'s default charlist does not cover).
     */
    private static function trim(string $value): string
    {
        return mb_trim($value, " \t\r\n\u{00A0}");
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
