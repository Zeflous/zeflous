<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Parses a `- **Area** — claim` bullet of a roadmap document.
 *
 * The cross-section contradiction rule ({@see RoadmapContradictionRules}) is the
 * only consumer. It lives in its own class for the same reason the rule classes
 * do: the repository's architecture gate (`php-codearch-config.yaml`,
 * `tooComplex.difficulty: 20`) scores a class that carries several operator
 * varieties above the ceiling, so each file owns one focused review surface.
 *
 * A bullet is recognised only when the line opens with the `- **` marker and the
 * body matches `**Area** <dash> claim`; the opening marker is checked as a string
 * before the body is parsed, so the pattern needs no leading context.
 */
final class RoadmapNotStartedBullet
{
    /**
     * The marker that declares an area unstarted when it opens the claim.
     */
    private const string UNSTARTED_MARKER = 'not started';

    /**
     * The area and claim of a `- **Area** — claim` bullet, or null when the line
     * is not such a bullet.
     *
     * @return null|array{0: string, 1: string}
     */
    public static function parse(string $line): ?array
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
     * Whether the claim opens with the "not started" marker. {@see parse()} has
     * already trimmed the claim's edges, so a case-insensitive prefix test is
     * enough; the fold is ASCII because the marker is ASCII and no non-ASCII
     * character lower-cases into it.
     */
    public static function declaresUnstarted(string $claim): bool
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
}
