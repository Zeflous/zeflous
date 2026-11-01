<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\RouteSchema;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleRouteRefusals;

/**
 * The route extension point's entry fold: one module's declared routes
 * file loaded, asserted and prefixed -- the whole table or nothing, every
 * failure carrying the module's name first.
 *
 * The fold owns one question (the per-module table's consumption) so the
 * join fold beside it ({@see ModuleRouteTable}) stays the walk over the
 * roster and the repository -- the lane's one-fold seam pattern the
 * construction guard and the rule-shape judgment already carry. Each
 * entry is asserted through the Standard Route Schema exactly as the
 * host's own table is ({@see RouteSchema}), then prefixed with the
 * module name's own segment (`zef/payment` serves under `/payment`) --
 * the sketch's automatic per-module prefix, derived through the grammar's
 * own two-segment guarantee ({@see ModuleName::suffix()}). Nothing else
 * is rewritten: a route's `name` stays the module author's own spelling,
 * and a collision with the host's or another module's name is the
 * routing engine's own compile-time conflict detection, fail-closed, not
 * a silent rewrite here.
 */
final readonly class ModuleRouteEntries
{
    public function __construct(
        private ModuleManifestLoader $moduleManifestLoader = new ModuleManifestLoader(),
        private RouteSchema $routeSchema = new RouteSchema(),
    ) {
    }

    /**
     * Answers the module's route entries, each path prefixed with the
     * module name's own segment.
     *
     * @return list<array<array-key, mixed>> the module's route entries,
     *                                       each path prefixed with the
     *                                       module name's own segment
     *
     * @throws ModuleException when the module's routes file cannot be
     *                         loaded or fails the Standard Route Schema
     */
    public function prefixed(string $module, string $routesPath): array
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
             */
            $path = $entry['path'];
            \assert(\is_string($path));
            $prefixed[] = [
                'path' => $prefix . '/' . mb_ltrim($path, '/'),
            ] + $entry;
        }

        return $prefixed;
    }
}
