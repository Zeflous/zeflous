<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteDispatcher;
use Zef\Framework\Container\Container;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\HeadResponse;
use Zef\Framework\Router\Router;

/**
 * PSR-15 adapter that joins request metadata, radix matching and route middleware.
 *
 * The global middleware pipeline wraps this handler. Route middleware is then
 * composed inside it in the order stored on the matched route, and the terminal
 * handler executes the already-computed match through RouteDispatchHandler,
 * translating a non-executed outcome into the typed refusal family the
 * request-scoped error boundary renders -- hosts compose this handler inside
 * a pipeline that carries {@see ErrorMappingMiddleware} (the shipped
 * profile's `http.errors` service) exactly as they do for their own
 * handlers' typed refusals. The request-to-match face lives in
 * {@see RouterRequestMatcher} (with {@see ServerRequestMetadata}), and
 * conditional GET/HEAD semantics live in {@see ConditionalRequests}.
 *
 * The match itself answers through the observability engines when the
 * construction carries a {@see RouteMatchTelemetry} (the router
 * observability family's unit): the match span and the router's own
 * metric series, opened under the request span the correlation boundary
 * exposed on the request. The telemetry is optional and null by default --
 * the family's honest off posture -- so a handler constructed without it
 * answers exactly as it always did.
 *
 * The handler also carries the matched route's pattern onto the error
 * boundary's own context ({@see ErrorRequestContext::setRoute()}) when
 * the boundary minted one on the request: the found match's pattern --
 * the routing table's own spelling -- rides the request-scoped carrier
 * the trace id's adoption already owns, so the boundary's fault metric
 * slices the errors total by the route the request actually landed on.
 * A request that carries no boundary context writes nothing (a custom
 * stack owes the counter nothing), and a refusal that matched no route
 * writes nothing either (the recorder's own closed spelling answers those
 * arms) -- the write is best-effort decoration, never a dependency of
 * the dispatch itself.
 *
 * @api
 */
final readonly class RouterRequestHandler implements RequestHandlerInterface
{
    private RouteDispatcher $routeDispatcher;

    private RouterRequestMatcher $routerRequestMatcher;

    private ConditionalRequests $conditionalRequests;

    public function __construct(
        Router $router,
        private Container $container,
        private StreamFactoryInterface $streamFactory,
        private ?RouteMatchTelemetry $routeMatchTelemetry = null,
    ) {
        $this->routeDispatcher = $router->dispatcher($this->container);
        $this->routerRequestMatcher = new RouterRequestMatcher($router);
        $this->conditionalRequests = new ConditionalRequests($this->streamFactory);
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $serverRequestMetadata = ServerRequestMetadata::fromRequest($request);
        $matchResult = $this->matchObserved($request, $serverRequestMetadata);

        if ($matchResult->isFound()) {
            ErrorRequestContext::fromAttribute($request->getAttribute(ErrorRequestContext::ATTRIBUTE))
                ?->setRoute($matchResult->route()->path()->pattern());
        }

        $routeDispatchHandler = new RouteDispatchHandler(
            $this->routeDispatcher,
            $matchResult,
            $serverRequestMetadata,
        );

        $stack = RouteMiddlewareStack::resolve($matchResult, $this->container);

        $response = new MiddlewarePipeline($stack, $routeDispatchHandler)->handle($request);
        $response = $this->conditionalRequests->apply($request, $response, $serverRequestMetadata->method);

        if ($serverRequestMetadata->method === 'HEAD') {
            // RFC 9110 section 9.3.2's SHOULD (issue #801's finding 1):
            // the HEAD answer carries the GET twin's Content-Length over
            // the head view's own body -- the length the router's own
            // empty-body swap used to lose to the reconciliation.
            return HeadResponse::view($response);
        }

        if ($response->getStatusCode() === 304) {
            $message = $response->withBody($this->streamFactory->createStream(''));
            if (!$message instanceof ResponseInterface) {
                throw new UnexpectedValueException(
                    'The HEAD response body operation did not return a ResponseInterface.',
                );
            }

            return $message;
        }

        return $response;
    }

    /**
     * The match, answered through the observability family's unit
     * when the construction carries one: the span under the request
     * span the correlation boundary exposed, the router's own metric
     * series, and the match's own result either way. A telemetry-less
     * construction answers the matcher directly -- the family's off
     * posture allocates and accounts nothing.
     */
    private function matchObserved(
        ServerRequestInterface $serverRequest,
        ServerRequestMetadata $serverRequestMetadata,
    ): MatchResult {
        if (!$this->routeMatchTelemetry instanceof RouteMatchTelemetry) {
            return $this->routerRequestMatcher->match($serverRequestMetadata);
        }

        return $this->routeMatchTelemetry->observed(
            $serverRequest,
            fn (): MatchResult => $this->routerRequestMatcher->match($serverRequestMetadata),
        );
    }
}
