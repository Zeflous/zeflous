<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use DeflateContext;
use Psr\Http\Message\StreamInterface;

/**
 * The compression family's body fold: one response body, read in
 * fixed-size chunks and compressed through the zlib engine's
 * incremental seam onto a new seekable stream -- the streaming shape
 * the roadmap's Phase 3.2 spells, not a one-shot buffer.
 *
 * The fold is the {@see EmittedBody} walk's own compression sibling:
 * the source is read in 8192-byte chunks (never the whole body in
 * memory), each chunk is handed to `deflate_add()` over one
 * `deflate_init()` context, and the compressed fragments land on a
 * `php://temp/maxmemory:` stream -- the roadmap's Phase 1.1
 * memory-safe-buffering posture replayed: the compressed payload
 * lives in memory up to its 64 KB allowance and spills to disk
 * beyond it, so a 200 MB download's compressed form never becomes a
 * 200 MB allocation. The context is closed with the engine's FINISH
 * flush so the wrapper's trailer (the gzip CRC and size fields) is
 * written -- a compressed stream without its trailer is bytes no
 * decoder accepts.
 *
 * Fail-closed throughout: a context the engine refuses, a fragment
 * the engine cannot produce and a destination the wrapper cannot
 * write are all errors ({@see HttpException}), never silent
 * truncation -- a half-compressed body served as complete is a
 * corruption every downstream cache would store.
 */
final class CompressedBody
{
    /** The source read's chunk size, the emitter walk's own. */
    private const int CHUNK_BYTES = 8192;

    /** The destination's in-memory allowance before the disk spill. */
    private const string TEMP_TARGET = 'php://temp/maxmemory:65536';

    /**
     * Compresses one body onto a new seekable stream.
     *
     * @param StreamInterface $stream the response's uncompressed body
     * @param string          $coding the negotiated content coding
     *                                (`gzip` or `deflate`)
     * @param int             $level  the zlib compression level, 1..9
     *
     * @throws HttpException when the engine refuses the context, cannot
     *                       produce a fragment, or the destination cannot
     *                       be written
     */
    public static function compress(StreamInterface $stream, string $coding, int $level): StreamInterface
    {
        $context = deflate_init(
            ContentCoding::window($coding),
            ['level' => $level],
        );

        if ($context === false) {
            throw new HttpException(
                "The compression engine refused the {$coding} context at level {$level}.",
            );
        }

        $destination = new Stream(StreamOpener::open(self::TEMP_TARGET, 'w+b'));

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            $chunk = $stream->read(self::CHUNK_BYTES);

            if ($chunk === '') {
                break;
            }

            self::fragment($destination, $context, $chunk, \ZLIB_SYNC_FLUSH);
        }

        self::fragment($destination, $context, '', \ZLIB_FINISH);
        $destination->rewind();

        return $destination;
    }

    /**
     * Compresses one fragment onto the destination.
     *
     * @param Stream         $stream         the compressed payload's stream
     * @param DeflateContext $deflateContext the engine's incremental context
     * @param string         $chunk          the bytes to compress, empty to
     *                                       finish the stream
     * @param int            $flush          the engine's flush mode
     *
     * @throws HttpException when the engine cannot produce the fragment
     */
    private static function fragment(
        Stream $stream,
        DeflateContext $deflateContext,
        string $chunk,
        int $flush,
    ): void {
        $fragment = deflate_add($deflateContext, $chunk, $flush);

        if ($fragment === false) {
            throw new HttpException('The compression engine failed mid-stream.');
        }

        if ($fragment === '') {
            return;
        }

        $stream->write($fragment);
    }
}
