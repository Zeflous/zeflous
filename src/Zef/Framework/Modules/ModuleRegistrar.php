<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * The registration surface a module's `register()` phase writes through:
 * the PSR-11 read side the contract already speaks, narrowed to one
 * declaration verb.
 *
 * A plain `ContainerInterface` cannot declare anything -- `get()`/`has()`
 * answer, nothing writes -- so the module contract passes this subtype
 * instead: the read side stays (a module may check what the boot already
 * carries), and `set()` is the one addition, the container's own
 * registration form. The claim is policed by the boot: an identifier
 * another module already claimed, or one the framework reserved, is a
 * refusal that names both sides -- never a silent overwrite.
 *
 * The lifetime is deliberately absent from the port: a registration is a
 * singleton by default, the container's own default, and the sharing
 * policy a module needs beyond that is a host concern the declarative
 * grammar owns.
 *
 * @api the module author's own write surface -- the contract's
 *      `register()` hands this port over, so its verb is called from
 *      module code that lives outside this repository
 */
interface ModuleRegistrar extends ContainerInterface
{
    /**
     * Registers a factory under an identifier.
     *
     * @param Closure(ContainerInterface): mixed $factory
     *
     * @throws ModuleException when the identifier is already claimed by
     *                         another module or reserved by the framework
     */
    public function set(string $id, Closure $factory): void;
}
