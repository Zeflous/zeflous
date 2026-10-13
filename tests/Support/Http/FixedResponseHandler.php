<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Deterministic terminal handler double: answers the response it was
 * constructed with, ignoring the request -- the shape every middleware
 * test needs on the far side of the chain it is exercising.
 */
final class FixedResponseHandler implements RequestHandlerInterface
{
    public function __construct(private readonly ResponseInterface $response)
    {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}
