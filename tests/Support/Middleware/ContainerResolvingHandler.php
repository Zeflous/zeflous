<?php

declare(strict_types=1);

namespace Zef\Test\Support\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Container\Container;
use Zef\Framework\Http\Response;

/**
 * The container-resolving terminal handler the boundary integration
 * suite resolves through: a handler whose answer asks the container
 * for the given identifier, so a resolution refusal rises through
 * the error boundary exactly the way a host's controller would let
 * it ({@see \Zef\Test\Middleware\ContainerRefusalBoundaryIntegrationTest}).
 */
final readonly class ContainerResolvingHandler implements RequestHandlerInterface
{
    public function __construct(
        private Container $container,
        private string $identifier,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $this->container->get($this->identifier);

        return new Response(200);
    }
}
