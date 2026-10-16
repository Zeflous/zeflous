<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The bounded span queue: the in-process buffer between the spans
 * that end and the batches the retry lane delivers -- extracted
 * from {@see BatchSpanProcessor} when the archaeology gate demanded
 * the split, the same extraction the correlation family gave its
 * inbound pair: the queue's own bound and its batch splicing are
 * one responsibility, the attempt discipline and the shutdown
 * ordering are another.
 *
 * The queue is bounded because a processor is a buffer for a
 * delivery lane that is slower than the requests feeding it: an
 * unbounded queue under load is a memory leak the observability
 * subsystem would inflict on the very application it instruments,
 * so the honest posture is a named bound and a silent drop beyond
 * it -- a dropped span is a lost measurement, never a lost request.
 */
final class SpanBatchQueue
{
    /** @var list<SpanData> the completed spans awaiting delivery */
    private array $spans = [];

    /**
     * @param int $capacity the queue's bound -- spans beyond it are
     *                      dropped, never queued
     *
     * @throws InvalidArgumentException when the bound is below one
     */
    public function __construct(
        private readonly int $capacity,
    ) {
        if ($capacity < 1) {
            throw new InvalidArgumentException('maxQueueSize must be >= 1.');
        }
    }

    /**
     * One completed record joins the queue, unless the queue is
     * full -- either way the span is already finished and needs no
     * answer: the drop beyond the bound is silent by design.
     */
    public function enqueue(SpanData $spanData): void
    {
        if (\count($this->spans) >= $this->capacity) {
            return;
        }

        $this->spans[] = $spanData;
    }

    /**
     * Answers the next delivery batch: at most the batch size,
     * whatever remains when the queue is shorter than that -- the
     * splice is the queue's own, so the empty array is the queue's
     * own answer for "nothing left".
     *
     * @return list<SpanData> the batch one delivery call carries
     */
    public function take(int $batchSize): array
    {
        $batch = \array_slice($this->spans, 0, $batchSize);
        $this->spans = \array_slice($this->spans, $batchSize);

        return $batch;
    }
}
