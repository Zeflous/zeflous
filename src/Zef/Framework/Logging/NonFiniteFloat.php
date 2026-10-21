<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

/**
 * The float a JSONL record may carry: a finite one as the number it is,
 * a non-finite one as its diagnostic string spelling -- NAN, INF, -INF.
 *
 * The record's JSON encoding refuses non-finite values outright, so a
 * bare NAN in a host's context would take the whole record down with it
 * (the exception leaves the writer's append throwing, and the failure
 * record is lost exactly when it is needed). The spelling answers
 * instead, the telemetry sanitizer's own doctrine, and it lives here as
 * a leaf so the sanitizer's coercion stays under the architecture
 * gate's difficulty ceiling -- the same leaf settlement the detail-key
 * gate took.
 */
final class NonFiniteFloat
{
    /**
     * Answers the recordable value for the float.
     *
     * @return float|string the finite float, or the non-finite one's spelling
     */
    public static function spelling(float $value): float|string
    {
        $spelling = (string) $value;

        return is_finite($value) ? $value : $spelling;
    }
}
