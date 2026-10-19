<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use ReflectionClass;

/**
 * The interception class entry's constructor fit (the
 * {@see Autowiring\PositionalFit} rule, narrowed to the entry
 * grammar's positional form): the declared argument list must neither
 * run past the constructor's last parameter nor stop before its
 * required prefix -- proven at the materialisation seam, the last
 * moment before the engine's own constructor invocation, so a
 * mis-arity entry is a boot refusal naming the entry and the class,
 * never a silently-ignored surplus argument the engine would have
 * swallowed ({@see InterceptionMaterialisations}).
 *
 * The reflection is boot-only, read once per entry -- the same
 * discipline the autowiring lane keeps for its own constructor reads.
 *
 * The required scan guards inline rather than skipping: the divergent
 * spelling a `continue` carries (an optional parameter read before a
 * later required one -- a shape the engine itself deprecates) has no
 * honest witness, so the walk throws at the required parameter
 * directly and the optional boundary stays the constructor's own
 * answer.
 */
final readonly class InterceptionClassFit
{
    /**
     * Asserts the declared argument list fits the class's constructor.
     *
     * @param list<mixed>  $arguments the entry's validated argument list
     * @param class-string $class     the entry's proven class
     */
    public static function asserted(string $label, array $arguments, string $class): void
    {
        $parameters = new ReflectionClass($class)->getConstructor()?->getParameters() ?? [];

        if (\count($arguments) > \count($parameters)) {
            throw InterceptionRefusals::tooManyArguments($label, $class, \count($parameters));
        }

        foreach (\array_slice($parameters, \count($arguments)) as $reflectionParameter) {
            if (!$reflectionParameter->isOptional()) {
                throw InterceptionRefusals::stopsBeforeRequired($label, $class, $reflectionParameter->getName());
            }
        }
    }
}
