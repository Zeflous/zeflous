<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;

/**
 * The framework kernel.
 *
 * The kernel owns the container and the configuration repository, and is
 * built once per worker process. Under a persistent runtime (RoadRunner,
 * Swoole, FrankenPHP) the same kernel instance serves many requests, so it
 * must stay free of per-request state.
 *
 * The repository is the configuration layer's frozen output (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree"): built once at
 * boot -- by the `ConfigBootstrapper` pipeline of load, merge, schema
 * validation and the compiled cache -- and then only read, through
 * dot-notation `get`/`has` lookups, for the lifetime of the process. A
 * kernel constructed without a repository carries the empty configuration,
 * which keeps the constructor backward compatible.
 */
final readonly class Kernel
{
    private Container $container;

    private Config $config;

    public function __construct(?Container $container = null, ?Config $config = null)
    {
        $this->container = $container ?? new Container();
        $this->config = $config ?? new Config();
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * The frozen configuration repository this kernel was booted with.
     *
     * Host applications read the configuration through this accessor from
     * outside the analysed source tree, which is why it is marked `@api`
     * rather than being baselined or suppressed.
     *
     * @api
     */
    public function config(): Config
    {
        return $this->config;
    }

    public function version(): string
    {
        return Version::current();
    }
}
