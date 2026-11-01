<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The second claimant of the shared identifier: its registration must be
 * the refusal that names the first claimant.
 */
final class SecondSharedModule implements ModuleInterface
{
    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/shared-two', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        $moduleRegistrar->set('shared.id', static fn (): string => 'second');
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        FixtureLog::record('boot', 'test/shared-two');
    }
}
