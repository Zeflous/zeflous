<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Zef\Framework\Config\ConfigException;

/**
 * The event extension point's refusal sentences: every way a module's
 * declared events file can fail its consumption, with the module named
 * first.
 *
 * The fold-per-concern pattern the lane's other refusal families carry
 * ({@see ModuleBootRefusals} discovery, {@see ModuleConfigRefusals}
 * configuration, {@see ModuleOrderRefusals} ordering, {@see
 * ModuleRouteRefusals} the routes half): this one owns the event
 * declaration's consumption. The configuration verdicts a module's
 * events file rides ({@see \Zef\Framework\Config\Schema\EventSchema})
 * arrive as the configuration layer's own {@see ConfigException} -- they
 * are wrapped, never replaced, so the module the failing file belongs to
 * is the first word the failure spells (the context assembler's KDD-MO6
 * doctrine, replayed the way the routes half carried it).
 */
final readonly class ModuleEventsRefusals
{
    /**
     * The module's declared events file is not loadable -- the loader's
     * own verdict (missing, unreadable, not an array return) wrapped with
     * the module named first.
     */
    public static function unloadable(string $module, ModuleException $moduleException): ModuleException
    {
        return new ModuleException(
            "The module '{$module}' declares the events file its boot cannot "
            . 'load: ' . $moduleException->getMessage(),
            previous: $moduleException,
        );
    }

    /**
     * The module's declared events file does not satisfy the Standard
     * Events Schema -- the events schema's own violations wrapped with
     * the module named first.
     */
    public static function invalid(string $module, ConfigException $configException): ModuleException
    {
        return new ModuleException(
            "The module '{$module}' declares an events file the Standard "
            . 'Events Schema refuses: ' . $configException->getMessage(),
            previous: $configException,
        );
    }
}
