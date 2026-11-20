<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The value lane's refusal vocabulary -- its own words, so the lane's
 * messages live in one place (the same discipline the injector and
 * contextual-binding families follow).
 */
final readonly class AutowireValueRefusals
{
    /** The spec reached the value arm without the attribute it serves. */
    public static function missingAttribute(string $ownerId, string $name): AutowiringException
    {
        return new AutowiringException(
            "Cannot plan '{$ownerId}': the value arm serves a #[Value] parameter, and "
            . "'\${$name}' carries no #[Value] attribute -- route the planner through "
            . 'carriesValue() first.',
        );
    }

    /** The address has no value in the parsed parameters map. */
    public static function missingKey(string $ownerId, string $name, string $key): AutowiringException
    {
        return new AutowiringException(
            "Cannot plan '{$ownerId}': the parameter '\${$name}' carries "
            . "#[Value('{$key}')] but the 'parameters' configuration has no value for '{$key}' -- "
            . "declare it under the parameters knob, or drop the attribute so the parameter's "
            . 'own default or explicit spelling applies.',
        );
    }

    /** The synthetic id collides with a declared definition. */
    public static function declaredCollision(string $ownerId, string $key): AutowiringException
    {
        return new AutowiringException(
            "Cannot plan '{$ownerId}': the synthetic value service '@value:{$key}' collides with a "
            . "declared definition of the same identifier -- the '@value:' prefix is the parameters "
            . "lane's own; declare the parameter under another address or the service under another "
            . 'identifier.',
        );
    }

    /**
     * The address's value cannot serve the parameter's declared type:
     * the environment layer hands raw strings, and no silent coercion
     * sits between it and the typed parameter (issue #800's finding 4 --
     * the first spelling let the mismatch through to the injection,
     * where a TypeError named the call site and never the value's own
     * source).
     *
     * @param string $builtinType the parameter's declared builtin type name
     */
    public static function typeMismatch(
        string $ownerId,
        string $name,
        string $key,
        mixed $value,
        string $builtinType,
    ): AutowiringException {
        return new AutowiringException(
            "Cannot plan '{$ownerId}': the parameter '\${$name}' declares {$builtinType} and "
            . "carries #[Value('{$key}')], but the 'parameters' value for '{$key}' is "
            . get_debug_type($value) . ' -- the environment layer hands raw strings and nothing '
            . 'casts them (the parameters lane coerces nothing, ever); declare the typed value '
            . 'through a parameter provider that computes it, or type the parameter as the '
            . 'string the source actually carries.',
        );
    }
}
