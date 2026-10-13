<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * HEAD request handling middleware (RFC 9110 section 9.3.2) -- the
 * HTTP optimizations list's closing slice (ROADMAP.md, "HTTP
 * Optimizations": the ETag and conditional-GET entries landed with
 * #303; the HEAD conversion lands here), and the performance phase's
 * code half: a HEAD request is reissued to the handler as GET so the
 * GET logic executes once, unchanged, and the answer's body is
 * discarded on the way out.
 *
 * The request half of the fold lives in {@see HeadRequest} (the exact
 * `HEAD` token reissued as `GET`), the response half in {@see
 * HeadResponse} (the empty body, the dropped `Content-Length`). Every
 * other request method -- including the lowercase `head` token, which
 * RFC 9110 section 9.1 makes a different, unregistered method --
 * delegates untouched: the same handler call, the same request
 * instance, the same response object back.
 *
 * Ordering: place this middleware outermost, or at least outside
 * body-consuming middleware -- the ETag hasher ({@see ETagMiddleware})
 * must see the full representation before the head view empties it,
 * so its validators describe what the GET would have shipped.
 *
 * OPT-IN: add the middleware to the stack of an application that
 * wants HEAD folded onto its GET routes; it is intentionally not part
 * of any default stack.
 */
final class HeadMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!HeadRequest::isHead($request)) {
            return $handler->handle($request);
        }

        return HeadResponse::view($handler->handle(HeadRequest::asGet($request)));
    }
}
