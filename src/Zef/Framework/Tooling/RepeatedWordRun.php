<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Finds the first run of words that repeats inside one table cell.
 *
 * The phrase-duplication rule ({@see RoadmapPhraseRules}) is the only consumer.
 * The search is linear in the cell's word count: it walks the cell once and
 * remembers every {@see MIN_RUN}-word window it has seen, so the first window
 * that appears twice is the duplicated phrase.
 */
final class RepeatedWordRun
{
    /**
     * The shortest repeated run of words that counts as a duplicated phrase.
     * Six words long enough that ordinary prose never trips it -- a sentence
     * may legitimately reuse a short run such as "found / notFound /
     * methodNotAllowed with" when it describes two different things -- and
     * short enough to catch a copy/pasted clause such as "the PSR-7 `Uri` value
     * object".
     */
    private const int MIN_RUN = 6;

    /**
     * The first run of {@see MIN_RUN} words that occurs at least twice in the
     * cell, or null when no such run repeats.
     */
    public static function first(string $cell): ?string
    {
        $words = self::words($cell);
        $seen = [];
        $limit = \count($words) - self::MIN_RUN;

        for ($start = 0; $start <= $limit; ++$start) {
            $gram = implode(' ', \array_slice($words, $start, self::MIN_RUN));

            if (isset($seen[$gram])) {
                return $gram;
            }

            $seen[$gram] = true;
        }

        return null;
    }

    /**
     * The comparable words of a cell: lower-cased tokens of letters, digits and
     * the punctuation that carries meaning inside a phrase.
     *
     * @return list<string>
     */
    private static function words(string $cell): array
    {
        preg_match_all('/[a-z0-9#`_.\/\\\-]+/', mb_strtolower($cell), $matches);

        return $matches[0];
    }
}
