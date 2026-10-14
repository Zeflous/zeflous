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
 * through the {@see FactoryInvocation} seam.
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

        return static function (Container $container) use ($target, $method, $arguments): mixed {
            if (!$container->has($target)) {
                return FactoryInvocation::answer($target, $method, $arguments, $container, $target);
            }

            $service = $container->get($target);
            if (!\is_object($service)) {
                throw new DefinitionException(
                    "Factory service '{$target}' did not resolve to an object, "
                    . get_debug_type($service) . ' given.',
                );
            }

            return FactoryInvocation::answer($service, $method, $arguments, $container, $target);
        };
    }
}
