<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

/**
 * The flushed-tail trim: the single-question fold the render scope's
 * refusal path walks (issue #787's finding 1) -- a template that
 * flushes the engine's buffer (`ob_end_flush()`) pushes its partial
 * output into the caller's own open buffer below, the discipline
 * check refuses it, but the bytes already landed. The trim keeps
 * exactly the prefix the caller owned before the render and drops
 * the tail, so the corrupted response the leak would have shipped
 * never leaves the engine.
 *
 * The caller's buffer instance survives the surgery (`ob_clean()`,
 * never `ob_end_clean()`): the buffer's own identity is the caller's
 * buffering choice, not the engine's to reopen on its behalf.
 *
 * The caller's own prefix length rides the two reads before the
 * open: the level (the entry) and the buffer content length -- a
 * level-zero entry holds NO buffer to trim (the false answer the
 * length read gives there is the no-buffer signal itself), and
 * the null spelling is the gate the refusal path keys on (the
 * fold only ever runs for a caller whose buffer exists).
 *
 * @internal
 */
final class ViewFlushedTail
{
    /**
     * Discards the tail a flushing template pushed into the caller's
     * own buffer, keeping the prefix the caller owned.
     *
     * The level arm is the whole answer for the past-flushing
     * residual: a template that flushes PAST the engine's walls
     * closes the caller's own buffer, the level then sits BELOW the
     * entry, and the buffer now at the entry's own level is an
     * INNOCENT one (the caller's caller) -- the early return refuses
     * to touch it (the bytes the flush pushed into it are the
     * recorded residual, never a slice the engine may re-cut).
     *
     * @param int $entry  the caller's buffer level before the render
     * @param int $caller the caller's own buffer content length
     *                    before the render
     */
    public static function discard(int $entry, int $caller): void
    {
        if (ob_get_level() !== $entry || ob_get_length() <= $caller) {
            return;
        }

        // The cut is BYTE-exact by demand: ob_get_length() answers bytes,
        // so the prefix the trim keeps is the caller's first $caller bytes
        // -- the '8bit' encoding spelling the house's byte-semantics
        // doctrine (the MbStrFunctions formatter owns the mb_ twin; the
        // encoding argument owns the byte semantics).
        $preserved = mb_substr((string) ob_get_contents(), 0, $caller, '8bit');

        ob_clean();
        echo $preserved;
    }
}
