<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Rate-limit middleware -- the admission control boundary, the HTTP
 * layer's sixth PSR-15 middleware.
 *
 * An armed policy ({@see RateLimitPolicy::$enabled}) resolves the
 * request's client address ({@see ClientAddress}, the trusted-proxy
 * boundary), accounts the request against the address's fixed window
 * ({@see RateLimiterInterface}, whichever store the factory built), and
 * answers:
 *
 * - allowed: the handler's response, stamped `X-RateLimit-Limit` and
 *   `X-RateLimit-Remaining` (replace, never append -- the accounting
 *   boundary wins over handler-set values);
 * - denied: `429 Too Many Requests` with `Retry-After` (the window's
 *   remaining seconds, clamped at one), `X-RateLimit-Limit` and
 *   `X-RateLimit-Remaining: 0`, in the layer's JSON error envelope
 *   ({@see ErrorResponseFactory}) so the client correlates the denial
 *   exactly like any other mapped failure;
 * - limiter failure (a store outage, the in-memory capacity bound): the
 *   fail-closed `503 Service Unavailable` with `Retry-After: 1` -- a
 *   rate limiter that cannot account is a protection that is not
 *   running, and failing open would admit unaccounted traffic.
 *
 * A disarmed policy is a pass-through: the middleware in the stack is
 * the admission seam, the knob arms it -- the operator's emergency
 * switch, one env overlay away from the file.
 *
 * OPT-IN: add the middleware to the stack of an application that wants
 * admission control; it is intentionally not part of any default stack.
 * The config-driven path is the framework profile's own
 * `security.rate_limit` service registration ({@see
 * \Zef\Framework\FrameworkDefaults}), whose knobs are validated and
 * frozen at boot.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Security/SecurityRuntimeMiddleware.php`, the rate-limit arm
 * of milestone E2's runtime boundary): the decision order, the 429/503
 * fail-closed answers, the header set and the clamps are move-verbatim;
 * restructured -- the reference resolved the address, throttled and
 * rendered three unrelated concerns inside one middleware; here the
 * address resolution is the layer's own collaborator, the store is
 * built by the factory from the `store` knob, and the error bodies use
 * the layer's single error envelope (the reference hand-rolled a second
 * shape) with the correlation id always present.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    /** The invariant every with*() chain upholds: the PSR-7 instance type. */
    private const string PRESERVED_RESPONSE_TYPE = 'withHeader() must preserve the response type.';

    public function __construct(
        private RateLimitPolicy $rateLimitPolicy,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->rateLimitPolicy->enabled) {
            return $handler->handle($request);
        }

        return $this->admission($request, $handler);
    }

    /**
     * The armed path: resolve the address, account the request against
     * its fixed window, and answer -- the outage refusal, the denial, or
     * the handler's response stamped with the accounting.
     */
    private function admission(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $clientAddress = ClientAddress::resolve($request, $this->rateLimitPolicy->trustedProxies);

        try {
            $decision = $this->rateLimiter->check(
                $clientAddress,
                $this->rateLimitPolicy->limit,
                $this->rateLimitPolicy->window,
            );
        } catch (Throwable) {
            return $this->unavailable($request);
        }

        if (!$decision->allowed) {
            return $this->denied($request, $decision);
        }

        return $this->limited($handler->handle($request), $decision);
    }

    /**
     * The fail-closed outage answer: 503 with a one-second retry window,
     * in the layer's error envelope.
     */
    private function unavailable(ServerRequestInterface $request): ResponseInterface
    {
        $message = ErrorResponseFactory::create(503, 'Service Unavailable', CorrelationId::for($request))
            ->withHeader('Retry-After', '1')
        ;

        \assert($message instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $message;
    }

    /**
     * The denial answer: 429 with the window's retry window and the
     * exhausted accounting headers.
     */
    private function denied(
        ServerRequestInterface $request,
        RateLimitDecision $rateLimitDecision,
    ): ResponseInterface {
        $message = ErrorResponseFactory::create(429, 'Too Many Requests', CorrelationId::for($request))
            ->withHeader('Retry-After', (string) $rateLimitDecision->retryAfter)
            ->withHeader('X-RateLimit-Limit', (string) $rateLimitDecision->limit)
            ->withHeader('X-RateLimit-Remaining', '0')
        ;

        \assert($message instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $message;
    }

    /**
     * The success stamp: the accounting headers, replace-not-append over
     * whatever the handler set.
     */
    private function limited(ResponseInterface $response, RateLimitDecision $rateLimitDecision): ResponseInterface
    {
        $message = $response
            ->withHeader('X-RateLimit-Limit', (string) $rateLimitDecision->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $rateLimitDecision->remaining)
        ;

        \assert($message instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $message;
    }
}
