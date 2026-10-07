<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Container\Container;

/**
 * The framework kernel.
 *
 * The kernel owns the container and is built once per worker process. Under a
 * persistent runtime (RoadRunner, Swoole, FrankenPHP) the same kernel instance
 * serves many requests, so it must stay free of per-request state.
 */
final readonly class Kernel
{
    private Container $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? new Container();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function version(): string
    {
        return Version::current();
    }
}
