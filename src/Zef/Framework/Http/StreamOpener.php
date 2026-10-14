<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Opens the resources behind every {@see Stream} the HTTP layer creates.
 *
 * The single seam for the layer's `fopen()` calls: a failing open is a real,
 * coverable path here (a missing file on disk), which a bare `php://temp`
 * open inside `Stream::fromString()` could never produce on its own. The
 * scoped error handler converts every engine diagnostic into the layer's
 * own failure -- the failure reason belongs to the caller, not to the error
 * log -- and the try/finally keeps the handler scoped even when a userland
 * wrapper throws straight through the engine's fopen().
 */
final class StreamOpener
{
    /**
     * Opens the given URI.
     *
     * @param non-empty-string $uri  the resource URI to open
     * @param non-empty-string $mode the fopen() mode to open it with
     *
     * @return resource the opened stream resource
     *
     * @throws HttpException when the resource cannot be opened; the message
     *                       carries the URI and the engine's diagnostic
     */
    public static function open(string $uri, string $mode): mixed
    {
        StreamScheme::assert($uri);

        set_error_handler(static function (int $severity, string $message) use ($uri): never {
            throw new HttpException('Unable to open stream ' . $uri . ': ' . $message);
        });

        try {
            $resource = fopen($uri, $mode);
        } finally {
            restore_error_handler();
        }

        if ($resource === false) {
            throw new HttpException('Unable to open stream ' . $uri . '.');
        }

        return $resource;
    }
}
