<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\AppEnvironment;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Router\Router;

/**
 * The router observability family's debug disclosure: the PSR-15
 * boundary that stamps the route match explanation on a
 * routing-refusal response -- the roadmap's debug mode, the
 * development answer to "why did this request not match".
 *
 * THE DISCLOSURE IS DOUBLE-GATED, fail-closed both ways: the
 * family's own `debug_match` knob must be armed (the factory
 * answers this middleware only when it is -- the middleware itself
 * never re-reads the knob, so a host cannot compose it by accident)
 * AND the running environment must be a development one ({@see
 * AppEnvironment}, the environment verdict's own allowlist fold:
 * an unset or unrecognized `APP_ENV` answers not-development, so no
 * spelling can accidentally arm the header in production). The
 * stamped value is the explanation's own bounded summary ({@see
 * RouteMatchExplanation::summary()}): every free-form rendering
 * clamped to its own budget and stripped of control bytes, because
 * a response header is a bounded, line-oriented channel.
 *
 * THE ATTRIBUTION IS PRECISE, never speculative: the middleware
 * explains the match for the request's own routing surface and
 * stamps nothing when the route table matched -- a 404 that came
 * from a handler's own typed refusal (or any other 404 the
 * application answered) is not the router's miss, and the header
 * would have claimed it was. The HEAD-over-GET fallback the
 * request matcher applies is replayed here on the explanation
 * itself (the same rule, the same folds): a HEAD request whose
 * only answer is the GET one is explained as the GET match the
 * dispatcher would have executed.
 *
 * The middleware composes OUTSIDE the error boundary in the global
 * stack (it observes the boundary's rendered answer), and never
 * overwrites a header a lane before it already stamped -- the
 * last writer wins everywhere else in PSR-15; the debug disclosure
 * refuses to be the second one.
 *
 * @internal
 */
final readonly class RouteDebugMiddleware implements MiddlewareInterface
{
    /** The response header the explanation is stamped under. */
    public const string DEBUG_HEADER = 'X-Route-Match-Debug';

    /** The invariant every with*() chain upholds: the PSR-7 instance type. */
    private const string PRESERVED_RESPONSE_TYPE = 'withHeader() must preserve the response type.';

    /** The routing-refusal statuses the disclosure answers: 404, 405, 406. */
    private const array REFUSAL_STATUSES = [404, 405, 406];

    /** The fallback method the request matcher replays for HEAD. */
    private const string HEAD_FALLBACK_METHOD = 'GET';

    public function __construct(
        private Router $router,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        if (!$this->disclosable($response)) {
            return $response;
        }

        return $this->disclosed($request, $response);
    }

    /**
     * Whether the response may carry the disclosure: the double
     * fail-closed gate's own fold -- a development environment, a
     * routing-refusal status, and no header a lane before it
     * already stamped.
     */
    private function disclosable(ResponseInterface $response): bool
    {
        return AppEnvironment::isDevelopment()
            && \in_array($response->getStatusCode(), self::REFUSAL_STATUSES, true)
            && !$response->hasHeader(self::DEBUG_HEADER);
    }

    /**
     * The disclosure itself: explain the request's own match and
     * stamp the bounded summary -- but only when the route table
     * itself refused the request, never when the 404/405/406 came
     * from past the router.
     */
    private function disclosed(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
    ): ResponseInterface {
        $serverRequestMetadata = ServerRequestMetadata::fromRequest($serverRequest);
        $routeMatchExplanation = $this->explained($serverRequestMetadata);

        if ($routeMatchExplanation->isFound()) {
            return $response;
        }

        $message = $response->withHeader(self::DEBUG_HEADER, $routeMatchExplanation->summary());
        \assert($message instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $message;
    }

    /**
     * Explains the request's match, replaying the request matcher's
     * own HEAD-over-GET fallback rule on the explanation: a HEAD
     * request whose only answer is the GET one is explained as the
     * GET match the dispatcher would have executed -- the same
     * folds, the same rule, so the explanation and the refusal it
     * rides agree.
     */
    private function explained(ServerRequestMetadata $serverRequestMetadata): RouteMatchExplanation
    {
        $routeMatchExplanation = $this->explainAs($serverRequestMetadata->method, $serverRequestMetadata);

        if (
            $serverRequestMetadata->method === 'HEAD'
            && $routeMatchExplanation->outcome() === RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED
            && \in_array(self::HEAD_FALLBACK_METHOD, $routeMatchExplanation->allowedMethods(), true)
        ) {
            return $this->explainAs(self::HEAD_FALLBACK_METHOD, $serverRequestMetadata);
        }

        return $routeMatchExplanation;
    }

    /**
     * The one explain call, over the router's own memoised tree --
     * the diagnostics seam the facade already exposes -- with the
     * routing surface exactly as the request matcher reads it (the
     * empty Accept and version spellings included: the engine's own
     * folds read them the same way the matcher's do).
     */
    private function explainAs(string $method, ServerRequestMetadata $serverRequestMetadata): RouteMatchExplanation
    {
        return $this->router->tree()->explain(
            $method,
            $serverRequestMetadata->path,
            $serverRequestMetadata->host,
            $serverRequestMetadata->accept,
            $serverRequestMetadata->version,
        );
    }
}
