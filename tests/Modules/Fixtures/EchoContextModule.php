<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The context echo: its boot phase records the whole context it received
 * -- the namespaced configuration namespacing's own proof tape.
 */
final class EchoContextModule implements ModuleInterface
{
    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/echo', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        FixtureLog::record('register', 'test/echo');
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        FixtureLog::record(
            'boot',
            'test/echo@' . $moduleContext->name . '@' . $moduleContext->version . '@'
            . json_encode($moduleContext->config, \JSON_UNESCAPED_SLASHES),
        );
    }
}
