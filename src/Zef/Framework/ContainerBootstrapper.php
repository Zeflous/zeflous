<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\AotRefusals;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\AotBootLane;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompileLane;

/**
 * Bootstraps the container from the frozen configuration repository.
 *
 * The kernel owns both the container and the configuration (`Kernel`),
 * so the composition glue lives here: this orchestrator parses the
 * repository's five container knobs into one validated set
 * ({@see ContainerKnobs}), guards the AOT seam, and answers the boot
 * through one of two lanes -- deliberately thin, every lane's real work
 * its own unit:
 *
 * - **The artifact lane** ({@see AotBootLane}, when `aot.enabled` is on,
 *   a cache is wired, an artifact is present, and the boot runs in
 *   production mode): the artifact's closures install with zero
 *   reflection, zero planning, zero graph walking, when its digest
 *   still matches the live configuration.
 * - **The full lane** ({@see ContainerCompileLane}, every other case):
 *   the multi-pass pipeline, the install loop, and -- with the knob on
 *   and the boot in production mode -- the fresh artifact laid down
 *   for the next boot. A present artifact whose digest does not match
 *   is *proven* stale: it is never run, the full lane compiles afresh
 *   and re-dumps.
 *
 * The mode's own gate ({@see ContainerMode}, the performance area's
 * environment doctrine): a development boot holds the artifact lane
 * to the fresh compile on both sides -- a present artifact is not
 * probed (the digest proves configuration freshness, never source
 * freshness, and development is the environment where source churn
 * is the normal state, not the exception) and a fresh one is never
 * dumped (churn a next dev boot would only distrust again). The
 * compile lane's outcome service names the disarm, so the boot's
 * answer stays honest; production keeps the full artifact contract.
 *
 * Both lanes register the same two reserved read-side services ahead of
 * the definitions ({@see \Zef\Framework\Container\ContainerRegistrations},
 * so a host's same-identifier rebind wins): the tags locator and the
 * lane's own boot answer. When `aot.warm` is on, every
 * singleton-lifetime definition is eagerly constructed once the install
 * loop closes -- on both lanes, because what it warms is the
 * container's own memoisation.
 */
final readonly class ContainerBootstrapper
{
    public function __construct(
        private AotBootLane $aotBootLane = new AotBootLane(),
        private ContainerCompileLane $containerCompileLane = new ContainerCompileLane(),
        private ?ContainerAotCache $containerAotCache = null,
    ) {
    }

    /**
     * Installs the configuration's declarative definitions into the given
     * container and returns it.
     */
    public function bootstrap(Config $config, Container $container): Container
    {
        $containerKnobs = ContainerKnobs::parsed($config);

        $cache = $this->containerAotCache;

        if ($containerKnobs->aot->enabled() && !$cache instanceof ContainerAotCache) {
            throw AotRefusals::enabledWithoutCache();
        }

        $artifactPresent = false;

        if (
            $containerKnobs->aot->enabled()
            && $containerKnobs->mode->isProduction()
            && $cache instanceof ContainerAotCache
            && $cache->exists()
        ) {
            $artifactPresent = true;

            if ($this->aotBootLane->answers($container, $cache, $containerKnobs)) {
                return $container;
            }
        }

        return $this->containerCompileLane->install($container, $containerKnobs, $cache, $artifactPresent);
    }
}
