<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The query-log family's validated knob set: the `database.query_log`
 * configuration posture as one immutable value object -- the
 * slow-request family's own {@see \Zef\Framework\Http\SlowRequestPolicy}
 * lineage, arriving with the per-query logging slice the lane's own
 * not-yet ledger named (the knob the wiring slice's KDD-DW7 promised
 * would land "the day a deployment asks, with its census entry").
 *
 * The policy is deliberately pure: the two knobs, the corridor they
 * must hold ({@see QueryLogBounds}, asserted at the constructor so it
 * holds at both construction paths), and the bar's own arithmetic.
 * The config-driven reading lives in the unlayered root beside the
 * family's other census folds ({@see \Zef\Framework\DatabaseDeclaration}
 * reads the sub-map strictly and constructs this object over it),
 * because the Database layer owns no configuration reading -- the same
 * split every knob family the tree has landed carries.
 *
 * The shipped default bar (100ms) is the round number the family's
 * own doctrine answers: a query is a component of the request's own
 * slow bar (the slow-request family's 1000ms), and at a tenth of that
 * bar one statement is spending a slice the request's slow verdict
 * cannot afford -- the weight where a query stops being an answer the
 * request waited for and starts being a conversation the operator
 * must join. The arm is ON by default within a spelled block, because
 * spelling `database.query_log` is itself the ask -- the same
 * arm-follows-declaration doctrine the slow-request family carries --
 * while the ABSENT block is the family's silent posture (no emission
 * object at all, the honest void the wiring fold answers).
 */
final readonly class QueryLogPolicy
{
    /**
     * @param bool $enabled     the family's arm within a spelled block
     * @param int  $thresholdMs the bar a query must strictly exceed to
     *                          answer the slow verdict, in milliseconds
     *
     * @throws QueryLogException when the threshold sits outside the
     *                           corridor ({@see QueryLogBounds})
     */
    public function __construct(
        public bool $enabled = true,
        public int $thresholdMs = 100,
    ) {
        QueryLogBounds::assertThresholdMs($this->thresholdMs);
    }

    /**
     * The bar's own arithmetic: whether a query's monotonic span
     * strictly exceeds the threshold -- the millisecond knob translated
     * to its nanosecond spelling once, here, where the knob lives, so
     * the verdict the emission answers is the policy's own judgment and
     * never a call site's re-derivation.
     *
     * The comparison is deliberately strict: a span that exactly MEETS
     * the bar is not slow (the slow-request family's own "melebihi"
     * doctrine), and a negative span (a broken clock seam whose second
     * reading lands before its first) exceeds no positive bar -- a
     * broken measurement can fabricate nothing.
     *
     * @param int $elapsedNs the statement's monotonic span, in
     *                       nanoseconds
     *
     * @return bool whether the span strictly exceeds the bar
     */
    public function exceededBy(int $elapsedNs): bool
    {
        return $elapsedNs > $this->thresholdMs * 1000000;
    }
}
