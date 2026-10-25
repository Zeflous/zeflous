<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\Response;

/**
 * The fixture route table's parameterised answer: the captured `{id}`
 * rendered into a JSON body. See {@see PingHandler} for the family's
 * shared rationale (class-shaped so the AOT artifact boundary can
 * render the plan).
 */
final class UserHandler
{
    /**
     * @param array<string, string> $parameters the route's captured parameter map
     */
    public function __invoke(array $parameters): ResponseInterface
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"id":' . (\is_string($parameters['id'] ?? null) ? $parameters['id'] : '0') . '}',
        );
    }
}
