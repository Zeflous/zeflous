<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
}
