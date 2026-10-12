<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The ingress policy's byte budget: how many bytes have already left the
 * stream, and whether more may.
 *
 * Extracted from the {@see LimitedInputStream} wrapper's read path -- the
 * same depth-split the layer's stream engine uses to stay under the
 * architecture gate's difficulty budget. The budget owns three answers:
 * the {@see probe()} (never ask the inner stream for more than one byte
 * past the remaining allowance -- that single extra byte is what tells
 * "exactly at the limit" from "past the limit" without ever buffering
 * more than the policy allows), the {@see refuses()} judgement (a
 * delivered chunk that crosses the limit poisons the counter past it, so
 * every later read fails too), and the positional re-bases ({@see rebase()}
 * and {@see reset()}: a seek or rewind re-charges from the new position,
 * never double-charging bytes the cursor already moved past).
 */
final class BodyBudget
{
    /**
     * The budget's refused marker: the overshoot a failed judgement
     * poisons the counter with, so every later read fails too. A named
     * constant rather than an inline literal -- the marker's value only
     * needs to be past the limit, never a specific depth.
     */
    private const int REFUSED_OVERSHOOT = 1;

    /** The number of bytes observed leaving the inner stream so far. */
    private int $observedBytes = 0;

    /**
     * @param RequestBodyPolicy $requestBodyPolicy the ingress policy the
     *                                             budget serves
     */
    public function __construct(
        private readonly RequestBodyPolicy $requestBodyPolicy,
    ) {
    }

    /**
     * Answers the effective size a PSR-7 consumer may reason about: the
     * inner stream's size clamped to the policy limit, or null when the
     * inner stream cannot measure itself.
     *
     * PSR-7 consumers reason about how many bytes the stream can actually
     * deliver, and the wrapper refuses to deliver anything past the limit
     * -- an oversized body therefore reports exactly the limit.
     */
    public function effectiveSize(?int $size): ?int
    {
        return $size === null ? null : min($size, $this->requestBodyPolicy->maxBytes);
    }

    /**
     * Answers the byte count a read may ask the inner stream for: the
     * requested length, capped at one byte past the remaining allowance.
     *
     * @param int $length the requested read length; only positive lengths
     *                    reach the budget
     */
    public function probe(int $length): int
    {
        $remaining = $this->requestBodyPolicy->maxBytes - $this->observedBytes;

        return min($length, max(1, $remaining + 1));
    }

    /**
     * Judges a delivered chunk against the budget and records it: a chunk
     * that crosses the limit is refused and the budget is poisoned, so
     * every later judgement refuses too.
     *
     * @param string $data the chunk the inner stream just delivered
     */
    public function refuses(string $data): bool
    {
        $len = mb_strlen($data, '8bit');

        if ($this->observedBytes + $len > $this->requestBodyPolicy->maxBytes) {
            $this->observedBytes = $this->requestBodyPolicy->maxBytes + self::REFUSED_OVERSHOOT;

            return true;
        }

        $this->observedBytes += $len;

        return false;
    }

    /**
     * Re-bases the observed count on the position the inner stream
     * answers after a seek, clamped: a negative answer is never a budget
     * the wrapper would honour.
     */
    public function rebase(int $position): void
    {
        $this->observedBytes = max(0, $position);
    }

    /**
     * Resets the observed count after a rewind, so a read-rewind-read
     * cycle is not double-charged for the same bytes.
     */
    public function reset(): void
    {
        $this->observedBytes = 0;
    }
}
