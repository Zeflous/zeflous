<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The compression family's runtime capability probe: which content
 * codings this runtime can actually produce, answered from the
 * family's own fold -- never from the roadmap's wish list, and never
 * from the function table alone.
 *
 * PHP core carries the zlib engine (the incremental
 * `deflate_init()`/`deflate_add()` seam the {@see CompressedBody} fold
 * reads), and that one seam covers both the `gzip` and the `deflate`
 * codings -- the window argument selects the wrapper. Brotli is not
 * in PHP core: it exists only behind a PECL extension -- but the
 * extension's presence is not a capability this family owns, because
 * the fold has no brotli seam to ride: a coding the fold cannot fold
 * is a 500 the negotiator would have chosen, so `br` is not admitted
 * however loudly the function table announces it. The preference
 * table still spells `br` first ({@see ContentCoding} carries the
 * roadmap's own order); the capability filter is honest: a coding
 * this fold cannot produce is never negotiated, whatever the client
 * accepts. When the fold grows a brotli seam, this probe grows its
 * admission with it.
 *
 * The probe is injectable: the middleware's constructor takes the
 * coding list, defaulting to {@see self::detect()}, so a test (or a
 * deployment that knows its engine better than the probe) can spell
 * the capabilities exactly and the capability-absent posture -- a
 * negotiator with an empty list that must answer identity -- is
 * pinnable without uninstalling an extension.
 */
final class CompressionCapabilities
{
    /**
     * The codings this runtime's fold can produce, in the preference
     * order the negotiator reads.
     *
     * @return list<'deflate'|'gzip'> the producible codings
     */
    public static function detect(): array
    {
        $codings = [];

        if (\function_exists('deflate_init')) {
            $codings[] = 'gzip';
            $codings[] = 'deflate';
        }

        return $codings;
    }
}
