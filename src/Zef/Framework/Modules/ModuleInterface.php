<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The registration unit of the ZEF module system (see `ROADMAP.md`, "Module
 * Integration System (Hexagonal Plug-n-Play)").
 *
 * A module is a self-registering unit the kernel boots in two phases. It is
 * the ONE contract a module author implements -- no inheritance, no base
 * class, no framework internals touched:
 *
 * - `metadata()` answers the module's declarative identity -- its name (the
 *   `vendor/name` spelling the configuration namespace derives from), its
 *   version and the other module names it requires. The manifest file
 *   carries the same identity declaratively; when both are present the boot
 *   refuses a disagreement, so neither spelling can lie about the other.
 * - `register()` receives the {@see ModuleRegistrar} -- a
 *   {@see ContainerInterface} narrowed by the one verb a registration needs
 *   -- and declares services on it. The phase runs for EVERY module before
 *   any module boots, and before a single declarative definition is
 *   installed, so anything a registration declares can be depended on by the
 *   configuration's own definitions and is resolved only later.
 * - `boot()` receives the module's readonly {@see ModuleContext} -- its
 *   identity and its own namespaced, schema-validated configuration block
 *   -- after every registration has settled. The context is the module's
 *   one window on the boot; the extension-point faces (routes, events, the
 *   command registry) join it as they land, each with its own slice.
 *
 * The contract keeps the hexagonal direction the roadmap spells: a module
 * speaks through interfaces and the container, never by importing another
 * module's classes, so removing one module leaves the rest untouched.
 */
interface ModuleInterface
{
    /**
     * The module's declarative identity: the name, the version and the
     * required module names the boot orders and validates against.
     */
    public function metadata(): ModuleMetadata;

    /**
     * Declares the module's services on the given registrar. Called for
     * every module before any module boots.
     */
    public function register(ModuleRegistrar $moduleRegistrar): void;

    /**
     * Runs the module's deferred boot work against its own readonly
     * context. Called after every module has registered.
     */
    public function boot(ModuleContext $moduleContext): void;
}
