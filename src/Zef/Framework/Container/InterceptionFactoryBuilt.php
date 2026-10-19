<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception entry's factory-shape construction (the factory
 * lane's own precedence, narrowed to what an entry is): a target the
 * container has registered resolves and runs as an instance method, a
 * class-named target runs statically -- both under {@see
 * FactoryInvocation}'s callable contract proof, and the built answer
 * proven an object ({@see InterceptionRefusals::builtNotAnObject()}
 * -- the contracts the knobs demand are interfaces, and only an object
 * can carry one).
 */
final readonly class InterceptionFactoryBuilt
{
    /**
     * Builds the factory-shaped entry's object.
     *
     * @param list<mixed>      $arguments the entry's validated argument list
     * @param non-empty-string $factory   the entry's proven 'Target::method' spelling
     */
    public static function built(string $label, array $arguments, string $factory, Container $container): object
    {
        [$target, $method] = explode('::', $factory);

        if (!$container->has($target)) {
            return self::objectAnswer(
                FactoryInvocation::answer($target, $method, $arguments, $container, $target),
                $label,
            );
        }

        $service = $container->get($target);
        if (!\is_object($service)) {
            throw InterceptionRefusals::factoryTargetNotAnObject($label, $target, $service);
        }

        return self::objectAnswer(
            FactoryInvocation::answer($service, $method, $arguments, $container, $target),
            $label,
        );
    }

    /**
     * The built answer's object proof.
     *
     * @param mixed $answer the factory shape's built answer
     */
    private static function objectAnswer(mixed $answer, string $label): object
    {
        if (!\is_object($answer)) {
            throw InterceptionRefusals::builtNotAnObject($label, $answer);
        }

        return $answer;
    }
}
