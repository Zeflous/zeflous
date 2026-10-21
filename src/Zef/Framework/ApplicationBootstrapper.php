<?php

declare(strict_types=1);

namespace Zef\Framework;

use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Config\Cache\ConfigCache;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigBootstrapper;
use Zef\Framework\Config\Loader\DirectoryInventory;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\Config\Merge\LayeredConfigBuilder;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompileLane;
use Zef\Framework\Container\ContainerInstalls;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Middleware\MiddlewarePipeline;
use Zef\Framework\Middleware\PipelineFactory;
use Zef\Framework\Middleware\ProcessErrorBoundaryFactory;

/**
 * Boots the whole framework from a directory of configuration files: the
 * one-call composition seam the roadmap's boot pipeline promised ("Load
 * Config -> Validate Schema -> Jika ada cache, baca dari cache -> Config",
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree").
 *
 * The orchestration, deliberately thin -- every stage is a unit that
 * already exists and is tested on its own terms:
 *
 * - **Default layer** is {@see FrameworkDefaults::profile()} -- the
 *   framework's code-declared defaults, laid down under everything.
 * - **App layer** is the given configuration directory, read through the
 *   configuration layer's own director (`ConfigLoader::directory()`): every
 *   direct `*.php` child becomes one top-level entry named after the file
 *   minus its extension (`app/configs/services.php` yields the `services`
 *   key, `app/configs/middleware.php` the `middleware` key, `app/configs/
 *   routes.php` the `routes` key -- the file-equals-key convention). The
 *   directory's executable inventory is its own `*.php` inventory: a
 *   host application owns its configuration directory the way it owns any
 *   other source it ships, so the allowlist the file loader enforces is
 *   built from exactly that inventory, and every fail-closed loader
 *   guarantee (the basename allowlist, the `realpath()` canonicalisation,
 *   the array return contract) propagates unchanged. A directory that
 *   exists but holds no `*.php` child contributes an empty app layer: a
 *   defaults-only boot, the same way an empty routes file is valid
 *   zero-routing. A directory that does not exist fails closed.
 * - **Env layer** is the caller's {@see EnvSource}, when one is handed
 *   over: the highest-precedence overlay, opt-in by naming a prefix
 *   (`EnvSource::fromEnvironment('APP_')`).
 * - **Validate + cache** is the {@see ConfigBootstrapper}: the route table
 *   is schema-validated, and an optional {@see ConfigCache} short-circuits
 *   the whole load when an artifact is present (KDD-6: rebuild is explicit,
 *   a present cache is loaded, never rebuilt -- the stale-by-design
 *   contract the boot tests pin).
 * - **Container** is the {@see ContainerBootstrapper}: the frozen
 *   repository's `services` key is parsed, graph-validated and installed
 *   fail-closed -- and the optional {@see ContainerAotCache} is the
 *   compile/dump lane's artifact location (the `aot` knob's seam; a
 *   present artifact with a matching digest installs without a single
 *   reflection, an absent one is dumped by the first boot that wires
 *   the seam, a proven-stale one is never run).
 *
 * The composition's last stage is the **process error boundary**
 * ({@see Middleware\ProcessErrorBoundary}, the `http.error_handling.process_boundary`
 * knob's seam): the one-call boot installs the process-level error face
 * the configuration asks for, so a diagnostic the engine raises inside
 * the booted process -- before the pipeline runs, outside the request
 * scope, or after nothing caught it -- flows through the same error
 * system the request boundary feeds, from the first line of host glue
 * onward. The knob defaults to an uninstalled boundary (the honest
 * default: a host opts its process in), and the resolution is the
 * factory family's own seam ({@see ProcessErrorBoundaryFactory::
 * resolveForInstallation()} -- the knob's grammar validated once, the
 * profile's own service registration resolved, a rebind that does not
 * answer the boundary's own class halting the boot, because a knob
 * asking for the boundary cannot be answered by something else).
 *
 * Before any definition is installed, the frozen repository itself is
 * registered as the container's `framework.config` service ({@see
 * FrameworkDefaults::CONFIG_SERVICE} -- the identifier the profile's own
 * registrations address) -- the one dependency a config-driven framework
 * registration needs, and a reference any host definition may resolve
 * through as well.
 *
 * The result is one {@see Kernel} -- container installed, configuration
 * frozen -- and {@see self::pipeline()} composes the PSR-15 execution lane
 * over it when the host hands over its terminal handler. Host applications
 * and the runtime wiring compose this orchestrator, which is why it is
 * marked `@api` rather than being baselined or suppressed.
 *
 * @api
 */
final readonly class ApplicationBootstrapper
{
    public function __construct(
        private ?ConfigCache $configCache = null,
        private DefinitionCompiler $definitionCompiler = new DefinitionCompiler(),
        private ?ContainerAotCache $containerAotCache = null,
    ) {
    }

    /**
     * Boots the kernel from a directory of PHP configuration files.
     *
     * @param non-empty-string $configDirectory the application's configuration
     *                                          directory (the `app/configs`
     *                                          layout of the shipped profile)
     * @param null|EnvSource   $envSource       the environment overlay, when
     *                                          the host opts in to one
     */
    public function boot(string $configDirectory, ?EnvSource $envSource = null): Kernel
    {
        $builder = LayeredConfigBuilder::create()
            ->withDefaults(FrameworkDefaults::profile())
        ;

        $configFiles = DirectoryInventory::names($configDirectory);

        if ($configFiles !== []) {
            $builder = $builder->withAppDirectory($configDirectory, $configFiles);
        }

        if ($envSource instanceof EnvSource) {
            $builder = $builder->withEnvironment($envSource);
        }

        $config = new ConfigBootstrapper($this->configCache)->boot($builder);

        $container = new Container();
        $container->set(
            FrameworkDefaults::CONFIG_SERVICE,
            static fn (): Config => $config,
        );

        $container = new ContainerBootstrapper(
            containerCompileLane: new ContainerCompileLane(
                containerInstalls: new ContainerInstalls($this->definitionCompiler),
            ),
            containerAotCache: $this->containerAotCache,
        )
            ->bootstrap($config, $container)
        ;

        ProcessErrorBoundaryFactory::resolveForInstallation($config, $container)?->install();

        return new Kernel($container, $config);
    }

    /**
     * Composes the PSR-15 pipeline over a booted kernel: the stack the
     * configuration's `middleware.stack` key declares, resolved through the
     * booted container, terminating in the host's handler.
     */
    public function pipeline(Kernel $kernel, RequestHandlerInterface $requestHandler): MiddlewarePipeline
    {
        return new PipelineFactory($kernel->container(), $kernel->config(), $requestHandler)->build();
    }
}
