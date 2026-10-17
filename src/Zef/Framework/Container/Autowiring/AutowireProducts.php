<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\UnplannedInstanceDefinition;

/**
 * The type one declaration proves its service to be (the collector's
 * per-declaration verdict; see {@see AutowireImplementations} for the
 * collection this serves).
 *
 * A class-shaped declaration names its product by the class it spells
 * -- positional or unplanned, the class is the declaration's own
 * answer. A factory-shaped declaration names its product by the
 * static spelling's return type ({@see AutowireFactoryProduct}, with
 * the run's shadowing set). The shapes that name no class -- an
 * alias, a value -- answer null: not the declaration's business to
 * join a collection.
 */
final readonly class AutowireProducts
{
    /**
     * @param array<string, ServiceDefinition> $declaredById the run's declared set, by identifier
     */
    public static function of(ServiceDefinition $serviceDefinition, array $declaredById, Container $container): ?string
    {
        return match (true) {
            $serviceDefinition instanceof InstanceDefinition => $serviceDefinition->class,
            $serviceDefinition instanceof UnplannedInstanceDefinition => $serviceDefinition->class,
            $serviceDefinition instanceof FactoryDefinition => AutowireFactoryProduct::type(
                $serviceDefinition->factory,
                $declaredById,
                $container,
            ),
            default => null,
        };
    }
}
