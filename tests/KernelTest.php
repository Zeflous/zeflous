<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Kernel;
use Zef\Framework\Version;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    public function testCreatesItsOwnContainerByDefault(): void
    {
        self::assertNotSame(new Kernel()->container(), new Kernel()->container());
    }

    public function testUsesTheInjectedContainer(): void
    {
        $container = new Container();

        self::assertSame($container, new Kernel($container)->container());
    }

    public function testReportsTheFrameworkVersion(): void
    {
        self::assertSame(Version::current(), new Kernel()->version());
    }

    public function testCreatesAnEmptyConfigurationByDefault(): void
    {
        self::assertSame([], new Kernel()->config()->toArray());
    }

    public function testUsesTheInjectedConfiguration(): void
    {
        $config = new Config(['name' => 'zef']);

        self::assertSame($config, new Kernel(null, $config)->config());
        self::assertSame('zef', new Kernel(null, $config)->config()->get('name'));
    }
}
