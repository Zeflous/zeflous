<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Zef\Framework\Config\ConfigException;

/**
 * The CLI extension point's refusal sentences: every way a module's
 * declared commands file can fail its consumption, with the module
 * named first.
 *
 * The fold-per-concern pattern the lane's other refusal families carry
 * ({@see ModuleBootRefusals} discovery, {@see ModuleConfigRefusals}
 * configuration, {@see ModuleOrderRefusals} ordering, {@see
 * ModuleRouteRefusals} the routes half, {@see ModuleEventsRefusals}
 * the events half): this one owns the command declaration's
 * consumption. The configuration verdicts a module's commands file
 * rides ({@see \Zef\Framework\Config\Schema\CliSchema}) arrive as the
 * configuration layer's own {@see ConfigException} -- they are
 * wrapped, never replaced, so the module the failing file belongs to
 * is the first word the failure spells (the context assembler's
 * KDD-MO6 doctrine, replayed the way the routes and events halves
 * carried it).
 */
final readonly class ModuleCliRefusals
{
    /**
     * The module's declared commands file is not loadable -- the
     * loader's own verdict (missing, unreadable, not an array return)
     * wrapped with the module named first.
     */
    public static function unloadable(string $module, ModuleException $moduleException): ModuleException
    {
        return new ModuleException(
            "The module '{$module}' declares the cli file its boot cannot "
            . 'load: ' . $moduleException->getMessage(),
            previous: $moduleException,
        );
    }

    /**
     * The module's declared commands file does not satisfy the Standard
     * CLI Schema -- the schema's own violations wrapped with the module
     * named first.
     */
    public static function invalid(string $module, ConfigException $configException): ModuleException
    {
        return new ModuleException(
            "The module '{$module}' declares a cli file the Standard "
            . 'CLI Schema refuses: ' . $configException->getMessage(),
            previous: $configException,
        );
    }
}
