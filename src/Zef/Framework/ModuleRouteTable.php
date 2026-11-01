<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The route extension point's join fold: every declaring module's route
 * table joined into the frozen repository's `routes` table, so the router
 * the host builds over the booted kernel serves the module's routes
 * exactly as if the host had declared them -- the roadmap sketch's
 * "module returns its routes from the manifest, the kernel merges them
 * into the radix tree at compile" sentence, kept honest by the tree's
 * own disciplines.
 *
 * The fold walks the roster's declarations in the boot order they were
 * filed and delegates each declaring module's table to the entry fold
 * ({@see ModuleRouteEntries} -- the load/assert/prefix question this
 * class deliberately does not own). The merged entries ride the SAME key
 * the host's table rides, appended after the host's own entries, so
 * every consumer downstream -- the compiled tree cache included -- sees
 * one table, never a second source of truth.
 *
 * The augmentation happens at one deterministic seam -- after the module
 * registration phase, before the container's definitions install -- so a
 * configuration-cached boot and a freshly loaded one answer the same
 * merged table, and the empty posture is the honest identity: no module
 * declares routes, the repository rides verbatim.
 */
final readonly class ModuleRouteTable
{
    public function __construct(
        private ModuleRouteEntries $moduleRouteEntries = new ModuleRouteEntries(),
    ) {
    }

    /**
     * Answers the repository with every declaring module's route entries
     * joined under the `routes` key -- or the verbatim repository when no
     * module declares routes.
     *
     * @throws ConfigException when the host's own `routes` value is not
     *                         the array the boot's validation guarantees
     */
    public function augmented(Config $config, ModuleRoster $moduleRoster): Config
    {
        $moduleEntries = [];

        foreach ($moduleRoster->declarations() as $moduleDeclaration) {
            if ($moduleDeclaration->routes === null) {
                continue;
            }

            $moduleEntries[] = $this->moduleRouteEntries->prefixed(
                $moduleDeclaration->metadata->name,
                $moduleDeclaration->routes,
            );
        }

        if ($moduleEntries === []) {
            return $config;
        }

        if ($config->has('routes')) {
            $hostEntries = $config->get('routes');

            if (!\is_array($hostEntries)) {
                throw new ConfigException(
                    'The "routes" configuration must be a list of route entries.',
                );
            }
        } else {
            $hostEntries = [];
        }

        return $config->withSet('routes', array_merge($hostEntries, ...$moduleEntries));
    }
}
