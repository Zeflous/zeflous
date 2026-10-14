<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The factory shape's invocation seam (called by {@see FactoryClosures}).
 *
 * The callable -- `$carrier::$method` for the static form,
 * `$carrier->$method` for the instance form -- goes through the `is_callable`
 * check (the {@see \Zef\Framework\Config\Routing\RouteHandlerResolver} idiom):
 * the callable contract is validated before the invocation, so a missing or
 * non-public method fails closed with a {@see DefinitionException} naming
 * the target and the method.
 */
final readonly class FactoryInvocation
{
    /**
     * Invokes the factory callable with the resolved arguments.
     *
     * @param list<mixed> $arguments
     */
    public static function answer(
        object|string $carrier,
        string $method,
        array $arguments,
        Container $container,
        string $target,
    ): mixed {
        $callable = [$carrier, $method];
        if (!\is_callable($callable)) {
            throw new DefinitionException(
                "Factory target '{$target}' has no callable method '{$method}'.",
            );
        }

        return $callable(...DefinitionArguments::resolve($arguments, $container));
    }
}
