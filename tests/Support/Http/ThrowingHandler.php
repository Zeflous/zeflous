<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Deterministic failing handler double: lets exactly the exception it was
 * constructed with escape -- the shape every error-mapping test needs on
 * the far side of the middleware it is exercising.
 */
final class ThrowingHandler implements RequestHandlerInterface
{
    public function __construct(private readonly Throwable $exception)
    {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->exception;
    }
}
