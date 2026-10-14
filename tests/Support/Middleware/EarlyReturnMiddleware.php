<?php

declare(strict_types=1);

namespace Zef\Test\Support\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Non-delegating middleware double: answers its own response and never
 * calls the handler -- the shape an ordering test needs to prove that an
 * outer middleware's early return keeps every inner middleware and the
 * terminal handler from running at all.
 */
final readonly class EarlyReturnMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseInterface $response)
    {
    }

    #[Override]
    public function process(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler,
    ): ResponseInterface {
        return $this->response;
    }
}
