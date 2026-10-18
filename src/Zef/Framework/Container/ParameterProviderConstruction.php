<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use ReflectionClass;

/**
 * One provider's constructibility: whether the class constructs with
 * no required arguments -- the lane has nothing to hand a provider, so
 * a constructor that demands parameters is a declaration defect
 * refused with the provider named (never a guess about what would
 * satisfy it -- exactly the magic the grammar refuses everywhere
 * else).
 *
 * Its own unit the way the declaration checks and the invocation half
 * are: one question, one answer.
 */
final readonly class ParameterProviderConstruction
{
    /**
     * Whether the provider constructs with no required arguments.
     *
     * @param class-string $provider the validated provider class
     */
    public static function bare(string $provider): bool
    {
        $constructor = new ReflectionClass($provider)->getConstructor();

        return ($constructor?->getNumberOfRequiredParameters() ?? 0) === 0;
    }
}
