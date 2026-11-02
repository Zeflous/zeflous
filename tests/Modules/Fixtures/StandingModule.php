<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The standing declarer: its manifest declares an events file whose one
 * declaration names several listeners, and its registration phase claims
 * the tagged services the declarations address -- the running order the
 * declarations spell is the boot's own promise, and the tagged listeners
 * the declarations name are the module's own to register.
 */
final class StandingModule implements ModuleInterface
{
    public const string FIRST_SERVICE = 'test/standing/first.listener';

    public const string SECOND_SERVICE = 'test/standing/second.listener';

    public const string THIRD_SERVICE = 'test/standing/third.listener';

    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/standing', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        $moduleRegistrar->set(
            self::FIRST_SERVICE,
            static fn (): StandingListener => new StandingListener('first'),
        );
        $moduleRegistrar->set(
            self::SECOND_SERVICE,
            static fn (): StandingListener => new StandingListener('second'),
        );
        $moduleRegistrar->set(
            self::THIRD_SERVICE,
            static fn (): StandingListener => new StandingListener('third'),
        );
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        // The event extension point is consumed by the boot's own fold;
        // the boot phase has nothing of its own to add.
    }
}
