<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The engine's positional answers over one stream resource: the cursor's
 * position, seeking, the end-of-file flag and the size statistic.
 *
 * Extracted from the PSR-7 {@see Stream} surface -- the same depth-split
 * the configuration layer's radix engine uses to stay under the
 * architecture gate's difficulty budget. Every operation guards the
 * resource it needs and fails closed with the layer's exception.
 */
final class StreamPosition
{
    /**
     * @param null|resource $resource
     *
     * @throws HttpException when the resource is detached or the engine
     *                       cannot report the position
     */
    public static function tell(mixed $resource): int
    {
        if (!\is_resource($resource)) {
            throw new HttpException('Stream detached.');
        }

        $position = ftell($resource);
        if ($position === false) {
            throw new HttpException('Unable to determine stream position.');
        }

        return $position;
    }

    /**
     * @param null|resource $resource
     *
     * @throws HttpException when the resource is detached, reports itself
     *                       unseekable, or the engine seek fails
     */
    public static function seek(mixed $resource, int $offset, int $whence): void
    {
        if (
            !\is_resource($resource)
            || !new StreamMetadata($resource)->seekable()
            || fseek($resource, $offset, $whence) !== 0
        ) {
            throw new HttpException('Stream is not seekable.');
        }
    }

    /**
     * @param null|resource $resource
     */
    public static function eof(mixed $resource): bool
    {
        return !\is_resource($resource) || feof($resource);
    }

    /**
     * @param null|resource $resource
     */
    public static function size(mixed $resource): ?int
    {
        if (!\is_resource($resource)) {
            return null;
        }

        $stats = fstat($resource);

        return $stats === false ? null : $stats['size'];
    }
}
