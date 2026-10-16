<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * The batch delivery's retry lane: the unit that owns one batch's
 * attempt discipline -- the export attempt, the failure's triage,
 * the backoff the family's policy declares between the retries it
 * earns, and the exhaustion that loses the batch -- extracted from
 * the processor's drain the same way the correlation family
 * extracted its inbound pair: the drain's queue-splicing and the
 * lane's attempt-discipline are different responsibilities with
 * different difficulty budgets, and the campaign's own porting
 * notes named the seam ("the retry lane will arrive with its own
 * knobs, on this same seam").
 *
 * The triage reads the failure's own type alone (KDD-OB23): the
 * plain {@see InvalidArgumentException} an exporter raises for an
 * answer no retry addresses is the permanent rejection -- one
 * attempt, no wait, the batch is lost -- and every other throwable
 * (the lane's own {@see OtlpExporterException}, the runtime's
 * socket failures) is the transient failure the declared backoff
 * earns retries for, until the count is spent and the batch is
 * lost while the drain walks on.
 *
 * The wait between retries reads the sleeper seam, never the
 * engine's own blocking call inline: the processor wires the
 * blocking closure at construction, the suite wires its recording
 * double, and the lane's blocking is observable -- the same seam
 * discipline the clock read before it ({@see TelemetryClockInterface}).
 */
final readonly class BatchRetryLane
{
    /**
     * @param SpanExporterInterface $spanExporter       the sink the batches drain to
     * @param RetryBackoffPolicy    $retryBackoffPolicy the lane's own knobs --
     *                                                  the zero-retry policy is the
     *                                                  one-attempt posture
     * @param Closure(int): void    $sleeper            the wait seam the backoff
     *                                                  reads -- the blocking closure
     *                                                  by default, a recording double
     *                                                  in the suite
     */
    public function __construct(
        private SpanExporterInterface $spanExporter,
        private RetryBackoffPolicy $retryBackoffPolicy,
        private Closure $sleeper,
    ) {
    }

    /**
     * Delivers one batch under the lane's own discipline: the
     * attempt, the failure's triage, the earned backoff between
     * retries, and the loss the exhaustion or the permanent
     * rejection answers -- the caller's drain walks on either way,
     * because a lost batch is a lost measurement, never a lost
     * process.
     *
     * @param list<SpanData> $batch the batch one delivery call carries
     */
    public function deliver(array $batch): void
    {
        $retryIndex = 0;

        while (true) {
            try {
                $this->spanExporter->export($batch);

                break;
            } catch (InvalidArgumentException) {
                // The permanent rejection: no retry addresses it,
                // the batch is lost, and the drain continues.
                break;
            } catch (Throwable) {
                if (!$this->retryBackoffPolicy->shouldRetry($retryIndex)) {
                    break;
                }

                ($this->sleeper)($this->retryBackoffPolicy->delayMs($retryIndex));
                ++$retryIndex;
            }
        }
    }
}
