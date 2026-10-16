<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;
use Throwable;

/**
 * The completed-span processor: the bounded queue between the spans
 * that end and the exporter that delivers them -- batches in,
 * best-effort out.
 *
 * The queue is bounded ({@see self::$maxQueueSize}) because a
 * processor is an in-process buffer for a delivery lane that is
 * slower than the requests feeding it: an unbounded queue under
 * load is a memory leak the observability subsystem would inflict
 * on the very application it instruments, so the honest posture is
 * a named bound and a silent drop beyond it -- a dropped span is a
 * lost measurement, never a lost request.
 *
 * The delivery is best-effort, single-attempt: an exporter that
 * throws loses its batch and the drain continues -- the tracing
 * core must never take the request path down with it, because every
 * consumer of a span sits inside the application's own work. The
 * reference's retry-with-backoff (its {@see Throwable} arm looped
 * over an environment-shaped retry policy, and its permanent-
 * rejection arm distinguished {@see InvalidArgumentException}) is
 * the campaign's hygiene-and-export lane, a later family: this core
 * ships the one-attempt posture the bounded queue already implies
 * -- a queue that cannot hold the backlog cannot honestly promise
 * to redeliver it either -- and the retry lane will arrive with its
 * own knobs, on this same seam.
 *
 * The flush is inert after shutdown by construction, not by guard:
 * the shutdown drains the queue and the closed flag refuses every
 * later enqueue, so there is nothing a post-shutdown flush could
 * reach -- the reference guarded it explicitly because its
 * deadline-bounded drain could leave a residue, and this core's
 * full drain cannot.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Application/Observability/BatchSpanProcessor.php`): the bounded
 * queue, the batch splice and the shutdown ordering move verbatim;
 * the environment-read retry policy, the drain deadline and the
 * sleep seam are the sequencing deviations the KDD log records.
 */
final class BatchSpanProcessor
{
    /** @var list<SpanData> the completed spans awaiting delivery */
    private array $queue = [];

    private bool $shutdown = false;

    /**
     * @param SpanExporterInterface $spanExporter the sink the batches
     *                                            drain to
     * @param int                   $maxQueueSize the queue's bound --
     *                                            spans beyond it are
     *                                            dropped, never queued
     * @param int                   $batchSize    the largest batch one
     *                                            delivery call carries
     *
     * @throws InvalidArgumentException when either bound is below one
     */
    public function __construct(
        private readonly SpanExporterInterface $spanExporter,
        private readonly int $maxQueueSize = 2048,
        private readonly int $batchSize = 256,
    ) {
        if ($maxQueueSize < 1) {
            throw new InvalidArgumentException('maxQueueSize must be >= 1.');
        }

        if ($batchSize < 1) {
            throw new InvalidArgumentException('batchSize must be >= 1.');
        }
    }

    /**
     * The end-of-span handoff: one completed record joins the queue,
     * unless the queue is full or the processor has closed -- either
     * way the span is already finished and needs no answer.
     */
    public function onEnd(SpanData $spanData): void
    {
        if ($this->shutdown || \count($this->queue) >= $this->maxQueueSize) {
            return;
        }

        $this->queue[] = $spanData;
    }

    /**
     * Delivers everything queued, in batches of at most {@see
     * self::$batchSize}, best-effort: a failed delivery loses its
     * batch and the drain continues.
     */
    public function flush(): void
    {
        $this->drain();
    }

    /**
     * Closes the processor: drains whatever remains (best-effort,
     * the same single-attempt posture), refuses every later enqueue,
     * and shuts the exporter down exactly once -- an exporter that
     * throws on shutdown takes nothing else with it, for the same
     * reason the drain swallows: the core never takes the process
     * down with it.
     */
    public function shutdown(): void
    {
        if ($this->shutdown) {
            return;
        }

        $this->drain();
        $this->shutdown = true;

        try {
            $this->spanExporter->shutdown();
        } catch (Throwable) {
            // The sink failed to close: the drain already completed,
            // and a failing close takes nothing else with it -- the
            // same one-attempt posture the drain itself carries.
        }

        $this->queue = [];
    }

    /**
     * The drain itself: splice the queue into delivery batches until
     * it is empty, one attempt per batch.
     */
    private function drain(): void
    {
        while ($this->queue !== []) {
            /** @var list<SpanData> $batch */
            $batch = array_splice($this->queue, 0, min($this->batchSize, \count($this->queue)));

            try {
                $this->spanExporter->export($batch);
            } catch (Throwable) {
                // The batch is lost: the drain continues, because the
                // tracing core must never take the work it measures
                // down with it.
            }
        }
    }
}
