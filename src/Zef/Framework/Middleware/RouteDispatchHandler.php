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
use Zef\Framework\Config\Routing\DispatchOutcome;
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
        private RouteDispatcher $dispatcher,
        private MatchResult $match,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $outcome = $this->dispatcher->dispatchMatch($this->match);

        if ($outcome->isNotAcceptable()) {
            return $this->withHeader(
                $this->textResponse(
                    406,
                    'Not Acceptable. Supported media types: ' . implode(', ', $outcome->acceptableMediaTypes()),
                ),
                'Vary',
                'Accept',
            );
        }

        if ($outcome->isMethodNotAllowed()) {
            $response = $this->textResponse(405, 'Method Not Allowed');
            return $this->withHeader($response, 'Allow', implode(', ', $outcome->allowedMethods()));
        }

        if (!$outcome->isExecuted()) {
            return $this->textResponse(404, 'Not Found');
        }

        $response = $outcome->result();
        if (!$response instanceof ResponseInterface) {
            throw new UnexpectedValueException(sprintf(
                'The HTTP route handler "%s" must return a ResponseInterface.',
                $outcome->route()->handler(),
            ));
        }

        return $outcome->route()->produces() === []
            ? $response
            : $this->varyOnAccept($response);
    }

    private function textResponse(int $status, string $body): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response = $this->withHeader($response, 'Content-Type', 'text/plain; charset=utf-8');
        $updated = $response->withBody($this->streamFactory->createStream($body));

        if (!$updated instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response body operation did not return a ResponseInterface.');
        }

        return $updated;
    }

    private function withHeader(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        $updated = $response->withHeader($name, $value);
        if (!$updated instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response header operation did not return a ResponseInterface.');
        }

        return $updated;
    }

    private function varyOnAccept(ResponseInterface $response): ResponseInterface
    {
        $current = trim($response->getHeaderLine('Vary'));
        if ($current === '*') {
            return $response;
        }

        $tokens = $current === '' ? [] : array_map('trim', explode(',', $current));
        foreach ($tokens as $token) {
            if (strtolower($token) === 'accept') {
                return $response;
            }
        }

        $tokens[] = 'Accept';

        return $this->withHeader($response, 'Vary', implode(', ', $tokens));
    }
}
