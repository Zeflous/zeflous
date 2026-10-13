<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Request-capturing terminal handler double: answers the response it
 * was constructed with while remembering the request it answered -- the
 * shape a middleware that rewrites the request line (the HEAD-to-GET
 * fold) needs to evidence what the far side of the chain observed.
 */
final class RecordingHandler implements RequestHandlerInterface
{
    private ?ServerRequestInterface $request = null;

    public function __construct(private readonly ResponseInterface $response)
    {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return $this->response;
    }

    /**
     * The request the handler answered.
     */
    public function observed(): ServerRequestInterface
    {
        \assert($this->request !== null, 'handle() must be called before observed().');

        return $this->request;
    }
}
