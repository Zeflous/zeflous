<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;

/**
 * The positional shape's honesty read (the pass's validation half; see
 * {@see AutowireCompilerPass} for the orchestration).
 *
 * The declared list is the hand-written plan and stays verbatim --
 * explicit wins over auto -- but the constructor is read once for the
 * two verdicts the plan must survive: the class instantiable, the list
 * neither longer than the constructor nor shorter than its required
 * prefix ({@see PositionalFit} over the specs
 * {@see ConstructorSpecs} reads).
 */
final readonly class AutowirePositionalCheck
{
    public static function checked(InstanceDefinition $instanceDefinition): InstanceDefinition
    {
        PositionalFit::assert(
            $instanceDefinition,
            ConstructorSpecs::of($instanceDefinition->class, new ReflectionMetadataExtractor()),
        );

        return $instanceDefinition;
    }
}
