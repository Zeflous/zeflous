<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The CLI declarer: its manifest declares a commands file, its
 * registration phase claims the handler service the declarations name
 * -- the CLI extension point's consumption is the boot's question, and
 * the handler the declarations address is the module's own to register.
 */
final class CliModule implements ModuleInterface
{
    public const string HANDLER_SERVICE = 'test/shop/ping.handler';

    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/shop', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        $moduleRegistrar->set(
            self::HANDLER_SERVICE,
            static fn (): PingHandler => new PingHandler(),
        );
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        // The CLI extension point is consumed by the boot's own fold;
        // the boot phase has nothing of its own to add.
    }
}
