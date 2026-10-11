<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;
use Zef\Framework\Container\Container;

/**
 * Resolves a route's handler string into the {@see RouteHandler} the
 * dispatcher invokes -- container-mediated, in the two forms the ecosystem
 * already produces (see `ROADMAP.md`, "Standard Route Schema"):
 *
 * - a **service id** the container resolves to a callable (a closure or an
 *   invokable object) -- the DSL's `match()` verbs carry whatever string
 *   the application registered;
 * - a `Class@method` pair -- the instance is resolved from the container
 *   under the class's own name (the form the resource macro and the
 *   roadmap's examples emit: `PhotosController@index`). The container
 *   layer's future autowiring lane will widen this same seam; the
 *   dispatcher never instantiates classes itself, because a reflection
 *   birth without dependency injection is neither fail-closed nor the
 *   framework's contract.
 *
 * Every failure is a defect in the route table or the container's
 * registrations, so it fails closed with the handler in the message.
 *
 * @internal consumed through {@see RouteDispatcher}
 */
final readonly class RouteHandlerResolver
{
    private const string SEPARATOR = '@';

    public function __construct(
        private Container $container,
    ) {
    }

    public function resolve(string $handler): RouteHandler
    {
        if ($this->container->has($handler)) {
            return $this->callableService($handler, $this->container->get($handler));
        }

        $parts = explode(self::SEPARATOR, $handler, 2);
        if (\count($parts) === 2) {
            return $this->callableMethod($handler, $parts[0], $parts[1]);
        }

        throw new ConfigException(\sprintf('The route handler "%s" is not a registered service.', $handler));
    }

    private function callableService(string $handler, mixed $service): RouteHandler
    {
        if (\is_callable($service)) {
            return new RouteHandler(static fn (array $parameters): mixed => $service($parameters));
        }

        throw new ConfigException(\sprintf('The route handler "%s" does not resolve to a callable.', $handler));
    }

    private function callableMethod(string $handler, string $class, string $method): RouteHandler
    {
        if (!$this->container->has($class)) {
            throw new ConfigException(\sprintf(
                'The route handler "%s" names the service "%s", which is not registered.',
                $handler,
                $class,
            ));
        }

        $callable = [$this->container->get($class), $method];
        if (\is_callable($callable)) {
            return new RouteHandler(static fn (array $parameters): mixed => $callable($parameters));
        }

        throw new ConfigException(\sprintf('The route handler "%s" does not resolve to a callable.', $handler));
    }
}
