<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\Response;

/**
 * The fixture route table's POST answer: the captured `{name}`
 * mirrored back with the created status. See {@see PingHandler} for
 * the family's shared rationale (class-shaped so the AOT artifact
 * boundary can render the plan).
 */
final class MirrorHandler
{
    /**
     * @param array<string, string> $parameters the route's captured parameter map
     */
    public function __invoke(array $parameters): ResponseInterface
    {
        return new Response(
            201,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            'mirrored ' . (\is_string($parameters['name'] ?? null) ? $parameters['name'] : ''),
        );
    }
}
