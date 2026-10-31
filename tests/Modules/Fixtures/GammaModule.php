<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The chain's tail: requires the middle, so the whole chain orders alpha
 * before beta before gamma even when the knob spells the reverse.
 */
final class GammaModule implements ModuleInterface
{
    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/gamma', '^2.0', ['test/beta']);
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        FixtureLog::record('register', 'test/gamma');
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        FixtureLog::record('boot', 'test/gamma');
    }
}
