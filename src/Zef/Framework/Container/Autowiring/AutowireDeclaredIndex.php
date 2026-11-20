<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ServiceDefinition;

/**
 * The reuse query's own index: the run's declared set, keyed by
 * identifier -- one lookup per arm of {@see AutowireReuse}, so the arms
 * never re-walk the list the run was opened with.
 *
 * The index is the loop other lanes spell inline ({@see AutowireReuse}
 * kept it inline until the archeology gate's difficulty ceiling asked
 * the query to shed it); its own unit answers the map alone, the same
 * shape the births bookkeeping and the value materialisations carry for
 * their own lanes.
 */
final readonly class AutowireDeclaredIndex
{
    /**
     * @param list<ServiceDefinition> $definitions the run's declared set
     *
     * @return array<string, ServiceDefinition> the declared definitions, by identifier
     */
    public static function byId(array $definitions): array
    {
        $byId = [];

        foreach ($definitions as $definition) {
            $byId[$definition->id] = $definition;
        }

        return $byId;
    }
}
