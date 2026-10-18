<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The factory shape's closure builder (one compile arm of
 * {@see DefinitionCompiler}).
 *
 * Factory precedence (mirrored by {@see DefinitionReferences}): a target
 * the container has registered is resolved and called as an instance
 * method; a class-named target is called statically. Both call sites go
 * through the {@see FactoryInvocation} seam, and a declaration carrying
 * `calls` has them applied to the factory's product through
 * {@see MethodCallInvocations} -- with the product proven an object
 * first, because a factory may answer any value and only an object can
 * carry injected method calls.
 */
final readonly class FactoryClosures
{
    /**
     * @return Closure(Container): mixed
     */
    public static function build(FactoryDefinition $factoryDefinition): Closure
    {
        [$target, $method] = explode('::', $factoryDefinition->factory);
        $arguments = $factoryDefinition->arguments;
        $calls = $factoryDefinition->calls;
        $factory = $factoryDefinition->factory;

        return static function (Container $container) use ($target, $method, $arguments, $calls, $factory): mixed {
            if (!$container->has($target)) {
                return MethodCallInvocations::onFactoryAnswer(
                    FactoryInvocation::answer($target, $method, $arguments, $container, $target),
                    $calls,
                    $container,
                    $factory,
                );
            }

            $service = $container->get($target);
            if (!\is_object($service)) {
                throw new DefinitionException(
                    "Factory service '{$target}' did not resolve to an object, "
                    . get_debug_type($service) . ' given.',
                );
            }

            return MethodCallInvocations::onFactoryAnswer(
                FactoryInvocation::answer($service, $method, $arguments, $container, $target),
                $calls,
                $container,
                $factory,
            );
        };
    }
}
