<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception lane's build-half refusal vocabulary: every
 * fail-closed message the materialisation can raise -- the constructor
 * fit's arity words ({@see InterceptionClassFit}), the factory lane's
 * object words ({@see InterceptionFactoryBuilt}), the dispatch's own
 * shape word ({@see InterceptionBuilder}) and the contracts' proof
 * ({@see InterceptionMaterialisations}) -- split from the parse half's
 * own words ({@see InterceptionRefusals}) the way the lane itself
 * splits, so neither vocabulary outgrows the word-owning size (the
 * {@see Autowiring\ConstructorFitRefusals} precedent for a stage's
 * own words).
 *
 * A throwing interceptor or listener is deliberately absent from this
 * vocabulary: the resolution lane has never wrapped a factory's own
 * failure, and the interception lane keeps that discipline -- the
 * host's machinery throws with the host's own stack, raw.
 */
final readonly class InterceptionBuiltRefusals
{
    /** The type-given tail the shape refusals share (one spelling). */
    private const string GIVEN = ' given.';

    /**
     * The class entry's declared plan runs past the constructor's last
     * parameter -- the surplus arguments would be silently swallowed by
     * the engine's own invocation.
     */
    public static function tooManyArguments(string $label, string $class, int $parameters): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' class '{$class}' declares more arguments than the "
            . "constructor carries ({$parameters}).",
        );
    }

    /**
     * The class entry's declared plan stops before a required
     * parameter -- the constructor cannot answer it itself.
     */
    public static function stopsBeforeRequired(string $label, string $class, string $parameter): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' class '{$class}' stops before the required parameter "
            . "'{$parameter}' -- declare its argument, or give the constructor a default.",
        );
    }

    /**
     * A materialised entry must implement the contract its knob
     * demands -- the build-time proof (the middleware stack's own
     * discipline: a non-conforming entry fails the boot closed).
     */
    public static function notImplementingContract(string $label, string $contract, mixed $built): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' built "
            . get_debug_type($built) . ', which does not implement the ' . $contract
            . ' contract the knob demands.',
        );
    }

    /**
     * A factory-shaped entry must build an object -- the contracts the
     * knobs demand are interfaces, and only an object can carry one.
     */
    public static function builtNotAnObject(string $label, mixed $built): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' factory built "
            . get_debug_type($built) . ', an interception builder must answer an object.',
        );
    }

    /**
     * A factory shape's registered target must resolve to an object
     * (the {@see FactoryClosures} discipline over the entry grammar).
     */
    public static function factoryTargetNotAnObject(string $label, string $target, mixed $service): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' factory service '{$target}' did not resolve to an object, "
            . get_debug_type($service) . self::GIVEN,
        );
    }

    /**
     * A hand-built entry carrying neither shape's spelling -- the
     * parse guarantees exactly one; the materialisation refuses the
     * misuse rather than guessing around it.
     */
    public static function shapeNotSpelled(string $label): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' carries neither a class nor a factory spelling -- "
            . 'exactly one must be declared.',
        );
    }
}
