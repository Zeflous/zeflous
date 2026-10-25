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
 * Session-writing handler double: answers a plain 200 while writing one
 * key into the session bag the decorated request carried -- the shape
 * the session family's boundary needs to evidence what a handler that
 * actually uses the session leaves behind (the observing sibling
 * answers the read-only walk; this one answers the written one).
 */
final class SessionWritingHandler implements RequestHandlerInterface
{
    public ?SessionState $state = null;

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $state = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        $this->state = $state instanceof SessionState ? $state : null;

        if ($this->state instanceof SessionState) {
            $this->state->set('user', 7);
        }

        return new Response(200);
    }
}
