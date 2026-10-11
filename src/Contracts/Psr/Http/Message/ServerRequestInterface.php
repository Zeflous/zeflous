<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Representation of an incoming, server-side HTTP request, as defined by
 * PSR-7.
 *
 * Vendored by the ZEF zero-library shim. Server-side requests carry the
 * additional parameter bags (server variables, cookies, query, uploads),
 * the deserialized body and the attribute bag middleware uses to derive
 * data without mutating the core properties.
 */
interface ServerRequestInterface extends RequestInterface
{
    /**
     * Retrieves the server parameters (typically $_SERVER).
     *
     * @return array<string, mixed>
     */
    public function getServerParams(): array;

    /**
     * Retrieves the cookies (typically $_COOKIE).
     *
     * @return array<string, mixed>
     */
    public function getCookieParams(): array;

    /**
     * Returns an instance with the specified cookies.
     *
     * @param array<string, mixed> $cookies
     */
    public function withCookieParams(array $cookies): ServerRequestInterface;

    /**
     * Retrieves the query string arguments (typically $_GET).
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array;

    /**
     * Returns an instance with the specified query string arguments.
     *
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): ServerRequestInterface;

    /**
     * Retrieves the normalized file upload data as a tree of
     * UploadedFileInterface leaves.
     *
     * @return array<string, mixed>
     */
    public function getUploadedFiles(): array;

    /**
     * Returns an instance with the specified uploaded files.
     *
     * @param array<string, mixed> $uploadedFiles A tree whose leaves are
     *        UploadedFileInterface instances.
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface;

    /**
     * Retrieves any parameters provided in the request body.
     */
    public function getParsedBody(): mixed;

    /**
     * Returns an instance with the specified body parameters.
     *
     * @param array<string, mixed>|object|null $data The deserialized body;
     *        scalars are invalid per PSR-7.
     */
    public function withParsedBody($data): ServerRequestInterface;

    /**
     * Retrieves the attributes derived from the request.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    /**
     * Retrieves a single derived request attribute.
     *
     * @param mixed $default Default value if the attribute is absent.
     */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /**
     * Returns an instance with the specified derived request attribute.
     *
     * @param mixed $value The attribute value.
     */
    public function withAttribute(string $name, mixed $value): ServerRequestInterface;

    /**
     * Returns an instance without the specified derived request attribute.
     */
    public function withoutAttribute(string $name): ServerRequestInterface;
}
