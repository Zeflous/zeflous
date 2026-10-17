<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The graph's capture verdict -- the third boot-time discipline over a
 * definition set's reference graph (see {@see ServiceDefinitionGraph}
 * for the other two).
 *
 * A dependency edge is a capture: the dependent observes the target's
 * instance at its own construction, and whatever lifetime that instance
 * had, the dependent holds it at least as long as itself. A singleton
 * capturing a request-scoped instance is therefore a lie the graph can
 * see before a single service resolves: the request-scoped declaration
 * promises its instances live no longer than one scope, and the
 * singleton would hold it forever -- the captive dependency, refused
 * with both lifetimes and the two ways out in the message.
 *
 * The narrower captures stay legal and are simply what they are: a
 * transient captured by anyone is built once at the captor's
 * construction (a fact of construction, not a broken promise), and a
 * request-scoped instance captured within its own scope lives exactly
 * as long as both of them.
 */
final readonly class LifetimeCaptures
{
    /**
     * Refuses every singleton-lifetime edge into a request-lifetime
     * definition.
     *
     * @param list<ServiceDefinition>     $definitions
     * @param array<string, list<string>> $edges
     */
    public static function refuse(array $definitions, array $edges): void
    {
        $lifetimes = self::lifetimesOf($definitions);

        foreach ($edges as $dependent => $targets) {
            if (($lifetimes[$dependent] ?? ServiceLifetime::SINGLETON) !== ServiceLifetime::SINGLETON) {
                continue;
            }

            foreach ($targets as $target) {
                if (($lifetimes[$target] ?? ServiceLifetime::SINGLETON) === ServiceLifetime::REQUEST) {
                    throw new DefinitionException(
                        "Service '{$dependent}' (singleton) depends on the request-scoped service '{$target}': "
                        . 'a request-scoped instance cannot be captured beyond its scope -- '
                        . "give '{$target}' a singleton or transient lifetime, or make '{$dependent}' request-scoped.",
                    );
                }
            }
        }
    }

    /**
     * @param list<ServiceDefinition> $definitions
     *
     * @return array<string, ServiceLifetime>
     */
    private static function lifetimesOf(array $definitions): array
    {
        $lifetimes = [];
        foreach ($definitions as $definition) {
            $lifetimes[$definition->id] = $definition->lifetime;
        }

        return $lifetimes;
    }
}
