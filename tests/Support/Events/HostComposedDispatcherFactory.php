<?php

declare(strict_types=1);

namespace Zef\Test\Support\Events;

use Zef\Framework\Event\EventDispatcher;

/**
 * The host's own composed dispatcher factory: the same-identifier rebind
 * the reserved-services precedence answers -- the host's word final over
 * the boot's wired registration, the rebind the integration test pins
 * (the module's joined declarations NOT answered by the host's own
 * composition).
 */
final class HostComposedDispatcherFactory
{
    public static function compose(): EventDispatcher
    {
        return new EventDispatcher();
    }
}
