<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The engine's data transfer over one stream resource: guarded reads,
 * writes and whole-remainder drains.
 *
 * Every operation checks the resource it needs through {@see StreamMetadata}
 * before touching the engine and fails closed with the layer's exception --
 * the message texts are byte-identical to the reference implementation
 * (legacy `zef-framework` `Adapters\Http\Stream`), so consumers migrating
 * keep their assertions.
 */
final class StreamTransfer
{
    /**
     * Reads up to the requested byte count from the resource.
     *
     * @param null|resource $resource
     *
     * @throws HttpException when the length is negative, the resource cannot
     *                       read, or the engine read fails
     */
    public static function read(mixed $resource, int $length): string
    {
        if ($length < 0) {
            throw new HttpException('Length must be non-negative.');
        }

        $streamMetadata = new StreamMetadata($resource);
        if (!$streamMetadata->readable() || !\is_resource($resource)) {
            throw new HttpException('Stream is not readable.');
        }

        if ($length === 0) {
            return '';
        }

        $data = fread($resource, $length);
        if ($data === false) {
            throw new HttpException('Unable to read stream.');
        }

        return $data;
    }

    /**
     * Writes the string to the resource and answers the byte count written.
     *
     * @param null|resource $resource
     *
     * @throws HttpException when the resource cannot write or the engine
     *                       write fails
     */
    public static function write(mixed $resource, string $string): int
    {
        $streamMetadata = new StreamMetadata($resource);
        if (!$streamMetadata->writable() || !\is_resource($resource)) {
            throw new HttpException('Stream is not writable.');
        }

        $written = fwrite($resource, $string);
        if ($written === false) {
            throw new HttpException('Unable to write to stream.');
        }

        return $written;
    }

    /**
     * Reads the resource's whole remainder from the current position.
     *
     * @param null|resource $resource
     *
     * @throws HttpException when the resource cannot read
     */
    public static function contents(mixed $resource): string
    {
        $streamMetadata = new StreamMetadata($resource);
        if (!$streamMetadata->readable() || !\is_resource($resource)) {
            throw new HttpException('Stream is not readable.');
        }

        // The engine's drain answers false only for streams the guard above
        // already rejects (measured: userland wrappers whose reads fail make
        // stream_get_contents answer ''), so the cast narrows a dead branch
        // rather than hiding a live failure.
        return (string) stream_get_contents($resource);
    }
}
