<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

/**
 * The diagnostic lane's repeat ledger: the per-signature suppression
 * window that keeps a repeated diagnostic from amplifying without
 * bound.
 *
 * One native diagnostic is one record; the SAME diagnostic -- the
 * vendor deprecation loop that raises the identical severity,
 * message, file and line ten thousand times in one request -- is one
 * record plus a counted silence: the first occurrence records
 * verbatim, every suppressed repeat increments its signature's
 * count, and every {@see self::CADENCE}-th suppressed repeat lands
 * one summary record whose message names how many more times the
 * engine raised it. The dropped count is explicit on the record, the
 * same honesty the queue's own bound keeps ({@see \Zef\Framework\Telemetry\SpanBatchQueue}:
 * a lost measurement is named, never silently lost).
 *
 * The ledger is bounded ({@see self::MAX_SIGNATURES}): a storm of
 * DISTINCT signatures is not a repeat storm this window can fold, so
 * beyond the bound unknown signatures record verbatim -- the ledger
 * degrades to no-dedup, never to unbounded memory. The window lives
 * inside the request facts memo's lifetime ({@see RequestFactsMemo}):
 * a fresh request opens a fresh ledger, and its first occurrence of
 * any signature records again.
 */
final class DiagnosticRepeats
{
    /** The suppression cadence: every n-th suppressed repeat lands its summary record. */
    private const int CADENCE = 100;

    /** The ledger's bound: distinct signatures beyond it record verbatim. */
    private const int MAX_SIGNATURES = 256;

    /** @var array<string, int> signature => the suppressed repeat count */
    private array $suppressed = [];

    /**
     * One diagnostic's verdict: the message to record -- the first
     * occurrence verbatim, every CADENCE-th suppressed repeat with the
     * suppressed count named -- or null when the diagnostic is a
     * suppressed repeat inside the cadence.
     *
     * @param non-empty-string $level   the PSR-3 level the diagnostic mapped to
     * @param string           $message the engine's diagnostic text
     * @param string           $file    the file the diagnostic names
     * @param int              $line    the line the diagnostic names
     */
    public function verdict(string $level, string $message, string $file, int $line): ?string
    {
        $signature = $level . "\0" . $file . "\0" . $line . "\0" . $message;

        if (!\array_key_exists($signature, $this->suppressed)) {
            if (\count($this->suppressed) >= self::MAX_SIGNATURES) {
                return $message;
            }

            $this->suppressed[$signature] = 0;

            return $message;
        }

        ++$this->suppressed[$signature];

        if ($this->suppressed[$signature] % self::CADENCE === 0) {
            return $message . ' [repeated ' . $this->suppressed[$signature] . ' more times]';
        }

        return null;
    }

    /**
     * Resets the ledger: a fresh request opens a fresh window, and its
     * first occurrence of any signature records again.
     */
    public function reset(): void
    {
        $this->suppressed = [];
    }
}
