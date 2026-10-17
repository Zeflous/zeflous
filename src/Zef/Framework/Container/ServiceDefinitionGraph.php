<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The boot-time validation of a definition set's reference graph.
 *
 * Three failures are surfaced before a single service is resolved -- the
 * roadmap's "detected at boot, not at runtime" discipline:
 *
 * - an *unknown target*: a declared reference names neither another
 *   definition nor a service the container already carries (collected by
 *   {@see DefinitionEdges});
 * - a *circular chain*: definitions referencing each other in a loop
 *   (directly or through intermediaries), reported with the walked chain
 *   ({@see DefinitionCycles});
 * - a *captive dependency*: a singleton-lifetime definition depending on
 *   a request-lifetime one, whose instance it would hold forever
 *   ({@see LifetimeCaptures}).
 *
 * Only definition-to-definition edges can cycle: an edge into a
 * closure-registered service is a leaf whose own behaviour is the host
 * application's responsibility, exactly as it is without the declarative
 * format.
 */
final readonly class ServiceDefinitionGraph
{
    /**
     * @param list<ServiceDefinition> $definitions
     */
    public static function validate(array $definitions, Container $container): void
    {
        $edges = DefinitionEdges::collect($definitions, $container);

        DefinitionCycles::assertAcyclic($edges);
        LifetimeCaptures::refuse($definitions, $edges);
    }
}
