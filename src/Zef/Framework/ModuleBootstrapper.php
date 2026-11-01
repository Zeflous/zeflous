<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Modules\ModuleEntries;
use Zef\Framework\Modules\ModuleOrder;

/**
 * The module engine's registration phase: the `modules` knob resolved into
 * its boot-ordered roster, every module registered on the container before
 * a single declarative definition is installed.
 *
 * The phase is the first half of the two-phase boot the roadmap spells
 * ("Module Integration System"): the knob's entries are discovered
 * ({@see ModuleResolver}), ordered by their declared requirements with the
 * declaration order as the tiebreak ({@see ModuleOrder}), and then
 * registered in that order through the boot-policing
 * {@see ContainerModuleRegistrar} -- so a configuration definition may
 * already depend on a module's registration, and no module resolves a
 * service before the whole set has declared.
 *
 * The empty knob is the honest default: no modules declared, no discovery,
 * no instances, an empty roster -- a boot without modules behaves exactly
 * as it did before the engine existed.
 */
final readonly class ModuleBootstrapper
{
    public function __construct(
        private ModuleResolver $moduleResolver = new ModuleResolver(),
    ) {
    }

    /**
     * Runs the registration phase and answers the roster the boot phase
     * will walk.
     *
     * @throws Modules\ModuleException when the knob's
     *                                 grammar, a module's
     *                                 discovery, the
     *                                 dependency order or a
     *                                 registration claim
     *                                 fails
     */
    public function register(Config $config, Container $container): ModuleRoster
    {
        $moduleRoster = new ModuleRoster($config);
        $entries = ModuleEntries::parse($config->get('modules', []));
        $declarations = [];
        $modules = [];

        foreach ($entries as $entry) {
            [$declaration, $module] = $this->moduleResolver->resolve($entry);
            $declarations[] = $declaration;
            $modules[$declaration->metadata->name] = $module;
        }

        $moduleClaims = new ModuleClaims();

        foreach (ModuleOrder::ordered($declarations) as $declaration) {
            $name = $declaration->metadata->name;
            $modules[$name]->register(new ContainerModuleRegistrar($container, $name, $moduleClaims));
            $moduleRoster->add($declaration, $modules[$name]);
        }

        return $moduleRoster;
    }
}
