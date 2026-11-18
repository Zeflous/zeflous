<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The derived edge's own judgment: the class-named factory target whose
 * identifier the planned set also carries -- the shadow the runtime
 * precedence resolves (a registered service identifier shadows an
 * equally-named class, the precedence {@see DefinitionCompiler} and
 * {@see FactoryClosures} both spell), so the edge the instance lane
 * will actually walk is an edge the boot graph collects and proves:
 * its cycles and its lifetime captures exactly like a declared
 * reference's (#783's finding: the runtime used to discover what the
 * boot never walked).
 *
 * The negative space is the static-call form -- a class-named target no
 * planned definition owns stays the pure static call, not an edge --
 * and the leaf: a closure-registered shadow takes the instance lane at
 * call time, but the host's own registration is a leaf the graph does
 * not pretend to prove; only the planned set's edges can cycle.
 */
final readonly class FactoryShadows
{
    /**
     * The shadowed factory target's identifier, or null when the target
     * stays the static-call form -- the derived edge's identifier,
     * collected by {@see DefinitionEdges} beside the declared
     * references.
     *
     * @param array<string, ServiceDefinition> $byId the planned set, keyed by identifier
     *
     * @return null|string the class-named target the planned set carries
     */
    public static function target(ServiceDefinition $serviceDefinition, array $byId): ?string
    {
        if (!$serviceDefinition instanceof FactoryDefinition) {
            return null;
        }

        [$target] = explode('::', $serviceDefinition->factory);

        if (!class_exists($target)) {
            return null;
        }

        return isset($byId[$target]) ? $target : null;
    }
}
