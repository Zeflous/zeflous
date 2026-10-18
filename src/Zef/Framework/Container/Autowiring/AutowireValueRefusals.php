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
}
