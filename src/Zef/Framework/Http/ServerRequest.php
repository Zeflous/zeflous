<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * The zero-library PSR-7 server-side request: the request line inherited
 * from {@see Request} plus the parameter bags an inbound request carries
 * -- server variables, cookies, query arguments, the normalized upload
 * tree, the deserialized body and the attribute bag middleware derives
 * data through.
 *
 * Every bag is validated at the boundary: the parsed body must be an
 * array, object or null ({@see ParsedBody}) and the upload tree must
 * carry only {@see UploadedTree}-checked leaves, both fail-closed with
 * the layer's exception, byte-identical to the reference implementation
 * (legacy `zef-framework` `Adapters\Http\ServerRequest`). Attributes are
 * request-derived data, deliberately unvalidated: the middleware lane
 * owns their contract.
 *
 * @api
 */
final class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * @param array<string, mixed>                   $serverParams    typically $_SERVER
     * @param array<string, mixed>                   $cookieParams    typically $_COOKIE
     * @param array<string, mixed>                   $queryParams     typically $_GET
     * @param array<string, mixed>                   $uploadedFiles   a tree whose leaves are
     *                                                                UploadedFileInterface instances
     * @param mixed                                  $parsedBody      array, object or null
     * @param array<int|string, list<string>|string> $headers         header names => string or
     *                                                                list<string> values per the PSR
     *                                                                contract
     * @param non-empty-string                       $protocolVersion
     * @param string                                 $requestTarget   explicit target; empty means
     *                                                                derive from the URI
     * @param array<string, mixed>                   $attributes      request-derived data
     *
     * @throws HttpException when the parsed body, upload tree, method,
     *                       protocol version, request target or a header
     *                       is rejected
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        private array $serverParams = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private array $uploadedFiles = [],
        private mixed $parsedBody = null,
        array $headers = [],
        ?StreamInterface $stream = null,
        string $protocolVersion = '1.1',
        string $requestTarget = '',
        private array $attributes = [],
    ) {
        ParsedBody::assert($parsedBody);
        parent::__construct($method, $uri, $headers, $stream, $protocolVersion, $requestTarget);
        UploadedTree::assert($uploadedFiles);
    }

    #[Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    #[Override]
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[Override]
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $recookied = clone $this;
        $recookied->cookieParams = $cookies;

        return $recookied;
    }

    #[Override]
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param array<string, mixed> $query
     */
    #[Override]
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $requeried = clone $this;
        $requeried->queryParams = $query;

        return $requeried;
    }

    #[Override]
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array<string, mixed> $uploadedFiles a tree whose leaves are
     *                                            UploadedFileInterface instances
     *
     * @throws HttpException when a leaf is not an UploadedFileInterface
     *                       instance
     */
    #[Override]
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        UploadedTree::assert($uploadedFiles);
        $releafed = clone $this;
        $releafed->uploadedFiles = $uploadedFiles;

        return $releafed;
    }

    #[Override]
    public function getParsedBody(): mixed
    {
        return $this->parsedBody;
    }

    /**
     * @param mixed $data the deserialized body; array, object or null per
     *                    the PSR contract, scalars are invalid
     *
     * @throws HttpException when the parsed body is not an array, object
     *                       or null
     */
    #[Override]
    public function withParsedBody(mixed $data): ServerRequestInterface
    {
        ParsedBody::assert($data);
        $reparsed = clone $this;
        $reparsed->parsedBody = $data;

        return $reparsed;
    }

    #[Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    #[Override]
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    #[Override]
    public function withAttribute(string $name, mixed $value): ServerRequestInterface
    {
        $reattributed = clone $this;
        $reattributed->attributes[$name] = $value;

        return $reattributed;
    }

    #[Override]
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $pruned = clone $this;
        unset($pruned->attributes[$name]);

        return $pruned;
    }
}
