<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The demanding identity: its metadata carries a non-empty requires list,
 * so the identity-agreement refusal that names it spells both sides'
 * requirements -- the arm the partial-disagreement manifest exercises
 * (same name, same requirements, different version).
 */
final class DemandingIdentityModule implements ModuleInterface
{
    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/demanding', '2.0.0', ['zef/alpha', 'zef/beta']);
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        FixtureLog::record('register', 'test/demanding');
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        FixtureLog::record('boot', 'test/demanding');
    }
}
