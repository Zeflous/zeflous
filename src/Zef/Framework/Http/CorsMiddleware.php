<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Cross-origin resource sharing middleware (the fetch spec's CORS
 * protocol) -- the HTTP layer's second PSR-15 middleware.
 *
 * A preflight never reaches the handler: {@see PreflightResponse::answer()}
 * decides and builds the short-circuit. Every other response whose
 * request carried an allowed `Origin` is decorated with the
 * `Access-Control-Allow-*` set and a `Vary` that includes `Origin`
 * ({@see CorsHeaders}), so caches key on it; every other response flows
 * through untouched.
 *
 * OPT-IN: add the middleware to the stack of an application that wants
 * CORS; it is intentionally not part of any default stack. Ported from
 * the reference implementation (legacy `zef-framework`
 * `Middleware/CorsMiddleware.php` over `Domain/Security/OriginPolicy.php`);
 * the allow-list judgment lives in {@see CorsPolicy} (constructed through
 * {@see AllowedOrigins}), the origin grammar in {@see CorsOrigin}, the
 * preflight detection in {@see PreflightRequest}.
 *
 * Deviation from the reference, restructured for observability: the
 * reference re-read the `Origin` header inside three separate helpers
 * (the preflight judgment, the denial judgment and the decoration path);
 * here each path reads it once, through the collaborators that need it.
 * A non-empty `Access-Control-Request-Method` marker stays the
 * definition of a preflight (audit #328).
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    private CorsPolicy $corsPolicy;

    /**
     * @param null|list<mixed>|string $allowOrigin  the allow-list: one origin, a list of them,
     *                                              `'*'` for the echo-all policy, or null when
     *                                              CORS is off (an empty list is off too)
     * @param string                  $allowMethods the `Access-Control-Allow-Methods` answer
     * @param string                  $allowHeaders the `Access-Control-Allow-Headers` answer
     *
     * @throws HttpException when an allow-list entry is not a string, or a
     *                       non-wildcard entry is not a well-formed origin
     */
    public function __construct(
        array|string|null $allowOrigin = null,
        private string $allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private string $allowHeaders = 'Content-Type, Authorization, X-Request-ID',
    ) {
        $this->corsPolicy = new CorsPolicy($allowOrigin);
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $preflight = PreflightResponse::answer($this->corsPolicy, $request, $this->allowMethods, $this->allowHeaders);
        if ($preflight instanceof ResponseInterface) {
            return $preflight;
        }

        return $this->decorate($request, $handler->handle($request));
    }

    /**
     * Decorates a handler response whose request carried an allowed
     * origin; every other response flows through untouched.
     */
    private function decorate(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface
    {
        $echo = $this->corsPolicy->echoFor(mb_trim($serverRequest->getHeaderLine('Origin')));

        if ($echo === null) {
            return $response;
        }

        return CorsHeaders::stamp($response, $echo, $this->allowMethods, $this->allowHeaders);
    }
}
