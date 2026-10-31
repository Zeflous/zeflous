<?php

declare(strict_types=1);

namespace Zef\Framework;

use Closure;
use Override;
use Zef\Framework\Container\Container;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The module registrar's container-backed answer: the port the module
 * contract declares, written through the boot's real container with the
 * claim policing the two-phase boot promises.
 *
 * Three refusals, each naming both sides:
 *
 * - a framework-reserved identifier (`framework.config` and the other
 *   read-side registrations the boot installs) is not a module's to claim
 *   -- the framework's own namespace stays one catalogue;
 * - an identifier another module already claimed in this boot is refused
 *   with the first claimant named -- the sketch's "any binding can be
 *   overridden" reading is deliberately NOT carried: an override's winner
 *   would depend on boot order, which is exactly the race the two-phase
 *   boot exists to close, and the tree's honest answer is a refusal that
 *   names both claimants (a module that means to vary a service decorates
 *   it through the container's decorator grammar instead) -- the claim
 *   ledger ({@see ModuleClaims}) is the boot's own shared state, one map
 *   every registrar of the phase writes through, because a claim flows
 *   into the container the moment it is made and no single registrar
 *   could see the others';
 * - an identifier the container already carries -- the `framework.config`
 *   service the bootstrapper registered ahead of this phase -- is refused
 *   the same way.
 *
 * What is not refused flows to the container as the registration form it
 * already speaks, so a module's services ride the same store, the same
 * memoisation and the same read side the configuration's own definitions
 * answer through. The host's declarative definitions are installed AFTER
 * the registration phase, riding the reserved-services precedent: a host
 * rebind of a module-claimed identifier wins, because the host is the
 * composition root -- the one direction an identifier may flow.
 */
final readonly class ContainerModuleRegistrar implements ModuleRegistrar
{
    /**
     * The identifiers the boot reserves for its own read side: a module
     * claiming one would be overwritten later in the same boot -- a lie the
     * refusal prevents from being told.
     */
    private const array RESERVED = [
        FrameworkDefaults::CONFIG_SERVICE,
        FrameworkDefaults::TAGS_SERVICE,
        FrameworkDefaults::AOT_SERVICE,
        FrameworkDefaults::NAMESPACES_SERVICE,
        FrameworkDefaults::INVENTORY_SERVICE,
        FrameworkDefaults::PROCESS_BOUNDARY_SERVICE,
    ];

    public function __construct(
        private Container $container,
        private string $module,
        private ModuleClaims $moduleClaims,
    ) {
    }

    #[Override]
    public function set(string $id, Closure $factory): void
    {
        if (\in_array($id, self::RESERVED, true)) {
            throw new ModuleException(
                "The module '{$this->module}' registers '{$id}', which the "
                . "framework reserves; a module never claims the framework's "
                . 'own namespace.',
            );
        }

        $claimant = $this->moduleClaims->claimant($id);

        if ($claimant !== null) {
            throw new ModuleException(
                "The module '{$this->module}' registers '{$id}', which the "
                . "module '{$claimant}' already claimed; one "
                . 'identifier is one registration.',
            );
        }

        if ($this->container->has($id)) {
            throw new ModuleException(
                "The module '{$this->module}' registers '{$id}', which the "
                . 'boot already carries; a module never rebinds the host.',
            );
        }

        $this->moduleClaims->claim($id, $this->module);
        $this->container->set($id, $factory);
    }

    #[Override]
    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }
}
