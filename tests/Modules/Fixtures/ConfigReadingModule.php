<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Config\Config;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The config service's registration-phase reader: its register phase reads
 * the boot's `framework.config` service the way the registration contract
 * spells it -- the service exists ahead of the phase, and it answers the
 * boot's own word, the routes table as of the boot (no module entry has
 * joined yet) -- and records both readings on the fixture log.
 */
final class ConfigReadingModule implements ModuleInterface
{
    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/reader', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        $present = $moduleRegistrar->has(FrameworkDefaults::CONFIG_SERVICE);
        $service = $moduleRegistrar->get(FrameworkDefaults::CONFIG_SERVICE);
        $routes = $service instanceof Config ? $service->get('routes') : null;

        FixtureLog::record(
            'config-service',
            'test/reader@' . ($present ? 'true' : 'false') . '@'
            . json_encode($routes, \JSON_UNESCAPED_SLASHES),
        );
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        // The boot phase has nothing of its own to read: the registration
        // phase is the contract's question.
    }
}
