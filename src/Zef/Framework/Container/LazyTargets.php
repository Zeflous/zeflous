<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use ReflectionMethod;

/**
 * The lazy factory grammar's target half: the parse-time mirrors of
 * the invocation seam's own refusals ({@see FactoryInvocation}), taken
 * where the lazy grammar needs them -- the lazy declaration reflects
 * its factory at the parse, so a target that is not a class the
 * runtime carries, or a method the target does not declare, refuses
 * there instead of crashing the reflection.
 *
 * A factory target spelled as a service identifier (the instance
 * form) refuses with the target: the service's class is not nameable
 * at the parse, so the declaration cannot name the class its proxy
 * defers behind -- the class-named spelling is the one the lazy
 * grammar accepts. The eager spelling takes both refusals at the
 * first resolution instead ({@see FactoryInvocation}'s own
 * `is_callable` guard).
 */
final readonly class LazyTargets
{
    /**
     * Answers the factory method's own reflection, both existence
     * guards behind it.
     *
     * @param string $factory the validated `'Target::method'` spelling
     */
    public static function reflected(string $id, string $factory): ReflectionMethod
    {
        [$target, $method] = explode('::', $factory);

        if (!class_exists($target)) {
            throw LazyRefusals::targetNotAClass($id, $factory, $target);
        }

        if (!method_exists($target, $method)) {
            throw LazyRefusals::targetMethodUnknown($id, $factory, $target, $method);
        }

        return new ReflectionMethod($target, $method);
    }
}
