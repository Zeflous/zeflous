<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * The zero-library PSR-17 factory: the six message-object factories over the
 * layer's own implementations.
 *
 * Every creation routes through the constructors the previous slices
 * landed, so the factory adds no policy of its own beyond the reference
 * implementation's input guards (the empty filename, the fopen() mode
 * grammar through {@see StreamMode}, the resource readability through
 * {@see StreamMetadata}, the uploaded stream readability and the
 * negative-size guard delegated to {@see UploadedFile}'s constructor).
 * File opens travel through the {@see StreamOpener} seam -- the layer's
 * single failing-open path -- so a missing file is a real, coverable
 * failure instead of a silent engine warning.
 *
 * @api
 */
final class Psr17Factory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    /**
     * @param mixed $uri the PSR-17 uri argument: a string or a UriInterface
     *
     * @throws HttpException when the uri is neither a string nor a
     *                       UriInterface instance or the method is rejected
     */
    #[Override]
    public function createRequest(string $method, mixed $uri): RequestInterface
    {
        return new Request($method, $this->resolveUri($uri));
    }

    #[Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, reasonPhrase: $reasonPhrase);
    }

    /**
     * @param mixed                $uri          the PSR-17 uri argument: a string or a UriInterface
     * @param array<string, mixed> $serverParams the SAPI parameters to carry
     *
     * @throws HttpException when the uri is neither a string nor a
     *                       UriInterface instance or the method is rejected
     */
    #[Override]
    public function createServerRequest(string $method, mixed $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $this->resolveUri($uri), $serverParams);
    }

    #[Override]
    public function createStream(string $content = ''): StreamInterface
    {
        return Stream::fromString($content);
    }

    /**
     * @param string $filename the file to open
     * @param string $mode     the fopen() mode to open it with
     *
     * @throws HttpException when the filename is empty, the mode does not
     *                       match the engine's grammar, or the file cannot
     *                       be opened
     */
    #[Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if ($filename === '') {
            throw new HttpException('Filename must not be empty.');
        }

        StreamMode::assert($mode);

        return new Stream(StreamOpener::open($filename, $mode));
    }

    /**
     * @param mixed $resource the PHP stream resource to adopt
     *
     * @throws HttpException when the resource is not a stream resource or
     *                       reports itself unreadable
     */
    #[Override]
    public function createStreamFromResource(mixed $resource): StreamInterface
    {
        if (!\is_resource($resource)) {
            throw new HttpException('Resource required.');
        }

        if (!new StreamMetadata($resource)->readable()) {
            throw new HttpException('Resource must be readable.');
        }

        return new Stream($resource);
    }

    /**
     * @throws HttpException when the payload stream reports itself
     *                       unreadable or the size is negative
     */
    #[Override]
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): UploadedFileInterface {
        if (!$stream->isReadable()) {
            throw new HttpException('Uploaded file stream must be readable.');
        }

        return new UploadedFile($stream, $size ?? $stream->getSize(), $error, $clientFilename, $clientMediaType);
    }

    #[Override]
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }

    /**
     * Narrows the factories' untyped PSR-17 uri argument to the layer's
     * value object. The reference implementation coerced anything
     * string-castable through `(string) $uri` -- a silent fallback the
     * fail-closed layer replaces with a loud rejection, mirroring how the
     * vendored contracts' untyped parameters are enforced at runtime
     * everywhere else in this layer.
     *
     * @throws HttpException when the uri is neither a string nor a
     *                       UriInterface instance
     */
    private function resolveUri(mixed $uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if (\is_string($uri)) {
            return new Uri($uri);
        }

        throw new HttpException('Request URI must be a string or a UriInterface instance.');
    }
}
