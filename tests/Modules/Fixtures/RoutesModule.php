<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The route declarer: its manifest declares a routes file, its own
 * phases no-op -- the route extension point's consumption is the boot's
 * question, not the module's.
 */
final class RoutesModule implements ModuleInterface
{
    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/shop', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        // The route extension point is consumed by the boot's own fold;
        // the registration phase has nothing of its own to claim.
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        // The context's shape is the grammar tests' question, not this
        // fixture's -- the boot phase no-ops by design.
    }
}
