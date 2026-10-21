<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use Zef\Framework\Config\Routing\RouteDispatcher;
use Zef\Framework\Container\Container;
use Zef\Framework\Router\Router;

/**
 * PSR-15 adapter that joins request metadata, radix matching and route middleware.
 *
 * The global middleware pipeline wraps this handler. Route middleware is then
 * composed inside it in the order stored on the matched route, and the terminal
 * handler executes the already-computed match through RouteDispatcher.
 *
 * @api
 */
final readonly class RouterRequestHandler implements RequestHandlerInterface
{
    private RouteDispatcher $routeDispatcher;

    public function __construct(
        private Router $router,
        private Container $container,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
        $this->routeDispatcher = $this->router->dispatcher($this->container);
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = mb_strtoupper($request->getMethod());
        $match = $this->router->match(
            $request->getMethod(),
            $this->requestPath($request),
            $this->requestHost($request),
            $request->getHeaderLine('Accept'),
            $request->getHeaderLine('Accept-Version') !== ''
                ? $request->getHeaderLine('Accept-Version')
                : $request->getHeaderLine('X-API-Version'),
        );

        // HEAD uses an explicit HEAD route when present; otherwise it may
        // reuse GET metadata and handler semantics for the same resource.
        if (
            $method === 'HEAD'
            && $match->isMethodNotAllowed()
            && \in_array('GET', $match->allowedMethods(), true)
        ) {
            $match = $this->router->match(
                'GET',
                $this->requestPath($request),
                $this->requestHost($request),
                $request->getHeaderLine('Accept'),
                $request->getHeaderLine('Accept-Version') !== ''
                    ? $request->getHeaderLine('Accept-Version')
                    : $request->getHeaderLine('X-API-Version'),
            );
        }

        $stack = [];
        if ($match->isFound()) {
            foreach ($match->route()->middleware() as $serviceId) {
                if (!$this->container->has($serviceId)) {
                    throw new MiddlewareException(\sprintf('Route middleware service "%s" is not registered.', $serviceId));
                }

                $middleware = $this->container->get($serviceId);
                if (!$middleware instanceof MiddlewareInterface) {
                    throw new MiddlewareException(\sprintf('Route middleware service "%s" does not implement MiddlewareInterface.', $serviceId));
                }

                $stack[] = $middleware;
            }
        }

        $routeDispatchHandler = new RouteDispatchHandler(
            $this->routeDispatcher,
            $match,
            $this->responseFactory,
            $this->streamFactory,
        );

        $response = new MiddlewarePipeline($stack, $routeDispatchHandler)->handle($request);
        $response = $this->applyConditionalRequest($request, $response, $method);

        if ($method !== 'HEAD' && $response->getStatusCode() !== 304) {
            return $response;
        }

        $message = $response->withBody($this->streamFactory->createStream(''));
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The HEAD response body operation did not return a ResponseInterface.');
        }

        return $message;
    }

    /**
     * Applies conditional GET/HEAD semantics to a successful representation.
     *
     * If-None-Match takes precedence over If-Modified-Since as required by HTTP.
     */
    private function applyConditionalRequest(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $method,
    ): ResponseInterface {
        if (!\in_array($method, ['GET', 'HEAD'], true) || $response->getStatusCode() !== 200) {
            return $response;
        }

        if ($request->hasHeader('If-None-Match')) {
            $condition = mb_trim($request->getHeaderLine('If-None-Match'));
            if ($condition === '*') {
                return $this->notModified($response);
            }

            $currentTag = mb_trim($response->getHeaderLine('ETag'));
            if ($currentTag === '') {
                return $response;
            }

            $currentOpaqueTag = preg_replace('/\AW\//i', '', $currentTag);
            preg_match_all('/(?:W\/)?"[^"]*"|\*/i', $condition, $matches);
            foreach ($matches[0] as $candidate) {
                if ($candidate === '*') {
                    return $this->notModified($response);
                }

                $candidateOpaqueTag = preg_replace('/\AW\//i', '', $candidate);
                if ($candidateOpaqueTag === $currentOpaqueTag) {
                    return $this->notModified($response);
                }
            }

            return $response;
        }

        if (!$request->hasHeader('If-Modified-Since')) {
            return $response;
        }

        $lastModified = strtotime($response->getHeaderLine('Last-Modified'));
        $ifModifiedSince = strtotime($request->getHeaderLine('If-Modified-Since'));
        if ($lastModified !== false && $ifModifiedSince !== false && $lastModified <= $ifModifiedSince) {
            return $this->notModified($response);
        }

        return $response;
    }

    private function notModified(ResponseInterface $response): ResponseInterface
    {
        $message = $response->withStatus(304)->withBody($this->streamFactory->createStream(''));
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The conditional response operation did not return a ResponseInterface.');
        }

        return $message;
    }

    private function requestPath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    private function requestHost(ServerRequestInterface $request): ?string
    {
        if (!$request->hasHeader('Host')) {
            $uriHost = $request->getUri()->getHost();

            return $uriHost === '' ? null : $uriHost;
        }

        $authority = mb_trim($request->getHeaderLine('Host'));
        if (preg_match('/\A([^:]+)(?::([0-9]{1,5}))?\z/D', $authority, $matches) !== 1) {
            return null;
        }

        if (isset($matches[2]) && ((int) $matches[2] < 1 || (int) $matches[2] > 65535)) {
            return null;
        }

        return $matches[1];
    }
}
