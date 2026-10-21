<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteDispatcher;

/**
 * Terminal PSR-15 handler for one precomputed routing match.
 *
 * @internal
 */
final readonly class RouteDispatchHandler implements RequestHandlerInterface
{
    public function __construct(
        private RouteDispatcher $routeDispatcher,
        private MatchResult $matchResult,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $dispatchOutcome = $this->routeDispatcher->dispatchMatch($this->matchResult);

        if ($dispatchOutcome->isNotAcceptable()) {
            return $this->withHeader(
                $this->problemResponse(
                    406,
                    'Not Acceptable',
                    'Supported media types: ' . implode(', ', $dispatchOutcome->acceptableMediaTypes()),
                ),
                'Vary',
                'Accept',
            );
        }

        if ($dispatchOutcome->isMethodNotAllowed()) {
            $response = $this->problemResponse(405, 'Method Not Allowed', 'The request method is not supported for this resource.');

            return $this->withHeader($response, 'Allow', implode(', ', $dispatchOutcome->allowedMethods()));
        }

        if (!$dispatchOutcome->isExecuted()) {
            return $this->problemResponse(404, 'Not Found', 'No route matches the requested resource.');
        }

        return $this->representation($dispatchOutcome->result(), $dispatchOutcome->route());
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

        return $compiledRoute->produces() === []
            ? $result
            : $this->varyOnAccept($result);
    }

    private function problemResponse(int $status, string $title, string $detail): ResponseInterface
    {
        $body = json_encode([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        $response = $this->responseFactory->createResponse($status);
        $response = $this->withHeader($response, 'Content-Type', 'application/problem+json');

        $message = $response->withBody($this->streamFactory->createStream($body));

        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response body operation did not return a ResponseInterface.');
        }

        return $message;
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
