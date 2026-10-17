<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\UnplannedInstanceDefinition;

/**
 * The unplanned form's planning run (the pass's request half; see
 * {@see AutowireCompilerPass} for the orchestration).
 *
 * The constructor's specs are read once, the declaration's own names
 * verified against them ({@see NamedFit}), the positional plan
 * assembled through the shared loop ({@see AutowirePlanning}) -- the
 * answer being the plain positional {@see InstanceDefinition} the graph
 * and the compiler already consume.
 */
final readonly class AutowireRequestPlanning
{
    public function __construct(
        private AutowireReferences $autowireReferences,
    ) {
    }

    public function plan(UnplannedInstanceDefinition $unplannedInstanceDefinition): InstanceDefinition
    {
        $specs = ConstructorSpecs::of(
            $unplannedInstanceDefinition->class,
            new ReflectionMetadataExtractor(),
        );

        $named = $unplannedInstanceDefinition->namedArguments;
        if ($named !== null) {
            NamedFit::assert($unplannedInstanceDefinition->id, $specs, $named);
        }

        $arguments = AutowirePlanning::arguments(
            $unplannedInstanceDefinition->id,
            $specs,
            $unplannedInstanceDefinition->namedArguments,
            $this->autowireReferences,
        );

        return new InstanceDefinition(
            $unplannedInstanceDefinition->id,
            $unplannedInstanceDefinition->class,
            $arguments,
        );
    }
}
