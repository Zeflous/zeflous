<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SessionMiddleware;
use Zef\Framework\Http\SessionState;

/**
 * Session-observing handler double: answers a plain 200 while
 * remembering the session bag the decorated request carried -- the
 * shape the session family's boundary needs to evidence what the far
 * side of the chain observed.
 */
final class SessionObservingHandler implements RequestHandlerInterface
{
    public ?SessionState $state = null;

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $state = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        $this->state = $state instanceof SessionState ? $state : null;

        return new Response(200);
    }
}
