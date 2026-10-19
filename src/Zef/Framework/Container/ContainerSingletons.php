<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The warmup's input collector: the singleton-lifetime identifiers a
 * planned definition set carries, in declaration order -- the one
 * question the eager-construction orchestration asks of the set
 * (ROADMAP area 8's warm knob; {@see SingletonWarmer} constructs what
 * this unit names).
 */
final readonly class ContainerSingletons
{
    /**
     * @param list<ServiceDefinition> $definitions the planned set
     *
     * @return list<string>
     */
    public static function planned(array $definitions): array
    {
        $ids = [];

        foreach ($definitions as $definition) {
            if ($definition->lifetime !== ServiceLifetime::SINGLETON) {
                continue;
            }

            $ids[] = $definition->id;
        }

        return $ids;
    }
}
