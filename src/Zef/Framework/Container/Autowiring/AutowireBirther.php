<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;

/**
 * The birth engine: an unregistered instantiable class becomes its own
 * declared definition (see {@see AutowireReferences} for the arms that
 * decide to birth).
 *
 * The chain guard refuses a chain that closes onto itself with the
 * walked path (the same message shape the runtime cycle guard carries);
 * the class's own parameters are planned recursively through the same
 * planner tiers ({@see AutowirePlanning} over the constructor's specs);
 * the memoised definition rides the run's {@see AutowireBirths}, so a
 * second parameter naming the class reuses the first birth.
 */
final readonly class AutowireBirther
{
    public function __construct(
        private AutowireBirths $autowireBirths,
        private ReflectionMetadataExtractor $reflectionMetadataExtractor,
    ) {
    }

    public function birth(string $class, AutowireReferences $autowireReferences): string
    {
        \assert(class_exists($class), 'the arms only birth identifiers that name a class');
        $this->autowireBirths->guardChain($class);
        $this->autowireBirths->open($class);

        $arguments = AutowirePlanning::arguments(
            $class,
            ConstructorSpecs::of($class, $this->reflectionMetadataExtractor),
            null,
            $autowireReferences,
        );

        $this->autowireBirths->close($class, new InstanceDefinition($class, $class, $arguments));

        return $class;
    }
}
