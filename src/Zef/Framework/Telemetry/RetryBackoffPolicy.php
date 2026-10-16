<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The retry lane's bounded policy: the value object the delivery
 * drain reads its retry judgements from -- how many attempts a
 * transient failure earns, and how long the lane waits between
 * them -- the family the tracing core's own porting note reserved
 * ({@see BatchSpanProcessor}'s "the retry lane will arrive with
 * its own knobs, on this same seam").
 *
 * The policy is three integers with one invariant -- the cap never
 * sits below the initial delay, because a backoff that starts
 * above its own ceiling is a mis-declared knob, not a policy --
 * and the two judgements the drain makes per failed attempt:
 * {@see self::shouldRetry()} (this failure has earned another
 * attempt) and {@see self::delayMs()} (the wait this attempt
 * backs off by, the exponential doubling the reference's own
 * lane carried, clamped at the cap).
 *
 * The port's sequencing deviation is recorded in the campaign log:
 * the reference constructed its policy from the process
 * environment ({@code fromEnvironment()} over three `ZEF_OTEL_*`
 * variables, read statically inside the drain itself), and this
 * port carries the same fold the tracing core already made -- the
 * knobs are configuration ({@see \Zef\Framework\Http\ExportPolicy}
 * reads them strictly over the layered builder's `observability.
 * export` subtree, the environment overlay included), the engine
 * never reads the environment, and the composition seam ({@see
 * \Zef\Framework\Middleware\TracingFactory}) is the one place the
 * two meet. The policy the drain holds is data, frozen at
 * construction, exactly like every other knob set the families
 * validated.
 *
 * Ported from the reference implementation (legacy
 * `zef-main` `Framework/Observability/RetryBackoffPolicy.php` and
 * its evolved `zef-framework-src` successor): the constructor's
 * invariant, the non-negative index guards and the exponential
 * clamp move verbatim; the environment construction moves to the
 * config face, as the deviation above records.
 */
final readonly class RetryBackoffPolicy
{
    /**
     * @param int $maxRetries     the additional attempts a
     *                            transient failure earns -- zero is
     *                            the one-attempt posture the queue's
     *                            own bound already implied
     * @param int $initialDelayMs the first retry's wait, in
     *                            milliseconds
     * @param int $maxDelayMs     the cap every later wait clamps at,
     *                            never below the initial delay
     *
     * @throws InvalidArgumentException when the retry count or either
     *                                  delay is negative, or the cap
     *                                  sits below the initial delay
     */
    public function __construct(
        public int $maxRetries,
        public int $initialDelayMs,
        public int $maxDelayMs,
    ) {
        if ($maxRetries < 0 || $initialDelayMs < 0 || $maxDelayMs < $initialDelayMs) {
            throw new InvalidArgumentException('Invalid telemetry retry policy.');
        }
    }

    /**
     * Answers whether a failed attempt has earned another: the
     * retry index counts the retries already spent, and the lane
     * retries while more remain.
     *
     * @throws InvalidArgumentException when the index is negative
     */
    public function shouldRetry(int $retryIndex): bool
    {
        if ($retryIndex < 0) {
            throw new InvalidArgumentException('Retry index must be non-negative.');
        }

        return $retryIndex < $this->maxRetries;
    }

    /**
     * Answers the wait one retry backs off by: the initial delay
     * doubled once per retry already spent, clamped at the cap --
     * the doubling through the integer shift of the delay itself,
     * because an arithmetic spelling the runtime parses as a
     * division would answer the identical wait through operator
     * precedence, and the exponent the runtime spells as a float
     * would leave the pure-integer lane carrying a float wait.
     *
     * @throws InvalidArgumentException when the index is negative
     */
    public function delayMs(int $retryIndex): int
    {
        if ($retryIndex < 0) {
            throw new InvalidArgumentException('Retry index must be non-negative.');
        }

        return min($this->maxDelayMs, $this->initialDelayMs << $retryIndex);
    }
}
