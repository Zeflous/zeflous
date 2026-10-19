<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One interception entry's construction dispatch: the entry's own
 * shape picks its builder -- the class shape through {@see
 * InterceptionClassBuilt} (the fit proof, then the instantiation), the
 * factory shape through {@see InterceptionFactoryBuilt} (the
 * registered-service-then-static precedence, the object proof) -- and
 * an entry carrying neither spelling is the hand-built misuse the
 * dispatch refuses rather than guesses around (the parse guarantees
 * exactly one; this is the defence the documented VO-misuse posture
 * keeps).
 *
 * The builder is materialisation machinery: the entries it builds are
 * boot machinery, never container services, so their arguments resolve
 * through the plain lane once, at the arming.
 */
final readonly class InterceptionBuilder
{
    /**
     * Builds the entry's object through its own shape.
     */
    public static function built(InterceptionEntry $interceptionEntry, string $label, Container $container): object
    {
        $class = $interceptionEntry->class;
        if (\is_string($class)) {
            return InterceptionClassBuilt::built($label, $interceptionEntry->arguments, $class, $container);
        }

        $factory = $interceptionEntry->factory;
        if (\is_string($factory) && $factory !== '') {
            return InterceptionFactoryBuilt::built($label, $interceptionEntry->arguments, $factory, $container);
        }

        throw InterceptionBuiltRefusals::shapeNotSpelled($label);
    }
}
