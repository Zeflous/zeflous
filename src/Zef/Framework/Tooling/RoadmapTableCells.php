<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The shared cell helpers of the roadmap document gate.
 *
 * Every roadmap rule reads the same Markdown table shape, so the cell split,
 * the separator test and the label normalisation live here once instead of
 * being copied into each rule. Keeping them in one small class also keeps each
 * rule class under the architecture gate's difficulty budget
 * (`php-codearch-config.yaml`, `tooComplex.difficulty: 20`).
 */
final class RoadmapTableCells
{
    /**
     * The trimmed cells of a table row.
     *
     * The trim mask carries the carriage return: a Windows contributor
     * without `.gitattributes` commits CRLF line endings, and a trailing
     * `\r` would otherwise survive the row's edges -- an extra empty cell
     * that silently un-separates every separator row (the count rules
     * judge nothing, the commit rules false-fail the separator as data).
     *
     * @return list<string>
     */
    public static function cells(string $line): array
    {
        return array_map(mb_trim(...), explode('|', mb_trim($line, "| \t\r")));
    }

    /**
     * Whether every cell is a Markdown separator cell (`---`, `:--`, `--:`).
     *
     * @param list<string> $cells
     */
    public static function isSeparator(array $cells): bool
    {
        return array_all($cells, static fn (string $cell): bool => preg_match('/^:?-{3,}:?$/', $cell) === 1);
    }

    /**
     * A label reduced to its comparable form: emphasis markers and every
     * non-alphanumeric character removed, lower-cased.
     */
    public static function normalise(string $label): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($label));
    }

    /**
     * The label's first word, lower-cased and stripped of punctuation.
     */
    public static function firstWord(string $label): string
    {
        if (preg_match('/[a-z0-9]+/', mb_strtolower($label), $matches) !== 1) {
            return '';
        }

        return $matches[0];
    }
}
