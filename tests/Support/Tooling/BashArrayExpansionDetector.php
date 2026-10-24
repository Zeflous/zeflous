<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Detects the bash word-splitting defect in one workflow file.
 *
 * A bash array whose elements contain spaces (the strict lane names, for
 * example "CI Static") must be expanded as `"${name[@]}"`. A bare expansion
 * answers only the first element and then word-splits it, so every lane
 * lookup resolves to a name that does not exist and a polling loop spins
 * until its deadline -- the "waiting" symptom this detector exists to
 * prevent. Both spellings of the defect are read: the braced `${name}` and
 * the unbraced `$name`, quoted or not, and an array is recognised however
 * it grew -- `name=(...)` and the append-only `name+=(...)` alike.
 *
 * Comments are stripped before matching so that text which merely MENTIONS
 * a bare expansion is never mistaken for the defect itself: full-line
 * comments and a trailing comment after whitespace both go. The strip is
 * positional, not lexical -- a `#` inside a quoted string survives if it is
 * not preceded by whitespace, and one that is preceded by whitespace inside
 * a string is stripped anyway; the corpus pins this as the accepted
 * approximation, because a comment grammar that is also quote-aware needs
 * a bash tokenizer rather than a line discipline.
 *
 * The detection-scope corpus ({@see BashArrayExpansionScopeCorpusTest},
 * issue #576) enumerates the escape forms this grammar answers; the corpus
 * counts are the ratchet that keeps further changes conscious.
 */
final readonly class BashArrayExpansionDetector
{
    private const string DECLARED_ARRAY = '/([A-Za-z_]\w*)\+?=\(/';

    private const string BARE_EXPANSION = '/\$\{([A-Za-z_]\w*)\}(?!\[)/';

    private const string UNBRACED_EXPANSION = '/\$([A-Za-z_]\w*)/';

    private const string COMMENT_LINE = '/^[ \t]*#.*$|(?<=[ \t])#[^\n]*/m';

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
        preg_match_all(self::BARE_EXPANSION, $code, $braced);
        preg_match_all(self::UNBRACED_EXPANSION, $code, $unbraced);

        $expanded = array_intersect($declarations[1], [...$braced[1], ...$unbraced[1]]);

        if ($expanded === []) {
            return [];
        }

        return [\sprintf('%s expands a bash array without [@] (word-splitting).', $workflow)];
    }

    private static function withoutComments(string $contents): string
    {
        return preg_replace(self::COMMENT_LINE, '', $contents) ?? '';
    }
}
