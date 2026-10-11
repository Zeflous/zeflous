<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Factory for streams, as defined by PSR-17.
 *
 * Vendored by the ZEF zero-library shim.
 */
interface StreamFactoryInterface
{
    /**
     * Creates a new stream from a string.
     *
     * The stream is readable, writable and seekable.
     */
    public function createStream(string $content = ''): StreamInterface;

    /**
     * Creates a stream from an existing file.
     *
     * @param non-empty-string $filename The file to open.
     * @param non-empty-string $mode The fopen() mode, e.g. 'rb'.
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface;

    /**
     * Creates a stream from an existing resource.
     *
     * @param resource $resource A readable PHP stream resource.
     */
    public function createStreamFromResource($resource): StreamInterface;
}
