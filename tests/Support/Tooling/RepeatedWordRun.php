<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Finds the first run of words that repeats inside one table cell.
 *
 * The phrase-duplication rule ({@see RoadmapPhraseRules}) is the only consumer.
 * The search walks the cell's {@see MIN_RUN}-word windows in order and returns
 * the first window that has already been seen, so the copy/pasted clause is
 * reported at the earliest window that repeats. A window is only considered
 * when {@see MIN_RUN} full words remain, so a short trailing window can never
 * be read as a repeated phrase.
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
        $start = 0;

        while (true) {
            $window = \array_slice($words, $start, self::MIN_RUN);

            if (\count($window) < self::MIN_RUN) {
                return null;
            }

            $gram = implode(' ', $window);

            if (isset($seen[$gram])) {
                return $gram;
            }

            $seen[$gram] = $start;
            ++$start;
        }
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
