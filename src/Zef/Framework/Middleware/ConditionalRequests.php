<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Conditional GET/HEAD semantics for a successful representation: weak
 * If-None-Match comparison ({@see EntityTagMatch}) and If-Modified-Since
 * ({@see LastModifiedMatch}) return a bodyless 304
 * ({@see NotModifiedResponse}) when the validators match; If-None-Match
 * takes precedence as HTTP requires. Validators are supplied by the
 * application's handlers and middleware, never fabricated here.
 *
 * @internal
 */
final readonly class ConditionalRequests
{
    private NotModifiedResponse $notModifiedResponse;

    public function __construct(StreamFactoryInterface $streamFactory)
    {
        $this->notModifiedResponse = new NotModifiedResponse($streamFactory);
    }

    /**
     * Applies conditional semantics to a successful representation.
     */
    public function apply(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
        string $method,
    ): ResponseInterface {
        if (!\in_array($method, ['GET', 'HEAD'], true) || $response->getStatusCode() !== 200) {
            return $response;
        }

        if ($serverRequest->hasHeader('If-None-Match')) {
            return $this->ifNoneMatch($serverRequest, $response);
        }

        return LastModifiedMatch::satisfied($serverRequest, $response)
            ? $this->notModifiedResponse->from($response)
            : $response;
    }

    private function ifNoneMatch(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface
    {
        $condition = mb_trim($serverRequest->getHeaderLine('If-None-Match'));
        if ($condition === '*') {
            return $this->notModifiedResponse->from($response);
        }

        $currentTag = mb_trim($response->getHeaderLine('ETag'));
        if ($currentTag === '') {
            return $response;
        }

        return EntityTagMatch::matches($condition, $currentTag)
            ? $this->notModifiedResponse->from($response)
            : $response;
    }
}
