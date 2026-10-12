<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * The zero-library PSR-7 request: the request line (method, target,
 * URI) plus the message half inherited from {@see Message}.
 *
 * The request-target answer is the explicit override when one was set
 * (origin-, absolute- or asterisk-form) and the origin-form derivation
 * from the URI otherwise ({@see RequestTarget}); the method follows the
 * token grammar {@see HttpMethod} guards. The Host header follows the
 * URI through the {@see UriHostPolicy}: bootstrapped from the URI when
 * the message carries none, refreshed by `withUri()` per the PSR-7
 * preserve-host rule, both rendered by {@see HostLine} -- the shared
 * header store the message half owns makes each a clone plus one
 * assignment. Ported from the reference implementation (legacy
 * `zef-framework` `Adapters\Http\Request` + `RequestTrait`, the trait
 * replaced by this class hierarchy).
 *
 * @api
 */
class Request extends Message implements RequestInterface
{
    private string $method;

    private UriInterface $uri;

    private ?string $requestTargetOverride = null;

    /**
     * @param array<int|string, list<string>|string> $headers         header names
     *                                                                => string or list<string> values
     *                                                                per the PSR contract; a numerical
     *                                                                key is cast to its decimal
     *                                                                spelling, which the token grammar
     *                                                                accepts
     * @param non-empty-string                       $protocolVersion
     * @param string                                 $requestTarget   explicit target
     *                                                                (origin-, absolute- or
     *                                                                asterisk-form); empty means
     *                                                                derive from the URI
     *
     * @throws HttpException when the method, protocol version, request
     *                       target or a header is rejected
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        array $headers = [],
        ?StreamInterface $stream = null,
        string $protocolVersion = '1.1',
        string $requestTarget = '',
    ) {
        parent::__construct($stream, $headers, $protocolVersion);
        HttpMethod::assert($method);
        $this->headerStore = UriHostPolicy::bootstrapped($this->headerStore, $uri);

        if ($requestTarget !== '') {
            RequestTarget::assert($requestTarget);
            $this->requestTargetOverride = $requestTarget;
        }

        $this->method = $method;
        $this->uri = $uri;
    }

    #[Override]
    public function getRequestTarget(): string
    {
        return RequestTarget::resolve($this->requestTargetOverride, $this->uri);
    }

    /**
     * @throws HttpException when the target carries a control character
     */
    #[Override]
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        RequestTarget::assert($requestTarget);
        $retargeted = clone $this;
        $retargeted->requestTargetOverride = $requestTarget;

        return $retargeted;
    }

    #[Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @throws HttpException when the method is not a single valid token
     */
    #[Override]
    public function withMethod(string $method): RequestInterface
    {
        HttpMethod::assert($method);
        $remethoded = clone $this;
        $remethoded->method = $method;

        return $remethoded;
    }

    #[Override]
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @throws HttpException when the refreshed Host header is rejected
     */
    #[Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $redirected = clone $this;
        $redirected->uri = $uri;

        if (UriHostPolicy::shouldRefresh($uri, $preserveHost, $this->getHeaderLine('Host'))) {
            $redirected->headerStore = $this->headerStore->with('Host', HostLine::fromUri($uri));
        }

        return $redirected;
    }
}
