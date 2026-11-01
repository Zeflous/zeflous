<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Zef\Framework\Config\ConfigException;

/**
 * The route extension point's refusal sentences: every way a module's
 * declared route table can fail its consumption, with the module named
 * first.
 *
 * The fold-per-concern pattern the lane's other refusal families carry
 * ({@see ModuleBootRefusals} discovery, {@see ModuleConfigRefusals}
 * configuration, {@see ModuleOrderRefusals} ordering): this one owns the
 * route declaration's consumption. The configuration verdicts a module's
 * table rides ({@see \Zef\Framework\Config\Schema\RouteSchema}) arrive as
 * the configuration layer's own {@see ConfigException} -- they are wrapped,
 * never replaced, so the module the failing table belongs to is the first
 * word the failure spells (the context assembler's KDD-MO6 doctrine).
 */
final readonly class ModuleRouteRefusals
{
    /**
     * The module's declared routes file is not loadable -- the loader's
     * own verdict (missing, unreadable, not an array return) wrapped with
     * the module named first.
     */
    public static function unloadable(string $module, ModuleException $moduleException): ModuleException
    {
        return new ModuleException(
            "The module '{$module}' declares the routes file its boot cannot "
            . 'load: ' . $moduleException->getMessage(),
            previous: $moduleException,
        );
    }

    /**
     * The module's declared routes file does not satisfy the Standard
     * Route Schema -- the routing schema's own violations wrapped with
     * the module named first.
     */
    public static function invalid(string $module, ConfigException $configException): ModuleException
    {
        return new ModuleException(
            "The module '{$module}' declares a routes file the Standard "
            . 'Route Schema refuses: ' . $configException->getMessage(),
            previous: $configException,
        );
    }
}
