<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The event declarer: its manifest declares an events file, its
 * registration phase claims the listener service the declarations name
 * -- the event extension point's consumption is the boot's question,
 * and the listener the declarations address is the module's own to
 * register.
 */
final class EventsModule implements ModuleInterface
{
    public const string LISTENER_SERVICE = 'test/shop/order-paid.listener';

    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/shop', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        $moduleRegistrar->set(
            self::LISTENER_SERVICE,
            static fn (): OrderPaidListener => new OrderPaidListener(),
        );
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        // The event extension point is consumed by the boot's own fold;
        // the boot phase has nothing of its own to add.
    }
}
