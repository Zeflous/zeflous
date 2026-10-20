<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\AotArtifact;

/**
 * The warmup's input collector: the singleton-lifetime identifiers a
 * boot's definitions carry, in declaration order -- the one question
 * the eager-construction orchestration asks (ROADMAP area 8's warm
 * knob; {@see SingletonWarmer} constructs what this unit names).
 *
 * Both lanes' definition sets answer it: the compile lane's planned
 * set ({@see self::planned()}) and the artifact lane's carried entries
 * ({@see self::carried()}).
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

    /**
     * The singleton-lifetime identifiers the artifact's entries carry,
     * in declaration order.
     *
     * @return list<string>
     */
    public static function carried(AotArtifact $aotArtifact): array
    {
        $ids = [];

        foreach ($aotArtifact->entries() as $id => $entry) {
            if ($entry->lifetime !== ServiceLifetime::SINGLETON) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
