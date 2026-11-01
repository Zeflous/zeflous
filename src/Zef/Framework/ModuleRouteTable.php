<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\RouteSchema;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleRouteRefusals;

/**
 * The route extension point's consumption fold: a module's declared
 * routes file joined into the frozen repository's `routes` table, so the
 * router the host builds over the booted kernel serves the module's
 * routes exactly as if the host had declared them -- the roadmap sketch's
 * "module returns its routes from the manifest, the kernel merges them
 * into the radix tree at compile" sentence, kept honest by the tree's own
 * disciplines.
 *
 * The merge rides the Standard Route Schema's own grammar: each module's
 * table is loaded through the module side's file loader, asserted entry
 * by entry through {@see RouteSchema}, and prefixed with the module
 * name's own segment (`zef/payment` serves under `/payment`) -- the
 * sketch's automatic per-module prefix. Nothing else is rewritten: a
 * route's `name` stays the module author's own spelling, and a collision
 * with the host's or another module's name is the routing engine's own
 * compile-time conflict detection, fail-closed, not a silent rewrite
 * here. The merged entries ride the SAME key the host's table rides, so
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
        private ModuleManifestLoader $moduleManifestLoader = new ModuleManifestLoader(),
        private RouteSchema $routeSchema = new RouteSchema(),
    ) {
    }

    /**
     * Answers the repository with every declaring module's route entries
     * joined under the `routes` key -- or the verbatim repository when no
     * module declares routes.
     *
     * @throws ModuleException when a module's routes file cannot be
     *                         loaded or fails the Standard Route Schema
     * @throws ConfigException when the host's own `routes` value is not
     *                         the array the boot's validation guarantees
     */
    public function augmented(Config $config, ModuleRoster $moduleRoster): Config
    {
        $moduleEntries = [];

        foreach ($moduleRoster->declarations() as $moduleDeclaration) {
            $routesPath = $moduleDeclaration->routes;

            if ($routesPath === null) {
                continue;
            }

            $moduleEntries[] = $this->prefixedEntries(
                $moduleDeclaration->metadata->name,
                $routesPath,
            );
        }

        if ($moduleEntries === []) {
            return $config;
        }

        $hostEntries = [];

        if ($config->has('routes')) {
            $hostEntries = $config->get('routes');
        }

        if (!\is_array($hostEntries)) {
            throw new ConfigException(
                'The "routes" configuration must be a list of route entries.',
            );
        }

        return $config->withSet('routes', array_merge($hostEntries, ...$moduleEntries));
    }

    /**
     * Loads, asserts and prefixes one module's route table -- the whole
     * table or nothing, every failure carrying the module's name first.
     *
     * @return list<array<array-key, mixed>> the module's route entries,
     *                                       each path prefixed with the
     *                                       module name's own segment
     */
    private function prefixedEntries(string $module, string $routesPath): array
    {
        try {
            $entries = $this->moduleManifestLoader->load($routesPath, 'module routes file');
        } catch (ModuleException $moduleException) {
            throw ModuleRouteRefusals::unloadable($module, $moduleException);
        }

        $prefix = '/' . ModuleName::suffix($module);
        $prefixed = [];

        foreach ($entries as $entry) {
            try {
                $this->routeSchema->assert($entry);
            } catch (ConfigException $verdict) {
                throw ModuleRouteRefusals::invalid($module, $verdict);
            }

            /**
             * @var array<array-key, mixed> $entry the schema-asserted route
             *                              record -- the assertion
             *                              above checked the shape
             *                              this reading trusts
             * @var string                  $path the record's own required path field
             */
            $path = $entry['path'];
            $prefixed[] = [
                'path' => $prefix . (str_starts_with($path, '/') ? '' : '/') . $path,
            ] + $entry;
        }

        return $prefixed;
    }
}
