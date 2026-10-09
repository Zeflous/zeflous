<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Detects the bash word-splitting defect in one workflow file.
 *
 * A bash array whose elements contain spaces (the strict lane names, for
 * example "CI Static") must be expanded as `"${name[@]}"`. A bare `${name}`
 * expands only the first element and then word-splits it, so every lane
 * lookup resolves to a name that does not exist and a polling loop spins
 * until its deadline -- the "waiting" symptom this detector exists to
 * prevent.
 *
 * Full-line comments are stripped before matching, so a comment that merely
 * MENTIONS a bare expansion (for example the explanatory note in
 * ci-strict.yml) is never mistaken for the defect itself.
 */
final readonly class BashArrayExpansionDetector
{
    private const string DECLARED_ARRAY = '/([A-Za-z_]\w*)=\(/';

    private const string BARE_EXPANSION = '/\$\{([A-Za-z_]\w*)\}(?!\[)/';

    private const string COMMENT_LINE = '/^[ \t]*#.*$/m';

    /**
     * The word-splitting failure for this workflow: a list with one message,
     * or an empty list when the file is clean.
     *
     * @return list<string>
     */
    public static function failures(string $workflow, string $contents): array
    {
        $code = self::withoutComments($contents);

        preg_match_all(self::DECLARED_ARRAY, $code, $declarations);
        preg_match_all(self::BARE_EXPANSION, $code, $expansions);

        if (array_intersect($declarations[1], $expansions[1]) === []) {
            return [];
        }

        return [\sprintf('%s expands a bash array without [@] (word-splitting).', $workflow)];
    }

    private static function withoutComments(string $contents): string
    {
        return preg_replace(self::COMMENT_LINE, '', $contents) ?? '';
    }
}
