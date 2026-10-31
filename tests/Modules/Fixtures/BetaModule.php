<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The chain's middle: requires the head.
 */
final class BetaModule implements ModuleInterface
{
    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/beta', '1.1.0', ['test/alpha']);
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        FixtureLog::record('register', 'test/beta');
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        FixtureLog::record('boot', 'test/beta');
    }
}
