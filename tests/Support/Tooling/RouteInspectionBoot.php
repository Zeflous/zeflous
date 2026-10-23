<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Router\Router;

/**
 * The route inspection commands' boot: the one seam `bin/zef
 * route:list`, `route:test` and `route:tree` share -- a configuration
 * directory becomes the router those commands answer from, booted
 * through the same application orchestrator the runtime rides
 * ({@see ApplicationBootstrapper}, the container inventory command's
 * own doctrine: never a parallel parse -- the layered, validated,
 * environment-resolved repository is the one the boot answers, and a
 * route table defect surfaces as the boot's own typed refusal).
 *
 * The router is composed, not fetched: the facade is deliberately not
 * a container service yet (the observability lane's own recorded
 * decision, KDD-OB6), so the inspection boot builds it over the
 * kernel's frozen configuration the same way the host's composition
 * root would -- `Router::fromConfig()` over the repository the boot
 * just validated.
 *
 * The one seam the boot owns is the container AOT artifact: the host's
 * real boot wires their own cache location, which a CLI cannot know,
 * so the boot wires the throwaway file the analysis CLIs share
 * ({@see ContainerAnalysisArtifact}) and never lets it outlive the
 * boot.
 */
final readonly class RouteInspectionBoot
{
    /**
     * Boots the configuration directory's route table as the router
     * the inspection commands answer from.
     *
     * @param non-empty-string $configDirectory the host's configuration
     *                                          directory (the `app/configs` layout)
     */
    public static function router(string $configDirectory): Router
    {
        $artifactFile = ContainerAnalysisArtifact::path();

        try {
            $kernel = new ApplicationBootstrapper(
                containerAotCache: new ContainerAotCache($artifactFile),
            )->boot($configDirectory);

            $router = Router::fromConfig($kernel->config());

            // The boot's own validation: the lazy radix compilation
            // runs once, here, so a defective table refuses at the
            // boot seam -- the inspection commands never answer from
            // a router whose table has not been judged yet. The
            // warm-up only touches the tree: the compilation IS the
            // judgment (a zero-route table is a valid table the
            // renderers spell honestly; the boot never judges
            // content).
            $router->tree();

            return $router;
        } finally {
            if (is_file($artifactFile)) {
                unlink($artifactFile);
            }
        }
    }
}
