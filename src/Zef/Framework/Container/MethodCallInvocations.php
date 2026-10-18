<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The method-call injection lane's runtime half (see {@see MethodCalls}
 * for the parsing half).
 *
 * The invocation goes through the callable-contract check (the
 * {@see FactoryInvocation} idiom): a method that is missing or not
 * callable from the outside is a named refusal, never an engine error.
 * The invoked method's answer is discarded -- a call is an injection,
 * not a builder.
 *
 * The factory arm's seam guards the product too: a factory may answer
 * any value, and only an object can carry injected method calls, so a
 * declared call on a non-object product is the honest refusal.
 */
final readonly class MethodCallInvocations
{
    /**
     * Applies the parsed calls to the built instance, each method invoked
     * with its arguments resolved, and answers the instance the calls
     * mutated.
     *
     * @param list<MethodCall> $calls
     */
    public static function apply(object $instance, array $calls, Container $container): object
    {
        foreach ($calls as $call) {
            $callable = [$instance, $call->method];
            if (!\is_callable($callable)) {
                throw new DefinitionException(
                    "Service call '{$call->method}' is not callable on "
                    . $instance::class . '.',
                );
            }

            $callable(...DefinitionArguments::resolve($call->arguments, $container));
        }

        return $instance;
    }

    /**
     * The factory arm's method-call seam: a declaration without calls
     * answers the product verbatim; one with calls has the product
     * proven an object before the calls apply.
     *
     * @param list<MethodCall> $calls
     */
    public static function onFactoryAnswer(mixed $answer, array $calls, Container $container, string $factory): mixed
    {
        if ($calls === []) {
            return $answer;
        }

        if (!\is_object($answer)) {
            throw new DefinitionException(
                "Factory '{$factory}' answered " . get_debug_type($answer)
                . '; method calls can only apply to an object product.',
            );
        }

        return self::apply($answer, $calls, $container);
    }
}
