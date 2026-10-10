<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

use Zef\Framework\Config\Cache\ConfigCache;
use Zef\Framework\Config\Merge\LayeredConfigBuilder;
use Zef\Framework\Config\Schema\RouteSchema;

/**
 * The configuration bootstrapper: the thin orchestrator that carries a
 * configuration from its sources to the kernel's frozen repository (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree" -- the boot-time
 * pipeline "Load Config -> Validate Schema -> Jika ada cache, baca dari
 * cache -> Config").
 *
 * The pipeline, deliberately thin -- every stage is a unit that already
 * exists and is tested on its own terms:
 *
 * - **Load + merge** happen in the {@see LayeredConfigBuilder} the caller
 *   hands over (Default < App < Env, the loaders' allowlist and
 *   `realpath()` guarantees propagating unchanged).
 * - **Validate** is the {@see RouteSchema} over the repository's `routes`
 *   key: every entry under it must satisfy the Standard Route Schema
 *   (`path`/`method`/`handler` required, the optional fields typed), so a
 *   malformed route table halts the bootstrap with its full path context
 *   instead of surfacing as a mis-route at request time. A repository
 *   without a `routes` key boots too -- a route-less configuration is
 *   valid zero-routing, the same way an empty routes file is.
 * - **Cache** is the optional {@see ConfigCache}: when a cache artifact is
 *   present, the bootstrapper reads it and the builder -- its loaders and
 *   its merge -- is never touched at all (the fast path a persistent
 *   worker takes once per process). When it is absent, the built
 *   repository is compiled *after* validation: an invalid configuration
 *   never reaches the cache (KDD-6's rebuild-is-explicit decision keeps
 *   staleness out of the runtime; this ordering keeps invalidity out of
 *   the artifact). Validation itself runs on both paths -- a cache is
 *   trusted to be fast, not to be right.
 *
 * Every failure is a `ConfigException` with the failing stage's own
 * message: a loader violation, a cache miss handled by building, a corrupt
 * cache, or a schema violation all name their offending path. The result
 * is one immutable {@see Config}, ready for `Kernel::config()`.
 *
 * Host applications and the kernel wiring compose this orchestrator,
 * which is why it is marked `@api` rather than being baselined or
 * suppressed.
 *
 * @api
 */
final readonly class ConfigBootstrapper
{
    public function __construct(
        private ?ConfigCache $configCache = null,
        private RouteSchema $routeSchema = new RouteSchema(),
    ) {
    }

    /**
     * Boots the frozen repository: from the compiled cache when one is
     * present (the builder is never touched), otherwise from the builder,
     * validated and then compiled into the cache.
     */
    public function boot(LayeredConfigBuilder $layeredConfigBuilder): Config
    {
        if (!$this->configCache instanceof ConfigCache) {
            return $this->validated($layeredConfigBuilder->build());
        }

        if ($this->configCache->exists()) {
            return $this->validated(new Config($this->configCache->load()));
        }

        return $this->compiled($layeredConfigBuilder->build(), $this->configCache);
    }

    /**
     * Validates the repository's route entries and returns it as booted.
     */
    private function validated(Config $config): Config
    {
        $this->assertRouteEntries($config);

        return $config;
    }

    /**
     * Validates the repository and compiles it into the cache -- in that
     * order, so an invalid configuration is never written to the artifact.
     */
    private function compiled(Config $config, ConfigCache $configCache): Config
    {
        $this->assertRouteEntries($config);
        $configCache->dump($config->toArray());

        return $config;
    }

    /**
     * A repository without a `routes` key boots too: a route-less
     * configuration is valid zero-routing, not an error. When the key is
     * present, every entry under it must satisfy the Standard Route Schema
     * (the list-level contract {@see RouteSchema::assertList()} owns).
     */
    private function assertRouteEntries(Config $config): void
    {
        if (!$config->has('routes')) {
            return;
        }

        $this->routeSchema->assertList($config->get('routes'));
    }
}
