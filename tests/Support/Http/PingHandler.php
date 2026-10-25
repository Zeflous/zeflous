<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\Response;

/**
 * The HTTP inspection fixture application's terminal handlers: the
 * callable services the fixture route table names -- plain classes
 * rather than closures, because the container's AOT artifact boundary
 * cannot render a closure value and the fixture deliberately keeps
 * the compile/dump lane exercised (the boot's throwaway artifact is
 * written and its finally-owned cleanup stays observable).
 *
 * Each answers through the routing layer's own one-argument contract
 * (the parameter map), building the response with the framework's own
 * Response object.
 */
final class PingHandler
{
    /**
     * @param array<string, string> $parameters the route's captured parameter map
     */
    public function __invoke(array $parameters): ResponseInterface
    {
        return new Response(
            200,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            'pong',
        );
    }
}
