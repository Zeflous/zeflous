<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use InvalidArgumentException;

/**
 * The `bin/zef errors:report` verb: the local JSONL error log summarised
 * by level and code -- the operator's own diagnosis surface, lifted out
 * of the CLI's joined branch (#717's structural step).
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0` the
 * answer (including the honest empty answer for a log that does not
 * exist -- nothing to report is a report, not a failure), `1` an
 * operational refusal (the layout refusing the derived address, an
 * unreadable or unopenable log), `2` a usage defect (an invalid
 * request-id filter, a symlinked log path). Every refusal names itself
 * on STDERR; the report lands on STDOUT with control bytes stripped from
 * every log-supplied field ({@see ErrorsDisplayText::of()}) so a stored
 * level or code cannot smuggle an escape sequence into the terminal --
 * all gathered as a {@see CommandOutcome} so the verb stays pure and the
 * dispatch owns the terminals.
 */
final class ErrorsReport
{
    /**
     * Runs the verb over the arguments the operator spelled after it.
     *
     * @param list<string> $arguments the raw arguments after the verb's
     *                                own name: an optional positional log
     *                                path and an optional
     *                                `--request-id=<id>` filter
     */
    public static function run(array $arguments): CommandOutcome
    {
        try {
            [$requestIdFilter, $positionalPath] = ErrorsLogArguments::parsed($arguments);
            $path = ErrorsLogPath::resolved($positionalPath);
        } catch (InvalidArgumentException $layoutRefusal) {
            return CommandOutcome::refused(
                1,
                'The error log storage layout refused: ' . $layoutRefusal->getMessage() . "\n",
            );
        } catch (ToolingException $usageDefect) {
            return CommandOutcome::refused(2, $usageDefect->getMessage() . "\n");
        }

        if (!is_file($path) || !is_readable($path)) {
            return CommandOutcome::answered("No readable error log found: {$path}\n");
        }

        $counts = [];
        $top = [];
        $total = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return CommandOutcome::refused(1, "Unable to read error log: {$path}\n");
        }

        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);
            if (!\is_array($entry)) {
                continue;
            }

            $storedContext = $entry['context'] ?? null;
            $context = \is_array($storedContext) ? $storedContext : [];
            if ($requestIdFilter !== null && ($context['request_id'] ?? null) !== $requestIdFilter) {
                continue;
            }

            ++$total;
            $storedLevel = $entry['level'] ?? null;
            $rawLevel = \is_string($storedLevel) ? $storedLevel : 'unknown';
            $level = ErrorsDisplayText::of($rawLevel);
            $counts[$level] = ($counts[$level] ?? 0) + 1;
            $storedCode = $context['exception_code'] ?? null;
            $storedClass = $context['exception_class'] ?? null;
            $rawCode = \is_string($storedCode)
                ? $storedCode
                : (\is_string($storedClass) ? $storedClass : 'unknown');
            $code = ErrorsDisplayText::of($rawCode);
            $top[$code] = ($top[$code] ?? 0) + 1;
        }

        fclose($handle);

        arsort($counts);
        arsort($top);
        $filterLabel = $requestIdFilter === null ? '' : " (request_id={$requestIdFilter})";

        return CommandOutcome::answered(
            "Error log report: {$path}{$filterLabel}\nTotal records: {$total}\n\nBy level:\n"
            . self::rows($counts, '%-10s')
            . "\nTop error codes/classes:\n"
            . self::rows(\array_slice($top, 0, 10, true), '%-40s'),
        );
    }

    /**
     * The report's own row spelling: one count row per entry, the label
     * padded the width the family's display answers.
     *
     * @param array<string, int> $counts the counted entries, already ordered
     * @param non-empty-string   $format the sprintf label format the
     *                                   family's display answers
     */
    private static function rows(array $counts, string $format): string
    {
        $rows = '';
        foreach ($counts as $label => $count) {
            $rows .= \sprintf("  {$format} %d\n", $label, $count);
        }

        return $rows;
    }
}
