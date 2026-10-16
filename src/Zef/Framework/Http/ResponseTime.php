<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The response-time header's value shape: the elapsed nanoseconds a
 * timing middleware measured, rendered as the two-decimal
 * millisecond string the header contract spells.
 *
 * The shape is one policy point ({@see self::fromElapsedNs()}): the
 * `%.2fms` spelling sprintf produces -- kept even when the elapsed
 * time rounds to zero, because string-concatenating a `0.0` float
 * yields `0ms`, which broke the header contract on fast runners (the
 * reference's own platform-smoke flake, pinned there as the sprintf
 * fix it now carries). The negative clamp mirrors the reference's
 * span arithmetic: a negative delta is a clock artifact, never a
 * negative duration -- the header answers `0.00ms`, never the
 * nonsense `-0.42ms` a raw render would print.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Middleware/TimingMiddleware.php`, the inline render the ZEF-DX-08
 * issue carried): the arithmetic lives in one named place the suite
 * can observe, rather than inline in the middleware where the
 * mutation run cannot reach it -- an elapsed-time render that reads
 * as `ns / 1000` still answers a header, so the only honest test is
 * the one that reads the shape itself.
 */
final readonly class ResponseTime
{
    /** The header the timing family stamps the elapsed time under. */
    public const string HEADER = 'X-Response-Time';

    /**
     * The nanoseconds one millisecond carries: the single literal the
     * elapsed-to-header arithmetic divides by.
     */
    private const int NANOSECONDS_PER_MILLISECOND = 1_000_000;

    private function __construct(public string $value)
    {
    }

    /**
     * Renders an elapsed-nanosecond span as the header's value.
     *
     * @param int $elapsedNs the span between the middleware's two
     *                       monotonic clock readings
     */
    public static function fromElapsedNs(int $elapsedNs): self
    {
        $milliseconds = max(0, $elapsedNs) / self::NANOSECONDS_PER_MILLISECOND;

        return new self(\sprintf('%.2fms', $milliseconds));
    }
}
