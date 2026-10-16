<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Closure;
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
 * The retry lane the campaign's hygiene-and-export family lands
 * has arrived, on this seam exactly as the core's own porting note
 * reserved it: a failed delivery now earns the attempts the
 * family's policy ({@see RetryBackoffPolicy}) declares, waiting
 * the backoff the policy computes between them -- and the
 * distinction the drain reads is the failure's own type ({@see
 * OtlpExporterException} and every other transient failure earn
 * retries; the permanent rejection -- the {@see
 * InvalidArgumentException} an exporter raises for an answer no
 * retry addresses -- does not). The attempt discipline itself
 * lives in the lane's own unit ({@see BatchRetryLane}), extracted
 * when the archaeology gate demanded it -- the queue-splicing and
 * the retry discipline are different responsibilities -- while
 * this processor keeps what it owns: the bounded queue, the batch
 * splice, the shutdown ordering. The one-attempt posture the core
 * shipped is the policy's zero-retry default, unchanged: a queue
 * that cannot hold the backlog cannot honestly promise to
 * redeliver it either, and a deployment that wants the lane to
 * retry writes the knobs ({@see \Zef\Framework\Http\ExportPolicy}),
 * the same fail-closed decision every other family's arm is.
 *
 * The wait between retries reads the sleeper seam, never the
 * engine's own blocking call inline: the processor wires the
 * blocking closure ({@see self::blockMilliseconds()}) into the
 * lane at construction, the suite wires its recording double, and
 * the lane's blocking is observable -- the same seam discipline
 * the clock read before it ({@see TelemetryClockInterface}).
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
 * the retry loop moves from the reference's flush-time
 * environment-shaped policy into the injected one (the drain holds
 * its policy as frozen data, the environment read belongs to the
 * config face), and the drain deadline the reference's shutdown
 * carried stays dropped -- the full drain it wrapped cannot leave
 * the residue a deadline would bound.
 */
final class BatchSpanProcessor
{
    private bool $shutdown = false;

    /** The bounded buffer the ended spans queue into -- the archaeology gate's own split. */
    private readonly SpanBatchQueue $spanBatchQueue;

    /** The batch delivery's own retry lane -- the attempt discipline the drain reads. */
    private readonly BatchRetryLane $batchRetryLane;

    /**
     * @param SpanExporterInterface   $spanExporter       the sink the batches
     *                                                    drain to
     * @param int                     $maxQueueSize       the queue's bound --
     *                                                    spans beyond it are
     *                                                    dropped, never queued
     * @param int                     $batchSize          the largest batch one
     *                                                    delivery call carries
     * @param RetryBackoffPolicy      $retryBackoffPolicy the retry lane's
     *                                                    own knobs -- the
     *                                                    zero-retry policy is
     *                                                    the one-attempt
     *                                                    posture, preserved
     * @param null|Closure(int): void $sleeper            the wait seam the
     *                                                    backoff reads --
     *                                                    the blocking closure
     *                                                    by default, a
     *                                                    recording double in
     *                                                    the suite
     *
     * @throws InvalidArgumentException when either bound is below one
     */
    public function __construct(
        private readonly SpanExporterInterface $spanExporter,
        int $maxQueueSize = 2048,
        private readonly int $batchSize = 256,
        RetryBackoffPolicy $retryBackoffPolicy = new RetryBackoffPolicy(0, 0, 0),
        ?Closure $sleeper = null,
    ) {
        $this->spanBatchQueue = new SpanBatchQueue($maxQueueSize);

        if ($batchSize < 1) {
            throw new InvalidArgumentException('batchSize must be >= 1.');
        }

        $this->batchRetryLane = new BatchRetryLane(
            $spanExporter,
            $retryBackoffPolicy,
            $sleeper ?? $this->blockMilliseconds(...),
        );
    }

    /**
     * The end-of-span handoff: one completed record joins the
     * queue, unless the processor has closed -- either way the
     * span is already finished and needs no answer, and the
     * queue's own bound drops the overflow silently.
     */
    public function onEnd(SpanData $spanData): void
    {
        if ($this->shutdown) {
            return;
        }

        $this->spanBatchQueue->enqueue($spanData);
    }

    /**
     * Delivers everything queued, in batches of at most {@see
     * self::$batchSize}: the queue answers batches until the empty
     * one answers, each delivered under the retry lane's own
     * attempt discipline ({@see BatchRetryLane::deliver()}) -- the
     * lane owns the triage, the backoff and the exhaustion, and a
     * batch the lane cannot deliver is lost while the drain walks
     * on.
     */
    public function flush(): void
    {
        while (($batch = $this->spanBatchQueue->take($this->batchSize)) !== []) {
            $this->batchRetryLane->deliver($batch);
        }
    }

    /**
     * Closes the processor: drains whatever remains (the retry
     * lane's own attempts per batch, the same posture the flush
     * carries), refuses every later enqueue, and shuts the exporter
     * down exactly once -- an exporter that throws on shutdown
     * takes nothing else with it, for the same reason the drain
     * swallows: the core never takes the process down with it. The
     * queue needs no explicit clearing: the drain above already
     * emptied it, and the closed flag refuses every later enqueue.
     */
    public function shutdown(): void
    {
        if ($this->shutdown) {
            return;
        }

        $this->flush();
        $this->shutdown = true;

        try {
            $this->spanExporter->shutdown();
        } catch (Throwable) {
            // The sink failed to close: the drain already completed,
            // and a failing close takes nothing else with it -- the
            // same one-attempt posture the drain itself carries.
        }
    }

    /**
     * The blocking wait the composition seam wires: the engine's
     * own microsecond sleep over the millisecond backoff -- the
     * one place the lane blocks, behind the seam the suite
     * observes.
     */
    private function blockMilliseconds(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
