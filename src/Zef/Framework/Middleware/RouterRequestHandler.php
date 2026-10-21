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
            self::requestPath($request),
            self::requestHost($request),
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
            && in_array('GET', $match->allowedMethods(), true)
        ) {
            $match = $this->router->match(
                'GET',
                self::requestPath($request),
                self::requestHost($request),
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
                    throw new MiddlewareException(sprintf('Route middleware service "%s" is not registered.', $serviceId));
                }

                $middleware = $this->container->get($serviceId);
                if (!$middleware instanceof MiddlewareInterface) {
                    throw new MiddlewareException(sprintf('Route middleware service "%s" does not implement MiddlewareInterface.', $serviceId));
                }

                $stack[] = $middleware;
            }
        }

        $terminal = new RouteDispatchHandler(
            $this->routeDispatcher,
            $match,
            $this->responseFactory,
            $this->streamFactory,
        );

        $response = (new MiddlewarePipeline($stack, $terminal))->handle($request);
        if ($method !== 'HEAD') {
            return $response;
        }

        $headResponse = $response->withBody($this->streamFactory->createStream(''));
        if (!$headResponse instanceof ResponseInterface) {
            throw new \\UnexpectedValueException('The HEAD response body operation did not return a ResponseInterface.');
        }

        return $headResponse;
    }

    private static function requestPath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    private static function requestHost(ServerRequestInterface $request): ?string
    {
        if (!$request->hasHeader('Host')) {
            $uriHost = $request->getUri()->getHost();

            return $uriHost === '' ? null : $uriHost;
        }

        $authority = trim($request->getHeaderLine('Host'));
        if (preg_match('/\A([^:]+)(?::([0-9]{1,5}))?\z/D', $authority, $matches) !== 1) {
            return null;
        }

        if (isset($matches[2]) && ((int) $matches[2] < 1 || (int) $matches[2] > 65535)) {
            return null;
        }

        return $matches[1];
    }
}
