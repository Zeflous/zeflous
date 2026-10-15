<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's clock: Unix milliseconds, one reading per
 * request -- the extracted seam the reference computed inline twice
 * (its middleware called `microtime(true) * 1000` at the resolution
 * and again at the admission, two readings a single request could
 * disagree over on a boundary tick, and no test could freeze).
 *
 * The extraction follows the CSRF family's own discipline ({@see
 * CsrfTokenManager}'s entropy seam): the arithmetic lives in one
 * named place the suite can observe, rather than inline where the
 * mutation run cannot reach it -- a clock that reads as
 * `microtime(true) / 1000` still admits requests, so the only honest
 * test is the one that reads the clock itself. The seconds-to-
 * milliseconds conversion lives here for the same reason ({@see
 * self::millisecondsFromSeconds()}): the replay window's knob is
 * seconds and the protector's arithmetic is milliseconds, and the
 * one line that bridges them is exactly the kind of inline literal
 * arithmetic a mutation run rewrites invisibly.
 */
final class AuthClock
{
    /**
     * The current instant, in Unix milliseconds.
     */
    public static function milliseconds(): int
    {
        return (int) (microtime(true) * 1000.0);
    }

    /**
     * The milliseconds a whole-second span covers: the bridge from
     * the family's seconds-granular knobs to the protector's
     * millisecond arithmetic.
     */
    public static function millisecondsFromSeconds(int $seconds): int
    {
        return $seconds * 1000;
    }
}
