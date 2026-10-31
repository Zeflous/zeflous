<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use RuntimeException;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleRegistrar;

/**
 * The unbuildable module: its constructor throws, so discovery must refuse
 * with the class and the reason named.
 */
final class ExplodingModule implements ModuleInterface
{
    public function __construct()
    {
        throw new RuntimeException('the fixture module refuses to exist');
    }

    #[Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('test/exploding', '1.0.0');
    }

    #[Override]
    public function register(ModuleRegistrar $moduleRegistrar): void
    {
        FixtureLog::record('register', 'test/exploding');
    }

    #[Override]
    public function boot(ModuleContext $moduleContext): void
    {
        FixtureLog::record('boot', 'test/exploding');
    }
}
