<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\DispatchOutcome;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteDispatcher;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\NotAcceptableException;
use Zef\Framework\Exception\NotFoundException;
use Zef\Framework\Exception\ZefException;

/**
 * Terminal PSR-15 handler for one precomputed routing match -- the
 * dispatch translation the error system's consumers were waiting for.
 *
 * A found match executes and its representation is answered as before.
 * A non-executed dispatch outcome is no longer rendered here: it is
 * translated into the typed refusal family and thrown, so the
 * request-scoped error boundary ({@see ErrorMappingMiddleware}) owns the
 * answer the way it owns every other failure a handler lets escape --
 * the disclosure policy, the Accept negotiation with the HTML fallback,
 * the RFC 9457 type URIs, the machine-readable error codes, the trace
 * and correlation ids, the `no-store`/`nosniff` headers, the 4xx warning
 * log level and the fault metrics all render through the one posture
 * every other typed refusal already answers. The router joins the
 * contract its own docblocks promised: one 404/405/406 shape whether
 * the miss happened at the pattern, behind it, or in the negotiation.
 *
 * Each refusal carries exactly what its outcome can know: not acceptable
 * advertises the media types the route does produce
 * ({@see NotAcceptableException}), method not allowed advertises the
 * methods the path does answer with the request's own method and path
 * in the message ({@see MethodNotAllowedException} -- the `Allow`
 * header renders from its payload in
 * {@see \Zef\Framework\Http\ProblemDetailsResponseHeaders}), and any
 * other non-executed outcome is the plain not-found refusal
 * ({@see NotFoundException}). The request's method and path travel on
 * the {@see ServerRequestMetadata} the routing adapter already
 * extracted, so the refusal reports what the client asked, not what the
 * matcher happened to re-derive.
 *
 * @internal
 */
final readonly class RouteDispatchHandler implements RequestHandlerInterface
{
    private RouteDeprecationHeaders $routeDeprecationHeaders;

    public function __construct(
        private RouteDispatcher $routeDispatcher,
        private MatchResult $matchResult,
        private ServerRequestMetadata $serverRequestMetadata,
    ) {
        $this->routeDeprecationHeaders = new RouteDeprecationHeaders();
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $dispatchOutcome = $this->routeDispatcher->dispatchMatch($this->matchResult);

        if ($dispatchOutcome->isExecuted()) {
            return $this->representation($dispatchOutcome->result(), $dispatchOutcome->route());
        }

        throw $this->refusalFor($dispatchOutcome);
    }

    /**
     * The typed refusal a non-executed dispatch outcome translates to:
     * not acceptable names the producible media types, method not
     * allowed names the answered methods, and any other non-executed
     * outcome is a plain not-found refusal.
     */
    private function refusalFor(DispatchOutcome $dispatchOutcome): ZefException
    {
        if ($dispatchOutcome->isNotAcceptable()) {
            return new NotAcceptableException($dispatchOutcome->acceptableMediaTypes());
        }

        if ($dispatchOutcome->isMethodNotAllowed()) {
            return new MethodNotAllowedException(
                $this->serverRequestMetadata->method,
                $this->serverRequestMetadata->path,
                $dispatchOutcome->allowedMethods(),
            );
        }

        return new NotFoundException('No route matches the requested resource.');
    }

    /**
     * Narrows the executed handler's mixed result to a response, exactly
     * like {@see \Zef\Framework\Config\Routing\RouteHandlerResolver::callableService()}
     * narrows a resolved service: the mixed value arrives as a parameter,
     * not an assignment, and a non-response result is refused with the
     * handler's name.
     */
    private function representation(mixed $result, CompiledRoute $compiledRoute): ResponseInterface
    {
        if (!$result instanceof ResponseInterface) {
            throw new UnexpectedValueException(\sprintf(
                'The HTTP route handler "%s" must return a ResponseInterface.',
                $compiledRoute->handler(),
            ));
        }

        $response = $this->routeDeprecationHeaders->apply($result, $compiledRoute->deprecation());

        return $compiledRoute->produces() === []
            ? $response
            : $this->varyOnAccept($response);
    }

    private function withHeader(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        $message = $response->withHeader($name, $value);
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response header operation did not return a ResponseInterface.');
        }

        return $message;
    }

    private function varyOnAccept(ResponseInterface $response): ResponseInterface
    {
        $current = mb_trim($response->getHeaderLine('Vary'));
        if ($current === '*') {
            return $response;
        }

        $tokens = $current === '' ? [] : array_map(trim(...), explode(',', $current));
        foreach ($tokens as $token) {
            if (mb_strtolower($token) === 'accept') {
                return $response;
            }
        }

        $tokens[] = 'Accept';

        return $this->withHeader($response, 'Vary', implode(', ', $tokens));
    }
}
