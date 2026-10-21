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
 * handler executes the already-computed match through RouteDispatchHandler.
 * The request-to-match face lives in {@see RouterRequestMatcher} (with
 * {@see ServerRequestMetadata}), and conditional GET/HEAD semantics live in
 * {@see ConditionalRequests}.
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
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
        $this->routeDispatcher = $router->dispatcher($this->container);
        $this->routerRequestMatcher = new RouterRequestMatcher($router);
        $this->conditionalRequests = new ConditionalRequests($this->streamFactory);
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $serverRequestMetadata = ServerRequestMetadata::fromRequest($request);
        $match = $this->routerRequestMatcher->match($serverRequestMetadata);

        $stack = [];
        if ($match->isFound()) {
            foreach ($match->route()->middleware() as $serviceId) {
                if (!$this->container->has($serviceId)) {
                    throw new MiddlewareException(\sprintf(
                        'Route middleware service "%s" is not registered.',
                        $serviceId,
                    ));
                }

                $middleware = $this->container->get($serviceId);
                if (!$middleware instanceof MiddlewareInterface) {
                    throw new MiddlewareException(\sprintf(
                        'Route middleware service "%s" does not implement MiddlewareInterface.',
                        $serviceId,
                    ));
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
        $response = $this->conditionalRequests->apply($request, $response, $serverRequestMetadata->method);

        if ($serverRequestMetadata->method !== 'HEAD' && $response->getStatusCode() !== 304) {
            return $response;
        }

        $message = $response->withBody($this->streamFactory->createStream(''));
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The HEAD response body operation did not return a ResponseInterface.');
        }

        return $message;
    }
}
