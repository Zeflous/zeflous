<?php

declare(strict_types=1);

namespace Psr\Http\Message;

use Stringable;

/**
 * Describes a data stream, as defined by PSR-7.
 *
 * Vendored by the ZEF zero-library shim. Typically used for request and
 * response bodies: a stream may be "detached" once the underlying resource
 * has been taken away, after which every capability reports false and every
 * stateful operation fails.
 */
interface StreamInterface extends Stringable
{
    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method attempts to seek to the beginning, reads until the end and
     * returns the contents. Never raises an exception: on failure the string
     * '' is returned.
     */
    #[\Override]
    public function __toString(): string;

    /**
     * Closes the stream and any underlying resources.
     */
    public function close(): void;

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream is detached, the stream is in an unusable state.
     *
     * @return resource|null The underlying PHP stream, if any.
     */
    public function detach(): mixed;

    /**
     * Gets the size of the stream if known.
     */
    public function getSize(): ?int;

    /**
     * Returns the current position of the file read/write pointer.
     */
    public function tell(): int;

    /**
     * Returns true if the stream is at the end of the stream.
     */
    public function eof(): bool;

    /**
     * Returns whether or not the stream is seekable.
     */
    public function isSeekable(): bool;

    /**
     * Seek to a position in the stream.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void;

    /**
     * Rewind to the beginning of the stream.
     *
     * @throws \RuntimeException On failure.
     */
    public function rewind(): void;

    /**
     * Returns whether or not the stream is writable.
     */
    public function isWritable(): bool;

    /**
     * Write data to the stream.
     *
     * @return int The number of bytes written.
     */
    public function write(string $string): int;

    /**
     * Returns whether or not the stream is readable.
     */
    public function isReadable(): bool;

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes; negative lengths are
     *        rejected at runtime.
     *
     * @return string The data read, possibly shorter than $length.
     */
    public function read(int $length): string;

    /**
     * Returns the remaining contents of the stream.
     */
    public function getContents(): string;

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * @param string|null $key Specific metadata to retrieve.
     *
     * @return mixed The metadata array, the key's value, or null.
     */
    public function getMetadata(?string $key = null): mixed;
}
