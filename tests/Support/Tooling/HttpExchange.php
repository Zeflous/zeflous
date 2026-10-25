<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The HTTP inspection commands' answered exchange: the request the
 * inspection simulated and the response the application's own
 * pipeline answered with, held together because the two are one
 * evidence -- a dump renders both, an audit judges the response under
 * the request's own scheme.
 */
final readonly class HttpExchange
{
    public function __construct(
        public RequestInterface $request,
        public ResponseInterface $response,
    ) {
    }

    /**
     * The exchange's scheme, read from the request's own URI -- the
     * one source of truth the audit's applicability rule reads.
     */
    public function scheme(): string
    {
        return $this->request->getUri()->getScheme();
    }
}
