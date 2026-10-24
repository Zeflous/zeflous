<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The compression family's runtime capability probe: which content
 * codings this PHP build can actually produce, answered from the
 * engine's own function table -- never from the roadmap's wish list.
 *
 * PHP core carries the zlib engine (the incremental
 * `deflate_init()`/`deflate_add()` seam the {@see CompressedBody} fold
 * reads), and that one seam covers both the `gzip` and the `deflate`
 * codings -- the window argument selects the wrapper. Brotli is not
 * in PHP core: it exists only behind a PECL extension, so the probe
 * asks the function table whether `brotli_compress` is there, and a
 * build without it answers a list the `br` preference cannot draw
 * from. The negotiator's preference table still spells
 * `br` > `gzip` > `deflate` -- the roadmap's own order -- but the
 * capability filter is honest: a coding this runtime cannot produce
 * is never negotiated, whatever the client accepts.
 *
 * The probe is injectable: the middleware's constructor takes the
 * coding list, defaulting to {@see self::detect()}, so a test (or a
 * deployment that knows its engine better than the function table)
 * can spell the capabilities exactly and the capability-absent
 * posture -- a negotiator with an empty list that must answer
 * identity -- is pinnable without uninstalling an extension.
 */
final class CompressionCapabilities
{
    /**
     * The codings this PHP build can produce, in the preference order
     * the negotiator reads.
     *
     * @return list<'br'|'deflate'|'gzip'> the producible codings
     */
    public static function detect(): array
    {
        $codings = [];

        if (\function_exists('brotli_compress')) {
            $codings[] = 'br';
        }

        if (\function_exists('deflate_init')) {
            $codings[] = 'gzip';
            $codings[] = 'deflate';
        }

        return $codings;
    }
}
